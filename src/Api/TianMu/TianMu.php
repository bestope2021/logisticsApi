<?php
/**
 *
 * User: Ghh.Guan
 * Date: 5/21/21
 */

namespace smiler\logistics\Api\TianMu;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

class TianMu extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface, TrackLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹,2自定义
     */
    const ORDER_COUNT = 2;
    /**
     * 一次最多查询多少个物流商
     */
    const QUERY_TRACK_COUNT = 1;
    public $iden = 'tianmu';
    public $iden_name = '天木头程';
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
     * DgPost constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['appToken','appKey', 'url'], $config);
        $this->config = $config;
        if (!empty($config['apiHeaders'])) {
            $this->apiHeaders = array_merge($this->apiHeaders, $config['apiHeaders']);
        }
    }

    /**
     * 创建订单
     * @param array $params
     * @return mixed
     * @throws InvalidIArgumentException
     * @throws ManyProductException
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
            $invoice = [];
            $cargovolume = [];
            foreach ($item['productList'] as $index => $value) {
                $invoice[] = [
                    'sku' => $value['productSku'] ?? '',//sku
                    'invoice_enname' => $value['declareEnName'] ?? '',//英文品名
                    'invoice_cnname' => $value['declareCnName'] ?? '',//中文品名
                    'invoice_quantity' => $value['quantity'] ?? '',//数量
                    'invoice_unitcharge' => $value['declarePrice'] ?? '',//单价
                    'hs_code' => $value['hsCode'] ?? '',//海关协制编号
                    'invoice_image' => $value['productImgUrl'] ?? $value['productUrl'],//商品图片地址
                    'invoice_material' => $value['productMaterial'] ?? '',//材质
                    'invoice_spec' => $value['modelType'] ?? '',//规格
                    'invoice_use' => $value['productPurpose'] ?? '',//用途
                    'invoice_brand' => $value['brand'] ?? '',//品牌
                ];
                $packageNumber = $value['packageNumber'] ?? $item['customerOrderNo'];
                $cargovolume[$packageNumber] = [
                    'child_number' => $packageNumber,//箱号(子单号)
                ];
            }


            //发件人信息
            $shipper = [
                'shipper_name' => $item['senderName'] ?? '',//发件人姓名
                'shipper_countrycode' => $item['senderCountryCode'] ?? '',//发件人国家二字代码
                'shipper_province' => $item['senderState'] ?? '',//发件人州/省
                'shipper_city' => $item['senderCity'] ?? '',//发件人城市
                'shipper_district' => $item['senderDistrict'] ?? '',//发件人区/县
                'shipper_street' => $item['senderAddress'] ?? '',//发件人街道地址
                'shipper_postcode' => $item['senderPostCode'] ?? '',//发件人邮编
                'shipper_telephone' => $item['senderPhone'] ?? '',//发件人电话
            ];

            $address = ($item['recipientStreet'] ?? ' ') . ($item['recipientStreet1'] ?? ' '). ($item['recipientStreet2'] ?? '');
            //收件人信息
            $consignee = [
                'consignee_name' => $item['recipientName'] ?? '',//收件人姓名
                'consignee_countrycode' => $item['recipientCountryCode'] ?? '',//收件人国家二字代码
                'consignee_province' => $item['recipientState'] ?? '',//收件人州/省
                'consignee_city' => $item['recipientCity'] ?? '',//收件人城市
                'consignee_street' => $address ?? '',//收件人街道地址
                'consignee_postcode' => $item['recipientPostCode'] ?? '',//收件人邮编
                'consignee_telephone' => $item['recipientPhone'] ?? '',//收件人电话
                'consignee_tariff' => $item['recipientTaxNumber'] ?? '',//收件人税号
            ];

            $order_info = [
                'reference_no' => $item['customerOrderNo'] ?? '',//客户参考号
                'shipping_method' => $item['shippingMethodCode'] ?? 'MGHJPB',//运输方式
                'cargotype' => 'W',//货物类型 W：包裹 D：文件B：袋子
                'order_status' => 'P',//订单状态 P：已预报 (默认) D：草稿 (如果创建草稿订单，则需要再调用submitforecast【提交预报】接口)
                'mail_cargo_type' => 4,//包裹申报种类 1：Gif礼品 2：CommercialSample 商品货样 3：Document 文件 4：Other 其他默认4
                'shipper' => $shipper,//发件人信息
                'consignee' => $consignee,//收件人信息
                'invoice' => $invoice,//海关申报信息
                'cargovolume' => array_values($cargovolume),//包裹材积信息
            ];
            $extra_service[] = [
                'extra_servicecode' => 'A0'
            ];
            if(!empty($extra_service)) $order_info['extra_service'] = $extra_service;
            $ls[] = $order_info;
        }
        $response = $this->request(__FUNCTION__, $ls[0]);
        // 处理结果
        $reqRes = $this->getReqResData();

        $fieldData = [];
        $fieldMap = FieldMap::createOrder();

        // 重复订单号
        if($response['success'] == 2){
            // 进行删除操作,再重新下单
            $delFlag = $this->deleteOrder($response['data']['refrence_no']);
            if($delFlag['success'] == 1){
                $response = $this->request(__FUNCTION__, $ls[0]);
                $reqRes = $this->getReqResData();
            }
        }

        // 结果
        $flag = $response['success'] == 1;

        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['cnmessage'] ?? ($response['enmessage'] ?? ''));
        // 获取追踪号
        if ($flag && empty($response['data']['channel_hawbcode'])) {
            $trackNumberResponse = $this->getTrackNumber($response['data']['refrence_no']);
            if ($trackNumberResponse['success'] != 1) {
                $fieldData['flag'] = false;
                $fieldData['info'] = $trackNumberResponse['cnmessage'];
            }
            $fieldData['channel_hawbcode'] = $trackNumberResponse['data']['channel_hawbcode'] ?? $response['data']['shipping_method_no'];
        }

        $fieldData['order_id'] = $response['data']['order_id'] ?? '';
        $fieldData['refrence_no'] = $response['data']['refrence_no'] ?? '';
        $fieldData['shipping_method_no'] = $response['data']['shipping_method_no'] ?? '';
        $fieldData['channel_hawbcode'] = $response['data']['channel_hawbcode'] ?? '';

        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
//        $this->dd($response, $ret, $reqRes);
        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);
    }

    /**
     * 获取跟踪号
     * @param $reference_no
     * @return array
     */
    public function getTrackNumber(string $reference_no)
    {
        $param = ['reference_no' => $reference_no];

        $response = $this->request(__FUNCTION__, $param);

        return $response;
    }

    public function request($function, $data = [])
    {
        $data = $this->buildParams($function, $data);
        $this->req_data = $data;
        $response = $this->sendCurl('post', $this->config['url'], $data, $this->dataType, $this->apiHeaders);
        $this->res_data = $response;
        return $response;
    }

    /**
     * 获取请求数据
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

    private function getSign($str,$MD5Key=''){
        if(empty($MD5Key)){
            $MD5Key = $this->config['MD5Key'];
            return base64_encode(md5($str.$MD5Key,TRUE));
        }
        return base64_encode(md5($str.$MD5Key));
    }

    /**
     * 获取物流商运输方式
     * @return mixed
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
            unset($item['note']);
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
            'reference_no' => $params['order_id'] ?? '',
            'order_weight' => $params['weight'] ?? '',
        ];
        $response = $this->request(__FUNCTION__, $data);
        return $response;
    }

    /**
     * 取消订单，删除订单
     * @return mixed
     */
    public function deleteOrder(string $reference_no)
    {
        $param = [
            'reference_no' => $reference_no,
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
     * @return mixed
     */
    public function queryTrack($reference_no)
    {
        $referenceNoArray = $this->toArray($reference_no);
        if (count($referenceNoArray) > self::QUERY_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "查询物流轨迹一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
        }

        $trackNumberResponse = $this->getTrackNumber($reference_no);
        if ($trackNumberResponse['success'] != 1) {
            return $this->retErrorResponseData($response['cnmessage'] ?? '未知错误');
        }

        $trackNumber = $trackNumberResponse['data']['shipping_method_no'];

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