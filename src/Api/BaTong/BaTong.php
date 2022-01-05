<?php
/**
 *
 * User: blaine
 * Date: 2/22/21
 */

namespace smiler\logistics\Api\BaTong;


use smiler\logistics\Api\BaTong\FieldMap;
use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

/**
 * 巴通物流
 * @link http://btgyl.rtb56.com/webservice/PublicService.asmx/ServiceInterfaceUTF8
 * Class BaTong
 * @package smiler\logistics\Api\BaTong
 */
class BaTong extends LogisticsAbstract implements BaseLogisticsInterface, TrackLogisticsInterface, PackageLabelLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹
     */
    const ORDER_COUNT = 1;
    /**
     * 一次最多查询多少个跟踪号
     */
    const QUERY_TRACK_COUNT = 1;

    public $iden = 'batong';

    public $iden_name = '巴通物流';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'form';

    public $apiHeaders = [];

    public $interface = [

        'createOrder' => 'createorder', // 【创建订单】

        'submitOrder' => 'submitforecast', //提交预报(先创建草稿状态的订单才需要再调用此接口提交预报)

        'operationPackages' => 'updateorder', //修改订单重量

        'deleteOrder' => 'removeorder', //删除订单

        'getPackagesLabel' => 'getnewlabel', // 【打印标签|面单】

        'getTrackNumber' => 'gettrackingnumber',//获取跟踪号

        'queryTrack' => 'gettrack', //轨迹查询

        'getFeeByOrder' => 'getbusinessfee', //费用查询

        'getShippingMethod' => 'getshippingmethod', //获取配送方式

        'getPackagesDetail' => 'getbusinessweight', //查询订单

        'feeTrail' => 'feetrail', //运费试算 todo 暂时未用
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
            $productList = [];
            $order_weight = 0;
            foreach ($item['productList'] as $value) {
                $productList[] = [
                    'sku' => $value['productSku'] ?? '',// N:产品 SKU;Length <= 100
                    'invoice_enname' => $value['declareEnName'] ?? '',// Y:申报英文名称Length <= 50
                    'invoice_cnname' => $value['declareCnName'] ?? '',// N:申报中文名称Length <= 50
                    'invoice_quantity' => (int)($value['quantity'] ?? ''),// Y:产品数量;数值必须为正整数
                    'unit_code' => 'PCE', //N:单位  MTR：米  PCE：件 SET：套 默认PCE
                    'invoice_weight' => $value['declareWeight'] ?? '',// Y:总量;Length <= 50 KG
                    'invoice_unitcharge' => (float)($value['declarePrice'] ?? ''), //Y:单价
                    'invoice_currencycode' => $value['currencyCode'] ?? 'USD',// , //申报币种，不传值默认为USD(美元)；USD-美元,AUD-澳元
                    'hs_code' => $value['hsCode'] ?? '',// N:海关编码
                    'invoice_note' => '', //配货信息
                    'invoice_url' => $value['productUrl'] ?? '',// N:销售地址
                    'invoice_info' => '', //N:商品图片地址
                    'invoice_material' => '', //N:申报材质
                    'invoice_spec' => '', //N:规格
                ];
                $order_weight += $value['declareWeight'];
            }
            $address = ($item['recipientStreet'] ?? ' ') .'   '. ($item['recipientStreet1'] ?? ' ')  .'   '. (empty($item['recipientStreet2']) ? '' : $item['recipientStreet2']);
            $extra_service = [];
            if (isset($item['iossNumber']) && !empty($item['iossNumber'])) {
                $extra_service = [
                    'extra_servicecode' => 'IO',//额外服务类型代码
                    'extra_servicevalue' => $item['iossNumber'],//额外服务值
                ];
            }
            $data = [
                'reference_no' => $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 50
                //todo 调试写死
                'shipping_method' => $item['shippingMethodCode'] ?? 'US0022',// Y:serviceCode: test => UBI.CN2FR.ASENDIA.FULLLY.TRACKED
                'shipping_method_no' => '', //N:服务商单号（追踪单号，默认不需要传值）
                'order_weight' => round($order_weight, 3),// Y:订单重量，单位KG，默认为0.2,3位小数
                'order_pieces' => 1, //N:外包装件数,默认1
                'cargotype' => 'W',//N:货物类型W：包裹  D：文件 B：袋子
                'order_status' => '', //N:订单状态P：已预报 (默认) D：草稿 (如果创建草稿订单，则需要再调用submitforecast【提交预报】接口)
                'mail_cargo_type' => '',//N:包裹申报种类（1-Gif礼品；2-CommercialSample商品货样；3-Document文件；4-Other其他。默认4）
                'buyer_id' => $item['buyer_id'] ?? '', //N:EORI
                'order_info' => $item['remark'] ?? '', //N:订单备注
                'platform_id' => '', //N:平台ID（如果您是电商平台，请联系我们添加并确认您对应的平台ID）
                'custom_hawbcode' => '', //N:自定义单号
                'shipper' => [
                    //发件人信息
                    'shipper_name' => $item['senderName'] ?? '', //Y:发件人姓名
                    'shipper_company' => $item['senderCompany'] ?? '', // N:发件人公司名
                    'shipper_countrycode' => $item['senderCountryCode'] ?? '', // Y:发件人国家二字码
                    'shipper_province' => $item['senderState'] ?? '', // Y:发件人省
                    'shipper_city' => $item['senderCity'] ?? '',// Y:发件人城市Length<=64
                    'shipper_district' => '', //发件人区/县
                    'shipper_street' => $item['senderFullAddress'] ?? '',// N:发件人完整地址Length <= 200
                    'shipper_postcode' => $item['senderPostCode'] ?? '',// N:发件人邮编Length <= 32
                    'shipper_areacode' => '', // N:区域代码
                    'shipper_telephone' => $item['senderPhone'] ?? '', //Y:发件人电话
                    'shipper_mobile' => $item['senderPhone'] ?? '',// Y:发件人电话Length <= 32,
                    'shipper_email' => $item['senderEmail'] ?? '', //N:发件人邮箱
                    'shipper_fax' => '', //N:发件人传真
                ],
                'consignee' => [ //发件人信息
                    'consignee_name' => $item['recipientName'] ?? '',// Y:收件人姓名
                    'consignee_company' => $item['recipientCompany'] ?? '', //N:收件人公司名
                    'consignee_countrycode' => $item['recipientCountryCode'] ?? '', //Y:收件人国家二字代码
                    'consignee_province' => $item['recipientState'] ?? '', //N:收件人省
                    'consignee_city' => $item['recipientCity'] ?? '', //N:收件人城市
                    'consignee_district' => '', //N:收件人区/县
                    'consignee_street' => $address ?? '',// Y:收件人街道
                    'consignee_postcode' => $item['recipientPostCode'] ?? '', //N:收件人邮编
                    'consignee_doorplate' => '', //N:收件人门牌号
                    'consignee_areacode' => '', //N:收件人区域代码
                    'consignee_telephone' => $item['recipientPhone'] ?? '', //N:收件人电话
                    'consignee_mobile' => $item['recipientMobile'] ?? '', //N:收件人手机
                    'consignee_email' => $item['recipientEmail'] ?? '',// N:收件人邮箱Length <= 128
//                        'consignee_certificatetype' => 1,// N:收件人证件类型（ID-身份证；PP-护照）
                    'consignee_fax' => '', //N:收件人传真
                    'consignee_certificatetype' => '',//N:证件类型代码  ID：身份证  PP：护照
                    'consignee_certificatecode' => $item['recipientIdentityNumber'] ?? '',// N:证件号码
                    'consignee_credentials_period' => '', //N:证件有效期， 格式：2014-04-15
                    'consignee_tariff' => $item['recipientTaxNumber'] ?? '',// 收件人税号
                ],
                'invoice' => $productList,// Y:一次最多支持 5 个产品信息（超过 5 个将会忽略）
            ];
            if (!empty($extra_service)) $data['extra_service'] = $extra_service;
            $ls[] = $data;
        }

        $response = $this->request(__FUNCTION__, $ls[0]);

        $reqRes = $this->getReqResData();


        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::createOrder();


//        // 重复订单号,2021/10/1,订单号重复，2021/11/16日，变更的判断
//        if ($response['success'] == 2) {
//            // 进行删除操作,再重新下单
//            $delFlag = $this->deleteOrder($response['data']['refrence_no']);
//            if ($delFlag) {
//                $response = $this->request(__FUNCTION__, $ls[0]);
//                $reqRes = $this->getReqResData();
//            }
//        }

        //2022/1/4日单号已存在新增重复判断,中文字符
        if ((stripos($response['cnmessage'], '单号已存在')) || (stripos($response['cnmessage'], 'exists')) || (stripos($response['enmessage'], 'exists'))  || (stripos($response['enmessage'], '单号已存在'))) {
            // 进行删除操作,再重新下单
            $delFlag = $this->deleteOrder($ls[0]['reference_no']);
            if ($delFlag) {
                $response = $this->request(__FUNCTION__, $ls[0]);
                $reqRes = $this->getReqResData();
            }
        }

        // 结果
        $flag = false;
        //2022/1/5新增判断 =2是重复判断，直接返回追踪号
        if(($response['success'] == 1) || ($response['success'] == 2)){
            $flag = true;
        }
        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['cnmessage'] ?? ($response['enmessage'] ?? ''));

        // 获取追踪号,如果延迟的话
        if ($flag && empty($response['data']['channel_hawbcode'])) {
            $trackNumberResponse = $this->getTrackNumber($response['data']['refrence_no']);
            if ($trackNumberResponse['flag']) {
                $fieldData['trackingNo'] = $trackNumberResponse['trackingNumber'] ?? '';//追踪号
                $fieldData['frt_channel_hawbcode'] = $trackNumberResponse['frtTrackingNumber'] ?? '';//尾程追踪号
            }
        }

        $fieldData['order_id'] = empty($response['data']['channel_hawbcode']) ? (empty($response['data']['order_id']) ? '' : $response['data']['order_id']) : $response['data']['channel_hawbcode'];
        $fieldData['refrence_no'] = empty($response['data']['refrence_no']) ? ($ls[0]['reference_no'] ?? '') : $response['data']['refrence_no'];
        $fieldData['trackingNo'] = empty($response['data']['shipping_method_no']) ? '' : $response['data']['shipping_method_no'];
        $fieldData['frt_channel_hawbcode'] = empty($response['data']['channel_hawbcode']) ? '' : $response['data']['channel_hawbcode'];
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
            'serviceMethod' => $this->interface[$interface],
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
        $param = ['reference_no' => $processCode];
        $response = $this->request(__FUNCTION__, $param);
        $fieldData = [];
        $fieldMap = FieldMap::getTrackNumber();
        if ($response['success'] == 1) {
            $flag = 1;
        } else {
            $flag = 0;
        }
        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['cnmessage'] ?? ($response['enmessage'] ?? '未知错误'));
        $fieldData['trackingNo'] = $flag ? $response['data']['shipping_method_no'] : '';//追踪号
        $fieldData['frt_channel_hawbcode'] = $flag ? $response['data']['channel_hawbcode'] : '';//尾程追踪号
        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
        if ($is_ret) return $fieldData['flag'] ? $this->retSuccessResponseData($ret) : $this->retErrorResponseData($fieldData['info'], $fieldData);
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
        if ($response['success'] != 1) {
            return $this->retErrorResponseData($response['cnmessage'] ?? '未知错误');
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
        $data = [
            'reference_no' => $params['ProcessCode'] ?? '',
            'order_weight' => empty($params['weight']) ? 0 : round($params['weight'], 3),//单位KG
        ];
        $response = $this->request(__FUNCTION__, $data);
        if (empty($response)) {
            return $this->retErrorResponseData('修改订单重量异常');
        }
        // 结果
        if ($response['success'] != 1) {
            return $this->retErrorResponseData($response['cnmessage'] ?? '未知错误');
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
        $flag = $response['success'] == 1;

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
     * 获取订单费用
     * @return mixed
     */
    public function getFeeByOrder(string $order_code)
    {
        $data = [
            'order_id' => '',
            'reference_no' => $order_code,
            'shipping_method_no' => '',
        ];
        $response = $this->request(__FUNCTION__, $data);
        return $response;
    }

    /**
     * 获取订单费用明细
     * @param $order_id
     * @return mixed
     */
    public function getFeeDetailByOrder($order_id)
    {
        $data = [
            'order_id' => '', //订单ID
            'reference_no' => $order_id, //三选一  客户参考号
            'shipping_method_no' => '', //服务商单号
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
            'configInfo' => [
                'lable_file_type' => 2, //标签文件类型1：PNG文件2：PDF文件
                'lable_paper_type' => $params['label_type'] ?? 1, //纸张类型1：标签纸2：A4纸
                'lable_content_type' => $params['label_content_type'] ?? 1, //标签内容类型代码1：标签2：报关单3：配货单4：标签+报关单5：标签+配货单6：标签+报关单+配货单
                'additional_info' => [
                    'lable_print_invoiceinfo' => 'N',//标签上打印配货信息 (Y:打印 N:不打印) 默认 N:不打印
                    'lable_print_buyerid' => 'N', //标签上是否打印买家ID (Y:打印 N:不打印) 默认 N:不打印
                    'lable_print_datetime' => 'Y', //标签上是否打印日期 (Y:打印 N:不打印) 默认 Y:打印
                    'customsdeclaration_print_actualweight' => 'N', //报关单上是否打印实际重量 (Y:打印 N:不打印) 默认 N:不打印
                ],
            ],
        ];
        $trackNumbers = $this->toArray($params['trackNumber']);
        foreach ($trackNumbers as $number) {
            $data['listorder'][] = [
                'reference_no' => $number,
                'config_code' => '1', //标签纸张配置代码1：标签纸-地址标签2：标签纸-地址标签+报关单3：标签纸-地址标签+配货单4：标签纸-地址标签+报关单+配货单5：A4纸-地址标签6：A4纸-地址标签+报关单7：A4纸-地址标签+配货单8：A4纸-地址标签+报关单+配货单
            ];
        }

        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        if ($response['success'] != 1) {
            return $this->retErrorResponseData($response['cnmessage'] ?? '未知错误');
        }

        foreach ($response['data'] as $item) {
            $item['flag'] = true;
            $item['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_PDF;

            $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
        }
        return $this->retSuccessResponseData($fieldData);
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
            'tracking_number' => $trackNumber,
        ];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        if ($response['success'] != 1) {
            return $this->retErrorResponseData($response['cnmessage'] ?? '未知错误');
        }

        $data = $response['data'][0];

        $ls = [];
        foreach ($data['details'] as $key => $val) {
            $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
        }
        $data['details'] = $ls;
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