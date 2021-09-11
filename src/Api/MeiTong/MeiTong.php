<?php
/**
 *
 * User: blaine
 * Date: 3/1/21
 */

namespace smiler\logistics\Api\MeiTong;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

class MeiTong extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface, TrackLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹,5自定义
     */
    const ORDER_COUNT = 10;
    /**
     * 一次最多查询多少个物流商
     */
    const QUERY_TRACK_COUNT = 1000;

    public $iden = 'meitong';

    public $iden_name = '美通头程';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'json';

    public $apiHeaders = [];

    public $order_track = [];

    public $interface = [

        'createOrder' => 'create', // 创建并预报订单 todo 如果调用创建订单需要预报

        'deleteOrder' => 'void', //删除(取消)订单。发货后的订单不可删除。

        'queryTrack' => 'tracking', //轨迹查询

        'getShippingMethod' => 'get_services',//获取配送方式,有效值： all 所有服务；b2b B2B 大货服务；b2c B2C 小包服 务；ex 大货快递；

        'getPackagesLabel' => 'get_labels', // 【打印标签|面单

        'operationPackages' => 'update_weight',// 核实提交订单重量
    ];

    /**
     * MeiTong constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['access_token', 'url'], $config);
        $this->config = $config;
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
                $productList[] = [
                    'sku' => $value['productSku'] ?? '',
                    'unit_value' => (float)(round($value['declarePrice'], 2) ?? ''),
                    'weight' => (float)(round($value['declareWeight'], 3) ?? ''),
                    'qty' => (int)($value['quantity'] ?? ''),
                    'name_en' => $value['declareEnName'] ?? '',
                    'name_zh' => $value['declareCnName'] ?? '',
                    'size' => (float)(round($value['length'], 3) ?? '') . '*' . (float)(round($value['width'], 3) ?? '') . '*' . (float)(round($value['height'], 3) ?? ''),
                    'hscode' => $value['hsCode'] ?? '',//海关编码
                    'material' => $value['declareCnName'],//材质
                    'usage' => 'play',//用途
                    'brand' => $value['declareCnName'],//品牌
                    'brand_type' => '无',
                    'model' => '无',
                    'is_battery' => $isElectricity ?? 0,//产品是否带电，默认为 0。1 为是，0 为否
                    'sale_price' => (float)(round($value['declarePrice'], 2) ?? 0),//销售价格
                    'amazon_ref_id' => $item['customerOrderNo'] ?? ($item['iossNumber'] ?? '无'),//产品的 PO Number
                ];
                $packages[] = [
                    'number' => $item['customerOrderNo']??$key + 1,
                    'client_length' => (float)(round($value['length'], 3) ?? ''),// N:包裹长度（单位：cm）
                    'client_width' => (float)(round($value['width'], 3) ?? ''),// N:包裹宽度（单位：cm）
                    'client_height' => (float)(round($value['height'], 3) ?? ''),// N:包裹高度（单位：cm）
                    'client_weight' => (float)(round($value['declareWeight'], 3) ?? ''),//包裹重，重量KG (11,3)小数
                    'declarations' => $productList,// Y:一次最多支持 10 个产品信息（超过 10 个将会忽略）
                ];
            }


            $data['shipment'] = [
                //todo 调试写死
                'service' => $item['shippingMethodCode'] ?? 'YT',//渠道代码,默认盐田海派
                'store_id' => '',
                'client_reference' => $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 12
                'parcel_count' => count($item['productList']),//箱子总数，默认为1
                //'export_scc' => 0,//是否单独报关，0 否，1 是；（此字段后 续会弃用。这里填 1 是的时候等同于下面字段 exportwith 的 2 报关 退税，export_scc 和 exportwith 同时传了的话，以 exportwith 为准。）
                //'import_scc' => 0,//是否单独清关，0 否，1 是；（此字段 后续会弃用。这里填 1 是的时候等同于下面字段 taxwith 的 3 自主 税号，import_scc 和 taxwith 同时传了的话，以 taxwith 为准。）
                'taxwith' => 0,//交税方式，默认值为 0 什么都不选择；1 不 包税；2 包税；3 自主税号
                'deliverywith' => '', //交货条款，默认为空。ddu 代表 DDU;ddp 代表 DDP
                'exportwith' => 2,//0, //报关，默认值为 0 什么都不选择；1 买单 报关；2 报关退税
                'importwith' => 0,//清关，默认值为 0 什么都不选择；1 一 般贸易清关；2 快件清关
                'attrs' => [],//物品属性。带电：elec 带磁：magnetic 危险品： danger
                'declaration_currency' => $item['packageCodCurrencyCode'] ?? 'USD', //N:币种
                'vat_number' => $item['iossNumber'] ?? $item['recipientTaxNumber'],//VAT号
                'to_address' => [
                    'name' => $item['recipientName'] ?? '',// Y:收件人姓名Length <= 50 '',// Y:收件人姓名Length <= 50
                    'company' => $item['recipientCompany'] ?? '', //N:收件人公司名称
                    'tel' => $item['recipientPhone'] ?? '', //N:收件人电话
                    'country' => $item['recipientCountryCode'] ?? '',//收件人国家
                    'state' => $item['recipientState'] ?? '', //N:收件人省/州
                    'city' => $item['recipientCity'] ?? '', //N:收件人城市
                    'address_1' => $item['recipientStreet'] ?? ' ' ?? '',// Y:收件人街道1
                    'address_2' => $item['recipientStreet2'] ?? '',// N:收件人街道2
                    'postcode' => $item['recipientPostCode'] ?? '', //Y:收件人邮编
                ],
                'from_address' => [
                    'name' => $item['senderName'] ?? '', //N:发件人姓名
                    'company' => $item['senderCompany'] ?? '', // N:发件人公司名
                    'tel' => $item['senderPhone'] ?? '', //N:发件人电话
                    'address_1' => $item['senderFullAddress'] ?? '',// Y:发件人完整地址Length <= 200
                    'city' => $item['senderCity'] ?? '',// Y:发件人城市Length<=50
                    'postcode' => $item['senderPostCode'] ?? '',// N:发件人邮编Length <= 32
                    'country' => 'CN',// Y:发件人国家简码，默认CN, $item['senderCountryCode'] ??
                ],
                'parcels' => $packages,
            ];
            $data['key'] = 'create';
            $data['validation'] = ['access_token' => $this->config['access_token']];
            $ls[] = $data;
        }

        $response = $this->request(__FUNCTION__, $ls[0]);

        if(empty($response)){
            return $this->retErrorResponseData($response['info']);
        }
        // 处理结果
        $reqRes = $this->getReqResData();

        $fieldData = [];
        $fieldMap = FieldMap::createOrder();

        // 结果
        $flag = $response['status'] == true;
        if(empty($flag)){
            return $this->retErrorResponseData($response['info']);
        }
        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['info'] ?? '未知错误');
        $fieldData['orderNo'] =$ls[0]['shipment']['client_reference']  ?? ($response['data']['shipment']['shipment_id']??'');
        $fieldData['trackingNo'] = $response['data']['shipment']['shipment_id'] ?? '';
        $fieldData['frt_channel_hawbcode'] = $flag ? ($trackNumberResponse['frtTrackingNumber'] ?? '') : '';//尾程追踪号
        $fieldData['id'] = $response['data']['shipment']['shipment_id'] ?? '';
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
            'shipment' => [
                'client_reference' => $params['ProcessCode'] ?? '',
                'parcels' => [[
                    'number' => $params['ProcessCode'] ?? '',
                    'client_weight' => $params['weight'] ?? '',
                ]],
            ],
            'validation' => ['access_token' => $this->config['access_token']],
            'key' => 'update_weight',
        ];
        $response = $this->request(__FUNCTION__, $data);
        if (empty($response)) {
            return $this->retErrorResponseData('修改订单重量异常');
        }
        // 结果
        if (empty($response['status'])) {
            return $this->retErrorResponseData($response['info'] ?? '未知错误');
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
            case 'create':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['create_order_command'], $data, $this->dataType, $this->apiHeaders);
                break;//下单
            case 'get_labels':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['get_label_command'], $data, $this->dataType, $this->apiHeaders);
                break;//获取面单
            case 'tracking':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['get_track_command'], $data, $this->dataType, $this->apiHeaders);
                break;//获取轨迹
            case 'get_services':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['get_method_command'], $data, $this->dataType, $this->apiHeaders);
                break;//获取运输方式
            case 'update_weight':
                unset($data['key']);
                unset($this->req_data['key']);
                $response = $this->sendCurl('post', $this->config['url'] . $this->config['update_weight_command'], $data, $this->dataType, $this->apiHeaders);
                break;//客户通过updateWeight API提交订单核重，与仓库操作的实重进行对比是否超重量差异值。注意：一定要在仓库操作前推送，否则将不接收客户推送的核重。
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
            'services' => ['type' => 'all'],
            'validation' => ['access_token' => $this->config['access_token']],
            'key' => 'get_services',
        ];
        $res = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();


        if (empty($res['status'])) {
            return $this->retErrorResponseData($res['info'] ?? '未知错误');
        }
        foreach ($res['data']['services'] as $item) {
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
            'shipment' => ['client_reference' => $order_id],
            'validation' => ['access_token' => $this->config['access_token']],
            'key' => 'void',
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
            'shipment' => ['client_reference' => $params['customerOrderNo']],
            'validation' => ['access_token' => $this->config['access_token']],
            'key' => 'get_labels',
        ];
        $response = $this->request(__FUNCTION__, $data);
        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        // 结果
        $flag = $response['status'] == true;

        if (!$flag) {
            return $this->retErrorResponseData($response['info'] ?? '未知错误');
        }

        $response['flag'] = $flag;
        $response['info'] = $response['info'] ?? '';
        $response['trackingNo'] = $params['trackingNumber'] ?? '';
        $response['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF;
        $response['url'] = $response['data']['shipment']['single_pdf'] ?? '';
        $response['label_path_plat'] = $response['data']['shipment']['single_pdf']??'';//不要填写
        $response['lable_content_type'] = 1;
        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($response, $fieldMap);
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
        $data = [
            'shipment' => ['client_reference' => $trackNumber, 'language' => 'zh'],
            'validation' => ['access_token' => $this->config['access_token']],
            'key' => 'tracking',
        ];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        // 结果
        $flag = $response['status'] == true;

        if (!$flag) {
            return $this->retErrorResponseData($response['info'] ?? '未知错误');
        }

        $data = $response['data']['shipment'];
       
        $ls = [];
        foreach ($data['traces'] as $key => $val) {
            $val['time'] = date('Y-m-d H:i:s', $val['time']);//格式化时间
            $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
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