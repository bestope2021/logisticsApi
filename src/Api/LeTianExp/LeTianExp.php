<?php
/**
 *
 * User: blaine
 * Date: 3/1/21
 */

namespace smiler\logistics\Api\LeTianExp;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

class LeTianExp extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface, TrackLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹,5自定义
     */
    const ORDER_COUNT = 1;
    /**
     * 一次最多查询多少个物流商
     */
    const QUERY_TRACK_COUNT = 100;

    public $iden = 'ltianexp';

    public $iden_name = '加邮国际';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'json';

    public $apiHeaders = [];

    public $order_track = [];

    public $interface = [

        'createOrder' => '/api/orderNew/createOrder', // 创建申请邮件号（运单号） todo 如果调用创建订单需要预报

        'deleteOrder' => '/api/orderNew/deleteOrder', //删除订单。发货后的订单不可删除。

        'queryTrack' => '/api/tracking/query/trackInfo', //轨迹查询

        'getShippingMethod' => '/api/orderNew/getChannelList', //获取配送方式

        'getPackagesLabel' => '/api/orderNew/printOrder', // 【打印标签|面单

        'operationPackages' => '/api/Weight/updateOrderWeight',// 核实提交订单重量
    ];

    /**
     * DgYz constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['code', 'apiKey', 'url'], $config);
        $this->config = $config;
        $this->apiHeaders = [
            'code' => $this->config['code'],
            'apiKey' => $this->config['apiKey'],
            'timestamp' => date('Y-m-d H:i:s', time()),
            'sign' => md5(str_replace(' ', '', $this->config['code'] . $this->config['apiKey'])),//去除拼接空格
            'Content-Type' => 'application/json;charset=UTF-8',
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
            $productList = $volume = [];
            foreach ($item['productList'] as $key => $value) {
                $productList[] = [
                    'ename' => $value['declareEnName'] ?? '',
                    'cname' => $value['declareCnName'] ?? '',
                    'sku' => $value['productSku'] ?? '',// N:产品 SKU;
                    'price' => round($value['declarePrice'], 2) ?? '0.00',//【必填】申报单价，浮点型，保留两位小数
                    'quantity' => (int)($value['quantity'] ?? 0),
                    'weight' => round($value['declareWeight'], 3) ?? '0.000',//【必填】重量（千克），正整数
                    'unitCode' => 'PCE',//[MTR=米,PCE=件,SET=套]
                    'hsCode' => $value['hsCode'] ?? '',
                ];
                $volume[] = [
                    'length' => round($value['length'], 2) ?? '0.00',
                    'width' => round($value['width'], 2) ?? '0.00',
                    'height' => round($value['height'], 2) ?? '0.00',
                    'quantity' => (int)$value['quantity'],
                    'rweight' => round($value['declareWeight'], 3) ?? '0.000',
                ];
            }
            $data = [
                'referenceNo' => $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 12
                //todo 调试写死
                'channelCode' => $item['shippingMethodCode'] ?? 'CA002',//产品代码,就是渠道代码
                'productType' => 1,//1,包裹:2,PAK 袋:3 文件,不 填默认 1
                'pweight' => round($item['predictionWeight'], 3),//重量（千克），3位小数
                'pieces' => 1,
                'insured' => 0,
                'consigneeName' => $item['recipientName'] ?? '',// Y:收件人姓名Length <= 100 '',// Y:收件人姓名Length <= 100
                'consigneeCountryCode' => $item['recipientCountryCode'] ?? '',//收件人国家
                'consigneeProvince' => $item['recipientState'] ?? '', //N:收件人省/州
                'consigneeCity' => $item['recipientCity'] ?? '', //N:收件人城市
                'consigneeAddress' => ($item['recipientStreet'] ?? ' ') .' ' . ($item['recipientStreet1'] ?? ' ') .' '. (empty($item['recipientStreet2']) ? '' : $item['recipientStreet2']),// Y:收件人街道地址 2021/12/14
                'consigneePostcode' => $item['recipientPostCode'] ?? '', //Y:收件人邮编
                'consigneePhone' => $item['recipientPhone'] ?? '', //N:收件人电话
                'shipperName' => $item['senderName'] ?? '',//发件人姓名
                'shipperCountryCode' => $item['recipientCountryCode'] ?? '', //Y:收件人国家二字代码
                'shipperProvince' => $item['senderState'] ?? '', // Y:发件人省
                'shipperCity' => $item['senderCity'] ?? '',// Y:发件人城市
                'shipperAddress' => $item['senderFullAddress'] ?? '',// N:发件人完整地址
                'shipperPostcode' => $item['senderPostCode'] ?? '',// N:发件人邮编
                'shipperPhone' => $item['senderPhone'] ?? '', //Y:发件人电话
                'apiOrderItemList' => $productList,//申报明细列表
                'apiOrderVolumeList' => $volume,//材积参数列表
            ];
            $data['key'] = 'create';
            $ls[] = $data;
        }

        $response = $this->request(__FUNCTION__, $ls[0]);

        // 处理结果
        $reqRes = $this->getReqResData();

        $fieldData = [];
        $fieldMap = FieldMap::createOrder();

        // 结果
        $flag = $response['code'] == 1;

        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['message'] ?? '未知错误');
        $fieldData['orderNo'] = $ls[0]['referenceNo'];
        $fieldData['trackingNo'] = $response['data']['trackingNo'] ?? '';
        $fieldData['frt_channel_hawbcode'] = $flag ? ($response['data']['markNo'] ?? '') : '';//尾程追踪号
        $fieldData['id'] = $response['data']['orderId'] ?? $ls[0]['referenceNo'];
        $this->order_track = $flag ? [$response['data']['trackingNo'] => $ls[0]['referenceNo'], $ls[0]['referenceNo'] => $response['data']['trackingNo']] : [];
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
            'trackingNo' => $params['ProcessCode'] ?? '',//跟踪号
            'weight' => empty($params['weight']) ? 0 : round($params['weight'], 3),//单位是KG
            'unit' => 'kg',
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
     * 取消订单，删除订单
     * @return mixed
     */
    public function deleteOrder(string $order_id)
    {
        $param = [
            'orderNumber' => $order_id,
            'key' => 'cancel',
        ];
        //平台提供通过跟踪单号或客户参考号对订单进行删除操作，每次只能删除一 个订单。
        $response = $this->request(__FUNCTION__, $param);
        // 结果
        $flag = $response['code'] == 1;
        return $flag;
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
            case 'create':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['create_order_command'], $data, $this->dataType, $this->apiHeaders);
                break;//下单
            case 'label':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['get_label_command'], $data['orderNo'], $this->dataType, $this->apiHeaders, '', '', false);
                break;//获取面单
            case 'trackInfoExt':
                unset($data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['get_track_command'], $data['traceNo'], $this->dataType, $this->apiHeaders, '', '', false);
                break;//获取轨迹
            case 'list':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['get_method_command'], [], $this->dataType, $this->apiHeaders);
                break;//获取运输方式
            case 'updateWeight':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['update_weight_command'], $data, $this->dataType, $this->apiHeaders);
                break;//客户通过updateWeight API提交订单核重，与仓库操作的实重进行对比是否超重量差异值。注意：一定要在仓库操作前推送，否则将不接收客户推送的核重。
            case 'cancel':
                unset($data['key']);
                unset($this->req_data['key']);
                $this->apiHeaders = [
                    'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
                ];
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['cancel_order_command'], $data, 'form', $this->apiHeaders);
                break;//取消订单
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
     * 获取物流商运输方式
     * @return mixed
     * [{"success":"true","transportWays":[{"autoFetchTrackingNo":"Y","code":"DHLV4-OT","name":"OTTO专线","trackingNoRuleMemo":[],"trackingNoRuleRegex":[],"used":"Y"},{"autoFetchTrackingNo":"Y","code":"DHL-ALL","name":"全欧特派","trackingNoRuleMemo":[],"trackingNoRuleRegex":[],"used":"Y"}]}]
     */
    public function getShippingMethod()
    {
        $data = [
            'key' => 'list',
        ];
        $res = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();


        if ($res['code'] != 1) {
            return $this->retErrorResponseData($res['message'] ?? '未知错误');
        }
        foreach ($res['data'] as $item) {
            $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
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
     * 获取订单标签(面单)
     * @return mixed
     */
    public function getPackagesLabel($params = [])
    {
        $trackNumbers = $this->toArray($params['trackingNumber']);
        $data = [
            'orderNo' => $trackNumbers,
            'key' => 'label',
        ];
        $fieldData = [];
        $responsesa = $this->request(__FUNCTION__, $data);
        if (!empty($responsesa)) {
            $responses = json_decode($responsesa, true);
            // 结果
            $flag = $responses['code'] == 1;
            // 处理结果

            $fieldMap = FieldMap::packagesLabel();
            foreach ($responses['data'] as $item) {
                $item['flag'] = $flag ? true : false;
                $item['info'] = $flag ? '' : ($responses['message'] ?? '未知错误');
                $item['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_PDF;
                $item['url'] = $item['labelPath'] ?? '';
                $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
            }
        }
        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 获取物流商轨迹,单独的接口获取轨迹
     * @return mixed
     */
    public function queryTrack($trackNumber)
    {
        $trackNumberArray = $this->toArray($trackNumber);
        if (count($trackNumberArray) > self::QUERY_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "查询物流轨迹一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
        }
        $data = [
            'traceNo' => $trackNumberArray,
            'key' => 'trackInfoExt',
        ];
        //平台提供通过跟踪单号或换单号获取轨迹详情,支持批量查询,单次查询不 能超过 100 个订单,请求频率限制每分钟 100 次
        $responses = $this->request(__FUNCTION__, $data);
        // 处理结果
        $fieldData = [];
        if (!empty($responses)) {
            $response = json_decode($responses, true);
            $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
            $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);
            // 结果
            $flag = $response['code'] == 1;
            if (!$flag) {
                return $this->retErrorResponseData($response['message'] ?? '未知错误');
            }
            $datas = $response['data'];
            $ls = [];
            if (!empty($datas)) {
                foreach ($datas as $keys => $vals) {
                    if (!empty($vals['fromDetail'])) {
                        foreach ($vals['fromDetail'] as $key => $val) {
                            $data['trackingNo'] = $vals['trackingNo'];
                            $data['status'] = $val['pathCode'] ?? '';
                            $data['status_msg'] = $val['pathInfo'] ?? '';
                            $ls[$keys] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
                        }
                    } else {
                        $data['trackingNo'] = $vals['trackingNo'];
                        $data['status'] = '';
                        $data['status_msg'] = '';
                        $ls[$keys] = LsSdkFieldMapAbstract::getResponseData2MapData([], $fieldMap2);
                    }

                }
            }
            $data['fromDetail'] = $ls;
            $fieldData = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);
        }
        return $this->retSuccessResponseData($fieldData);
    }


    public function getPackagesDetail($order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }
}