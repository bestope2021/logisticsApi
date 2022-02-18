<?php
/**
 *
 * User: blaine
 * Date: 2/22/21
 */

namespace smiler\logistics\Api\ZhiXinDa;


use smiler\logistics\Api\ZhiXinDa\FieldMap;
use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

/**
 * 至信达小包
 * @link https://ec.wiki.eccang.com/docs/show/806
 * Class ZhiXinDa
 * @package smiler\logistics\Api\ZhiXinDa
 */
class ZhiXinDa extends LogisticsAbstract implements BaseLogisticsInterface, TrackLogisticsInterface, PackageLabelLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹
     */
    const ORDER_COUNT = 1;
    /**
     * 一次最多查询多少个跟踪号
     */
    const QUERY_TRACK_COUNT = 1;

    public $iden = 'zhixinda';

    public $iden_name = '至信达小包';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'xml';

    public $apiHeaders = [
        'Content-Type' => 'application/xml'
    ];

    public $interface = [

        'createOrder' => 'createOrder', // 【创建订单】

        'operationPackages' => 'modifyOrderWeight', //修改订单重量

        'deleteOrder' => 'cancelOrder', //删除订单

        'getPackagesLabel' => 'getLabelUrl', // 【打印标签|面单】

        'getTrackNumber' => 'getTrackNumber',//获取跟踪号

        'queryTrack' => 'getCargoTrack', //轨迹查询

        'getShippingMethod' => 'getShippingMethodInfo', //获取配送方式

        'getPackagesDetail' => 'getOrder', //查询订单

        'getShippingFee' => 'getReceivingExpense', //获取费用
    ];

    /**
     * HuaHan constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['appToken', 'url', 'appKey'], $config);
        $this->config = $config;
    }

    /**
     * 拼接array转为XML
     * @param $array
     * @param string $root
     * @return string
     */
    public static function arrayToXml($array, $root = 'ns1:callService', $encoding = 'utf-8')
    {
        $xml = '<?xml version="1.0" encoding="' . $encoding . '"?>';
        $xml .= '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.example.org/Ec/">';
        $xml .= '<SOAP-ENV:Body>';
        $xml .= "<ns1:callService>";
        $xml .= static::arrayToXmlInc($array);
        $xml .= "</ns1:callService>";
        $xml .= "</SOAP-ENV:Body>";
        $xml .= "</SOAP-ENV:Envelope>";
        return $xml;
    }


    /**
     * 参数转为xml格式化
     * @param $array
     * @return string
     */
    public static function arrayToXmlInc($array)
    {
        $xml = '';
        foreach ($array as $key => $val) {
            if (empty($val)) continue;
            if (is_array($val)) {
                if (is_numeric($key)) {
                    $xml .= static::arrayToXmlInc($val);
                } else {
                    $xml .= "<$key>";
                    $xml .= static::arrayToXmlInc($val);
                    $xml .= "</$key>";
                }
            } else {
                $xml .= "<$key>$val</$key>";
            }
        }
        return $xml;
    }

    /**
     * 最后将xml解析为array数组,特殊化处理
     * @param $xml
     * @return mixed
     */
    public static function xmlToArray($xml)
    {
        $str_xml = stristr($xml, '<response>');
        $xml_result = str_replace('</response></ns1:callServiceResponse></SOAP-ENV:Body></SOAP-ENV:Envelope>', ' ', $str_xml);
        $str_xml_result = str_replace('<response>', ' ', $xml_result);
        return json_decode($str_xml_result, true);
    }


    /**
     * 创建订单，生成跟踪号
     * @param array $params
     * @return mixed
     * @throws InvalidIArgumentException
     * @throws ManyProductException
     * {"code":0,"info":"success","data":{"flag":true,"tipMsg":"","customerOrderNo":"T1020210305180144601085785","syOrderNo":"HMEUS0000209822YQ","trackingNumber":"4208340492748927005485000029484197","frtTrackingNumber":"","predictionFreight":"","effectiveDays":"","req_data":{"appToken":"133995f4fd84a213dca365a024416007","appKey":"32bcfeeeb4476abd2ea706c94fcfe50a32bcfeeeb4476abd2ea706c94fcfe50a","serviceMethod":"createorder"},"res_data":{"data":{"order_id":2671015,"refrence_no":"T1020210305180144601085785","shipping_method_no":"HMEUS0000209822YQ","channel_hawbcode":"4208340492748927005485000029484197","consignee_areacode":null,"station_code":null},"success":1,"cnmessage":"订单创建成功","enmessage":"订单创建成功","order_id":2671015}}}
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
            $productList = $volume = [];
            $order_weight = 0;
            foreach ($item['productList'] as $value) {
                $productList[] = [
                    'sku' => $value['productSku'] ?? '',// N:产品 SKU;Length <= 100
                    'invoice_enname' => $value['declareEnName'] ?? '',// Y:申报英文名称Length <= 50
                    'invoice_cnname' => $value['declareCnName'] ?? '',// N:申报中文名称Length <= 50
                    'invoice_quantity' => (int)($value['quantity'] ?? ''),// Y:产品数量;数值必须为正整数
                    'unit_code' => 'PCE', //N:单位  MTR：米  PCE：件 SET：套 默认PCE
                    'invoice_weight' => empty($value['declareWeight']) ? '0.000' : round($value['declareWeight'], 3),// Y:总量;Length <= 50 KG
                    'invoice_unitcharge' => empty($value['declarePrice']) ? '0.000' : round($value['declarePrice'], 3), //Y:单价
                    'invoice_currencycode' => $value['currencyCode'] ?? 'USD',// , //申报币种，不传值默认为USD(美元)；USD-美元,AUD-澳元
                    'hs_code' => $value['hsCode'] ?? '',// N:海关编码
                    'invoice_note' => '', //配货信息
                    'invoice_url' => $value['productUrl'] ?? '',// N:销售地址
                    'material_enture' => '', //N:英文材质
                    'material' => '', //N:申报材质
                    'product_id' => $value['orderItemId'] ?? '', //N:产品ID
                ];
                $order_weight += $value['declareWeight'];
                $volume[] = [
                    'length' => round($value['length'], 3) ?? '0.000',//长,单位CM
                    'width' => round($value['width'], 3) ?? '0.000',//宽,单位CM
                    'height' => round($value['height'], 3) ?? '0.000',//高,单位CM
                    'weight' => round($value['declareWeight'], 3) ?? '0.000',//箱子重量，单位KG
                ];
            }
            $data = [
                'reference_no' => $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 50
                //todo 调试写死
                'shipping_method' => $item['shippingMethodCode'] ?? 'EUR-XX',// Y:serviceCode: test => UBI.CN2FR.ASENDIA.FULLLY.TRACKED
                'country_code' => $item['recipientCountryCode'] ?? '', //Y:收件人国家二字码
                'order_weight' => round($order_weight, 3),// Y:订单重量，单位KG,3位小数
                'order_pieces' => 1, //N:外包装件数,默认1
                'cargo_type' => 'W',//N:货物类型W：包裹  D：文件 B：袋子
                //    'sales_amount' => $item['packageCodAmount'] ?? '',//COD金额
                //    'sales_currency' => $item['packageCodCurrencyCode'] ?? '',//COD币种
                //    'is_COD'=>empty($item['packageCodAmount'])?'N':Y,
                'mail_cargo_type' => '',//N:包裹申报种类（1-Gif礼品；2-CommercialSample商品货样；3-Document文件；4-Other其他。默认4）
                'buyer_id' => $item['buyer_id'] ?? '', //N:EORI
                'ioss' => $item['iossNumber'] ?? '',//ioss税号 否非必填
                'Shipper' => [
                    //发件人信息
                    'shipper_name' => $item['senderName'] ?? '', //Y:发件人姓名
                    'shipper_company' => $item['senderCompany'] ?? '', // N:发件人公司名
                    'shipper_countrycode' => $item['senderCountryCode'] ?? '', // Y:发件人国家二字码
                    'shipper_province' => $item['senderState'] ?? '', // Y:发件人省
                    'shipper_city' => $item['senderCity'] ?? '',// Y:发件人城市Length<=64
                    'shipper_tax_number' => $item['senderTaxNumber'] ?? '', //发件人税号
                    'shipper_tax_number_type' => '',//发件人税号类型 1=个人 2=公司 3=护照 4=其他
                    'shipper_street' => $item['senderFullAddress'] ?? '',// N:发件人完整地址Length <= 200
                    'shipper_postcode' => $item['senderPostCode'] ?? '',// N:发件人邮编Length <= 32
                    'shipper_areacode' => '', // N:区域代码
                    'shipper_telephone' => $item['senderPhone'] ?? '', //Y:发件人电话
                    'shipper_mobile' => $item['senderPhone'] ?? '',// Y:发件人电话Length <= 32,
                    'shipper_email' => $item['senderEmail'] ?? '', //N:发件人邮箱
                    'shipper_fax' => '', //N:发件人传真
                    'shipper_eori' => '',//N,发件人EORI
                ],
                'Consignee' => [ //发件人信息
                    'consignee_name' => $item['recipientName'] ?? '',// Y:收件人姓名
                    'consignee_company' => $item['recipientCompany'] ?? '', //N:收件人公司名
                    // 'consignee_countrycode' => $item['recipientCountryCode'] ?? '', //Y:收件人国家二字代码
                    'consignee_province' => $item['recipientState'] ?? '', //N:收件人省
                    'consignee_city' => $item['recipientCity'] ?? '', //N:收件人城市
                    'consignee_district' => '', //N:收件人区/县
                    'consignee_street' => $item['recipientStreet'] ?? '',// Y:收件人街道1
                    'consignee_street2' => $item['recipientStreet1'] ?? '',// Y:收件人街道2
                    'consignee_street3' => empty($item['recipientStreet2']) ? '' : $item['recipientStreet2'],// Y:收件人街道3
                    'consignee_postcode' => $item['recipientPostCode'] ?? '', //N:收件人邮编
                    //         'consignee_doorplate' => '', //N:收件人门牌号
                    'consignee_telephone' => $item['recipientPhone'] ?? '', //N:收件人电话
                    'consignee_mobile' => $item['recipientMobile'] ?? '', //N:收件人手机
                    'consignee_email' => $item['recipientEmail'] ?? '',// N:收件人邮箱Length <= 128
//                        'consignee_certificatetype' => 1,// N:收件人证件类型（ID-身份证；PP-护照）
                    'consignee_taxno' => $item['recipientTaxNumber'] ?? '', //N:收件人税号（VAT&GST Number）
                    'consignee_certificatetype' => '',//N:证件类型代码  ID：身份证  PP：护照
                    'consignee_certificatecode' => $item['recipientIdentityNumber'] ?? '',// N:证件号码
                    'consignee_credentials_period' => '', //N:证件有效期， 格式：2014-04-15
                    'consignee_taxno_type' => '',//收件人税号类型 1=个人 2=公司 3=护照 4=其他
                    'consignee_tariff' => $item['recipientTaxNumber'] ?? '',// 收件人税号
                    'consignee_eori' => '',//	收件人EORI
                    'buyer_id' => $item['buyer_id'] ?? '',
                ],
                'ItemArr' => $productList,// Y:海关申报信息
                'Volume' => $volume,//材积信息,快件材积,如果传入多个则为一票多件
            ];

            $ls[] = $data;
        }

        $response = $this->request(__FUNCTION__, $ls[0]);

        $reqRes = $this->getReqResData();


        // 处理结果,没有重复下单
        $fieldData = [];
        $fieldMap = FieldMap::createOrder();


        // 结果
        $flag = $response['ask'] == 'Success';

        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['Error']['errMessage'] ?? ($response['message'] ?? ''));

        // 获取追踪号,如果延迟的话
        if ($flag && ($response['track_status'] == 2)) {
            $trackNumberResponse = $this->getTrackNumber($response['refrence_no']);
            if ($trackNumberResponse['flag']) {
                $fieldData['trackingNo'] = $trackNumberResponse['trackingNumber'] ?? '';//追踪号
                $fieldData['frt_channel_hawbcode'] = $trackNumberResponse['frtTrackingNumber'] ?? '';//尾程追踪号
            }
        }

        $fieldData['order_id'] = empty($response['order_code']) ? (empty($response['system_number']) ? '' : $response['system_number']) : $response['order_code'];
        $fieldData['refrence_no'] = empty($response['refrence_no']) ? ($ls[0]['reference_no'] ?? '') : $response['refrence_no'];
        $fieldData['trackingNo'] = empty($response['shipping_method_no']) ? (empty($trackNumberResponse['trackingNumber']) ? '' : $trackNumberResponse['trackingNumber']) : $response['shipping_method_no'];
        $fieldData['frt_channel_hawbcode'] = empty($response['order_code']) ? (empty($trackNumberResponse['frtTrackingNumber']) ? '' : $trackNumberResponse['frtTrackingNumber']) : $response['order_code'];
        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);
    }

    /**统一封装请求
     * @param string $function
     * @param array $data
     * @return mixed
     */
    public function request($function, $data = [])
    {
        $data = $this->buildParams($function, $data);
        $this->req_data = $data;
        $response = $this->sendCurl('post', $this->config['url'], $data, $this->dataType, $this->apiHeaders);
        $this->res_data = $response;
        return $response;
    }

    /**
     * @param $interface string 方法名
     * @param array $arr 请求参数
     * @return array
     */
    public function buildParams($interface, $arr = [])
    {
        $data = [
            'appToken' => $this->config['appToken'],
            'appKey' => $this->config['appKey'],
            'service' => $this->interface[$interface],
            'paramsJson' => "{}",
        ];
        if (!empty($arr)) {
            $data['paramsJson'] = json_encode($arr, JSON_UNESCAPED_UNICODE);
        }
        return $data;
    }

    /**
     * 获取跟踪号
     * @param $processCode
     * @param $is_ret
     * @return array
     */
    public function getTrackNumber(string $processCode, $is_ret = false)
    {
        $param = ['reference_no' => [$processCode]];
        $response = $this->request(__FUNCTION__, $param);
        $fieldData = [];
        $fieldMap = FieldMap::getTrackNumber();
        $flag = 0;
        if ($response['ask'] == 'Success') {
            $flag = 1;
        }

        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : (empty($response['Error']['errMessage']) ? ($response['message'] ?? '未知错误') : $response['Error']['errMessage']);
        $fieldData['trackingNo'] = $flag ? $response['data'][0]['TrackingNumber'] : '';//追踪号
        $fieldData['frt_channel_hawbcode'] = $flag ? $response['data'][0]['WayBillNumber'] : '';//尾程追踪号,运单号
        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
        if ($is_ret) return $fieldData['flag'] ? $this->retSuccessResponseData($ret) : $this->retErrorResponseData($fieldData['info'], $fieldData);
        return $ret;
    }


    /**
     * 通过客户单号获取运费试算
     * @param string $processCode
     * @return mixed|string
     */
    public function getShippingFee(string $processCode)
    {
        if (empty($processCode)) {
            return '';
        }

        $extUrlParams = ['reference_no' => $processCode];
        $response = $this->request(__FUNCTION__, $extUrlParams);
        // 结果
        $flag = $response['ask'] == 'Success';
        if (!$flag) {
            return '';
        }
        $ret = $response['Data'];
        return $ret;
    }

    /**
     * 获取物流商运输方式
     * @return mixed
     * {"code":0,"info":"success","data":[{"shippingMethodCode":"CH001","shippingMethodEnName":"瑞士Asendia专线","shippingMethodCnName":"瑞士Asendia专线","shippingMethodType":"","remark":""},{"shippingMethodCode":"USYT01","shippingMethodEnName":"美国海卡","shippingMethodCnName":"美国海卡","shippingMethodType":"","remark":""}]}
     */
    public function getShippingMethod()
    {
        $response = $this->request(__FUNCTION__);
        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();
        if ($response['ask'] != 'Success') {
            return $this->retErrorResponseData((empty($response['Error']['errMessage']) ? $response['message'] : $response['Error']['errMessage']) ?? '未知错误');
        }

        foreach ($response['data'] as $item) {
            $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
        }
        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 修改订单重量
     * @param array $params
     * @return mixed|void
     */
    public function operationPackages($params)
    {
        $data[] = [
            'order_code' => $params['ProcessCode'] ?? '',
            'weight' => empty($params['weight']) ? 0 : round($params['weight'], 3),//单位KG
        ];
        $response = $this->request(__FUNCTION__, $data);
        if (empty($response)) {
            return $this->retErrorResponseData('修改订单重量异常');
        }
        // 结果
        if ($response['ask'] != 'Success') {
            return $this->retErrorResponseData((empty($response['Result'][0]['Error']['errMessage']) ? $response['message'] : $response['Result'][0]['Error']['errMessage']) ?? '未知错误');
        }
        return $this->retSuccessResponseData($response);
    }

    /**
     * 删除订单
     * @param string $order_code
     * @return mixed|void
     */
    public function deleteOrder(string $order_code)
    {
        $data = [
            'reference_no' => $order_code, //客户参考号
        ];
        $response = $this->request(__FUNCTION__, $data);
        // 结果
        $flag = $response['ask'] == 'Success';

        return $flag;
    }

    /**
     * 修改订单状态
     * @return mixed
     */
    public function updateOrderStatus(array $params)
    {
        $this->throwNotSupport(__FUNCTION__);
    }


    /**
     * 获取订单费用明细
     * @param $order_id
     * @return mixed
     */
    public function getFeeDetailByOrder($order_id)
    {
        $data = [
            'reference_no' => $order_id, //三选一  客户参考号
        ];
        $response = $this->request(__FUNCTION__, $data);
        return $response;
    }

    /**
     * 获取订单标签
     * @return mixed
     * {"code":0,"info":"success","data":[{"flag":"","tipMsg":"","orderNo":"","labelPathType":"pdf","labelPath":"http://szdbf.rtb56.com/api-lable/pdf/20210305/aad7b262-e3c0-49db-872b-adeb1431b633.pdf","labelPathPlat":"","labelType":"1"}]}
     */
    public function getPackagesLabel($params)
    {
        $data = [
            'reference_no' => $params['trackNumber'],
            //    'type' => 3,//单号类型 1：运单号 2：客户单号 3：跟踪号，不传默认自动匹配三个单号
            'label_type' => 1, //PDF标签尺寸类型(仅对系统标签生效)：1：10 X 10标签,2：A4纸,3：10X15标签,默认1
        ];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        if ($response['ask'] != 'Success') {
            return $this->retErrorResponseData((empty($response['Error']['errMessage']) ? $response['message'] : $response['Error']['errMessage']) ?? '未知错误');
        }
        $item = $response;
        $item['flag'] = true;
        $item['type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF;
        $item['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF;
        $item['url'] = base64_encode(file_get_contents($item['url']));
        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
        return $this->retSuccessResponseData($fieldData);
    }


    /**
     * 获取订单费用
     * @return mixed
     */
    public function getFeeByOrder(string $order_code)
    {
        $data = [
            'reference_no' => $order_code,
        ];
        $response = $this->request(__FUNCTION__, $data);
        return $response;
    }


    /**
     * 获取物流商轨迹
     * {"data":[{"shipper_hawbcode":"T1020210305164402901045177","server_hawbcode":"HMEUS0000223958YQ","channel_hawbcode":null,"destination_country":"US","destination_country_name":null,"track_status":"NT","track_status_name":"转运中","signatory_name":"","details":[{"tbs_id":"2826898","track_occur_date":"2021-03-05 16:44:59","track_location":"","track_description":"快件电子信息已经收到","track_description_en":"Shipment information received","track_code":"IR","track_status":"NT","track_status_cnname":"转运中"}]}],"success":1,"cnmessage":"获取跟踪记录成功","enmessage":"获取跟踪记录成功","order_id":0}
     * @return mixed
     * {"code":0,"info":"success","data":[{"flag":"","tipMsg":"","orderNo":"HMEUS0000223958YQ","status":"NT","statusMsg":"转运中","logisticsTrackingDetails":[{"status":"NT","statusContent":"快件电子信息已经收到","statusTime":"2021-03-05 16:44:59","statusLocation":""}]}]}
     */
    public function queryTrack($trackNumber)
    {
        $trackNumberArray = $this->toArray($trackNumber);
        if (count($trackNumberArray) > self::QUERY_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "查询物流轨迹一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
        }
        $data = [
            'codes' => $trackNumberArray,//传输组
        ];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        if ($response['ask'] != 'Success') {
            return $this->retErrorResponseData((empty($response['Error']['errMessage']) ? $response['message'] : $response['Error']['errMessage']) ?? '未知错误');
        }

        $data = $response['Data'][0];

        $ls = [];
        foreach ($data['Detail'] as $key => $val) {
            $val['track_status'] = $data['Status'];//货态
            $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
        }
        $data['Detail'] = $ls;
        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);

        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * @param $order_id
     * @return mixed
     * 获取包裹详情
     */
    public function getPackagesDetail($order_id)
    {
        $data = [
            'reference_no' => $order_id,
        ];
        $response = $this->request(__FUNCTION__, $data);
        return $response;
    }
}