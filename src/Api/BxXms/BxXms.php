<?php
/**
 *
 * User: blaine
 * Date: 3/1/21
 */

namespace smiler\logistics\Api\BxXms;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

class BxXms extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface, TrackLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹,5自定义
     */
    const ORDER_COUNT = 2;
    /**
     * 一次最多查询多少个物流商
     */
    const QUERY_TRACK_COUNT = 1;
    public $iden = 'bxxms';
    public $iden_name = '八星物流';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'xml';

    public $apiHeaders = [
        'Content-Type' => 'application/xml'
    ];

    public $interface = [
        'createOrder' => 'createAndAuditOrder', // 创建并预报订单 todo 如果调用创建订单需要预报

        'deleteOrder' => 'deleteOrder', //删除订单。发货后的订单不可删除。

        'queryTrack' => 'getTrack', //轨迹查询

        'getShippingMethod' => 'getTransportWayList', //获取配送方式

        'getPackagesLabel' => 'printOrder', // 【打印标签|面单

    ];

    /**
     * HuaHan constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['userToken', 'url'], $config);
        $this->config = $config;
        if (!empty($config['apiHeaders'])) {
            $this->apiHeaders = array_merge($this->apiHeaders, $config['apiHeaders']);
        }
    }

    /**
     * array转XML
     * @param $array
     * @param string $root
     * @return string
     */
    public static function arrayToXml($array, $root = 'ns1:callService', $encoding = 'utf-8')
    {
        $xml = '<?xml version="1.0" encoding="' . $encoding . '"?>';
        $xml .= '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://service.hop.service.ws.hlt.com/">';
        $xml .= '<soapenv:Body>';
        $xml .= "<ser:{$root}>";
        $xml .= static::arrayToXmlInc($array);
        $xml .= "</ser:{$root}>";
        $xml .= "<soapenv:Body>";
        $xml .= "</soap:Envelope>";
        return $xml;
    }

    public static function xmlToArray($xml)
    {
        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $xml);
        $xml = new \SimpleXMLElement($response);
        $body = $xml->xpath('//return')[0];
        $array = json_decode(json_encode($body), true);
        return $array;
    }

    /**
     * 创建订单
     * @param array $params
     * @return mixed
     * @throws InvalidIArgumentException
     * @throws ManyProductException
     * {"ask":"Success","message":"Success","reference_no":"T1020210221154454829981280","shipping_method_no":"HHWMS1052000004YQ","order_code":"HHWMS1052000004YQ","track_status":"3","sender_info_status":"0","ODA":"","agent_number":"","time_cost(s)":"0.484375"}
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
            $productList = [
                'name' => '',// Y:申报英文名称Length <= 50
                'cnName' => '',// N:申报中文名称Length <= 50
                'pieces' => 0,// Y:产品数量;数值必须为正整数
                'netWeight' => 0,// Y:总量;Length <= 50 KG
                'unitPrice' => 0, //Y:单价
                'customsNo' => '',// N:物品的 HTS Code
                'productMemo' => '', //配货信息
            ];
            foreach ($item['productList'] as $key => $value) {
                if($key == 0){
                    $productList['name'] = $value['declareEnName'] ?? '';
                    $productList['cnName'] = $value['declareCnName'] ?? '';
                    $productList['pieces'] = (int)($value['quantity'] ?? '');
                    $productList['netWeight'] = ($value['declareWeight'] ?? '');
                    $productList['unitPrice'] = (float)($value['declarePrice'] ?? '');
                    $productList['customsNo'] = $value['hsCode'] ?? '';
                    $productList['productMemo'] = $value['productSku'] ?? '';
                }else{
                    break;
                }
            }
            $address = ($item['recipientStreet'] ?? ' ') . ($item['recipientStreet1'] ?? ' '). ($item['recipientStreet2'] ?? '');
            $ls[] = [
                'orderNo' => $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 12
                'trackingNo' => '', //N:服务商跟踪号码。若填写，需符合运输方式中规定的编码规则。length <= 32
                //todo 调试写死
                'transportWayCode' => $item['shippingMethodCode'] ?? 'USRNN',// Y:serviceCode: test => UBI.CN2FR.ASENDIA.FULLLY.TRACKED
                'cargoCode' => 'W', //N:货物类型。取值范围[W:包裹/D:文件]
                'originCountryCode' => $item['senderCountryCode'] ?? 'CN', //Y:起运国家二字简码。
                'destinationCountryCode' => $item['recipientCountryCode'] ?? '',// Y:收件人国家二字代码，
                'pieces' => 1, //Y:货物件数。value>=1
                'length' => (float)($item['packageLength'] ?? ''),// N:包裹长度（单位：cm）
                'width' => (float)($item['packageWidth'] ?? ''),// N:包裹宽度（单位：cm）
                'height' => (float)($item['packageHeight'] ?? ''),// N:包裹高度（单位：cm）
                'shipperCompanyName' => $item['senderCompany'] ?? '', // N:发件人公司名
                'shipperName' => $item['senderName'] ?? '', //N:发件人姓名
                'shipperAddress' => $item['senderFullAddress'] ?? '',// Y:发件人完整地址Length <= 200
                'shipperTelephone' => $item['senderPhone'] ?? '', //N:发件人电话
                'shipperMobile' => $item['senderPhone'] ?? '',// Y:发件人电话Length <= 32,
                'shipperPostcode' => $item['senderPostCode'] ?? '',// N:发件人邮编Length <= 32
                'shipperStreetNo' => '', //N:发件人门牌号/建筑物名称。
                'shipperStreet' => $item['senderFullAddress'] ?? '', //N:发件人街道。
                'shipperCity' => $item['senderCity'] ?? '',// Y:发件人城市Length<=64
                'shipperProvince' => $item['senderState'] ?? '', // N:发件人省
                'consigneeCompanyName' => $item['recipientCompany'] ?? '', //N:收件人公司名称
                'consigneeName' => $item['recipientName'] ?? '',// Y:收件人姓名Length <= 64 '',// Y:收件人姓名Length <= 64
                'consigneeStreetNo' => '', //N:收件人门牌号/建筑物名称。
                'street' => $address ?? '',// Y:收件人街道
                'city' => $item['recipientCity'] ?? '', //N:收件人城市
                'province' => $item['recipientState'] ?? '', //N:收件人省
                'consigneePostcode' => $item['recipientPostCode'] ?? '', //N:收件人邮编
                'consigneeTelephone' => $item['recipientPhone'] ?? '', //N:收件人电话
                'consigneeMobile' => $item['recipientPhone'] ?? '', //N:收件人手机
                'weight' => (float)($item['predictionWeight'] ?? ''),
                'insured' => 'N', //Y:购买保险（投保：Y，不投保：N）
                'goodsCategory' => 'O', //Y:物品类别。取值范围[G:礼物/D:文件/S:商业样本/R:回货品/O:其他]
                'goodsDescription' => '', //物品类别内容
                'memo' => $item['remark'] ?? '', //N:备注
                'codSum' => (float)($item['packageCodAmount'] ?? ''), //N:COD金额
                'codCurrency' => $item['packageCodCurrencyCode'] ?? 'USD', //N:币种
                'declareItems' => $productList,// Y:一次最多支持 5 个产品信息（超过 5 个将会忽略）
            ];
        }
        $response = $this->request(__FUNCTION__, ['createOrderRequest' => $ls[0]]);

        // 处理结果
        $reqRes = $this->getReqResData();

        $fieldData = [];
        $fieldMap = FieldMap::createOrder();

        // 结果
        $flag = $response['success'] == 'true';

        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['error']['errorInfo'] ?? '未知错误');

        $fieldData['orderNo'] = $ls[0]['orderNo'];
        $fieldData['trackingNo'] = $response['trackingNo'] ?? '';
        $fieldData['id'] = $response['id'] ?? '';

        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
//        $this->dd($response, $ret, $reqRes);
        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);
    }

    public function request($function, $data = [])
    {
        $data = $this->buildParams($function, $data);
        $this->req_data = $data;
        $res = $this->sendCurl('post', $this->config['url'], $data, $this->dataType, $this->apiHeaders, 'UTF-8', $this->interface[$function]);
        $this->res_data = $res;
        return $res;
    }

    /**
     * 获取请求数据
     */
    public function buildParams($interface, $arr = [])
    {
        return array_merge(['userToken' => $this->config['userToken']], $arr);
    }

    /**
     * 获取物流商运输方式
     * @return mixed
     * [{"success":"true","transportWays":[{"autoFetchTrackingNo":"Y","code":"DHLV4-OT","name":"OTTO专线","trackingNoRuleMemo":[],"trackingNoRuleRegex":[],"used":"Y"},{"autoFetchTrackingNo":"Y","code":"DHL-ALL","name":"全欧特派","trackingNoRuleMemo":[],"trackingNoRuleRegex":[],"used":"Y"}]}]
     */
    public function getShippingMethod()
    {
        $res = $this->request(__FUNCTION__);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();

//        $this->dd($res);
        if ($res['success'] != 'true') {
            return $this->retErrorResponseData($res['errorInfo'] ?? '未知错误');
        }
        foreach ($res['transportWays'] as $item) {
            $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
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
            'printOrderRequest' => [
                'trackingNo' => implode(',', $this->toArray($params['orderNo'])),
                'printSelect' => $params['label_content'] ?? 1, //选择打印样式“1” 地址标签打印 “11” 报关单 “2” 地址标签+配货信息 “3” 地址标签+报关单（默认） “13”地址标签+(含配货信息) “12” 地址标签+(含配货信息)+报关单 “15” 地址标签+报关单+配货信息
                'pageSizeCode' => $params['label_type'] ?? 6, //“1”表示80.5mm × 90mm “2”表示105mm × 210mm “7”表示100mm × 150mm “4”表示102mm × 76mm “5”表示110mm × 85mm “6”表示100mm × 100mm（默认） “3”表示A4,
                'downloadPdf' => 0,
            ],
        ];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        // 结果
        $flag = $response['success'] == 'true';

        if (!$flag) {
            return $this->retErrorResponseData($response['error']['errorInfo'] ?? '未知错误');
        }

        $response['flag'] = $flag;
        $response['trackingNo'] = $params['trackNumber'][0] ?? '';
        $response['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_PDF;
        $response['lable_content_type'] = $params['label_content'] ?? 1;

        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($response, $fieldMap);
//        $this->dd($fieldData);
        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 获取物流商轨迹
     * @return mixed
     * {"success":"true","trace":{"pathAddr":"深圳SZ","pathInfo":"包裹已进入八星深圳SZ, 已分拣出仓（Outbound）","pathTime":"2021-03-01T17:08:37+08:00","rcountry":"US","status":"5","tno":"90110US009482375","sPaths":[{"pathAddr":"深圳SZ","pathInfo":"包裹已进入八星深圳SZ, 收货完成（Inbound）","pathTime":"2021-03-01T17:08:25+08:00","pathType":"0"},{"pathAddr":"深圳SZ","pathInfo":"包裹已进入八星深圳SZ, 已分拣出仓（Outbound）","pathTime":"2021-03-01T17:08:37+08:00","pathType":"0"}]}}
     */
    public function queryTrack($trackNumber)
    {
        $trackNumberArray = $this->toArray($trackNumber);
        if (count($trackNumberArray) > self::QUERY_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "查询物流轨迹一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
        }
        $data = [
            'trackingNo' => $trackNumber,
            'orderNo' => '',
        ];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        // 结果
        $flag = $response['success'] == 'true';

        if (!$flag) {
            return $this->retErrorResponseData($response['error']['errorInfo'] ?? '未知错误');
        }

        $data = $response['trace '];

        $ls = [];
        foreach ($data['sPaths'] as $key => $val) {
            $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
        }
        $data['sPaths'] = $ls;
        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);

        return $this->retSuccessResponseData($fieldData);
    }

    public function getPackagesDetail($order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }
}