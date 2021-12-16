<?php
/**
 *
 * User: Ghh.Guan
 * Date: 4/22/21
 */

namespace smiler\logistics\Api\DgPost;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

class DgPost extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface, TrackLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹,5自定义
     */
    const ORDER_COUNT = 2;
    /**
     * 一次最多查询多少个物流商
     */
    const QUERY_TRACK_COUNT = 1;
    public $iden = 'dgpost';
    public $iden_name = '东莞邮政';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'form';

    public $apiHeaders = [
        'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'
    ];

    public $interface = [
        'createOrder' => 'orderService/OrderReceiveBack', // 订单交运(返回运单号)

        'queryTrack' => 'queryMailTrackWn', //轨迹查询

        'getShippingMethod' => 'businessDataService/getBusinessData', //获取配送方式

        'getPackagesLabel' => 'surface/download', // 【打印标签|面单
    ];

    /**
     * DgPost constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['ecCompanyId','wh_Code', 'url', 'MD5Key', 'trackUrl'], $config);
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
            $contents_total_weight = 0;
            $contents_total_value = 0;
            $isElectricity = false;
            $productList = [];
            foreach ($item['productList'] as $index => $value) {
                $contents_total_weight += $value['quantity'] * $value['declareWeight'];
                $contents_total_value += $value['quantity'] * $value['declarePrice'];
                if(isset($value['isElectricity']) && $value['isElectricity'] == 1 && !$isElectricity) $isElectricity = true;
                $productList[] = [
                    'cargo_no' => $value['productNo'] ?? '', //商品id
                    'cargo_name' => $value['declareCnName'] ?? '', //报关品名（中文）
                    'cargo_name_en' => $value['declareEnName'] ?? '', //报关品名（英文）
                    'cargo_type_name' => !empty($value['brand']) ?$value['brand']: $value['declareCnName'], //商品类型名称（中文）
                    'cargo_origin_name' => $value['originCountry'] ?? '', //商品产地
                    'cargo_link' => $value['productUrl'] ?? '', //销售链接
                    'cargo_quantity' => $value['quantity'] ?? '', //商品数量
                    'cargo_value' => $value['declarePrice'] ?? '', //商品单价
                    'cost' => $value['declarePrice'] ?? '', //默认美元，申报价值可以和商品单价一样
                    'cargo_currency' => 'USD', //商品申报币制,固定填 USD
                    'carogo_weight' => $value['declareWeight'] * 1000?? '', //商品重量,（单位：克）
                    'cargo_description' => $value['declareEnName']?? '', //商品描述（内件成分）,国际邮件必填
                    'cargo_serial' => $value['hsCode']?? '', //商品序号（内件海关 HS 商品编号）
                    'unit' => $value['unit']?? '个', //计量单位,默认（个）
                ];
            }


            //发件人节点
            $sender = [
                'name' => $item['senderName'] ?? '',//发件人姓名
                'post_code' => $item['senderPostCode'] ?? '',//发件人邮编
                'phone' => '86'.$item['senderPhone'] ?? '',//发件人电话
                'mobile' => '86'.$item['senderPhone'] ?? '',//发件人手机
                'nation' => $item['senderCountryCode'] ?? '',//采用万国邮联国家代码 2 位
                'province' => $item['senderState'] ?? '',//发件人所在省（洲）
                'city' => $item['senderCity'] ?? '',//发件人所在城市
                'county' => $item['senderDistrict'] ?? '',//发件人所在县（区）
                'address' => $item['senderAddress'] ?? '',//发件人详细地址
                'linker' => $item['senderName'] ?? '',//发件人联系人
            ];
            $address = ($item['recipientStreet'] ?? ' ') .'   '. ($item['recipientStreet1'] ?? ' ')  .'   '. (empty($item['recipientStreet2']) ? '' : $item['recipientStreet2']);
            //收件人节点
            $receiver = [
                'name' => $item['recipientName'] ?? '',//收件人姓名
                'post_code' => $item['recipientPostCode'] ?? '',//收件人邮编
                'phone' => $item['recipientPhone'] ?? '',//收件人电话
                'mobile' => $item['recipientMobile'] ?? '',//收件人手机
                'nation' => $item['recipientCountryCode'] ?? '',//采用万国邮联国家代码 2 位
                'province' => $item['recipientState'] ?? '',//收件人所在省（洲）
                'city' => $item['recipientCity'] ?? '',//收件人所在城市
                'address' => $address ?? '',//收件人详细地址
                'linker' => $item['recipientName'] ?? '',//收件人联系人
            ];

            $order_info = [
                'created_time' => date('Y-m-d H:i:s'),//订单接入时间
                'sender_no' => $this->config['ecCompanyId'],//邮政 13-15 位大客户代码
                'mailType' => 'BST',//电商标识, 对接时由“中邮 API 对接”注册的电商或者 ERP 标识
                'wh_code' => $this->config['wh_Code'],//用户揽收机构编号,从当地邮局获取（第三方ERP 建议由电商自己配置）不能搞错，否则邮政收寄不了
                'logistics_order_no' => $item['customerOrderNo'] ?? '',//物流订单号
                'biz_product_no' => $item['shippingMethodCode'] ?? '001',//业务产品代码
                'weight' => $contents_total_weight * 1000,//邮件重量
                'contents_total_weight' => $contents_total_weight * 1000,//内件总重量
                'contents_total_value' => $contents_total_value * 1000,//内件总价值
                'battery_flag' => $isElectricity?1:0,//是否有电池
                'insurance_flag' => isset($item['insuranceOption'])?$item['insuranceOption']+1:1,//保险保价标志,1:基本 2:保价 3:保险
                'insurance_amount' => $item['ChargeValue'] ?? 0,//保价保险金额
                'declare_source' => 2,//申报信息来源,1、个人申报；2:企业申报；3:个人税款复核；目前选 2.企业申报
                'declare_type' => 1,//申报类别,1:物品 2：货物,目前选 1:物品
                'declare_curr_code' => 'USD',//申报币制代码,固定填 USD
                'forecastshut' => isset($item['tariffPrepay']) && $item['tariffPrepay'] > 0?1:0,//预报关：0-无预报关信息1-有预报关信息
                'mail_sign' => 2,//9610 标识,1:是 2：否；目前填 2：否
                's_tax_id' => !empty($item['iossNumber']) ?$item['iossNumber']: $item['recipientTaxNumber'],//寄件人税号, VAT 识别账号
                'platform_type' => 0,//0:其他 1:速卖通 (目前支持速卖通平台通过平台主订单号查询预缴增值税方式和 VAT 识别账号，其他平台需要通过接口字段传预缴增值税方式和VAT 识别账号) 空值默认 0
                'prepayment_of_vat' => $item['iossNumber'] ?0:2,//0/1/2 (0: IOSS 1: no-IOSS 2: other)
                'sender' => $sender,//发件人信息
                'receiver' => $receiver,//收件人信息
                'items' => $productList,//商品信息
            ];
            $ls[] = $order_info;
        }

        $data = [
            'logistics_interface' => json_encode($ls[0],JSON_UNESCAPED_UNICODE),
            'data_digest' => $this->getSign(json_encode($ls[0],JSON_UNESCAPED_UNICODE)),
            'msg_type' => 'B2C_TRADE',
            'ecCompanyId' => $this->config['ecCompanyId'],
            'data_type' => 'JSON',
            'biz_product_no' => $ls[0]['biz_product_no']
        ];
        $response = $this->request(__FUNCTION__, $data);
        // 处理结果
        $reqRes = $this->getReqResData();

        $fieldData = [];
        $fieldMap = FieldMap::createOrder();

        // 结果
        $flag = $response['success'] == 'true';

        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['reason'].':'.$response['msg'] ?? '未知错误');

        $fieldData['orderNo'] = $ls[0]['logistics_order_no'] ?? '';
        $fieldData['trackingNo'] = $response['waybillNo'] ?? '';
        $fieldData['id'] = $response['waybillNo'] ?? '';

        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
//        $this->dd($response, $ret, $reqRes);
        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);
    }

    public function request($function, $data = [])
    {
        $data = $this->buildParams($function, $data);
        $this->req_data = $data;

        $url = $this->config['url'];
        if($this->interface[$function] == 'queryMailTrackWn') $url = $this->config['trackUrl'];
        $url .= $this->interface[$function];

        $dataType = $this->dataType;
        if($this->interface[$function] == 'queryMailTrackWn'){
            $param = '';
            foreach ($data as $k => $v){
                $param .= "{$k}={$v}&";
            }
            $url .= '?'.rtrim($param,"&");
            $dataType = 'json';
        }
        $res = $this->sendCurl('post', $url, $data, $dataType, $this->apiHeaders, 'UTF-8', $this->interface[$function]);
        $this->res_data = $res;
        return $res;
    }

    /**
     * 获取请求数据
     */
    public function buildParams($interface, $arr = [],$biz_product_no=0)
    {
        return $arr;
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
        $data = [
            'queryType' => 'queryBusinessType'
        ];
        $res = $this->request(__FUNCTION__,$data);
        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();

//        $this->dd($res);
        if (!isset($res['data'])) {
            return $this->retErrorResponseData($res['errorInfo'] ?? '未知错误');
        }
        foreach ($res['data'] as $item) {
            $item_arr = [
                'code' =>$item['businessCode'],
                'name_en' =>$item['businessName'],
                'name_cn' =>$item['businessName'],
                'shipping_method_type' =>$item['businessName'],
                'remark' =>$item['businessName'],
            ];
            $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item_arr, $fieldMap);
        }
        return $this->retSuccessResponseData($fieldData);
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
     * 获取订单标签
     * @return mixed
     */
    public function getPackagesLabel($params = [])
    {
        $data = [
            'ecCompanyId' => $this->config['ecCompanyId'],
            'dataDigest' => $this->getSign(implode(',', $this->toArray($params['trackNumber']))),
            'barCode' => implode(',', $this->toArray($params['trackNumber'])),
            'version' => '2.0',//版本号，如：1.0 - HTTP 状态为 200 时返回 PDF文件流,非 200 时返回 JSON 数据;2.0-成功失败都返回 JSON 数据
        ];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        // 结果
        $flag = $response['success'];

        if (!$flag) {
            $fieldData['info'] = $flag ? '' : ($response['err_code'].':'.$response['msg'] ?? '未知错误');
        }

        $response['flag'] = $flag;
        $response['trackingNo'] = $params['trackNumber'][0] ?? '';
        $response['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF;
        $response['lable_file'] = base64_encode(pack('H*', $response['data'])) ?? 1;
        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($response, $fieldMap);
//        $this->dd($fieldData);
        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 获取物流商轨迹
     * @return mixed
     */
    public function queryTrack($trackNumber)
    {
        $trackNumberArray = $this->toArray($trackNumber);
        if (count($trackNumberArray) > self::QUERY_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "查询物流轨迹一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
        }

        $msgBody = json_encode(['traceNo' => $trackNumber]);
        $dataDigest = $this->getSign($msgBody,$this->config['token']);
        $msgBody = urlencode($msgBody);
        $data = [
            'sendID' => $this->config['ecCompanyId'],
            'proviceNo' => '99',
            'msgKind' => 'XXX_JDPT_TRACE',
            'serialNo' => '0000000000122',
            'sendDate' => date('YmdHis'),
            'receiveID' => 'JDPT',
            'dataType' => '1',
            'dataDigest' => $dataDigest,
            'msgBody' => $msgBody,
            ];
        $response = $this->request(__FUNCTION__, $data);
        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        // 结果
        $flag = $response['responseState'];
        if (!$flag) {
            return $this->retErrorResponseData($response['errorDesc'] ?? '未知错误');
        }

        $list = $response['responseItems'];
        $ls = [];
        foreach ($list as $key => $val) {
            $info = [
                'status' => $val['opCode'],
                'pathInfo' => $val['opDesc'],
                'pathTime' => $val['opTime'],
                'pathAddr' => $val['opOrgProvName'].' '.$val['opOrgCity'],
            ];
            $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($info, $fieldMap2);
        }
        $data['responseState'] = $flag;
        $data['errorDesc'] = $response['errorDesc'];
        $data['sPaths'] = $ls;
        $data['trackNumber'] = $trackNumber;
        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);

        return $this->retSuccessResponseData($fieldData);
    }

    public function getPackagesDetail($order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }
}