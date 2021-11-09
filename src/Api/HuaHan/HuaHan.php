<?php
/**
 *
 * User: blaine
 * Date: 2/20/21
 */

namespace smiler\logistics\Api\HuaHan;


use smiler\logistics\Api\HuaHan\FieldMap;
use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\Exception\NotSupportException;
use smiler\logistics\LogisticsAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\TrackLogisticsInterface;

/**
 * Class HuaHan
 * @package smiler\logistics\Api\HuaHan
 * @desc 华翰物流
 * @link http://new.hh-exp.com:8181/docs/mindoc/v1
 */
class HuaHan extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface, TrackLogisticsInterface
{
    public $iden = 'huahan';

    public $iden_name = '华翰物流';

    /**
     * 一次最多提交多少个包裹,5自定义
     */
    const ORDER_COUNT = 5;

    /**
     * 一次最多查询多少个物流商
     */
    const QUERY_TRACK_COUNT = 50;

    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'xml';

    public $apiHeaders = [];

    public $interface = [
        'createOrder' => 'batchCreateOrder', // 【创建订单】

        'batchCreateOrder' => 'batchCreateOrder', //批量创建订单

        'queryTrack' => 'getCargoTrack', //轨迹查询

        'operationPackages' => 'modifyOrderWeight', //修改订单重量

        'getTrackNumber' => 'getTrackNumber',//二次查询跟踪号

        'getShippingMethod' => 'getShippingMethod', //获取配送方式

        'getPackagesLabel' => 'batchGetLabelUrl', // 【打印标签|面单】 todo 单个获取标签 getLabelUrl

        'batchGetLabelUrl' => 'batchGetLabelUrl', //批量获取面单

        'deleteOrder' => 'cancelOrder', //取消订单

        'getPackagesDetail' => 'getOrder', //查询订单

        'feeTrail' => 'feeTrail', //运费试算 todo 暂时未用

        'interceptOrder' => 'interceptOrder', //拦截订单 todo 暂时未用

        'getFeeByOrder' => 'getOrderFee', //费用查询
    ];

    /**
     * HuaHan constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['appToken', 'url', 'appKey'], $config);
        $this->config = $config;
        if(!empty($config['apiHeaders'])){
            $this->apiHeaders = array_merge($this->apiHeaders,$config['apiHeaders']);
        }
    }


    /**
     * 获取请求数据
     */
    public function buildParams($interface, $arr = [])
    {
        $data = [
            'appToken' => $this->config['appToken'],
            'appKey' => $this->config['appKey'],
            'service' => $this->interface[$interface]
        ];
        if (!empty($arr)) {
            $data['paramsJson'] = json_encode($arr, JSON_UNESCAPED_UNICODE);
        }
        return $data;
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
            $invoiceValue = 0;
            foreach ($item['productList'] as $value) {
                $productList[] = [
                    'invoice_enname' => $value['declareEnName'] ?? '',// Y:申报英文名称Length <= 50
                    'invoice_cnname' => $value['declareCnName'] ?? '',// N:申报中文名称Length <= 50
                    'invoice_weight' => $value['declareWeight'] ?? '',// Y:总量;Length <= 50 KG
                    'invoice_quantity' => (int)($value['quantity'] ?? ''),// Y:产品数量;数值必须为正整数
                    'unit_code' => 'PCE', // N: 单位(MTR-米；PCE-件；SET-套),默认PCE
                    'invoice_unitcharge' => (float)($value['declarePrice'] ?? ''), //Y:单价
                    'invoice_currencycode' => $value['currencyCode'] ?? 'USD',// , //申报币种，不传值默认为USD(美元)；USD-美元,AUD-澳元
                    'hs_code' => $value['hsCode'] ?? '',// N:海关编码
                    'invoice_note' => '', //配货信息
                    'invoice_url' => $value['productUrl'] ?? '',// N:销售地址
                    'sku' => $value['productSku'] ?? '',// Y:产品 SKU;Length <= 100
                    'material' => '', //N:申报材质
                    'invoice_function' => '', //N:用途s
                ];
                $invoiceValue += (float)($value['declarePrice'] ?? '') * (int)($value['quantity'] ?? '');
            }

            $ls[] = [
                'reference_no' =>  $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 50
                //todo 调试写死
                'shipping_method' => $item['shippingMethodCode'] ?? 'HYTGHC',// Y:serviceCode: test => UBI.CN2FR.ASENDIA.FULLLY.TRACKED
                'country_code' => $item['recipientCountryCode'] ?? '',// Y:收件人国家二字代码，可用值参见 6.1。Lenth = 2
                'extra_service' => '', //附加服务代码，每个以英文分号“;”隔开
                'order_weight' => (float)($item['predictionWeight'] ?? ''),// Y:订单重量，单位KG，默认为0.2
                'shipping_method_no' => $item['trackingNumber'] ?? '',//N:服务商单号（追踪单号，默认不需要传值，由华翰接口返回）
                'order_pieces' => '1', //N:外包装件数,默认1（默认值1即可,发FBA空海运等一票货多箱的时候才需要根据情况设置）
                'insurance_value' => '', //N:投保金额，默认RMB
                'mail_cargo_type' => '', //N:包裹申报种类（1-Gif礼品；2-CommercialSample商品货样；3-Document文件；4-Other其他。默认4）
                'length' => (float)($item['packageLength'] ?? ''),// N:包裹长度（单位：cm）
                'width' => (float)($item['packageWidth'] ?? ''),// N:包裹宽度（单位：cm）
                'height' => (float)($item['packageHeight'] ?? ''),// N:包裹高度（单位：cm）s
                'Consignee' => [ //收件人信息
                    'consignee_company' => $item['recipientCompany'] ?? '', //N:收件人公司名
                    'consignee_province' => $item['recipientState'] ?? '', //N:收件人省
                    'consignee_city' => $item['recipientCity'] ?? '', //N:收件人城市
                    'consignee_street' => $item['recipientStreet'] ?? '',// Y:收件人街道
                    'consignee_street2' => $item['recipientStreet1'] ?? '',// Y:收件人街道
                    'consignee_street3' => $item['recipientStreet2'] ?? '',// Y:收件人街道
                    'consignee_postcode' => $item['recipientPostCode'] ?? '', //N:收件人邮编
                    'consignee_name' => $item['recipientName'] ?? '',// Y:收件人姓名Length <= 64 '',// Y:收件人姓名Length <= 64
                    'consignee_telephone' => $item['recipientPhone'] ?? '', //N:收件人电话
                    'consignee_mobile' => $item['recipientPhone'] ?? '', //N:收件人手机
                    'consignee_email' => $item['recipientEmail'] ?? '',// N:收件人邮箱Length <= 128
//                        'consignee_certificatetype' => 1,// N:收件人证件类型（ID-身份证；PP-护照）
                    'consignee_certificatecode' => $item['recipientIdentityNumber'] ?? '',// N:证件号码
                    'consignee_credentials_period' => '', //N:证件有效期， 格式：2014-04-15
                    'consignee_doorplate' => '', //收件人门牌号（注意：除一些特殊专线产品要求单独上传门牌号外，门牌号直接放地址里面即可无需单独放到此字段）
                    'consignee_taxno' => $item['recipientTaxNumber'] ?? '',// N:收件人个人税号Length<=32
                    'IOSS' => $item['iossNumber'] ?? '',// 欧盟税号（ioss税号）
                ],
                'Shipper' => [
                    //发件人信息
                    'shipper_company' => $item['senderCompany'] ?? '', // N:发件人公司名
                    'shipper_countrycode' => $item['senderCountryCode'] ?? '', // Y:发件人国家二字码
                    'shipper_province' => $item['senderState'] ?? '', // Y:发件人省
                    'shipper_city' => $item['senderCity'] ?? '',// Y:发件人城市Length<=64
                    'shipper_street' => $item['senderFullAddress'] ?? '',// N:发件人完整地址Length <= 200
                    'shipper_postcode' => $item['senderPostCode'] ?? '',// N:发件人邮编Length <= 32
                    'shipper_areacode' => '', // N:区域代码
                    'shipper_name' => $item['senderName'] ?? '', //Y:发件人姓名
                    'shipper_telephone' => $item['senderPhone'] ?? '', //Y:发件人电话
                    'shipper_mobile' => $item['senderPhone'] ?? '',// Y:发件人电话Length <= 32,
                    'shipper_email' => $item['senderEmail'] ?? '', //N:发件人邮箱
                    'shipper_fax' => '', //N:发件人传真
                    'order_note' => $item['remark'] ?? '', //N:订单备注
                    'shipper_taxno' => '',//N:发件人税号
                ],
                'ItemArr' => $productList,// Y:一次最多支持 5 个产品信息（超过 5 个将会忽略）
            ];
        }
        $response = $this->request(__FUNCTION__, 'post', $ls);

        $reqRes = $this->getReqResData();
        $fieldMap = FieldMap::createOrder();


        $trackNumberData = [];
        if (!empty($trackNumberResponse['data'])) {
            $trackNumberData = array_column($trackNumberResponse['data'], null, 'OrderNumber');
        }
        $data = [];
        if ($response['Result']) {
            foreach ($response['Result'] as $keyVale=>$value) {
                $fieldData =  [
                    'channel_hawbcode' => $trackNumberData[$value['reference_no']]['TrackingNumber'] ?? ($value['shipping_method_no'] ?? ''),
                    'refrence_no' => $value['reference_no'] ?? '',
                    'shipping_method_no' => $value['order_code'] ?? '',
                    'flag' => $value['ask'] != 'Failure' ? true : false,
                    'info' => $value['Error']['errMessage'] ?? ($value['message'] ?? ''),
                ];
                $data[] = array_merge($reqRes,LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap));

            }
        }
        return $this->retSuccessResponseData($data);
    }

    /**
     * 获取跟踪号，todo 有些渠道生成订单号不能立刻获取跟踪号
     * @param $reference_no
     * @return array|mixed
     */
    public function getTrackNumber($reference_no)
    {
        $params = [
            'reference_no' => $this->toArray($reference_no)
        ];
        $res = $this->request(__FUNCTION__, 'post', $params);
        return $res;
    }

    /**
     * 获取物流商运输方式
     * @return mixed
     * {"ask":"Success","data":[{"code":"PK0088","cn_name":"A深圳UPS红单5000","en_name":"SZUPSHD5XC","group_code":"ZD","track_status":"Y","aging":"3-11","note":"拿大等需另加私人住宅费25\/票+燃油， 偏远费、关税预付费用等单独咨询"}]}
     */
    public function getShippingMethod()
    {
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();
        $res = $this->request(__FUNCTION__);
        if ($res['ask'] != 'Success') {
            return $this->retErrorResponseData($res['Error']['errMessage'] ?? ($res['message'] ?? '未知错误'));
        }
        foreach ($res['data'] as $item) {
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
        $ls = [];
        if ($pars) {
            foreach ($pars as $item) {
                if (!empty($item['order_code']) && !empty($item['weight'])) {
                    $ls[] = [
                        'order_code' => $item['order_id'] ?? '',// Y:客户单号（或者系统订单号，或者服务商单号都可以）
                        'weight' => empty($item['weight']) ? 0 : round($item['weight'], 3),// N:包裹总重量（单位：kg）,系统接收后自动四舍五入至 3 位小数
                    ];
                }

            }
        }

        if (empty($ls)) {
            throw new InvalidIArgumentException("请求参数不能为空");
        }

        $response = $this->request(__FUNCTION__, 'post', $ls);

        return $response;

    }

    /**
     * 取消订单，删除订单
     * @return mixed
     */
    public function deleteOrder(string $order_code)
    {
        $param = [
            'reference_no' => $order_code,
        ];
        $response = $this->request(__FUNCTION__, 'post', $param);
        $flag=$response['ask']=='Success';
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
        $param = [
            'reference_no' => $order_code,
        ];
        $response = $this->request(__FUNCTION__, 'post', $param);
        return $response;
    }

    public function getFeeDetailByOrder($order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    /**
     * 获取订单标签
     * todo 批量获取 得到的结果在一个pdf文件上面
     * @return mixed
     */
    public function getPackagesLabel($params = [])
    {
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();
        $data = [
            'reference_nos' => $this->toArray($params['trackNumber']),
            'label_type' => $params['label_type'] ?? 1, //PDF标签尺寸类型：1：10 * 10 标签；2：A4纸；3：10 * 15标签
            'label_content_type' => $params['label_content_type'] ?? 1, //1–标签；2–报关单；3–配货单；4–标签+报关单；5–标签+配货单；6–标签+报关单+配货单，默认为4
            'extra_option' => [
                'print_declare_info' => $params['print_declare_info'] ?? 'N', //是否打印配货信息：Y是，N否，默认为Y
            ],
        ];
        $response = $this->request(__FUNCTION__, 'post', $data);
        if($response['ask'] != 'Success'){
            $this->retErrorResponseData();
        }
        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData([
            'label_path_type' => ResponseDataConst::LSA_LABEL_PATH_TYPE_PDF,
            'lable_file' => $response['url'],
            'flag' => true,
        ], $fieldMap);
        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 获取物流商轨迹
     * @return mixed
     */
    public function queryTrack($trackNumber)
    {
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        $trackNumberArray = $this->toArray($trackNumber);
        if (count($trackNumberArray) > self::QUERY_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "查询物流轨迹一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
        }
        $data = [
            'codes' => $this->toArray($trackNumber),
        ];
        $response = $this->request(__FUNCTION__, 'post', $data);

        if ($response['ask'] != 'Success') {
            return $this->retErrorResponseData($value['Error']['errMessage'] ?? ($value['message'] ?? ''));
        }

        $data = $response['Data'];
        $fieldData = [];
        foreach ($data as $item){
            $ls = [];
            foreach ($item['Detail'] as $key => $val) {
                $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
            }
            $item['details'] = $ls;
            $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap1);
        }

        return $this->retSuccessResponseData($fieldData);
    }

    public function getPackagesDetail($order_id)
    {
        $data = [
            'reference_no' => $order_id
        ];
        $response = $this->request(__FUNCTION__, 'post', $data);
        return $response;
    }

    public function request($function, $method = 'post', $data = [])
    {
        $data = $this->buildParams($function, $data);
        $this->req_data = $data;
        $res = $this->sendCurl($method, $this->config['url'], $data, $this->dataType, $this->apiHeaders, 'UTF-8', 'ns1:callService');
        $this->res_data = $res;
        return $res;
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
        $xml .= '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.example.org/Ec/">';
        $xml .= '<SOAP-ENV:Body>';
        $xml .= "<{$root}>";
        $xml .= static::arrayToXmlInc($array);
        $xml .= "</{$root}>";
        $xml .= "</SOAP-ENV:Body>";
        $xml .= "</SOAP-ENV:Envelope>";
        return $xml;
    }

    public static function xmlToArray($xml)
    {
        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $xml);
        $xml = new \SimpleXMLElement($response);
        $body = (array)$xml->xpath('//response')[0];
        $array = json_decode($body[0], true);
        return $array;
    }
}