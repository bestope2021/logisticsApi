<?php
/**
 *
 * User: Ghh.Guan
 * Date: 4/13/21
 */

namespace smiler\logistics\Api\Kjyt;


use smiler\logistics\Api\Kjyt\FieldMap;
use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\CurlException;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\Exception\NotSupportException;
use smiler\logistics\Exception\ResponseException;
use smiler\logistics\LogisticsAbstract;

/**
 * Class Kjyt 跨境易通
 * @link http://www.sz56t.com:8090/pages/viewpage.action?pageId=3473454
 * @package smiler\logistics\Api\Kjyt
 */
class Kjyt extends LogisticsAbstract implements BaseLogisticsInterface, TrackLogisticsInterface
{
    public $iden = 'kjyt';

    public $iden_name = '跨境易通';

    /**
     * 一次最多创建多少个包裹
     */
    const ORDER_COUNT = 1;

    /**
     * 一次最多查询多少个物流轨迹
     */
    const QUERY_TRACK_COUNT = 50;

    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'form';

    public $apiHeaders = [];

    public $interface = [
        'getAuth' => 'selectAuth.htm', // 身份认证

        'createOrder' => 'createOrderBatchApi.htm', // 【创建订单】

        'queryTrack' => 'selectTrack.htm', //轨迹查询

        'operationPackages' => 'updateOrderWeightByApi.htm', //修改订单重量

        'getShippingMethod' => 'getProductList.htm', //获取配送方式

        'getTrackNumber' => 'getOrderTrackingNumber.htm', //获取跟踪号

    ];

    /**
     * Kjyt constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['username', 'url', 'password'], $config);
        $this->config = $config;
    }

    /**
     * 获取创建订单时需要的两个必传参数
     */
    public function getAuth()
    {
        if (!empty($this->config['customer_id']) && !empty($this->config['customer_userid'])) {
            return;
        }

        $data = [
            'username' => $this->config['username'],
            'password' => $this->config['password'],
        ];

        $response = $this->request(__FUNCTION__, $data, false);
        if (empty($response)) {
            throw new CurlException($this->iden_name . __FUNCTION__ . '请求异常');
        }
        $response = json_decode(str_replace("'", "\"", $response), true);
        if ($response['ack'] != "true") {
            throw new ResponseException($this->iden_name . __FUNCTION__ . "响应异常");
        }
        $this->config['customer_id'] = $response['customer_id'];
        $this->config['customer_userid'] = $response['customer_userid'];
    }


    /**
     * 创建订单
     * @param array $params
     * @return mixed
     * @throws InvalidIArgumentException
     * @throws ManyProductException
     * {"ask":"Success","message":"Success","reference_no":"T1020210221154454829981280","shipping_method_no":"HHWMS1052000004YQ","order_code":"HHWMS1052000004YQ","track_status":"3","sender_info_status":"0","ODA":"","agent_number":"","time_cost(s)":"0.484375"}
     * todo $trade_type  SUMAI 速脉ERP  QQZS 全球助手 WDJL 网店精灵 IEBAY365 IEBAY365 STOMS 赛兔OMS TTERP 通途ERP MGDZ 芒果店长 LRERP 懒人erp SUMOOL 速猫ERP GLBPAY 上海九赢 DXM 店小秘 ZYXT 客户自用系统/其他不在列表中的均使用该代码
     */
    public function createOrder(array $params = [])
    {
        $this->getAuth();
        if (empty($params)) {
            throw new InvalidIArgumentException($this->iden_name . " 创建订单参数不能为空");
        }

        $ls = [];

        if (count($params) > self::ORDER_COUNT) {
            throw new ManyProductException($this->iden_name . "一次最多支持提交" . self::ORDER_COUNT . "个包裹");
        }
        foreach ($params as $item) {
            $productList = [];
            $invoiceValue = $weight = 0;
            foreach ($item['productList'] as $value) {
                $productList[] = [
                    'invoice_title' => $value['declareEnName'] ?? '',// Y:申报英文名称Length <= 50
                    'sku' => $value['declareCnName'] ?? '',// N:中文品名
                    'invoice_weight' => $value['declareWeight'] ?? '',// Y:总量;Length <= 50 KG
                    'invoice_pcs' => (int)($value['quantity'] ?? ''),// Y:产品数量;数值必须为正整数
                    'invoice_amount' => (float)($value['declarePrice'] ?? '') * (int)($value['quantity'] ?? ''), //Y:申报总价值，必填
                    'hs_code' => $value['hsCode'] ?? '',// N:海关编码
                    'sku_code' => $value['productSku'] ?? '',// Y:产品 SKU;Length <= 100
                    "item_id" => '',
                    "item_transactionid" => '',
                    "transaction_url" => $value['productUrl'] ?? '', //销售地址
                    "invoiceunit_code" => "", //申报单位
                    "invoice_imgurl" => "", //图片地址
                    "invoice_brand" => $value['brand'] ?? '', //品牌
                    "invoice_rule" => "", //规格
                    "invoice_currency" => $value['currencyCode'] ?? 'USD', //申报币种
                    "invoice_taxno" => "", //税则号
                    "origin_country" => $value['originCountry'] ?? '', //原产国
                    "invoice_material" => $value['productMaterial'] ?? '', //材质
                    "invoice_purpose" => $value['productPurpose'] ?? '', //用途
                ];
                $invoiceValue += (float)($value['declarePrice'] ?? '') * (int)($value['quantity'] ?? '');
                $weight += $value['declareWeight'];
            }
            $address = ($item['recipientStreet'] ?? ' ') .'   '. ($item['recipientStreet1'] ?? ' ')  .'   '. (empty($item['recipientStreet2']) ? '' : $item['recipientStreet2']);
            $ls[] = [
                "buyerid" => $item['buyer_id'] ?? '',
                'order_piece' => 1, //件数，小包默认1，快递需真实填写
                'consignee_mobile' => $item['recipientPhone'] ?? '', //N:收件人手机
                'order_returnsign' > 'N', //退回标志，默认N表示不退回，Y标表示退回。中邮可以忽略该属性
                'trade_type' => 'ZYXT', //SUMAI 速脉ERP  QQZS 全球助手 WDJL 网店精灵 IEBAY365 IEBAY365 STOMS 赛兔OMS TTERP 通途ERP MGDZ 芒果店长 LRERP 懒人erp SUMOOL 速猫ERP GLBPAY 上海九赢 DXM 店小秘 ZYXT 客户自用系统/其他不在列表中的均使用该代码
                'consignee_name' => $item['recipientName'] ?? '',// Y:收件人姓名Length <= 64 '',// Y:收件人姓名Length <= 64
                'consignee_companyname' => $item['recipientCompany'] ?? '', //N:收件人公司名
                'consignee_address' => $address ?? '',// Y:收件人街道
                'consignee_telephone' => $item['recipientPhone'] ?? '', //N:收件人电话
                'country' => $item['recipientCountryCode'] ?? '',// Y:收件人国家二字代码，可用值参见 6.1。Lenth = 2
                'consignee_state' => $item['recipientState'] ?? '', //N:收件人省
                'consignee_city' => $item['recipientCity'] ?? '', //N:收件人城市
                "consignee_suburb" => '', //N:收件区
                'consignee_postcode' => $item['recipientPostCode'] ?? '', //N:收件人邮编
                "consignee_passportno" => $item['recipientIdentityNumber'] ?? '', //收件护照号，选填
                'consignee_email' => $item['recipientEmail'] ?? '',// N:收件人邮箱Length <= 128
                'consignee_taxno' => $item['recipientTaxNumber'] ?? '', //税号
                'consignee_doorno' => '', //门牌号
                'shipper_taxnotype' => (isset($item['iossNumber']) && !empty($item['iossNumber'])) ? 'IOSS' : 'OTHER', //税号类型，可选值IOSS,NO-IOSS,OTHER
                'shipper_taxno' => $item['iossNumber'] ?? '',// 欧盟税号（ioss税号）
                'customer_id' => $this->config['customer_id'],
                'customer_userid' => $this->config['customer_userid'],
                'order_customerinvoicecode' => $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。
                //todo 调试写死
                'product_id' => $item['shippingMethodCode'] ?? '2721', // 运输方式ID，必填
                'weight' => (float)($item['predictionWeight'] ?? ''),// Y:订单重量，单位KG，默认为0.2,
                'product_imagepath' => '', //N:图片地址，多图片地址用分号隔开
                'shipper_name' => $item['senderName'] ?? '', //Y:发件人姓名
                'shipper_companyname' => $item['senderCompany'] ?? '', // N:发件人公司名
                'shipper_address1' => $item['senderFullAddress'] ?? '',// N:发件人完整地址Length <= 200
                'shipper_address2' => '',
                'shipper_address3' => '',
                'shipper_city' => $item['senderCity'] ?? '',// N:发件人城市Length<=64
                'shipper_state' => $item['senderState'] ?? '', // N:发件人省
                'shipper_postcode' => $item['senderPostCode'] ?? '',// N:发件人邮编Length <= 32
                'shipper_country' => $item['senderCountryCode'] ?? '', // Y:发件人国家二字码
                'shipper_telephone' => $item['senderPhone'] ?? '', //Y:发件人电话
//                'order_transactionurl' => '', //N:产品销售地址
//                'order_codamount' => 0, //N:代收金额
//                'order_codcurrency' => $item['packageCodCurrencyCode'] ?? 'USD',//币种，标准三字代码
//                'order_cargoamount' => $invoiceValue, //订单实际金额，用于白关申报
//                'order_insurance' => '', //保险金额
                'cargo_type' => 'P', //包裹类型，P代表包裹，D代表文件，B代表PAK袋
                "order_customnote" => $item['remark'] ?? '', //N:自定义信息
                'orderInvoiceParam' => $productList,// Y:一次最多支持 5 个产品信息（超过 5 个将会忽略）
            ];
        }
        $response = $this->request(__FUNCTION__, ['param' => json_encode($ls, JSON_UNESCAPED_UNICODE)]);

        //请求创建单号失败
        if ($response['resultCode'] != 'true') {
            return $this->retErrorResponseData(urldecode($response['message']));
        }
        $data = [];
        $reqRes = $this->getReqResData();
        $fieldMap = FieldMap::createOrder();
        if ($response['data']) {
            foreach ($response['data'] as $keyVale => $value) {
                $fieldData = [
                    'channel_hawbcode' => $value['tracking_number'] ?? '',
                    'refrence_no' => $value['reference_number'] ?? '',
                    'shipping_method_no' => $value['order_id'] ?? '',
                    'flag' => $value['ack'] == 'true' ? true : false,
                    'info' => urldecode($value['message']),
                ];
                $data = array_merge($reqRes, LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap));

            }
        }
        return $this->retSuccessResponseData($data);

    }

    /**
     * 获取跟踪号
     */
    public function getTrackNumber(string $order_id)
    {
        $data = [
            'order_id' => $order_id
        ];
        $response = $this->request(__FUNCTION__, $data, false);
        $response = json_decode($response, true);
        if (empty($response) || $response['status'] == 'false') {
            return $this->retErrorResponseData($response['msg'] ?? '');
        }
        return $this->retSuccessResponseData($response);
    }

    /**
     * 获取物流商运输方式
     * @return mixed
     * [{"express_type":"EYB","product_doornorule":"","product_id":"1881","product_shortname":"东莞E邮宝","product_tracknoapitype":""},{"express_type":"EMS","product_doornorule":"","product_id":"1921","product_shortname":"国际EMS","product_tracknoapitype":""},{"express_type":"XBPY","product_doornorule":"","product_id":"1941","product_shortname":"东莞平邮小包","product_tracknoapitype":""}]
     */
    public function getShippingMethod()
    {
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();
        $res = $this->request(__FUNCTION__, [], false);
        if (empty($res)) {
            $this->retErrorResponseData();
        }
        $res = iconv('GBK', 'utf-8', $res);
        $res = json_decode($res, true);
        foreach ($res as $item) {
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
        $this->getAuth();
        $ls = [
            'orderNo' => $pars['order_id'] ?? '',// Y:客户单号（或者系统订单号，或者服务商单号都可以）
            'weight' => empty($pars['weight']) ? 0 : round($pars['weight'], 3),// N:包裹总重量（单位：kg）,系统接收后自动四舍五入至 3 位小数
            'customerId' => $this->config['customer_id'],
        ];


        if (empty($ls)) {
            throw new InvalidIArgumentException("请求参数不能为空");
        }
        $response = $this->request(__FUNCTION__, $ls, false);

        if (!empty($response)) {
            return $this->retSuccessResponseData($response);
        } else {
            return $this->retErrorResponseData('修改订单重量异常');
        }
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
     * 获取物流商轨迹
     * @return mixed
     */
    public function queryTrack($trackNumber)
    {
        $data = [
            'documentCode' => implode(',', $this->toArray($trackNumber))
        ];
        $response = $this->request(__FUNCTION__, $data, false);
        $response = json_decode($response, true);
        if (empty($response) || $response[0]['ack'] == 'false') {
            return $this->retErrorResponseData($response[0]['message'] ?? '');
        }
        $response = $response[0]['data'] ?? [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);
        foreach ($response as $item) {
            $tmpArr = [
                'flag' => $item ? true : false,
                'info' => '',
                'trackingNumber' => $item['trackingNumber'],
                'trackContent' => $item['trackContent']
            ];

            $ls = [];
            foreach ($item['trackDetails'] as $key => $detail) {
                $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($detail, $fieldMap2);
            }
            $tmpArr['details'] = $ls;
            $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($tmpArr, $fieldMap1);
        }
        return $this->retSuccessResponseData($fieldData);
    }

    public function getPackagesDetail($order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    /**统一请求处理
     * @param string $function
     * @param array $data
     * @param bool $parseResponse
     * @return mixed
     */
    public function request($function, $data = [], $parseResponse = true)
    {
        $requestUrl = $this->config['url'] . $this->interface[$function];
        $this->req_data = $data;
        switch ($function) {
            case 'operationPackages':
                $res = $this->sendCurl('get', $requestUrl . '?customerId=' . $data['customerId'] . '&orderNo=' . $data['orderNo'] . '&weight=' . $data['weight'], [], $this->dataType, $this->apiHeaders, 'utf-8', 'xml', $parseResponse);
                break;//更新重量
            case 'getTrackNumber':
                $res = $this->sendCurl('get', $requestUrl . '?order_id=' . $data['order_id'], [], $this->dataType, $this->apiHeaders, 'utf-8', 'xml', $parseResponse);
                break;//获取追踪号
            case 'queryTrack':
                $res = $this->sendCurl('get', $requestUrl . '?documentCode=' . $data['documentCode'], [], $this->dataType, $this->apiHeaders, 'utf-8', 'xml', $parseResponse);
                break;//获取轨迹
            default:
                $res = $this->sendCurl('post', $requestUrl, $data, $this->dataType, $this->apiHeaders, 'utf-8', 'xml', $parseResponse);
                break;
        }
        $this->res_data = $res;
        return $res;
    }

    /**
     * 获取请求数据
     */
    public function buildParams($interface, $arr = [])
    {

    }

}