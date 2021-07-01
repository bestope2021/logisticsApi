<?php
/**
 *
 * User: Ghh.Guan
 * Date: 4/2/21
 */

namespace smiler\logistics\Api\BtdXms;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

class BtdXms extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface, TrackLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹,5自定义
     */
    const ORDER_COUNT = 5;
    /**
     * 一次最多查询多少个物流商
     */
    const QUERY_TRACK_COUNT = 1;
    public $iden = 'btdxms';
    public $iden_name = '宝通达物流';
    
    // 定义请求方式
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    // 定义API是否授权
    static $isApiAuth = false;

    //面单标签类型
    public $labelType = 'L10x10';

    //生成pdf标签地址
    public $pdfUrl = "http://open.btdair.com:8099/GenerateLabels.ashx";

    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'xml';

    public $apiHeaders = [
        'Content-Type' => 'text/xml; charset=utf-8',
        'SOAPAction' => 'http://tempuri.org/ILogisticsService/',
    ];

    public $interface = [
        'authApi' => 'Verify',// 验证API授权

        'createOrder' => 'CreateOrder', // 创建订单

        'getShippingMethod' => 'GetLogisticsWay', //获取配送方式

        'getTrackNumber' => 'GetLogisticsInfo',//获取追踪号

        'getPackagesLabel' => 'getPackagesLabel', // 【打印标签|面单

    ];

    /**
     * HuaHan constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['clientId', 'clientAccount', 'url'], $config);
        $this->config = $config;
        if (!empty($config['apiHeaders'])) {
            $this->apiHeaders = array_merge($this->apiHeaders, $config['apiHeaders']);
        }
    }

    public static function xmlToArray($xml)
    {
        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $xml);
        $xml = new \SimpleXMLElement($response);
        $array = json_decode(json_encode($xml), true)['sBody'];
        return $array;
    }

    /**
     * array转XML
     * @param $array
     * @param string $root
     * @return string
     */
    public static function arrayToXml($array, $root = 'ns1:callService', $encoding = 'utf-8')
    {
        $xml = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">';
        $xml .= '<s:Body>';

        switch ($root){
            case "GetLogisticsWay":
                $xml .= '<'.$root.' xmlns="http://tempuri.org/"/>';
                break;
            default:
                $xml .= '<'.$root.' xmlns="http://tempuri.org/">';
                $xml .= static::arrayToXmlInc($array);
                $xml .= '</'.$root.'>';
                break;
        }
        $xml .= "</s:Body>";
        $xml .= "</s:Envelope>";
        return $xml;
    }

    public static function arrayToXmlInc($array)
    {
        $xml = '';
        foreach ($array as $key => $val) {
            if(is_array($val)) {
                $orderXmlStr = 'xmlns:a="http://schemas.datacontract.org/2004/07/JSON.Logistics.Emmis.Entity" xmlns:i="http://www.w3.org/2001/XMLSchema-instance"';
                $xml .= ($key === 'order')?"<$key $orderXmlStr>": (is_numeric($key)?"<a:LogisticsOrderProduct>":"<$key>");
                $xml .= static::arrayToXmlInc($val);
                $xml .= is_numeric($key)?"</a:LogisticsOrderProduct>":"</$key>";
            }else{
                $xml .= "<$key>$val</$key>";
            }
        }
        return $xml;
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
            $order_info = [
                'a:City' => $item['recipientCity'] ?? '',//收件人城市
                'a:Country' => $item['recipientCountryCode'] ?? '',//收件人国家二字代码
                'a:Email' => $item['recipientEmail'] ?? '',//收件人邮箱
                'a:Id' => $item['customerOrderNo'] ?? '',//内单号
                'a:Mobile' => $item['recipientMobile'] ?? '',//手机号码
                'a:Name' => $item['recipientName'] ?? '',//收件人姓名
                'a:OrderId' => $item['customerOrderNo'] ?? '',//订单号
                'a:Phone' => $item['recipientPhone'] ?? '',//收件人姓名
                'a:Postcode' => $item['recipientPostCode'] ?? '',//收件人邮编
            ];
            $productList = [];
            foreach ($item['productList'] as $key => $value) {
                $productList[] = [
                    'a:GoodsCn' => $value['declareCnName'] ?? '', //报关品名（中文）
                    'a:GoodsEn' => $value['declareEnName'] ?? '', //报关品名（英文）
                    'a:HsCode' => $value['hsCode'] ?? '', //海关编码
                    'a:Name' => $value['declareCnName'] ?? '', //产品名称
                    'a:Qtd' => (int)($value['quantity'] ?? ''), //产品数量
                    'a:Remark' => $value['invoiceRemark'] ?? '', //产品备注
                    'a:Sku' => $value['productSku'] ?? '', //商家编码
                    'a:SkuPriperties' => $value['modelType'] ?? '', //产品属性
                    'a:TotalWeight' => (float)($value['declareWeight'] * $value['quantity'] ?? ''), //产品总重量单位Kg
                    'a:Unit' => $value['unit'] ?? '', //单位
                    'a:UnitPrice' => (float)($value['customsDeclarationPrice'] ?? ''), //单价（报关价值）
                ];
            }
            $address = ($item['recipientStreet'] ?? ' ') . ($item['recipientStreet1'] ?? ' '). ($item['recipientStreet2'] ?? '');
            $order_info['a:Products'] = $productList;//产品信息,多个产品信息建议不要超过5个
            $order_info['a:Province'] = $item['recipientState'] ?? '';//收件人省州
            $order_info['a:ShippingWay'] = $item['shippingMethodCode'] ?? 'FedEx';//渠道
            $order_info['a:Street'] = $address ?? '';//街道
            $order_info['a:TaxID'] = !empty($item['iossNumber']) ?$item['iossNumber']: $item['recipientTaxNumber'];// 欧盟税号（ioss税号）
            $order_info['a:TrackingNumber'] = '';//物流单号

            $ls[]['order'] = $order_info;
        }
        $response = $this->request(__FUNCTION__, $ls[0]);

        // 处理结果
        $reqRes = $this->getReqResData();

        $response = $this->resultsVerify($response,__FUNCTION__);

        $fieldData = [];
        $fieldMap = FieldMap::createOrder();
        // 结果
        $fieldData['flag'] = $response['flag'];
        $fieldData['info'] = $response['flag'] ? '' : ($response['info'] ?? '未知错误');
        $fieldData['orderNo'] = $response['flag'] ? $response['info']['aID'] : '';//客户订单号
        $fieldData['id'] = $response['flag'] ? $response['info']['aID'] : '';//客户内单号

        $fieldData['trackingNo'] = $response['flag'] ? $response['info']['aRefID'] : '';//追踪号
        $fieldData['frt_channel_hawbcode'] = $response['flag'] ? $response['info']['aTrackingNumber'] : '';//尾程追踪号

        //单号延迟返回时，重新获取
        if($response['flag'] && (empty($response['info']['aRefID']) || empty($response['info']['aTrackingNumber']))){
            $trackNumber = $this->getTrackNumber($response['info']['aID']);
            if($trackNumber['flag']){
                $fieldData['trackingNo'] = $trackNumber['info']['aRefID'] ?? '';//追踪号
                $fieldData['frt_channel_hawbcode'] = $trackNumber['info']['aTrackingNumber'] ?? '';//尾程追踪号
            }
        }

        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);
    }

    /**
     * 获取跟踪号
     * @param $processCode
     * @param $is_ret
     * @return array
     */
    public function getTrackNumber(string $processCode,$is_ret=false)
    {
        $param = ['number' => $processCode];

        $response = $this->request(__FUNCTION__, $param);

        $response = $this->resultsVerify($response,__FUNCTION__);

        if($is_ret){
            $data = [
                'trackingNumber' => $trackNumber['info']['aRefID'] ?? '',//追踪号
                'frtTrackingNumber' => $trackNumber['info']['aTrackingNumber'] ?? '',//尾程追踪号
            ];
            $this->retSuccessResponseData($data);
        }

        return $response;
    }

    public function request($function, $data = [] ,$method = self::METHOD_POST)
    {
        $data = $this->buildParams($function, $data);
        if (!self::$isApiAuth && $function != 'authApi') {
            $this->authApi();
        }
        
        $this->req_data = $data;
        $apiHeaders = $this->setApiHeaders($function);
        $url = $this->config['url'];
        if($function == 'getPackagesLabel'){
            $url = $this->pdfUrl;
            $apiHeaders['CURLOPT_HEADER'] = true;
        }
        $res = $this->sendCurl($method, $url, $data, $this->dataType, $apiHeaders, 'UTF-8', $this->interface[$function],false);
        $this->res_data = $res;
        return $res;
    }

    public function setApiHeaders($function){
        $apiHeaders = $this->apiHeaders;
        $apiHeaders['SOAPAction'] .= $this->interface[$function];
        return $apiHeaders;
    }

    /**
     * 获取验证API授权结果
     * @return $this
     */
    protected function authApi()
    {
        $data = $this->buildParams(__FUNCTION__, []);
        $apiHeaders = $this->setApiHeaders(__FUNCTION__);
        $res = $this->sendCurl('post', $this->config['url'], $data, $this->dataType, $apiHeaders, 'UTF-8', $this->interface[__FUNCTION__],false);
        $res = $this->resultsVerify($res,__FUNCTION__);
        if (!$res['flag'] || $res['info'] !== 'true') {
            self::$isApiAuth = false;
        } else {
            self::$isApiAuth = true;
        }
        if (!self::$isApiAuth) {
            throw new InvalidIArgumentException($this->iden_name . "(API授权失败)");
        }
    }

    /**
     * 验证返回结果
     * @param String $params
     * @return mixed
     */
    protected function resultsVerify($params,$function){
        $response = static::xmlToArray($params);

        $response_info = [];
        //错误信息
        if(isset($response['sFault'])){
            $response_info['flag'] = false;
            $response_info['info'] = $response['sFault']['faultstring'];
        }else{
            $response_info['flag'] = true;
            $response_info['info'] = $response[$this->interface[$function].'Response'][$this->interface[$function].'Result'];
        }

        return $response_info;
    }

    /**
     * 获取物流方式
     * @return mixed
     */
    public function getShippingMethod()
    {
        $response = $this->request(__FUNCTION__);
        $response = $this->resultsVerify($response,__FUNCTION__);
        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();
        if (!$response['flag']) {
            return $this->retErrorResponseData($response['info'] ?? '未知错误');
        }

        foreach ($response['info']['astring'] as $item) {
            $item_arr = [
                'code' =>$item,
                'name_en' =>$item,
                'name_cn' =>$item,
                'shipping_method_type' =>$item,
                'remark' =>$item,
            ];
            $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item_arr, $fieldMap);
        }
        return $this->retSuccessResponseData($fieldData);
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
     * 删除订单
     * @param string $order_code
     * @return mixed|void
     */
    public function deleteOrder(string $order_code)
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
        $this->throwNotSupport(__FUNCTION__);
    }

    /**
     * 获取订单费用明细
     * @param $order_id
     * @return mixed
     */
    public function getFeeDetailByOrder($order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    /**
     * @param $order_id
     * @return mixed
     * 获取包裹详情
     */
    public function getPackagesDetail($order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    /**
     * 获取订单标签
     * @return mixed
     */
    public function getPackagesLabel($params)
    {
        $data = [
            'type' => $this->labelType,
            'ids' => implode(',', $this->toArray($params['orderNo'])),
            'HasInvoice' => 1,
        ];

        $response = $this->request(__FUNCTION__, $data, self::METHOD_GET);
        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();
        $item = [];

        $item['flag'] = true;
        $item['order_no'] = $params['orderNo'];
        $item['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF;
        $item['lable_file'] = base64_encode($response);

        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
        return $this->retSuccessResponseData($fieldData);
    }


    /**
     * 获取物流商轨迹
     * @return mixed
     */
    public function queryTrack($trackNumber)
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    /**
     * 获取请求数据
     */
    public function buildParams($interface, $arr = [])
    {
        return $this->interface[$interface]=='GetLogisticsWay'?$arr:array_merge(['clientId' => $this->config['clientId'],'clientAccount' => $this->config['clientAccount']], $arr);
    }

    /**
     * 字符串转换为数组
     * @param $string
     * @return array
     */
    public function toArray($string)
    {
        $arr = [];
        if (is_string($string) && strpos(',', $string) === false) {
            return [$string];
        }

        if (is_string($string) && strpos(',', $string) !== false) {
            return explode(',', $string);
        }
        if (is_array($string)) {
            return $string;
        }
        return $arr;
    }
}