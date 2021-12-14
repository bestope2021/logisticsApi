<?php
/**
 *
 * User: blaine
 * Date: 3/1/21
 */

namespace smiler\logistics\Api\BaXing;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

class BaXing extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface, TrackLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹,5自定义
     */
    const ORDER_COUNT = 1;
    /**
     * 一次最多查询多少个物流商
     */
    const QUERY_TRACK_COUNT = 1000;

    public $iden = 'baxing';

    public $iden_name = '八星小包物流';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'json';

    public $apiHeaders = [];

    public $order_track = [];

    public $interface = [

        'createOrder' => 'createAndAuditOrder', // 创建并预报订单 todo 如果调用创建订单需要预报

        'deleteOrder' => 'deleteOrder', //删除订单。发货后的订单不可删除。

        'queryTrack' => 'track', //轨迹查询

        'getShippingMethod' => 'transport', //获取配送方式

        'getPackagesLabel' => 'label', // 【打印标签|面单

        'operationPackages' => 'updateWeight',// 核实提交订单重量

        'getTrackNumber' => 'lastnum',//获取追踪号
    ];

    /**
     * HuaHan constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['CODE', 'TOKEN', 'url'], $config);
        $this->config = $config;
        $this->apiHeaders = [
            'CODE' => $this->config['CODE'],
            'TOKEN' => $this->config['TOKEN'],
        ];
        if (!empty($config['apiHeaders'])) {
            $this->apiHeaders = array_merge($this->apiHeaders, $config['apiHeaders']);
        }
    }

    /**
     * 获取请求数据
     */
    public function buildParams($interface, $arr = [])
    {
        return $arr;
    }

    /**
     * 创建订单
     * @param array $params
     * @return mixed
     * @throws InvalidIArgumentException
     * @throws ManyProductException
     * {
     * "code": "1",
     * "data": {
     * "orderNo": "test202104052239",
     * "trackingNumber": "9212490237757389131937",
     * "label": "JVBERi0xLjQKMyAwIG9iago8PC9UeXBlIC9QYWdlCi9QYXJlbnQgMSAwIFIKL1Jlc291cmNlcyAyIDAgUgovQ29udGVudHMgNCAwIFI+PgplbmRvYmoKNCAwIG9iago8PC9GaWx0ZXIgL0ZsYXRlRGVjb2RlIC9MZW5ndGggOTQ+PgpzdHJlYW0KeJwzUvDiMtAzNVco5ypUMFDwUjBUKAfSWUDsDsTpQFFDPQMgUABBGBNC6ZrpWRgYGyok53LphwT4GCq45CsEcgUCtRhZWOhBtZgYG+lBtUA0AJUr6HtCFQMAToQZegplbmRzdHJlYW0KZW5kb2JqCjEgMCBvYmoKPDwvVHlwZS="
     * },
     * "message": "Success"
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
            $isElectricity = 0;
            $packages = [];

            foreach ($item['productList'] as $key => $value) {
                if ($key == 0) {
                    $productList[] = [
                        'sku' => $value['productSku'] ?? '',
                        'price' => (float)(round($value['declarePrice'], 2) ?? ''),
                        'weight' => (float)(round($value['declareWeight'], 3) ?? ''),
                        'quantity' => (int)($value['quantity'] ?? ''),
                        'nameen' => $value['declareEnName'] ?? '',
                        'namecn' => $value['declareCnName'] ?? '',
                    ];
                } else {
                    break;
                }
            }

            $packages[] = [
                'length' => (float)(round($item['packageLength'], 3) ?? ''),// N:包裹长度（单位：cm）
                'width' => (float)(round($item['packageWidth'], 3) ?? ''),// N:包裹宽度（单位：cm）
                'height' => (float)(round($item['packageHeight'], 3) ?? ''),// N:包裹高度（单位：cm）
                'weight' => (float)(round($item['predictionWeight'], 3) ?? ''),//包裹重，重量KG (11,3)小数
                'goods' => $productList,// Y:一次最多支持 5 个产品信息（超过 5 个将会忽略）
            ];
            $data = [
                'orderNo' => $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 12
                'hasBattery' => $isElectricity, //是否带电，1带电，0不带电，默认不带电
                //todo 调试写死
                'productCode' => $item['shippingMethodCode'] ?? 'DHLV4-RSE',//产品代码,就是渠道代码
                'Currency' => $item['packageCodCurrencyCode'] ?? 'USD', //N:币种
                'iossNum' => $item['iossNumber'] ?? '',//IOSS号
                'vatNum' => '',//VAT号
                'consignee' => [
                    'name' => $item['recipientName'] ?? '',// Y:收件人姓名Length <= 50 '',// Y:收件人姓名Length <= 50
                    'company' => $item['recipientCompany'] ?? '', //N:收件人公司名称
                    'phone' => $item['recipientPhone'] ?? '', //N:收件人电话
                    'country' => $item['recipientCountryCode'] ?? '',//收件人国家
                    'state' => $item['recipientState'] ?? '', //N:收件人省/州
                    'city' => $item['recipientCity'] ?? '', //N:收件人城市
                    'address1' => $item['recipientStreet'] ?? ' ' ?? '',// Y:收件人街道1
                    'address2' => ($item['recipientStreet1'] ?? ' ') . ' ' . $item['recipientStreet2'] ?? '',// N:收件人街道2//2021/12/14
                    'houseno' => '', //N:收件人门牌号/建筑物名称。
                    'zipcode' => $item['recipientPostCode'] ?? '', //Y:收件人邮编
                ],
                'shipper' => [
                    'name' => $item['senderName'] ?? '', //N:发件人姓名
                    'company' => $item['senderCompany'] ?? '', // N:发件人公司名
                    'phone' => $item['senderPhone'] ?? '', //N:发件人电话
                    'address' => $item['senderFullAddress'] ?? '',// Y:发件人完整地址Length <= 200
                    'province' => $item['senderState'] ?? '', // N:发件人省
                    'city' => $item['senderCity'] ?? '',// Y:发件人城市Length<=50
                    'zipcode' => $item['senderPostCode'] ?? '',// N:发件人邮编Length <= 32
                    'country' => 'CN',// Y:发件人国家简码，默认CN, $item['senderCountryCode'] ??
                ],
                'packages' => $packages,
            ];
            $data['key'] = 'order';
            $ls[] = $data;
        }

        $response = $this->request(__FUNCTION__, $ls[0]);

        // 处理结果
        $reqRes = $this->getReqResData();

        $fieldData = [];
        $fieldMap = FieldMap::createOrder();

        // 结果
        $flag = $response['message'] == 'Success';

        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['message'] ?? '未知错误');
        // 获取追踪号,如果延迟的话
        if ($flag && empty($response['data']['frt_channel_hawbcode'])) {
            $trackNumberResponse = $this->getTrackNumber($response['data']['orderNo']);
            if ($trackNumberResponse['flag']) {
                $fieldData['trackingNo'] = $trackNumberResponse['trackingNumber'] ?? '';//追踪号
                $fieldData['frt_channel_hawbcode'] = $trackNumberResponse['frtTrackingNumber'] ?? '';//尾程追踪号
            }
        }
        $fieldData['orderNo'] = $response['data']['orderNo'];
        $fieldData['trackingNo'] = $response['data']['trackingNumber'] ?? '';
        $fieldData['frt_channel_hawbcode'] = $flag ? ($trackNumberResponse['frtTrackingNumber'] ?? '') : '';//尾程追踪号
        $fieldData['id'] = $response['data']['serialNum'] ?? $response['data']['orderNo'];
        $this->order_track = $flag ? [$response['data']['trackingNumber'] => $ls[0]['orderNo']] : [];
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
        $data = [
            'orderNo' => $params['ProcessCode'] ?? '',
            'weight' => empty($params['weight']) ? 0 : round($params['weight'], 3),//单位是KG
            'key' => 'updateWeight',
        ];
        $response = $this->request(__FUNCTION__, $data);
        if (empty($response)) {
            return $this->retErrorResponseData('修改订单重量异常');
        }
        // 结果
        if (empty($response['code'])) {
            return $this->retErrorResponseData($response['message'] ?? '未知错误');
        }
        return $this->retSuccessResponseData([]);
    }


    /**
     * @param string $function
     * @param array $data
     * @return mixed
     */
    public function request($function, $data = [])
    {
        $this->req_data = $data;
        switch ($this->req_data['key']) {
            case 'order':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['create_order_command'], $data, $this->dataType, $this->apiHeaders);
                break;//下单
            case 'label':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('get', $this->config['url'] . $this->config['get_label_command'] . '?orderNo=' . $data['orderNo'], [], $this->dataType, $this->apiHeaders);
                break;//获取面单
            case 'track':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('get', $this->config['url'] . $this->config['get_track_command'] . '?orderNo=' . $data['orderNo'], [], $this->dataType, $this->apiHeaders);
                break;//获取轨迹
            case 'transport':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['get_method_command'], $data, $this->dataType, $this->apiHeaders);
                break;//获取运输方式
            case 'cancel':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['get_cancel_command'], $data, $this->dataType, $this->apiHeaders);
                break;//删除订单
            case 'updateWeight':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['update_weight_command'], $data, $this->dataType, $this->apiHeaders);
                break;//客户通过updateWeight API提交订单核重，与仓库操作的实重进行对比是否超重量差异值。注意：一定要在仓库操作前推送，否则将不接收客户推送的核重。
            case 'lastnum':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('get', $this->config['url'] . $this->config['last_num_command'], [], $this->dataType, $this->apiHeaders);
                break;//获取追踪号和转单号用的
            default:
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['create_order_command'], $data, $this->dataType, $this->apiHeaders);
                break;//默认是下单
        }
        $this->res_data = $response;
        return $response;
    }

    /**
     * 获取跟踪号，todo 有些渠道生成订单号不能立刻获取跟踪号
     * @param $reference_no
     * @return array|mixed
     */
    public function getTrackNumber(string $processCode, $is_ret = false)
    {
        $params = [
            'orderNo' => $processCode, //客户参考号
            'key' => 'lastnum',
        ];
        $response = $this->request(__FUNCTION__, $params);
        $fieldData = [];
        $fieldMap = FieldMap::getTrackNumber();
        $flag = $response['message'] == 'Success';
        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['message'] ?? ($response['message'] ?? '未知错误'));
        $fieldData['trackingNo'] = $flag ? $response['data']['trackingNumber'] : '';//追踪号
        $fieldData['frt_channel_hawbcode'] = $flag ? ($response['data']['lastnum'] ?? '') : '';//尾程追踪号
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
            'key' => 'transport',
        ];
        $res = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();


        if ($res['message'] != 'Success') {
            return $this->retErrorResponseData($res['message'] ?? '未知错误');
        }
        foreach ($res['transportWays'] as $item) {
            $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
        }
        return $this->retSuccessResponseData($fieldData);
    }


    /**
     * 取消订单，删除订单
     * @return mixed
     */
    public function deleteOrder(string $order_id)
    {
        $param = [
            'orderId' => $order_id,
            'key' => 'cancel',
        ];
        $response = $this->request(__FUNCTION__, $param);
        // 结果
        $flag = $response['message'] == 'Success';
        return $flag;
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
            'orderNo' => $params['customerOrderNo'],
            'key' => 'label',
        ];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        // 结果
        $flag = $response['message'] == 'Success';

        if (!$flag) {
            return $this->retErrorResponseData($response['message'] ?? '未知错误');
        }

        $response['flag'] = $flag;
        $response['info'] = $response['message'] ?? '';
        $response['trackingNo'] = $params['trackingNumber'] ?? '';
        $response['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF;
        $response['url'] = $response['data']['label'] ?? '';
        $response['label_path_plat'] = '';//不要填写
        $response['lable_content_type'] = $params['label_content'] ?? 1;

        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($response, $fieldMap);

        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 获取物流商轨迹
     * @return mixed
     * {
     *     "code": "1",
     *     "data": {
     *         "orderNo": "GL962889980",
     *         "trackingNumber": "9214490237757389730787",
     *         "events": [
     *             {
     *                 "event_code": "DL",
     *                 "event_time": "2021-04-09 15:40:00",
     *                 "event_content": "Delivered, In/At Mailbox",
     *                 "event_loaction": "ILMACHESNEY PARK"
     *             },
     *             {
     *                 "event_code": "OP",
     *                 "event_time": "2021-04-09 06:10:00",
     *                 "event_content": "Out for Delivery",
     *                 "event_loaction": "ILMACHESNEY PARK"
     *             },
     *             {
     *                 "event_code": "OP",
     *                 "event_time": "2021-04-09 06:10:00",
     *                 "event_content": "Out for Delivery, Expected Delivery by 9:00pm",
     *                 "event_loaction": "ILMACHESNEY PARK"
     *             }
     *         ]
     *     },
     */
    public function queryTrack($trackNumber)
    {
        $trackNumberArray = $this->toArray($trackNumber);
        if (count($trackNumberArray) > self::QUERY_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "查询物流轨迹一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
        }
        $data = [
            'orderNo' => $trackNumber,
            'key' => 'track',
        ];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        // 结果
        $flag = $response['message'] == 'Success';

        if (!$flag) {
            return $this->retErrorResponseData($response['message'] ?? '未知错误');
        }

        $data = $response['data'];
        $data['orderNo'] = $this->order_track[$trackNumber];

        $ls = [];
        foreach ($data['events'] as $key => $val) {
            $data['status'] = $val['event_code'];
            $data['trackingNumber'] = $val['event_content'];
            $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
        }
        $data['events'] = $ls;

        $fieldData = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);

        return $this->retSuccessResponseData($fieldData);
    }

    public function getPackagesDetail($order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }
}