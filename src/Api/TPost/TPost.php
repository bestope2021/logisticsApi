<?php
/**
 * Created by PhpStorm
 * User: Administrator:smiler
 * Date: 2021/3/1 17:53
 * Email: <2166909366@qq.com>
 */

namespace smiler\logistics\Api\TPost;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

class TPost extends LogisticsAbstract implements BaseLogisticsInterface, TrackLogisticsInterface, PackageLabelLogisticsInterface
{
    /**
     * 一次最多创建多少个包裹
     */
    const ORDER_COUNT = 1;
    /**
     * 一次最多查询多少个物流轨迹
     */
    const QUERY_TRACK_COUNT = 50;
    public $iden = 'tpost';
    public $iden_name = '通邮';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'json';

    public $apiHeaders = [
        'Content-type' => 'application/json',
    ];

    public $interface = [
        'createOrder' => 'order/createOrder', // 【创建订单】
        'orderCallback' => 'order/orderCallback',// 回调订单
        'queryTrack' => '', //轨迹查询
        'operationPackages' => '', //修改订单重量
        'getShippingMethod' => 'order/getLogisticsChannel', //获取配送方式
        'getPackagesLabel' => 'order/printOrder', // 【打印标签|面单】
        'getTrackNumber' => '', //获取跟踪号

    ];

    /**
     * Wts constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['userToken', 'url', 'source'], $config);
        $this->config = $config;
        $this->apiHeaders['userToken'] = $config['userToken'];
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
            $productList = [];
            $isElectricity = 0;
            $material = [];
            foreach ($item['productList'] as $value) {
                $productList[] = [
                    'currency' => $value['currencyCode'] ?? 'USD',// Y:申报币种，默认：USD
                    'des' => $value['declareEnName'] ?? '',// N:物品描述
                    'sku' => $value['productSku'] ?? '',// Y:Sku
                    'nameEN' => $value['declareEnName'] ?? '',// Y:物品英文名称
                    'nameCN' => $value['declareCnName'] ?? '',// Y:物品中文名称
                    'qty' => (int)($value['quantity'] ?? ''),// Y:申报数量
                    'price' => (float)($value['declarePrice'] ?? ''),// Y:申报物品价格(单价)
                    'hs' => $value['hsCode'] ?? '',// N:海关编号
                    'weight' => (float)($value['declareWeight'] ?? ''),// N:申报重量（kg）(单件重量)
                    'url' => $value['productUrl'] ?? '',// N:产品链接
                ];
                // 判断
                if (isset($value['isElectricity']) && $value['isElectricity'] == 1) {
                    $isElectricity = 1;
                }
                if (!empty($value['productMaterial']) && !in_array(trim($value['productMaterial']), $material)) {
                    array_push($material, trim($value['productMaterial']));
                }
            }
            $ls[] = [
                'source' => $this->config['source'],// Y:来源
                'bNo' => $item['customerReferenceNo'] ?? '',// N:业务单号
                'orderNo' => $item['customerOrderNo'] ?? '',// N:客户订单号，由客户自定义，同一客户不允许重复。Length <= 50
                'charged' => $isElectricity,// Y:带电与否（0：否 ; 1：是）。 默认 0：否
                'itemType' => intval($item['itemType'] ?? 4),// Y:物品类型（0、礼物；1、文 件;2、商业样本;3、回货品;4、 其他） 默认 0：礼物
                'logisticsId' => $item['shippingMethodCode'] ?? 'BGOCMMPD623',// Y:物流编码（拓扑链系统中的 系统渠道编号）
                'material' => !empty($material) ? join(',', $material) : '',// 材质
                'note' => $item['packageRemark'] ?? '',// 备注
                'passportNumber' => $item['passportNumber'] ?? '',// 护照号
                'pracelType' => intval($item['pracelType'] ?? 1),// 包裹类型（0、文件；1、包 裹;）默认 1：包裹
                'taxId' => $item['recipientTaxNumber'] ?? '',// 收件人税号
                'iossVatId' => $item['iossNumber'] ?? '',// IOSS 编号
                'trackNo' => $item['trackingNumber'] ?? '',// N:追踪条码
                'transportNo' => $item['transportNumber'] ?? '',// N:转单号
                'weight' => (float)($item['predictionWeight'] ?? ''),// Y:预报重量（kg）
                'declareInfos' => $productList,// Y:申报产品数据
                'sender' => [
                    'act_id' => '',// 卖家账号 id
                    'c_name' => $item['senderCompany'] ?? '',// N:公司名称
                    'name' => $item['senderName'] ?? '',// Y:姓名
                    'tel' => $item['senderPhone'] ?? '',// Y:电话（可与手机号一致）
                    'postcode' => $item['senderPostCode'] ?? '',// Y:邮编
                    'address1' => $item['senderAddress'] ?? '',// Y:地址 1
                    'country' => $item['senderCountryCode'] ?? '',// Y:国家二字简码
                    'province' => $item['senderState'] ?? '',// Y:州省
                    'city' => $item['senderCity'] ?? '',// Y:城市
                    'email' => $item['senderEmail'] ?? '',// N:邮箱
                    'address2' => $item['senderAddress2'] ?? '',// Y:地址 2
                    'address3' => $item['senderAddress3'] ?? '',// Y:地址 3
                ],
                'recipient' => [
                    'act_id' => '',// 买家账号 id
                    'address' => $item['recipientStreet'] ?? '',// Y:地址 1
                    'address2' => $item['recipientStreet1'] ?? '',// N:地址 2
                    'address3' => empty($item['recipientStreet2']) ? '' : $item['recipientStreet2'],// N:地址 3
                    'c_name' => $item['senderCompany'] ?? '',// N:公司名称
                    'contact_person' => $item['recipientName'] ?? '',// Y:联系人
                    'country' => $item['recipientCountry'] ?? '',// Y:国 家
                    'country_code' => $item['recipientCountryCode'] ?? '',// Y:国 家 二 字 简 码
                    'zip' => $item['recipientPostCode'] ?? '',// Y:邮编
                    'province' => $item['recipientState'] ?? '',// N:州省
                    'city' => $item['recipientCity'] ?? '',// Y:城市
                    'tel_no' => $item['recipientPhone'] ?? '',// Y:电话[必传]（电话、手机其中 一个可传空字符）
                    'mobile_no' => $item['recipientMobile'] ?? '',// Y:手机[必传]（电话、手机其中 一个可传空字符）
                    'email' => $item['recipientEmail'] ?? '',// N:邮箱
                    'fax_no' => $item['recipientFaxNumber'] ?? '',// N:传真号码
                ],
            ];
        }
        $data = $ls[0];
        $sign = $this->getMd5Sign(__FUNCTION__, true, $data);
        $this->apiHeaders['sign'] = $sign;
        $response = $this->request(__FUNCTION__, $data);

        $reqRes = $this->getReqResData();

        $fieldData = [];
        $fieldMap = FieldMap::createOrder();

        // 处理数据
        $flag = $response['success'] == true;

        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['msg'] ?? '未知错误');

        $fieldData['order_no'] = $response['order_no'] ?? '';
        $fieldData['logisticsId'] = $response['logisticsId'] ?? '';
        $fieldData['logistics_no'] = $response['logistics_no'] ?? '';
        $fieldData['tlTrackNo'] = $response['tlTrackNo'] ?? '';

        //todo需要调用回调
        if($flag && $response['isCallBack']){
            $callbackResponse = $this->orderCallback([
                'orderNo' => $response['order_no'],
                'logisticsId' => $response['logisticsId'],
            ]);
        }

//        $this->dd($response);

        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
//        $this->dd($response, $ret, $reqRes);
        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);
    }


    public function orderCallback($params)
    {
        $sign = $this->getMd5Sign(__FUNCTION__, true, $params);
        $this->apiHeaders['sign'] = $sign;
        $res = $this->request(__FUNCTION__, $params);
        return $res;
    }
    /**
     * MD5签名
     * @param string $action 请求方法
     * @param bool $isSign 是否需要签名，默认 ，false
     * @param array $data
     */
    protected function getMd5Sign(string $action = '', bool $isSign = false, $data = [])
    {
        if (!$isSign) {
            return true;
        }
        $interface = $action . 'SignData';
        $signData = $this->$interface($data);
        $fieldValue = '';
        array_walk($signData, function ($item) use (&$fieldValue) {
            $fieldValue .= $item;
        });
        $sign = $this->config['userToken'] . $fieldValue;
        return strtoupper(md5($sign));
    }

    public function request($function, $data = [], $parseResponse = true)

    {
        $requestUrl = $this->config['url'] . $this->interface[$function];
        $this->req_data = $data;
        $res = $this->sendCurl('post', $requestUrl, $data, $this->dataType, $this->apiHeaders, 'utf-8', 'xml', $parseResponse);
        $this->res_data = $res;
        return $res;
    }

    /**
     * 获取跟踪号
     */
    public function getTrackNumber(string $order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    /**
     * 获取物流商运输方式
     * @return mixed
     * [{"express_type":"EYB","product_doornorule":"","product_id":"1881","product_shortname":"东莞E邮宝","product_tracknoapitype":""},{"express_type":"EMS","product_doornorule":"","product_id":"1921","product_shortname":"国际EMS","product_tracknoapitype":""},{"express_type":"XBPY","product_doornorule":"","product_id":"1941","product_shortname":"东莞平邮小包","product_tracknoapitype":""}]
     */
    public function getShippingMethod()
    {
        $res = $this->request(__FUNCTION__);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();

//        $this->dd($res);
        if ($res['success'] != true) {
            return $this->retErrorResponseData($res['msg'] ?? '未知错误');
        }
        foreach ($res['channelInfos'] as $item) {
            $item['enName'] = is_null($item['enName']) ? '' : $item['enName'];
            $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
        }
//        $this->dd($fieldData);
        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 打印面单
     * @param
     * @return mixed
     */
    public function getPackagesLabel($params){
        if(!isset($params['source']) || empty($params['source'])){
            $params['source'] = $this->config['source'];
        }
        $sign = $this->getMd5Sign(__FUNCTION__, true, $params);
        $this->apiHeaders['sign'] = $sign;
        $res = $this->request(__FUNCTION__,$params);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

//        $this->dd($res);
        if ($res['success'] != true) {
            return $this->retErrorResponseData($res['msg'] ?? '未知错误');
        }

        $path_type = ResponseDataConst::LSA_LABEL_PATH_TYPE_PDF;
        if($res['type'] == 1){
            $path_type = ResponseDataConst::LSA_LABEL_PATH_TYPE_PDF;
            $url = $res['url'] ?? '';
        }
        if($res['type'] == 0){
            $path_type = ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF;
            $url = $res['base64'] ?? '';
        }

        $res['flag'] = true;
        $res['orderNo'] = $params['orderNo'] ?? '';
        $res['label_path_type'] = $path_type;
        $res['lable_content_type'] = 1;
        $res['url'] = $url;

        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($res, $fieldMap);

//        $this->dd($fieldData);
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
    public function deleteOrder(string $order_code)
    {
        $this->throwNotSupport(__FUNCTION__);
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
     * 获取物流商轨迹
     * @return mixed
     */
    public function queryTrack($trackNumber)
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    public function getPackagesDetail($order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    /**
     * 获取请求数据
     */
    public function buildParams($interface, $arr = [])
    {

    }

    /**
     * 创建订单加密数据
     * 格式：userToken+物流编码 (logisticsId)+订单号 (orderNo)+买家联系人 (contact_person)+买家国家 简码(country_code)+买家洲 省(province)+买家城市 (city)+买家地址 1(address)+ 买家地址 2(address2)+买家 地址 3(address3)+买家电话 (tel_no)+买家手机 (mobile_no)+买家邮编(zip)。 (注：签名中所需的各字段在 请求报文中必须传递，缺一 不可，如果贵司有个别字段 没有对应值，可以传空字符， 例如： ”address2”:””。 各字段直接拼接，不需要添 加其他字符)
     * @param array $data
     */
    protected function createOrderSignData(array $data = [])
    {
        $recipient = $data['recipient'];
        $signData = [
            'logisticsId' => $data['logisticsId'],
            'orderNo' => $data['orderNo'],
            'contact_person' => $recipient['contact_person'],
            'country_code' => $recipient['country_code'],
            'province' => $recipient['province'],
            'city' => $recipient['city'],
            'address' => $recipient['address'],
            'address2' => $recipient['address2'],
            'address3' => $recipient['address3'],
            'tel_no' => $recipient['tel_no'],
            'mobile_no' => $recipient['mobile_no'],
            'zip' => $recipient['zip'],
        ];
        return $signData;
    }

    /**
     * 打印面单加密数据
     * md5(32 位大写)加密签名。格 式：userToken +物流编码 (logisticsId )+ 订 单 编 号 (orderNo)+追踪条码(trackNo) (注：各字段顺序必须和本文 档提供的字段顺序相同)
     * @param array $data
     */
    protected function getPackagesLabelSignData(array $data = [])
    {
        $signData = [
            'logisticsId' => $data['logisticsId'],
            'orderNo' => $data['orderNo'],
            'trackNo' => $data['trackNo'],
        ];
        return $signData;
    }

    /**
     * 订单回调加密数据
     * @param array $data
     */
    protected function orderCallbackSignData(array $data = []){
        $signData = [
            'logisticsId' => $data['logisticsId'],
            'orderNo' => $data['orderNo'],
        ];
        return $signData;
    }

}