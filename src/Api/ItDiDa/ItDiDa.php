<?php
/**
 *
 * User: blaine
 * Date: 2/22/21
 */

namespace smiler\logistics\Api\ItDiDa;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

/**
 * (嘀嗒)易抵达物流
 * @link http://hotfix.yidida.top/itdida-api/swagger-ui.html#!/21151330212716922359/loginUsingPOST_7
 * Class ItDiDa
 * @package smiler\logistics\Api\ItDiDa
 */
class ItDiDa extends LogisticsAbstract implements BaseLogisticsInterface, TrackLogisticsInterface, PackageLabelLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹
     */
    const ORDER_COUNT = 10;
    /**
     * 一次最多查询多少个跟踪号
     */
    const QUERY_TRACK_COUNT = 10;

    public $iden = 'ITDIDA';

    public $iden_name = '易抵达物流';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'json';

    public $apiHeaders = [];

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
        } else {
            return $this;
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
        $this->throwNotSupport(__FUNCTION__);
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
            'key' => 'login',
        ];
        $response = $this->request(__FUNCTION__, $data);

        if ((!empty($response['success'])) && ($response['success'] === true)) {
            $this->loginToken = $response['data'];
            $this->expireTime = date("Y-m-d H:i:s", strtotime("+2 days"));//有效期是两天
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
            foreach ($item['productList'] as $value) {
                $danjian[] = [
                    'chang' => round($value['length'], 2) ?? '',
                    'kuan' => round($value['width'], 2) ?? '',
                    'gao' => round($value['height'], 2) ?? '',
                    'shiZhong' => round($value['declareWeight'], 2) ?? '',
                    'warehouseNo' => '',
                ];
                $productList[] = [
                    'caiZhiEn' => $value['declareEnName'] ?? '',// Y:申报材质英文名称Length <= 50
                    'caiZhiCn' => $value['productMaterial'] ?? '',// N:申报材质中文名称Length <= 50
                    'cargoModel' => $value['modelType'] ?? '',//商品型号
                    //    'imageUrl' => $value['productUrl'] ?? '',// N:图片
                    //    'salesUrl' => $value['productUrl'] ?? '',// N:销售地址,
                    'shenBaoBiZhong' => $value['currencyCode'] ?? 'USD',// , //申报币种，不传值默认为USD(美元)；USD-美元,AUD-澳元
                    'shenBaoDanJia' => (float)($value['declarePrice'] ?? ''), //Y:单价
                    'shenBaoHaiGuanBianMa' => $value['hsCode'] ?? '',// N:海关编码
                    'shenBaoPinMing' => $value['declareEnName'] ?? '',//申报品名
                    'shenBaoShuLiang' => (int)($value['quantity'] ?? ''),// Y:产品数量;数值必须为正整数
                    'sku' => $value['productSku'] ?? '',// N:产品 SKU;Length <= 100
                    'unit' => 'PCE', //N:单位  MTR：米  PCE：件 SET：套 默认PCE
                    'unitGrossWeight' => $value['declareWeight'] ?? '',// Y:毛量;Length <= 50 KG
                    'unitNetWeight' => $value['declareWeight'] ?? '',//净重
                    'yongTuEn' => '',// Y:申报用途英文名称Length <= 50 'customs_purpose'
                    'yongTuCn' => $value['productPurpose'] ?? '',// N:申报用途中文名称Length <= 50 'customs_purpose'
                    'zhongWenPinMing' => $value['declareCnName'] ?? '',//中文品名，最大长度为30字符
                ];
                $order_weight += $value['declareWeight'];
                $order_num += $value['quantity'];
            }
            //$address = ($item['recipientStreet'] ?? ' ') .'   '. ($item['recipientStreet1'] ?? ' ')  .'   '. (empty($item['recipientStreet2']) ? '' : $item['recipientStreet2']);
            $data = [
                'baoGuanFangShi' => 0,//报关方式 0:其它 1:单独报关 ,
                'baoGuoLeiXing' => 1,//N:包裹类型 0:文件 1:包裹 2:包裹袋 ,
                'clientCode' => '',//客户编码,无需传参
                'companyTaxNo' => $item['senderTaxNumber'] ?? '',
                'danJianList' => $danjian,
                'guoJia' => $item['recipientCountryCode'] ?? '', //Y:收件人国家二字代码
                'huoWuTeXing' => '不带电',//货物特性，带电和不带电 ,默认不带电
                'jiJianGongSiMingCheng' => $item['senderCompany'] ?? '',//寄件人公司名 ,
                'jiJianRenDiZhi1' => $item['senderFullAddress'] ?? '',//寄件人地址一 ,
                'jiJianRenDianHua' => $item['senderPhone'] ?? '',//寄件人电话
                'jiJianRenMingCheng' => $item['senderName'] ?? '',//寄件人名称
                'jianShu' => (int)$order_num ?? 1,//件数,整数,默认为1 ,
                'keHuDanHao' => $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 50
                'recipientEmail' => $item['recipientEmail'] ?? '',// N:收件人邮箱Length <= 128
                'recipientVat' => '',//收件人VAT ,
                'shenBaoXinXiList' => $productList,//申报信息数组
                //todo 调试写死
                'shouHuoQuDao' => $item['shippingMethodCode'] ?? '佐川小包普货',// Y:serviceCode: test => UBI.CN2FR.ASENDIA.FULLLY.TRACKED
                'shouHuoShiZhong' => (float)$order_weight,//收货实重
                'shouJianRenChengShi' => $item['recipientCity'] ?? '', //N:收件人城市
                'shouJianRenDiZhi1' => $item['recipientStreet'] ?? ' ',//收件人地址一，最大长度为35字符 ,//2021/12/14
                'shouJianRenDiZhi2' => $item['recipientStreet1'] ?? ' ',//收件人地址二，//2021/12/14
                'shouJianRenDiZhi3' => empty($item['recipientStreet2']) ? ' ' : $item['recipientStreet2'],//收件人地址三，//2021/12/14
                'shouJianRenDianHua' => $item['recipientPhone'] ?? '',//N:收件人电话
                'shouJianRenGongSi' => $item['recipientCompany'] ?? '', //N:收件人公司名
                'shouJianRenShouJi' => $item['recipientMobile'] ?? '', //N:收件人手机
                'shouJianRenXingMing' => $item['recipientName'] ?? '',// Y:收件人姓名
                'shouJianRenYouBian' => $item['recipientPostCode'] ?? '', //N:收件人邮编
                'wuLiuFangShi' => 0,//业务类型 0:快递 1:小包 2:专线 4:空运 5:海运 7:陆运 100:快递进口 ,
                'zhouMing' => $item['recipientState'] ?? '', //N:收件人省
            ];

            $ls[] = $data;
        }
        $ls['key'] = 'yundans';
        $response = $this->request(__FUNCTION__, $ls);

        $reqRes = $this->getReqResData();


        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::createOrder();

        foreach ($response['data'] as $k => $v) {
            // 结果
            $flag = $v['code'] === 200;
            $fieldData['flag'] = $flag ? true : false;
            $fieldData['info'] = $flag ? '' : ($v['message'] ?? '');
            $fieldData['order_id'] = $v['xiTongDanHao'] ?? ($v['keHuDanHao'] ?? '');
            $fieldData['refrence_no'] = $v['keHuDanHao'] ?? '';
            $fieldData['shipping_method_no'] = $v['zhuanDanHao'] ?? '';
            $fieldData['channel_hawbcode'] = $v['xiTongDanHao'] ?? '';

            $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);

            return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);
        }

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
            case 'files' || 'queryTracks':
                $this->apiHeaders = [
                    'Authorization' => 'Bearer  ' . $this->loginToken,
                ];
                break;
            default:
                $this->apiHeaders = [
                    'Authorization' => 'Bearer  ' . $this->loginToken,
                    'Content-Type' => 'application/json; charset=utf-8',
                ];
                break;
        }

        switch ($this->req_data['key']) {
            case 'login':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['login_command'], $data, 'form', []);
                break;//登录
            case 'yundans':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['create_order_command'], $data, $this->dataType, $this->apiHeaders);
                break;//下单
            case 'files':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['get_label_command'], $data, 'form', $this->apiHeaders);
                break;//获取面单
            case 'queryTracks':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('get', $this->config['url'] . $this->config['get_track_command'] . '?no=' . $data['no'], [], 'form', $this->apiHeaders);
                break;//获取轨迹
            case 'deleteYundans':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['cancel_order_command'], $data, $this->dataType, $this->apiHeaders);
                break;//取消订单
            case 'getReceivingChannels':
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
        return $res;
    }

    /**
     * 获取物流商运输方式
     * @return mixed
     * {"code":0,"info":"success","data":[{"shippingMethodCode":"CH001","shippingMethodEnName":"瑞士Asendia专线","shippingMethodCnName":"瑞士Asendia专线","shippingMethodType":"","remark":""},{"shippingMethodCode":"USYT01","shippingMethodEnName":"美国海卡","shippingMethodCnName":"美国海卡","shippingMethodType":"","remark":""}]}
     */
    public function getShippingMethod()
    {
        $data = ['key' => 'getReceivingChannels'];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();
        if ($response['success'] != 1) {
            return $this->retErrorResponseData('易抵达物流商发生未知错误，获取失败！');
        }
        foreach ($response['data'] as $item) {
            $item['code'] = $item['channelName'];
            $item['enname'] = $item['channelName'];
            $item['cnname'] = $item['channelName'];
            $item['shipping_method_type'] = $item['logisticsModeCode'];
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
            'key' => 'deleteYundans',
        ];
        $response = $this->request(__FUNCTION__, $data);
        $flag = $response['success'] == 1;
        return $flag;
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
            'key' => 'files',
            'keHuDanHaoList' => $params['trackNumber'],//客户单号，以逗号分割
            'wenJianLeiXingList' => 3,//文件类型列表，用逗号隔开,1：系统label，2：系统发票，3：转单lebel，4：转单发票
        ];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        if ($response['success'] != 1) {
            return $this->retErrorResponseData('易抵达物流商【获取面单】接口失败，发生未知错误');
        }

        foreach ($response['data'] as $item) {
            $item['flag'] = true;
            $item['info'] = $item['message'];
            $item['order_no'] = $item['keHuDanHao'];
            $item['lable_file'] = $item['fileList'][0]['data'];//面单内容
            $item['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF;
            $item['label_path_plat'] = '';//不要填写
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
//        $trackNumberArray = $this->toArray($trackNumber);
//        if (count($trackNumberArray) > self::QUERY_TRACK_COUNT) {
//            throw new InvalidIArgumentException($this->iden_name . "查询物流轨迹一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
//        }
        $data = [
            'no' => $trackNumber,//对方说没上限
            'key' => 'queryTracks',
        ];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        if ($response['success'] != 1) {
            return $this->retErrorResponseData('易抵达物流商【获取轨迹】接口失败，发生未知错误');
        }

        $data = $response['data'][0];

        $ls = [];
        foreach ($data['trackList'] as $key => $val) {
            $data['flag'] = true;
            $data['track_status'] = $val['desc'];
            $data['track_status_name'] = $val['desc'];
            $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
        }

        $data['details'] = $ls;
        $data['server_hawbcode'] = $data['no'];

        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);

        return $this->retSuccessResponseData($fieldData);
    }

}