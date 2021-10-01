<?php
/**
 *
 * User: blaine
 * Date: 3/1/21
 */

namespace smiler\logistics\Api\DgYz;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

class DgYz extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface, TrackLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹,5自定义
     */
    const ORDER_COUNT = 1;
    /**
     * 一次最多查询多少个物流商
     */
    const QUERY_TRACK_COUNT = 30;

    public $iden = 'dgyz';

    public $iden_name = '东莞邮政直发小包';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'json';

    public $apiHeaders = [];

    public $order_track = [];

    public $interface = [

        'createOrder' => 'apply', // 创建申请邮件号（运单号） todo 如果调用创建订单需要预报

        'deleteOrder' => 'removeorder', //删除订单。发货后的订单不可删除。

        'queryTrack' => 'trackInfoExt', //轨迹查询

        'getShippingMethod' => 'list', //获取配送方式

        'getPackagesLabel' => 'print', // 【打印标签|面单

        'operationPackages' => 'updateWeight',// 核实提交订单重量
    ];

    /**
     * DgYz constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['token', 'url'], $config);
        $this->config = $config;
        $this->apiHeaders = [
            'token' => $this->config['token'],
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
            $productList = [];
            $order_weight = 0;
            $isElectricity = 0;

            foreach ($item['productList'] as $key => $value) {
                $productList[] = [
                    'cargoNo' => $key + 1,
                    'cargoCurrency' => 'USD',
                    'unit' => '个',
                    'cargoValue' => (float)(round($value['declarePrice'], 2) ?? ''),//【必填】申报单价，浮点型，保留两位小数
                    'cargoWeight' => (int)((round($value['declareWeight'], 2) ?? '') * 1000),//【必填】重量（克），正整数
                    'quantity' => (int)($value['quantity'] ?? ''),
                    'cargoNameEn' => $value['declareEnName'] ?? '',
                    'cargoName' => $value['declareCnName'] ?? '',
                    'cargoOriginName' => $value['originCountry'] ?? '',
                    'cargoQuantity' => $value['quantity'] ?? '',
                    'cargoSeria' => $value['hsCode'] ?? '',
                ];
                $order_weight += $value['declareWeight'];
            }
            $data = [
                'logisticsOrderNo' => $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 12
                //todo 调试写死
                'bizProductNo' => $item['shippingMethodCode'] ?? '001',//产品代码,就是渠道代码
                'weight' => (int)($order_weight * 1000),//邮件重量（克），正整数
                'batteryFlag' => $isElectricity, //是否有电池 0：无电池,1：有电池，默认 0，整数型
                'senderTaxNo' => $item['iossNumber'] ?? '',//【选填】收件人税号（巴西必填）    9.30物流商告知写反了
                'taxNo' => $item['senderTaxNumber'] ?? '',//【选填】寄件人税号，VAT 识别账号 6.11 新增,   9.30物流商告知写反了
                'prepaymentOfVat' => 0,//【选填】，预缴增值税方式，(0: IOSS 1: no-IOSS 2: other)，6.11 新增
                'receiver' => [
                    'receiverName' => $item['recipientName'] ?? '',// Y:收件人姓名Length <= 50 '',// Y:收件人姓名Length <= 50
                    'receiverPhone' => $item['recipientPhone'] ?? '', //N:收件人电话
                    'receiverMobile' => $item['recipientPhone'] ?? '',// 【必填】收件人手机（手机为准）
                    'receiverNation' => $item['recipientCountryCode'] ?? '',//收件人国家
                    'receiverProvince' => $item['recipientState'] ?? '', //N:收件人省/州
                    'receiverCity' => $item['recipientCity'] ?? '', //N:收件人城市
                    'receiverAddress' => $item['recipientStreet'] ?? ' ' ?? '',// Y:收件人街道1
                    'receiverEmail' => $item['recipientEmail'] ?? '',// N:收件人邮箱
                    'receiverPostCode' => $item['recipientPostCode'] ?? '', //Y:收件人邮编
                ],
                'items' => $productList,
            ];
            $data['key'] = 'apply';
            $ls[] = $data;
        }

        $response = $this->request(__FUNCTION__, $ls[0]);

        // 处理结果
        $reqRes = $this->getReqResData();

        $fieldData = [];
        $fieldMap = FieldMap::createOrder();

        // 结果
        $flag = $response['code'] == 0;

        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['msg'] ?? '未知错误');
        // 获取追踪号,如果延迟的话
        if ($flag && empty($response['data']['frt_channel_hawbcode'])) {
            $trackNumberResponse = $this->getTrackNumber($response['data']['trackingNo']);
            if ($trackNumberResponse['flag']) {
                $fieldData['trackingNo'] = $trackNumberResponse['trackingNumber'] ?? '';//追踪号
                $fieldData['frt_channel_hawbcode'] = $trackNumberResponse['frtTrackingNumber'] ?? '';//尾程追踪号
            }
        }
        $fieldData['orderNo'] = $ls[0]['logisticsOrderNo'];
        $fieldData['trackingNo'] = $response['data']['trackingNo'] ?? '';
        $fieldData['frt_channel_hawbcode'] = $flag ? ($trackNumberResponse['frtTrackingNumber'] ?? '') : '';//尾程追踪号
        $fieldData['id'] = $response['data']['serialNum'] ?? $ls[0]['logisticsOrderNo'];
        $this->order_track = $flag ? [$response['data']['trackingNo'] => $ls[0]['logisticsOrderNo'], $ls[0]['logisticsOrderNo'] => $response['data']['trackingNo']] : [];
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
            'weight' => $params['weight'] ?? '',
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
    public function deleteOrder(string $order_code)
    {
        $this->throwNotSupport(__FUNCTION__);
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
            case 'apply':
                unset($data['key']);unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['create_order_command'], $data, $this->dataType, $this->apiHeaders);
                break;//下单
            case 'print':
                unset($data['key']);unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['get_label_command'], $data, $this->dataType, $this->apiHeaders, '', '', false);
                break;//获取面单
            case 'trackInfoExt':
                unset($data['key']);unset($this->req_data['key']);
                $this->apiHeaders = [
                    'token' => $this->config['track_token'],
                    'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
                ];
                $queryStr='sendID='.$this->config['sendID'].'&proviceNo=99&msgKind=XXX_JDPT_TRACE&serialNo=100000000001&sendDate='.date('YmdHis', time()).'&receiveID=JDPT&batchNo=999&dataType=1&dataDigest='.base64_encode(md5(json_encode($data, JSON_FORCE_OBJECT).$this->config['track_token'])).'&msgBody='.urlencode(json_encode($data, JSON_FORCE_OBJECT));
                $response = $this->sendCurl('post', $this->config['get_track_url'].'?'.$queryStr, urlencode(json_encode($data, JSON_FORCE_OBJECT)),$this->dataType, $this->apiHeaders);
                break;//获取轨迹
            case 'list':
                unset($data['key']);unset($this->req_data['key']);
                $response = $this->sendCurl('get', $this->config['get_method_url'], [], $this->dataType, $this->apiHeaders);
                break;//获取运输方式
            case 'updateWeight':
                unset($data['key']);unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['update_weight_command'], $data, $this->dataType, $this->apiHeaders);
                break;//客户通过updateWeight API提交订单核重，与仓库操作的实重进行对比是否超重量差异值。注意：一定要在仓库操作前推送，否则将不接收客户推送的核重。
            case 'lastnum':
                unset($data['key']);unset($this->req_data['key']);
                $response = $this->sendCurl('get', $this->config['url'] . $this->req_data['orderNo'], [], $this->dataType, $this->apiHeaders);
                break;//获取追踪号和转单号用的
            default:
                unset($data['key']);unset($this->req_data['key']);
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
        $flag = $response['code'] == 0;
        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['msg'] ?? ($response['msg'] ?? '未知错误'));
        $fieldData['trackingNo'] = $flag ? ($response['data']['trackingNo'] ?? '') : '';//追踪号
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
            'key' => 'list',
        ];
        $res = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();


        if ($res['code'] != 0) {
            return $this->retErrorResponseData($res['msg'] ?? '未知错误');
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
     * 获取订单标签
     * @return mixed
     */
    public function getPackagesLabel($params = [])
    {
        $data = [
            'logisticsOrderNoList' => [[
                'logisticsOrderNo' => $params['customerOrderNo'],
            ]],
            'printType' => '1',// --打印类型（默认热敏 1） 0：普通 A4 1：热敏
            'printFormat' => 'pdf',//--标签类型（默认 pdf） pdf、html
            'labelFormat' => 'label_100x100',//-- 标 签 类 型 （ label_100x100 、 label_100x150，默认 label_100x100）
            'key' => 'print',
        ];
        $responses = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        $response['flag'] = true;
        $response['info'] = '';
        $response['trackingNo'] = $params['trackingNumber'] ?? '';
        $response['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF;
        $response['url'] = base64_encode($responses) ?? '';
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
        $trackNumberArray = $this->toArray($trackNumber);
        if (count($trackNumberArray) > self::QUERY_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "查询物流轨迹一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
        }
        $data = [
            'traceNo' => $trackNumber,
            'key' => 'trackInfoExt',
        ];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        // 结果
        $flag = $response['responseState'] == true;

        if (!$flag) {
            return $this->retErrorResponseData($response['errorDesc'] ?? '未知错误');
        }

        $data = $response;

        $ls = [];
        if (!empty($data['responseItems'])) {
            foreach ($data['responseItems'] as $key => $val) {
                $data['trackingNo'] = $val['traceNo'];
                $data['status'] = $val['opCode'] ?? '';
                $data['status_msg'] = $val['opName'] ?? '';
                $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
            }
        }

        $data['traces'] = $ls;

        $fieldData = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);

        return $this->retSuccessResponseData($fieldData);
    }

    public function getPackagesDetail($order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }
}