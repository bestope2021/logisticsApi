<?php
/**
 *
 * User: blaine
 * Date: 2/22/21
 */

namespace smiler\logistics\Api\JiaLiCodTw;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

/**
 * 台湾嘉里COD物流
 * @link http://hotfix.yidida.top/itdida-api/swagger-ui.html#!/21151330212716922359/loginUsingPOST_7
 * Class JiaLiCodTw
 * @package smiler\logistics\Api\JiaLiCodTw
 */
class JiaLiCodTw extends LogisticsAbstract implements BaseLogisticsInterface, TrackLogisticsInterface, PackageLabelLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹
     */
    const ORDER_COUNT = 10;
    /**
     * 一次最多查询多少个跟踪号
     */
    const QUERY_TRACK_COUNT = 10;

    public $iden = 'jialicodtw';

    public $iden_name = '台湾嘉里COD物流';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'json';

    public $apiHeaders = [
        'Content-Type' => 'application/json; charset=utf-8',
    ];

    //登录Token
    public $loginToken;

    //过期时间
    public $expireTime;


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
        $this->checkKeyExist(['login_account', 'login_password', 'url'], $config);
        $this->config = $config;
        //验证是否登录
        if (((!empty($this->expireTime)) && (time() > strtotime($this->expireTime))) || (empty($this->expireTime)) || (empty($this->loginToken))) {
            $this->isLogin($this->config['login_account'], $this->config['login_password']);
        }
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

    /**验证是否登录
     * @param $account
     * @param $password
     * @throws InvalidIArgumentException
     */
    public function isLogin(string $account, string $password)
    {
        if (empty($account) || (empty($password))) {
            throw new InvalidIArgumentException($this->iden_name . "登录接口参数不能为空");
        }
        $data = [
            'username' => $account,
            'password' => $password,
            'key' => 'token/get',
        ];
        $response = $this->request(__FUNCTION__, $data);
        if ((!empty($response['code'])) && ($response['code'] == 200)) {
            $this->loginToken = $response['body']['token'];
            $this->expireTime = date("Y-m-d H:i:s", strtotime("+1 days"));//有效期是两天
        } else {
            $this->loginToken = '';
            $this->expireTime = '';//失败则返回为空
        }
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
            $order_num = 0;
            $order_price = 0;
            foreach ($item['productList'] as $value) {
                $productList[] = [
                    'sku' => $value['productSku'] ?? '',// N:产品 SKU;Length <= 100
                    'description' => $value['declareEnName'] ?? '',// Y:申报材质英文名称Length <= 50
                    'description_origin_language' => $value['declareCnName'] ?? '',//中文品名，最大长度为30字符
                    'unit_price' => (float)($value['declarePrice'] ?? ''), //Y:单价
                    'currency' => $value['currencyCode'] ?? 'USD',// , //申报币种，不传值默认为USD(美元)；USD-美元,AUD-澳元
                    'quantity' => (int)($value['quantity'] ?? ''),// Y:产品数量;数值必须为正整数
                    'unit_weight' => (int)round($value['declareWeight'] * 1000, 2) ?? '',
                    'length' => (int)round($value['length'], 2) ?? '',
                    'width' => (int)round($value['width'], 2) ?? '',
                    'height' => (int)round($value['height'], 2) ?? '',
                    'hs_code' => $value['hsCode'] ?? '',// N:海关编码
                    'brand' => $value['brand'] ?? '',// N:品牌名称Length <= 50
                    'remark' => $value['_sort_ident_info'] ?? '',// N:备注

                ];
                $order_weight += $value['declareWeight'];
                $order_num += $value['quantity'];
                $order_price += $value['declarePrice'];
            }

            $address = ($item['recipientStreet'] ?? ' ') . ($item['recipientStreet1'] ?? ' ') . ($item['recipientStreet2'] ?? '');
            $data = [
                'sale_platform' => $item['platformSource'] ?? '',
                'service' => [
                    'channel_code' => $item['shippingMethodCode'] ?? 'CAHKTWS18',// Y:serviceCode: test => UBI.CN2FR.ASENDIA.FULLLY.TRACKED
                    'service_type' => 'default',//(默认 : default)service_type 可选值会根据不运输渠道有所不同使用自提点服务则填pickup_point
                    'delivery_instruction' => '',//派送特别需求备注
                ],
                'package' => [
                    'reference_number' => $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 50
                    'declared_value' => round($order_price, 2),//申报价值
                    'declared_value_currency' => $item['productList'][0]['currencyCode'] ?? ($item['packageCodCurrencyCode'] ?? ''),
                    'cod_value' => round($item['packageCodAmount'], 2) ?? '0.00',
                    'cod_value_currency' => $item['packageCodCurrencyCode'] ?? '',
                    'length' => (int)($item['packageLength'] ?? 1),// N:包裹长度（单位：cm）
                    'width' => (int)($item['packageWidth'] ?? 1),// N:包裹宽度（单位：cm）
                    'height' => (int)($item['packageHeight'] ?? 1),// N:包裹高度（单位：cm）
                    'actual_weight' => (int)(($order_weight * 1000) ?? 1),//包裹重，重量g
                    'payment_method' => (round($item['packageCodAmount'], 2) > 0) ? 'COD' : 'PP',//• COD : 货到付款 • PP : 预付 (默认)
                    'shipment_type' => 'General',//• General : 普货 (默认) • Sensitive : 敏货• Mobile & Tablet : 手机和平板
                ],
                'sender' => [
                    'name' => $item['senderName'] ?? '',//必填 寄件人名
                    'company' => $item['senderCompany'] ?? '',//必填 寄件公司名
                    'address' => $item['senderAddress'] ?? '',//必填 寄件地址
                    'district' => $item['senderDistrict'] ?? '',//非必填 寄件地址分区
                    'city' => $item['senderCity'] ?? '',//必填 寄件城市
                    'province' => $item['senderState'] ?? '',//非必填 寄件州/省
                    'country_code' => $item['senderCountryCode'] ?? 'CN',//必填 寄件国家, ISO 3166 标准
                    'post_code' => $item['senderPostCode'] ?? '',//必填 寄件邮编
                    'phone' => $item['senderPhone'] ?? '',//必填 寄件电话
                    'email' => $item['senderEmail'] ?? '',//非必填 寄件邮箱
                ],
                'receiver' => [
                    'name' => $item['recipientName'] ?? '',//必填 收件人姓名
                    'company' => $item['recipientCompany'] ?? '',//必填 收件人公司名
                    'address' => $address ?? '',//必填 收件人地址
                    'city' => $item['recipientCity'] ?? '',//必填 收件人城市
                    'province' => $item['recipientState'] ?? '',//非必填 收件人省
                    'country_code' => $item['recipientCountryCode'] ?? '',//必填 收件人国家, ISO 3166 标准
                    'post_code' => $item['recipientPostCode'] ?? '',//必填 收件人邮编
                    'phone' => $item['recipientPhone'] ?? '',//必填 收件人电话
                    'email' => $item['recipientEmail'] ?? '',//非必填 收件人邮箱
                    'id_number' => empty($item['recipientTaxNumber']) ? '999999' : $item['recipientTaxNumber'],//收件人税号，收件税号跨境，收件人为 TW，CN时，必填   2021/08/11紧急需求，台湾嘉里COD默认值为999999
                ],

                'items' => $productList,//商品信息数组
            ];

            $ls[] = $data;
        }

        $ls[0]['key'] = 'shipment/create';
        $response = $this->request(__FUNCTION__, $ls[0]);

        //2021/9/14优化重复下单

        $reqRes = $this->getReqResData();
        $flag=0;

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::createOrder();
        // 结果
        if($response['code'] == 201){
            $flag = 1;//201正常，
        }elseif($response['code'] == 409){
            //409是重复下单已存在,先删除再重新下单，再返回
            $deleteFlag=$this->deleteOrder($response['data']['tracking_number']);//应该是先删除追踪号
            if(!empty($deleteFlag)){
                //已删除成功，再重新下单
                $response = $this->request(__FUNCTION__, $ls[0]);//调用下单程序，生成新的追踪号
                if($response['code'] == 201){
                    $flag = 1;//201正常，
                }
            }
        }

        if (!empty($response['data'])) {
            $newdata = $response['data'];
            $fieldData['flag'] = $flag ? true : false;
            $fieldData['info'] = $flag ? '' : ($response['message'] ?? '');
            $fieldData['order_id'] = $newdata['tracking_number'] ?? ($newdata['package_number'] ?? ($newdata['reference_number'] ?? ''));
            $fieldData['refrence_no'] = $newdata['reference_number'] ?? '';
            $fieldData['shipping_method_no'] = $newdata['tracking_number'] ?? '';
            $fieldData['channel_hawbcode'] = $newdata['package_number'] ?? '';
            $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
        } else {
            $fieldData['flag'] = $flag ? true : false;
            $fieldData['info'] = $flag ? '' : ($response['message'] ?? '');
            $fieldData['order_id'] = '';
            $fieldData['refrence_no'] = $ls[0]['package']['reference_number'] ?? '';
            $fieldData['shipping_method_no'] = '';
            $fieldData['channel_hawbcode'] = '';
            $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
        }

        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);
    }


    /**公共请求方法
     * @param string $function
     * @param array $data
     * @return mixed
     */
    public function request($function, $data = [])
    {
        $this->req_data = $data;
        switch ($this->req_data['key']) {
            case 'token/get':
                $this->apiHeaders = [
                    'Content-Type' => 'application/json; charset=utf-8',
                ];//登录接口
                break;
            case 'orders';
                $this->apiHeaders = [
                    'Authorization' => 'Bearer ' . $this->loginToken,
                ];//删除取消订单，未获取轨迹的订单
                break;
            default:
                $this->apiHeaders = [
                    'Authorization' => 'Bearer ' . $this->loginToken,
                    'Content-Type' => 'application/json; charset=utf-8',
                ];//其余接口
                break;
        }

        switch ($this->req_data['key']) {
            case 'token/get':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['login_command'], $data, $this->dataType, $this->apiHeaders);
                break;//登录
            case 'shipment/create':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['create_order_command'], $data, $this->dataType, $this->apiHeaders);
                break;//下单
            case 'shipment/label':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('get', $this->config['url'] . $this->config['get_label_command'] . '?tracking_number=' . $data['tracking_number'], [], $this->dataType, $this->apiHeaders);
                break;//获取面单
            case 'shipment/status':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('get', $this->config['url'] . $this->config['get_track_command'] . '?tracking_number=' . $data['tracking_number'], [], $this->dataType, $this->apiHeaders);
                break;//获取轨迹
            case 'orders':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('delete', $this->config['url'] . $this->config['cancel_order_command'] . '/' . $data['keHuDanHao'], [], $this->dataType, $this->apiHeaders);
                break;//取消订单
            case 'channel/list':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('get', $this->config['url'] . $this->config['get_method_command'], [], $this->dataType, $this->apiHeaders);
                break;//获取运输方式
            default:
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['login_command'], $data, $this->dataType, $this->apiHeaders);
                break;//默认是验证登录
        }
        $this->res_data = $response;
        return $response;
    }


    /**
     * 获取跟踪号，todo 有些渠道生成订单号不能立刻获取跟踪号
     * @param $reference_no
     * @return array|mixed
     */
    public function getTrackNumber(string $reference_no)
    {
        $params = [
            'no' => $reference_no, //客户参考号
            'key' => '',
        ];
        $res = $this->request(__FUNCTION__, $params);
        if ($res['code'] != 200) {
            return $this->retErrorResponseData('嘉里COD物流商【获取追踪号】接口失败，发生未知错误');
        }
        return $this->retSuccessResponseData($res);
    }
    /**
     * 获取物流商运输方式
     * @return mixed
     * {"code":0,"info":"success","data":[{"shippingMethodCode":"CH001","shippingMethodEnName":"瑞士Asendia专线","shippingMethodCnName":"瑞士Asendia专线","shippingMethodType":"","remark":""},{"shippingMethodCode":"USYT01","shippingMethodEnName":"美国海卡","shippingMethodCnName":"美国海卡","shippingMethodType":"","remark":""}]}
     */
    public function getShippingMethod()
    {
        $data = ['key' => 'channel/list'];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();
        if ($response['code'] != 200) {
            return $this->retErrorResponseData('嘉里COD物流商【获取运输方式】发生未知错误，获取失败！');
        }

        foreach ($response['data'] as $item) {
            $item['enname'] = $item['name'];
            $item['cnname'] = $item['name'];
            $item['shipping_method_type'] = $item['name'];
            $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
        }

        return $this->retSuccessResponseData($fieldData);
    }


    /**
     * 删除订单
     * @param string $order_code
     * @return mixed|void
     */
    public function deleteOrder(string $order_code)
    {
        $data = [
            'keHuDanHao' => $order_code, //客户参考号
            'key' => 'orders',
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

        if (count($params) > self::ORDER_COUNT) {
            throw new ManyProductException($this->iden_name . "一次最多支持提交" . self::ORDER_COUNT . "个包裹");
        }
        $data = [
            'key' => 'shipment/label',
            'tracking_number' => $params['trackNumber'],//客户单号，以逗号分割
        ];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        if ($response['code'] != 200) {
            return $this->retErrorResponseData('嘉里COD物流商【获取面单】接口失败，发生未知错误');
        }
        $item = [];
        $item['flag'] = true;
        $item['info'] = $response['message'];
        $item['order_no'] = $data['tracking_number'];
        $item['lable_file'] = $response['data']['base64'];//面单内容
        $item['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF;
        $item['label_path_plat'] = '';//不要填写
        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 获取物流商轨迹
     * {"data":[{"shipper_hawbcode":"T1020210305164402901045177","server_hawbcode":"HMEUS0000223958YQ","channel_hawbcode":null,"destination_country":"US","destination_country_name":null,"track_status":"NT","track_status_name":"转运中","signatory_name":"","details":[{"tbs_id":"2826898","track_occur_date":"2021-03-05 16:44:59","track_location":"","track_description":"快件电子信息已经收到","track_description_en":"Shipment information received","track_code":"IR","track_status":"NT","track_status_cnname":"转运中"}]}],"success":1,"cnmessage":"获取跟踪记录成功","enmessage":"获取跟踪记录成功","order_id":0}
     * @return mixed
     * {"code":0,"info":"Success","data":[{"flag":"","tipMsg":"","orderNo":"HMEUS0000223958YQ","status":"NT","statusMsg":"转运中","logisticsTrackingDetails":[{"status":"NT","statusContent":"快件电子信息已经收到","statusTime":"2021-03-05 16:44:59","statusLocation":""}]}]}
     */
    public function queryTrack($trackNumber)
    {
        $trackNumberArray = $this->toArray($trackNumber);
        if (count($trackNumberArray) > self::QUERY_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "查询物流轨迹一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
        }
        $data = [
            'tracking_number' => $trackNumber,//追踪号
            'key' => 'shipment/status',
        ];
        $response = $this->request(__FUNCTION__, $data);
        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        if ($response['code'] != 200) {
            return $this->retErrorResponseData('嘉里COD物流商【获取轨迹】接口失败，发生未知错误');
        }

        $data = $response['data'];

        $ls = [];
        foreach ($data['status'] as $key => $val) {
            $data['flag'] = true;
            $data['track_status'] = $val['status_code'];
            $data['track_status_name'] = $val['status_code'];
            $data['reference_number']=$data['tracking_number'];//2021/08/31加的
            $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
        }

        $data['status'] = $ls;

        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);

        return $this->retSuccessResponseData($fieldData);
    }

}