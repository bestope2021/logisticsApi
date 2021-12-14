<?php
/**
 *
 * User: blaine
 * Date: 2/22/21
 */

namespace smiler\logistics\Api\WeiShi;


use smiler\logistics\Api\WeiShi\FieldMap;
use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

/**
 * 纬狮物流
 * @link http://btgyl.rtb56.com/webservice/PublicService.asmx/ServiceInterfaceUTF8
 * Class WeiShi
 * @package smiler\logistics\Api\WeiShi
 */
class WeiShi extends LogisticsAbstract implements BaseLogisticsInterface, TrackLogisticsInterface, PackageLabelLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹
     */
    const ORDER_COUNT = 1;
    /**
     * 一次最多查询多少个跟踪号
     */
    const QUERY_TRACK_COUNT = 1;

    public $iden = 'weishi';

    public $iden_name = '纬狮物流';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'json';

    public $apiHeaders = [
        'Content-Type' => 'application/json'
    ];

    public $interface = [

        'createOrder' => 'createOrder', // 【创建订单】

        'operationPackages' => 'modifyOrderWeight', //修改订单重量

        'deleteOrder' => 'cancelOrder', //删除订单

        'getPackagesLabel' => 'getLabelUrl', // 【打印标签|面单】

        'queryTrack' => 'getCargoTrack', //轨迹查询

        'getFeeByOrder' => 'getbusinessfee', //费用查询

        'getShippingMethod' => 'getShippingMethod', //获取配送方式

        'getPackagesDetail' => 'getbusinessweight', //查询订单

        'feeTrail' => 'feetrail', //运费试算 todo 暂时未用
    ];

    /**
     * WeiShi constructor.
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
                    'invoice_enname' => $value['declareEnName'] ?? '',// Y:申报英文名称Length <= 50
                    'invoice_cnname' => $value['declareCnName'] ?? '',// N:申报中文名称Length <= 50
                    'invoice_weight' => $value['declareWeight'] ?? '',// Y:总量;Weight <= 50 KG
                    'invoice_quantity' => (int)($value['quantity'] ?? ''),// Y:产品数量;数值必须为正整数
                    'unit_code' => 'PCE', //N:单位  MTR：米  PCE：件 SET：套 默认PCE
                    'invoice_unitcharge' => (float)($value['declarePrice'] ?? ''), //Y:单价
                    'invoice_currencycode' => $value['currencyCode'] ?? 'USD',// , //申报币种，不传值默认为USD(美元)；USD-美元,AUD-澳元
                    'hs_code' => $value['hsCode'] ?? '',// N:海关编码
                    'invoice_note' => '', //配货信息
                //    'invoice_url' => $value['productUrl'] ?? '',// N:销售地址
                    'sku' => $value['productSku'] ?? '',// N:产品 SKU;Length <= 100
                ];
                $order_weight += $value['declareWeight'];
            }
            $address = ($item['recipientStreet'] ?? ' ') . ($item['recipientStreet1'] ?? ' ') . ($item['recipientStreet2'] ?? '');
            $data = [
                'reference_no' => $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 50
                //todo 调试写死
                'shipping_method' => $item['shippingMethodCode'] ?? 'MX1001',// Y:serviceCode: test => UBI.CN2FR.ASENDIA.FULLLY.TRACKED
                'country_code' => $item['recipientCountryCode'] ?? '', //Y:收件人国家二字代码
                'order_weight' => (float)$order_weight,// Y:订单重量，单位KG，默认为0.2
                'order_pieces' => 1, //N:外包装件数,默认1
                'mail_cargo_type' => 4,//N:包裹申报种类（1-Gif礼品；2-CommercialSample商品货样；3-Document文件；4-Other其他。默认4）
                'Shipper' => [
                    //发件人信息
                    'shipper_company' => $item['senderCompany'] ?? '', // N:发件人公司名
                    'shipper_countrycode' => $item['senderCountryCode'] ?? '', // Y:发件人国家二字码
                    'shipper_province' => $item['senderState'] ?? '', // Y:发件人省
                    'shipper_city' => $item['senderCity'] ?? '',// Y:发件人城市Length<=64
                    'shipper_street' => $item['senderFullAddress'] ?? '',// N:发件人完整地址Length <= 200
                    'shipper_postcode' => $item['senderPostCode'] ?? '',// N:发件人邮编Length <= 32
                    'shipper_areacode' => '', // N:区域代码
                    'shipper_name' => $item['senderName'] ?? '', //Y:发件人姓名
                    'shipper_telephone' => $item['senderPhone'] ?? '', //Y:发件人电话
                    'shipper_mobile' => $item['senderPhone'] ?? '',// Y:发件人电话Length <= 32,
                    'shipper_email' => $item['senderEmail'] ?? '', //N:发件人邮箱
                    'shipper_fax' => '', //N:发件人传真
                    'order_note'=> '',//N:订单备注
                ],
                'Consignee' => [ //收件人信息
                    'consignee_company' => $item['recipientCompany'] ?? '', //N:收件人公司名
                    'consignee_province' => $item['recipientState'] ?? '', //N:收件人省
                    'consignee_city' => $item['recipientCity'] ?? '', //N:收件人城市
            //        'consignee_street' => $address ?? '',// Y:收件人街道
                    'consignee_street' => $item['recipientStreet'] ?? ' ',//收件人地址一，最大长度为35字符 ,//2021/12/14
                    'consignee_street2' => $item['recipientStreet1'] ?? ' ',//收件人地址二，//2021/12/14
                    'consignee_street3' => empty($item['recipientStreet2']) ? ' ' : $item['recipientStreet2'],//收件人地址三，//2021/12/14
                    'consignee_postcode' => $item['recipientPostCode'] ?? '', //N:收件人邮编
                    'consignee_name' => $item['recipientName'] ?? '',// Y:收件人姓名
                    'consignee_telephone' => $item['recipientPhone'] ?? '', //N:收件人电话
                    'consignee_mobile' => $item['recipientMobile'] ?? '', //N:收件人手机
                    'consignee_email' => $item['recipientEmail'] ?? '',// N:收件人邮箱Length <= 128
                    'buyer_id' => $item['buyer_id'] ?? '', //N:EORI
                    'consignee_taxno' => $item['recipientTaxNumber'] ?? '',// 收件人税号
                ],
                'ItemArr' => $productList,// Y:一次最多支持 5 个产品信息（超过 5 个将会忽略）
            ];
            $ls[] = $data;
        }
        $response = $this->request(__FUNCTION__, $ls[0]);
        $reqRes = $this->getReqResData();
        if (empty($response['ask'])) {
            return $this->retErrorResponseData($response['msg'] ?? '未知错误');
        }
        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::createOrder();
        $flag = !empty($response['ask'])?($response['ask'] == 'Success'?true:false):false;
        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['message'] ?? ($response['message'] ?? ''));
        $fieldData['order_id'] = $response['order_code'] ?? ($response['reference_no'] ?? '');
        $fieldData['reference_no'] = $response['reference_no'] ?? '';
        $fieldData['trackingNo'] = $response['order_code'] ?? '';
        $fieldData['frt_channel_hawbcode'] = $response['shipping_method_no'] ?? '';
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
            'paramsJson' => '{}',
        ];
        if (!empty($arr)) {
            $data['paramsJson'] = $arr;
        }
        return $data;
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
            return $this->retErrorResponseData($response['message'] ?? '未知错误');
        }
        foreach ($response['data'] as $item) {
            $item['shipping_method_type']='';// 运输方式类型为空
            $item['remark']=$item['track_status'];
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
        $data = [[
            'order_code' => $params['ProcessCode'] ?? '',
            'weight' => empty($params['weight']) ? 0 : round($params['weight'], 2),//单位是KG
        ]];
        $response = $this->request(__FUNCTION__, $data);
        if (empty($response)) {
            return $this->retErrorResponseData('修改订单重量接口异常');
        }
        // 结果
        if ($response['ask'] != 'Success') {
            return $this->retErrorResponseData($response['message'] ?? '未知错误');
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
            'reference_no' => $order_code, //客户单号，注：只有草稿、已预报状态支持取消。
        ];
        $response = $this->request(__FUNCTION__, $data);
        $flag=$response['ask']=='Success';
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
            'reference_no' => $params['trackNumber'],
            'lable_type' => '1', //PDF标签尺寸类型：1：10 * 10 标签;2：A4纸;3：10 * 15标签
        ];

        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        // 结果
        $flag = $response['ask'] == 'Success';

        if (!$flag) {
            return $this->retErrorResponseData($response['message'] ?? '未知错误');
        }

        $response['flag'] = $flag;
        $response['info'] = $response['message'] ?? '';
        $response['order_no'] = $params['trackNumber'] ?? '';
        $response['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_PDF;
        $response['lable_file'] = $response['url'] ?? '';
        $response['label_path_plat'] = '';//不要填写
        $response['lable_content_type'] = $params['label_content'] ?? 1;

        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($response, $fieldMap);

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
            'codes' => $trackNumberArray,//传数组
        ];
        $response = $this->request(__FUNCTION__, $data);
        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        if ($response['ask'] != 'Success') {
            return $this->retErrorResponseData($response['message'] ?? '未知错误');
        }

        $data = $response['Data'][0];
        $ls = [];
        if(!empty($data['Detail'])){
            foreach ($data['Detail'] as $key => $val) {
                $data['Status']=$val['Comment_en'];
                $data['New_Comment']=$val['Comment'];
                $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
            }
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