<?php
/**
 *
 * User: Ghh.Guan
 * Date: 5/21/21
 */

namespace smiler\logistics\Api\SzDhl;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

class SzDhl extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface, TrackLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹,5自定义
     */
    const ORDER_COUNT = 2;
    /**
     * 一次最多查询多少个物流商
     */
    const QUERY_TRACK_COUNT = 1;
    public $iden = 'szdhl';
    public $iden_name = '深圳DHL';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'xml';

    public $apiHeaders = [
        'Content-Type' => 'application/xml'
    ];

    public static $xmlHeader = [
        'Shipment' => "<req:ShipmentRequest schemaVersion='10.0' xmlns:req='http://www.dhl.com' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:schemaLocation='http://www.dhl.com ship-val-global-req-6.2.xsd'>",
        'KnownTracking' => "<req:KnownTrackingRequest xmlns:req='http://www.dhl.com' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:schemaLocation='http://www.dhl.com TrackingRequestKnown.xsd' schemaVersion='1.0'>",
    ];

    public static $xmlFoot = [
        'Shipment' => "</req:ShipmentRequest>",
        'KnownTracking' => "</req:KnownTrackingRequest>",
    ];

    public $interface = [
        'createOrder' => 'Shipment', // 创建运单和发票

        'queryTrack' => 'KnownTracking', //轨迹查询
    ];

    //需要在增值服务里设置危险代码
    public $specialServiceDgList = [
        'HB' => 965,
        'HD' => 966,
        'HV' => 967,
        'HM' => 969,
        'HW' => 970,
    ];

    //需要在包裹信息节点里设置危险代码
    public $detailDgList = [
        'HV' => 'Lithium ion batteries in compliance with Pl967 ll(less than 2 batteries or 4 cells)',
        'A123' => 'THE substance is not subject to IATA DGR according to special provision A123',
        'A67' => 'THE substance is not restricted to IATA DGR according to special provision A67',
        'A12' => 'Not restricted according to IATA DGR A12',
        'PI970 LESS' => 'PI970 Sec II(≤4 cells or 2 batt)',
        'PI967 LESS' => 'PI967 Sec II(≤4 cells or 2 batt)',
    ];

    /**
     * HuaHan constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['siteID', 'password', 'url'], $config);
        $this->config = $config;
    }

    /**
     * array转XML
     * @param $array
     * @param string $root
     * @return string
     */
    public static function arrayToXml($array, $root='ns1:callService' ,$encoding = 'UTF-8')
    {
        $xml = "<?xml version = '1.0' encoding = '$encoding'?>";
        $xml .= self::$xmlHeader[$root];
        $xml .= static::arrayToXmlInc($array);
        $xml .= self::$xmlFoot[$root];
        return $xml;
    }

    public static function arrayToXmlInc($array)
    {
        $xml = '';
        foreach ($array as $key => $val) {
            if(empty($val)) continue;
            if(is_array($val)) {
                if(is_numeric($key)){
                    $xml .= static::arrayToXmlInc($val);
                }else{
                    $xml .= "<$key>";
                    $xml .= static::arrayToXmlInc($val);
                    $xml .= "</$key>";
                }
            }else{
                $xml .= "<$key>$val</$key>";
            }
        }
        return $xml;
    }

    public static function xmlToArray($xml){
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string(utf8_encode($xml), 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }

    //生成uuid唯一标识
    public static function uuid($prefix = '')
    {
        $chars = md5(uniqid(mt_rand(), true));
        $uuid = substr($chars, 0, 8);
        $uuid .= substr($chars, 8, 4);
        $uuid .= substr($chars, 12, 4);
        $uuid .= substr($chars, 16, 4);
        $uuid .= substr($chars, 20, 12);
        return $prefix . $uuid;
    }

    /**
     * 创建订单
     * @param array $params
     * @return mixed
     * @throws InvalidIArgumentException
     * @throws ManyProductException
     * {"ask":"Success","message":"Success","reference_no":"T1020210221154454829981280","shipping_method_no":"HHWMS1052000004YQ","order_code":"HHWMS1052000004YQ","track_status":"3","sender_info_status":"0","ODA":"","agent_number":"","time_cost(s)":"0.484375"}
     */
    public function createOrder(array $params = [])
    {
        if (empty($params)) {
            throw new InvalidIArgumentException($this->iden_name . " 创建订单参数不能为空");
        }

        $ls = [];

        if (count($params) > self::ORDER_COUNT) {
            throw new ManyProductException($this->iden_name . "一次最多支持提交" . self::ORDER_COUNT . "个包裹");
        }
        foreach ($params as $item) {
            $dhl = $item['dhl_param'];
            //付款信息
            $billing = [
                'ShipperAccountNumber' => $dhl['shipper_account_number'] ?? '',//发件人账号
                'ShippingPaymentType' => 'S' ?? '',//运费付款方,S: 发件人; R: 收件人; T:第三方
                'DutyAccountNumber' => $dhl['shipper_account_number'] ?? '',//使用发件人账号或第三国/地区的账号（收、发件人之外的第三国/地区）支付目的地税金时（即启用DTP服务时），建议同时在SpecialService字段添加DD代码。启用DTP服务会产生额外服务费用，费用详情请咨询DHL销售经理或DHL客服95380
            ];

            //收件人注册号/税号节点，暂定一个
            $registrationNumbers[0] = [
                'RegistrationNumber' => [
                    'Number' => $item['recipientTaxNumber'] ?? '',//收件人注册号/税号
                    'NumberTypeCode' => $dhl['recipient_tax_number_type'] ?? '',//收件人注册号/税号类别
                    'NumberIssuerCountryCode' => $item['recipientTaxNumberIssuerCountryCode'] ?? '',//收件人注册号/税号所属国国家代码
                ]
            ];

            //收件人信息
            $consignee = [
                'CompanyName' => $item['recipientName'] ?? '',//收件人公司名(个人物品写收件人姓名)
                'AddressLine1' => $item['recipientStreet'] ?? '',// 收件地址栏1
                'AddressLine2' => $item['recipientStreet1'] ?? '',// 收件地址栏2
                'AddressLine3' => $item['recipientStreet2'] ?? '',// 收件地址栏3
                'City' => $item['recipientCity'] ?? '',//收件人城市
                'Division' => $item['recipientState'] ?? '',//收件人州名称
                'DivisionCode' => $item['recipientState'] ?? 'AK',//收件人州代码(仅对美国)
                'Suburb' => $item['recipientSuburb'] ?? '',//收件人郊区信息
                'PostalCode' => $item['recipientPostCode'] ?? '',//收件人邮编
                'CountryCode' => $item['recipientCountryCode'] ?? '',//收件人国家代码
                'CountryName' => $item['recipientCountryCode'] ?? '',//收件人国家名称
                'Contact' => [
                    'PersonName' => $item['recipientName'] ?? '',//收件人姓名
                    'PhoneNumber' => str_replace("+","",$item['recipientPhone'] ?? ''),//收件人电话
                    'MobilePhoneNumber' => $item['recipientMobile'] ?? '',//收件人手机
                    'Email' => $item['recipientEmail'] ?? '',//收件人邮箱
                ],
                'RegistrationNumbers' => isset($item['recipientTaxNumber']) && !empty($item['recipientTaxNumber']) ? $registrationNumbers : '',
                'BusinessPartyTypeCode' => $dhl['recipient_business_type'] ?? '',//收件人类别
            ];
            $declaredValue = 0;
            $declaredCurrency = 'CNY';
            $LabelImageFormat = 'PDF';

            //单项商品的详细信息节点
            $exportLineItem = [];
            $shipmentDetails = [];
            $commodity = [];
            $productContents = [];
            $grossWeight = 0;
            foreach ($item['productList'] as $index => $value) {
                array_push($productContents,$value['declareEnName']);
                $declaredCurrency = $value['currencyCode'];
                $declaredValue += $value['quantity'] * $value['declarePrice'];
                $commodity[] = [
                    'Commodity' => [
                        'CommodityCode' => $value['hsCode'],
                        'CommodityName' => $value['declareEnName'],
                    ]
                ];
                $exportLineItem[] = [
                    'ExportLineItem' => [
                        'LineNumber' => $index + 1,//顺序号，用以区分每个ExportLineItem
                        'Quantity' => (int)($value['quantity'] ?? ''),//单项商品的数量
                        'QuantityUnit' => 'PCS',//数量单位,
                        'Description' => $value['declareEnName'],//单项商品的英文描述,
                        'Value' => (float)(round($value['declarePrice'],2) ?? 0.00),//商品单价,
                        'Weight' => [
                            'Weight' => $value['netWeight'] * $value['quantity'] / 1000 ?? '',//净重
                            'WeightUnit' => 'K',//重量单位,K:千克,L:磅
                        ],
                        'GrossWeight' => [
                            'Weight' => $value['grossWeight'] * $value['quantity'] / 1000 ?? '',//毛重
                            'WeightUnit' => 'K',//重量单位,K:千克,L:磅
                        ],
                        'ManufactureCountryCode' => $value['originCountry'] ?? 'CN',//原产国国家代码,默认CN
                        'ItemReferences' => $dhl['item_reference_type'] ? [
                            'ItemReference' => [
                                'ItemReferenceType' => $dhl['item_reference_type'] ?? '',
                                'ItemReferenceNumber' => $dhl['item_reference_number'] ?? '',
                            ]
                        ] : "",//单项商品参考信息节点
                    ]
                ];
                $grossWeight += $value['grossWeight']*$value['quantity'];
            }
            //快件重量、尺寸信息节点
            $shipmentDetails['Pieces'][] = [
                'Piece' => [
                    'Weight' => $grossWeight / 1000,//单件毛重
                    'Width' => round($item['packageWidth']),//单件宽度
                    'Height' => round($item['packageHeight']),//单件高度
                    'Depth' => round($item['packageLength']),//单件长度
                ]
            ];

            //发件人注册号/税号节点，暂定一个
            $shipperRegistrationNumbers[0] = [
                'RegistrationNumber' => [
                    'Number' => ($dhl['sender_tax_number_type'] == 'SDT')?$item['iossNumber']:$item['senderTaxNumber'],//发件人注册号/税号
                    'NumberTypeCode' => $dhl['sender_tax_number_type'] ?? '',//发件人注册号/税号类别
                    'NumberIssuerCountryCode' => $dhl['tax_numberIssuer_country_code'] ?? 'CN',//发件人注册号/税号所属国国家代码
                ]
            ];

            $senderTaxNumber = $item['senderTaxNumber']??'';
            $iossNumber = $item['iossNumber']??'';
            //发件人信息节点
            $shipper = [
                'ShipperID' => $dhl['shipper_account_number'] ?? '',//发件人账号
                'CompanyName' => $item['senderName'] ?? '',//发件人公司名
                'AddressLine1' => $item['senderAddress'] ?? '',//发件人地址栏1
                'AddressLine2' => $item['senderDistrict'] ?? '',//发件人地址栏2
                'City' => $item['senderCity'] ?? '',//发件人城市
                'Division' => $item['senderState'] ?? '',//发件人省州
                'PostalCode' => $item['senderPostCode'] ?? '',//有邮编国家必输
                'CountryCode' => $item['senderCountryCode'] ?? '',//发件人国家代码
                'CountryName' => $item['senderCountryCode'],//发件人国家名称
                'Contact' => [
                    'PersonName' => $item['senderName'] ?? '',//发件人姓名
                    'PhoneNumber' => $item['senderPhone'] ?? '',//发件人电话
                    'Email' => $item['senderEmail'] ?? '',//发件人邮箱
                ],//发件人信息
            ];

            if(!empty($senderTaxNumber || !empty($iossNumber))) $shipper['RegistrationNumbers'] = $shipperRegistrationNumbers;

            $shipper['BusinessPartyTypeCode'] = $dhl['sender_business_type'] ?? '';//发件人类别

            $shipmentDetails['WeightUnit'] = 'K';//重量单位,K:千克,L:磅
            $shipmentDetails['GlobalProductCode'] = $item['productCode'] ?? 'P';//Global 产品代码,普通包裹：P，正午特派包裹：Y
            $shipmentDetails['LocalProductCode'] = $item['productCode'] ?? 'P';//本地产品代码,对于CN来说，GlobalProductCode通常与LocalProductCode一致。但对于某些国家/某些产品，两者可能存在差异，这取决于当地DHL的设定。虽然LocalProductCode是可选项，但出于数据完整传输考虑，建议在Request中保留LocalProductCode，一起传输。
            $shipmentDetails['Date'] = date('Y-m-d');//创建日期,当日开始九日之内
            $shipmentDetails['Contents'] = implode(',',$productContents);//货物描述,该字段的值对应运单上的货物描述部分请在该字段使用英文准确地录入所寄快件的货物描述
            $shipmentDetails['DimensionUnit'] = 'C';//尺寸单位,C: 厘米; I : 英寸
            $shipmentDetails['PackageType'] = $item['recipientPackageType'] ?? 'EE';//包装类型,EE: DHL Express Envelope, OD:Other DHL Packaging, CP:Customer-provided, JB-Jumbo box, JJ-Junior jumbo Box, DF-DHL Flyer, YP-Your
            $shipmentDetails['IsDutiable'] = 'Y';//快件类别,可选值为：---Y  包裹---N  文件
            $shipmentDetails['CurrencyCode'] = 'CNY';//运费结算币种
            if(!empty($dhl['dg'])){
                if(in_array($dhl['dg'],array_keys($this->detailDgList))) $shipmentDetails['CustData'] = $this->detailDgList[$dhl['dg']];//额外信息
            }

            $dutiable = [
                'DeclaredValue' => (float)(round($declaredValue,2) ?? 0.00),//申报总价值
                'DeclaredCurrency' => $declaredCurrency,//货币单位
                'ShipperEIN' => $item['senderTaxNumber'] ?? '',//发件人增值税号
                'ConsigneeEIN' => $item['recipientTaxNumber'] ?? '',//收件人增值税号
                'TermsOfTrade' => $dhl['terms_of_trade'] ?? 'DAP',//贸易条款
            ];

            //其他费用节点
            $otherCharges = [];
            if (isset($dhl['other_charge_type']) && !empty($dhl['other_charge_type'])) {
                $otherCharges[] = [
                    'OtherCharge' => [
                        'OtherChargeValue' => $dhl['other_charge_value'],//费用金额
                        'OtherChargeType' => $dhl['other_charge_type'],//费用类型
                    ]
                ];
            }


            $exportDeclaration = [
                'SignatureName' => $dhl['signature_name'] ?? 'zhangjie@bestope.com',//发票签名
                'SignatureTitle' => $dhl['signature_title'] ?? '13632581942',//发票标题签名
                'ExportReasonCode' => $item['export_reason_code'] ?? 'P',//出口原因代码（出口类型）
                'InvoiceNumber' => self::getInvoiceNumber(),//发票号码
                'InvoiceDate' => date('Y-m-d'),//发票日期
                'OtherCharges' => $otherCharges,
                'SignatureImage' => $dhl['signature_image'] ?? '',//Base64编码，不能超过1M.签名影像（电子签名）的Base64编码
                $exportLineItem, //单项商品的详细信息节点
                'PlaceOfIncoterm' => $dhl['place_of_incoterm'] ?? 'CN',//该字段用来录入具体的贸易条款所适用的港口名称（启运港、装运港、目的港等）
            ];//海关申报节点

            $label = [
                'HideAccount' => 'N',//是否隐藏付款账号,Y: 隐藏; N: 显示账号, 默认值
                'LabelTemplate' => $dhl['label_template'] ?? '8X4_PDF',//运单模板（运单规格）,常用的可选值为：8X4_A4_PDF: A4纸运单（默认值）, 8X4_PDF : 标签运单
                'CustomsInvoiceTemplate' => $dhl['customs_invoice_template'] ?? 'COMMERCIAL_INVOICE_P_10',//清关发票样式（清关发票模板）,COMMERCIAL_INVOICE_L_10:横向打印,COMMERCIAL_INVOICE_P_10:纵向打印
            ];//打印运单设置

            //增值服务
            $specialService = [];
            if (!empty($dhl['special_service_type'])) {
                $special_service_type = explode(',',$dhl['special_service_type']);
                foreach ($special_service_type as $special) {
                    $special_tmp['SpecialServiceType'] = $special;//DHL特殊/增值服务代码
                    if ($special == 'II') $special_tmp['ChargeValue'] = $dhl['special_service_value'];//特殊/增值服务的价值（当前主要用于填写保险价值）最大10位数，最多保留2位小数
                    $specialService[] = ['SpecialService' => $special_tmp];
                }
                if(!empty($dhl['dg'])) {
                    $dg_list = explode(',',$dhl['dg']);
                    foreach ($dg_list as $dg) {
                        if(!in_array($dg,array_keys($this->specialServiceDgList))) continue;
                        $special_tmp['SpecialServiceType'] = $dg;//DHL特殊/增值服务代码
                        $specialService[] = ['SpecialService' => $special_tmp];
                    }
                }
            }

            //危险品节点
            $dgs = [];
            if(!empty($dhl['dg'])){
                $dg_list = explode(',',$dhl['dg']);
                foreach ($dg_list as $dg) {
                    if(in_array($dhl['dg'],array_keys($this->specialServiceDgList))){
                        $dgs[] = [
                            'DG' => [
                                'DG_ContentID' => $this->specialServiceDgList[$dg]
                            ]
                        ];
                    }
                }
            }

            $ls[] = [
                'Billing' => $billing,
                'Consignee' => $consignee,
                $commodity,
                'Dutiable' => $dutiable,
                'UseDHLInvoice' => $dhl['is_invoice'] ?? 'Y',//使用DHL接口创建发票,PLT自动创建发票, 只针对包裹; 默认为N
                'DHLInvoiceLanguageCode' => 'en',//发票语言
                'DHLInvoiceType' => $dhl['invoice_type'] ?? 'CMI',//发票类型,CMI: Commercial; PFI: Proforma; 默认为CMI
                'ExportDeclaration' => $exportDeclaration,
                'ShipmentDetails' => $shipmentDetails,
                'Shipper' => $shipper,
                $specialService,
                'LabelImageFormat' => $LabelImageFormat,
                'RequestArchiveDoc' => 'Y',//是否输出Archive / waybill doc 联,如果为Y, 则至少输出一张
                'Label' => $label,
                'DGs' => $dgs,
            ];
            $customerOrderNo = $item['customerOrderNo'] ?? '';
        }
        $response = $this->request(__FUNCTION__, $ls[0]);
        // 处理结果,返回数据太多，不做保存
        $reqRes = [
            ResponseDataConst::LSA_CURL_REQ_DATA => $this->req_data,
            ResponseDataConst::LSA_CURL_RES_DATA => []
        ];

        $fieldData = [];
        $fieldMap = FieldMap::createOrder();

        // 结果
        $flag = (isset($response['Note']['ActionNote']) && $response['Note']['ActionNote'] == 'Success');

        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['Response']['Status']['Condition']['ConditionCode'] ?'ConditionCode:'.$response['Response']['Status']['Condition']['ConditionCode'].' ConditionData:'.$response['Response']['Status']['Condition']['ConditionData']: '未知错误');

        $fieldData['orderNo'] = $customerOrderNo;//客户订单号
        $fieldData['trackingNo'] = $flag ? $response['AirwayBillNumber'] : '';//空运单号
        $fieldData['id'] = $flag ? $response['AirwayBillNumber'] : '';//第三方id，用空运单号代替
        $fieldData['frt_channel_hawbcode'] = $flag ? $response['AirwayBillNumber'] : '';//尾程追踪号
        $fieldData['prediction_freight'] = $flag ? $response['ShippingCharge'] : '';//预估费用

        $fieldData['extended'] = $flag ? [
            ResponseDataConst::LSA_LABEL_TYPE => ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF,//文件类型
            ResponseDataConst::LSA_LABEL_PATH => $flag ? $response['LabelImage']['OutputImage'] : '',//面单
            ResponseDataConst::LSA_LABEL_INVOICE_PATH => $response['LabelImage']['MultiLabels']['MultiLabel']['DocImageVal']?? '',//发票
            ResponseDataConst::LSA_IS_LABEL => 0,//是否存在面单接口[默认存在，存在可以不设]
        ] : [];//扩展参数

        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
//        $this->dd($response, $ret, $reqRes);
        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);
    }

    //随机生成发票号
    private static function getInvoiceNumber(){
        return mt_rand(10000000,99999999);
    }

    public function request($function, $data = [])
    {
        $data = $this->buildParams($function, $data);
        $this->req_data = $data;
        $res = $this->sendCurl('post', $this->config['url'], $data, $this->dataType, $this->apiHeaders, 'UTF-8', $this->interface[$function]);
        $this->res_data = $res;
        return $res;
    }

    /**
     * 获取请求数据
     */
    public function buildParams($interface, $arr = [])
    {
        $header_arr = [
            'Request' => [
                'ServiceHeader' => [
                    'MessageTime' =>date("c"),
                    'MessageReference' =>self::uuid(),
                    'SiteID' =>$this->config['siteID'],
                    'Password' =>$this->config['password'],
                    ]
            ],
        ];
        if($interface == 'createOrder'){
            $header_arr['Request']['MetaData'] = [
                'SoftwareName' => 'BST',
                'SoftwareVersion' => 1.0,
            ];
            $header_arr['RegionCode'] = 'AP';
        }

        $header_arr['LanguageCode'] = 'cn';
        return array_merge($header_arr,$arr);
    }

    /**
     * 获取物流商运输方式
     * @return mixed
     */
    public function getShippingMethod()
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    /**
     * 修改重量
     * @return mixed
     */
    public function operationPackages(array $pars = [])
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    /**
     * 取消订单，删除订单
     * @return mixed
     */
    public function deleteOrder(string $order_id)
    {
        $param = [
            'orderId' => $order_id,
        ];
        $response = $this->request(__FUNCTION__, $param);
        return $response;
    }

    /**
     * 修改订单状态
     * @return mixed
     */
    public function updateOrderStatus($params = [])
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    /**
     * 获取订单费用
     * @return mixed
     */
    public function getFeeByOrder(string $order_code)
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    public function getFeeDetailByOrder($order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    /**
     *
     * 获取订单标签(创建订单有返回并保存)
     * @return mixed
     */
    public function getPackagesLabel($params = [])
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    /**
     * 获取物流商轨迹
     *
     * @return mixed
     */
    public function queryTrack($trackNumber)
    {
        $trackNumberArray = $this->toArray($trackNumber);
        if (count($trackNumberArray) > self::QUERY_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "查询物流轨迹一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
        }
        $data = [
            'LanguageCode' => 'zh',
            'AWBNumber' => $trackNumber,
            'LevelOfDetails' => 'ALL_CHECK_POINTS',
        ];
        $response = $this->request(__FUNCTION__, $data);
        // 结果
        $flag = (isset($response['AWBInfo']['Status']['ActionStatus']) && $response['AWBInfo']['Status']['ActionStatus'] == 'success');

        // 结果
        if ($flag != true) {
            return $this->retErrorResponseData(isset($response['AWBInfo']['Status']['ActionStatus']) ?? '未知错误');
        }

        // 处理结果
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);
        $data = isset($response['AWBInfo']['Status']['ShipmentInfo']['ShipmentEvent'])?$response['AWBInfo']['Status']['ShipmentInfo']['ShipmentEvent']:null;

        $ls = [];
        //ShipmentEvent如果是二维数组
        if(!empty($data)) {
            foreach ($data as $key => $val) {
                $info = [
                    'status' => $val['ServiceEvent']['EventCode'],
                    'pathInfo' => $val['ServiceEvent']['Description'],
                    'pathTime' => $val['Date'] . ' ' . $val['Time'],
                    'pathAddr' => $val['ServiceArea']['ServiceAreaCode'] . ':' . $val['ServiceArea']['Description'],
                ];
                $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($info, $fieldMap2);
            }
        }

        //ShipmentEvent如果是一维数组
        /*$info = [
            'status' => $data['ServiceEvent']['EventCode'],
            'pathInfo' => $data['ServiceEvent']['Description'],
            'pathTime' => $data['Date'].' '.$data['Time'],
            'pathAddr' => $data['ServiceArea']['ServiceAreaCode'].':'.$data['ServiceArea']['Description'],
        ];
        $ls[0] = LsSdkFieldMapAbstract::getResponseData2MapData($info, $fieldMap2);*/

        $data['flag'] = $flag;
        $data['info'] = $flag ? $response['AWBInfo']['Status']['ActionStatus']:'';
        $data['status'] = $flag ? $response['AWBInfo']['Status']['ActionStatus']:'';
        $data['tno'] = $trackNumber;
        $data['sPaths'] = $ls;
        $fieldData = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);

        return $this->retSuccessResponseData($fieldData);
    }

    public function getPackagesDetail($order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }
}