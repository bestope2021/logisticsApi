<?php
/**
 *
 * User: blaine
 * Date: 3/1/21
 */

namespace smiler\logistics\Api\RuiJie;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

class RuiJie extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface, TrackLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹,5自定义
     */
    const ORDER_COUNT = 1;
    /**
     * 一次最多查询多少个物流商
     */
    const QUERY_TRACK_COUNT = 30;

    const SOURCEERP = 34;//固定的

    public $iden = 'ruijie';

    public $iden_name = '睿捷物流';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'json';

    public $apiHeaders = [];

    public $interface = [

        'createOrder' => 'CreateAndConfirmPackage', // 创建申请邮件号（运单号），直接返回追踪号

        'deleteOrder' => 'CancelPackage', //删除订单。发货后的订单不可删除。

        //    'queryTrack' => 'trackInfoExt', //轨迹查询,暂无轨迹

        'getShippingMethod' => 'GetChannelInfoList', //获取配送方式,传分页信息，PageIndex和PageSize

        'getPackagesLabel' => 'LabelInfo', // 【打印标签|面单

        //    'operationPackages' => 'updateWeight',// 核实提交订单重量

        'getTrackNumber' => 'LabelInfo',//获取追踪号和面单是同一个接口
    ];

    /**
     * DgYz constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['Userid', 'Token', 'Url'], $config);
        $this->config = $config;
        $this->apiHeaders = [
            'userid' => $this->config['Userid'],
            'timestamp' => $this->getMillisecond(),
            'nonce' => mt_rand(1000, 9999),
            'Content-Type' => 'application/json;charset=UTF-8',
        ];
        if (!empty($config['apiHeaders'])) {
            $this->apiHeaders = array_merge($this->apiHeaders, $config['apiHeaders']);
        }
    }

    /**
     *
     * @param $interface
     * @param array $arr
     * @return array|mixed
     */
    public function buildParams($interface, $arr = [])
    {
        return $this->config['Url'] . $this->interface[$interface];
    }


    /**
     * 生成13位时间戳
     * @return float
     */
    public function getMillisecond()
    {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }


    /**
     * 平台对应关系
     * 平台来源 1-eBay；2-Wish；3-Amazon；4-Shopee；5-Walmart；6-Cdiscount；7-速卖通；8-Joom；9-fyndiq；10-TopBuy；11-Tophatter；12-FactoryMarket；13-vova；14-Mymall；15-自营；99-其他
     * @param $platform_id
     */
    public function handlePaltRelation($platform_id)
    {
        return [
            2 => 3,//亚马逊
            3 => 1,//ebay
            4 => 2,//wish
            5 => 99,//shopify
            6 => 99,//lazada
            7 => 4,//shopee
            8 => 99,//shoplazza
            9 => 7, //速卖通
            10 => 99,//COD
            11 => 99,//Coupang
            12 => 6,//Cdiscount
            13 => 99,//Rakuten
            14 => 5,//Walmart
        ];
    }


    /**
     * 创建订单
     * @param array $params
     * @return mixed
     * @throws InvalidIArgumentException
     * @throws ManyProductException
     * {
     * "Data":{
     * "Data":{
     * "OrderCode":"FLS19001684"
     * }
     * },
     * "IsSuccess":true,
     * "Msg":null
     * }
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
            $order_weight = $order_price = 0;
            $isElectricity = 0;

            foreach ($item['productList'] as $key => $value) {
                $productList[] = [
                    'ProductCode' => $value['productSku'],
                    'Quantity' => (int)($value['quantity'] ?? 0),
                    'DeclareName' => $value['declareEnName'] ?? '',//报关英文名
                    'DeclareNameCN' => $value['declareCnName'] ?? '',//报关中文名
                    'DeclareAmout' => (float)(round($value['declarePrice'], 2) ?? ''),//【必填】申报价值,默认为USD,若不填写算法逻辑为：明细申报价值 = 包裹单总价值 * 40 % / 明细总数量之和，下限3上限10；但是不能小于1（小于1则默认为1）。例如，包裹申报金额为10，含有两个明细，每个明细数量为2，每个明细申报价值 = 10 / 4 = 2.5
                    'DeclareCode' => $value['hsCode'] ?? '',
                ];
                $order_weight += $value['declareWeight'];
                $order_price += $value['declarePrice'];
            }
            $data = [
                'CustomerOrderCode' => $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 12
                'ToCountry' => $item['recipientCountryCode'] ?? '',//收件人国家二字简码
                'Platform' => (int)$this->handlePaltRelation($item['platformSource'])[$item['platformSource']] ?? '',//平台关系
                'SourceErp' => (int)self::SOURCEERP,
                'ShippingMethodType' => (int)1,//默认值1
                'ShippingMethodCode' => $item['shippingMethodCode'] ?? 'RJUSZS',//产品代码,就是渠道代码
                'IsSingle' => count(array_column($item['productList'], 'productSku')) > 1 ? 0 : 1,//是否为单品(true:明细种类等于1,false:明细种类大于1)
                'TotalPrice' => $item['orderCostCurrencyCode'] == 'USD' ? round($order_price, 3) : round($order_price / ($item['exchangeRate'][$item['orderCostCurrencyCode']] ?? 0), 3), //包裹总价值，币种USD,统一换算成USD
                'Weight' => empty($order_weight) ? '0.000' : round($order_weight, 3),//重量（千克），浮点数
                'Length' => empty($item['packageLength']) ? '0.000' : round($item['packageLength'], 3),//长
                'Width' => empty($item['packageWidth']) ? '0.000' : round($item['packageWidth'], 3),//宽
                'Height' => empty($item['packageHeight']) ? '0.000' : round($item['packageHeight'], 3),//高
                'IsElectricity' => (int)$isElectricity, //是否有电池 0：无电池,1：有电池，默认 0，整数型
                'GoodsType' => (int)4,//货物类型:1-礼品，2-文件，3-商品货样，4-其他
                'ReqLogisticsExt' => '',//默认值：{}
                'VATID' => $item['senderTaxNumber'] ?? '',//VAT税号（ShippingMethodCode为英国渠道时，该字段必填）
                'IOSSCodes' => $item['iossNumber'] ?? '',//【选填】  IOSS编号（要求目标国家为欧盟国家时，该字段为必填项）
                'OrderItems' => $productList,
                'OrderAddress' => [
                    'BuyerFullName' => $item['recipientName'] ?? '',// Y:收件人姓名Length <= 50 '',// Y:收件人姓名Length <= 50
                    'BuyerCountry' => $item['recipientCountryCode'] ?? '',//Y:收件人国家
                    'BuyerState' => $item['recipientState'] ?? '', //Y:收件人省/州
                    'BuyerCity' => $item['recipientCity'] ?? '', //Y:收件人城市
                    'BuyerStreet1' => $item['recipientStreet'] ?? ' ',// Y:收件人街道1
                    'BuyerStreet2' => $item['recipientStreet1'] ?? '',//N:收件人街道2
                    'BuyerZipCode' => (int)$item['recipientPostCode'] ?? '', //N:收件人邮编
                    'BuyerPhone' => (int)$item['recipientPhone'] ?? '', //Y:收件人电话
                    'BuyerEmail' => $item['recipientEmail'] ?? '',// N:收件人邮箱
                    'HouseNumber' => '',// N:门牌号
                ],
            ];
            $ls[] = $data;
        }

        $response = $this->request(__FUNCTION__, $ls[0]);

        // 处理结果
        $reqRes = $this->getReqResData();

        $fieldData = [];
        $fieldMap = FieldMap::createOrder();

        // 结果
        $flag = $response['IsSuccess'] == true;

        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['Msg'] ?? '未知错误');

        // 获取追踪号,如果延迟的话
        if ($flag && !empty($response['Data']['Data']['OrderCode'])) {
            $trackNumberResponse = $this->getTrackNumber($response['Data']['Data']['OrderCode']);
            if ($trackNumberResponse['flag']) {
                $fieldData['trackingNo'] = $trackNumberResponse['trackingNumber'] ?? '';//追踪号
                $fieldData['frt_channel_hawbcode'] = $trackNumberResponse['frtTrackingNumber'] ?? '';//尾程追踪号
            }
        }
        $fieldData['orderNo'] = $ls[0]['CustomerOrderCode'];
        $fieldData['trackingNo'] = $flag ? ($trackNumberResponse['trackingNumber'] ?? '') : '';//追踪号，不能实时返回，要过一分钟
        $fieldData['frt_channel_hawbcode'] = $flag ? ($trackNumberResponse['frtTrackingNumber'] ?? '') : '';//尾程追踪号
        $fieldData['id'] = $response['Data']['Data']['OrderCode'] ?? $ls[0]['CustomerOrderCode'];
        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);
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
     * 取消订单，删除订单
     * @return mixed
     */
    public function deleteOrder(string $order_code)
    {
        $param = [
            'OrderCode' => $order_code,
        ];
        $response = $this->request(__FUNCTION__, $param);
        // 结果
        $flag = $response['IsSuccess'] == true;
        return $flag;
    }


    /**
     * 处理加密数据
     * @param $data
     * @return mixed
     */
    public function handleDataJson($data)
    {
        //转化一次
        return strtolower(json_encode($data, JSON_UNESCAPED_UNICODE));
    }


    /**
     * @param string $function
     * @param array $data
     * @return mixed
     */
    public function request($function, $data = [])
    {
        $this->req_data = $data;
        $this->apiHeaders['signature'] = md5(strtolower($this->config['Token'] . $this->config['Userid'] . $this->apiHeaders['timestamp'] . $this->apiHeaders['nonce'] . $this->handleDataJson($data)));
        $response = $this->sendCurl('post', $this->buildParams($function, $data), $data, $this->dataType, $this->apiHeaders);
        $this->res_data = $response;
        return $response;
    }


    /**
     * 获取跟踪号，todo 有些渠道生成订单号不能立刻获取跟踪号
     * @param $reference_no
     * @return
     * Array
     * (
     * [flag] => 1
     * [tipMsg] =>
     * [trackingNumber] => 93001209246000000049814581
     * [frtTrackingNumber] => 09900630721395
     * )
     */
    public function getTrackNumber(string $processCode, $is_ret = false)
    {
        $params = [
            'OrderCode' => $processCode, //Fls系统包裹单单号
        ];
        $response = $this->request(__FUNCTION__, $params);
        $fieldData = [];
        $fieldMap = FieldMap::getTrackNumber();
        $flag = $response['IsSuccess'] == true;
        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['Msg'] ?? ($response['Msg'] ?? ($response['DetailExceptionMsg'] ?? '未知错误')));
        $fieldData['trackingNo'] = $flag ? ($response['Data']['Data']['TrackingNumber'] ?? '') : '';//追踪号
        $fieldData['frt_channel_hawbcode'] = $flag ? ($response['Data']['Data']['TrackingNumber'] ?? ($response['Data']['Data']['OrderCode'] ?? '')) : ($response['Data']['Data']['MethodApiCode'] ?? '');//尾程追踪号,优先取追踪号的值
        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
        if ($is_ret) return $fieldData['flag'] ? $this->retSuccessResponseData($ret) : $this->retErrorResponseData($fieldData['info'], $fieldData);
        return $ret;
    }

    /**
     * 获取物流商运输方式
     * @return mixed
     * [{"success":"true","transportWays":[{"autoFetchTrackingNo":"Y","code":"DHLV4-OT","name":"OTTO专线","trackingNoRuleMemo":[],"trackingNoRuleRegex":[],"used":"Y"},{"autoFetchTrackingNo":"Y","code":"DHL-ALL","name":"全欧特派","trackingNoRuleMemo":[],"trackingNoRuleRegex":[],"used":"Y"}]}]
     */
    public function getShippingMethod()
    {
        $data = [
            'PageIndex' => 1,
            'PageSize' => 100,
        ];

        $res = $this->request(__FUNCTION__, $data);//目前暂时只有二十几条渠道，一页就够

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();


        if (!$res['IsSuccess']) {
            return $this->retErrorResponseData($res['Msg'] ?? '未知错误');
        }

        if (!empty($res['Data']['Data'])) {
            foreach ($res['Data']['Data'] as $item) {
                $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
            }
        }

        return $this->retSuccessResponseData($fieldData);
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

    /**
     * @param $order_id
     * @return mixed|void
     * @throws \smiler\logistics\Exception\NotSupportException
     */
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
            'OrderCode' => $params['syOrderNo']
        ];
        $responses = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        $response['flag'] = $responses['IsSuccess']==true;
        $response['info'] = $responses['Msg']??'';
        $response['trackingNo'] = $responses['Data']['Data']['TrackingNumber']??($params['trackingNumber'] ?? '');
        $response['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF;
        $response['url'] = $responses['Data']['Data']['Label64'] ?? '';//base64_encode过后的编码pdf数据
        $response['label_path_plat'] = '';//不要填写
        $response['lable_content_type'] = $params['label_content'] ?? 1;
        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($response, $fieldMap);
        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 获取物流商轨迹,单独的接口获取轨迹
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
}