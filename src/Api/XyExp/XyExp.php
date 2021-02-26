<?php
/**
 *
 * User: blaine
 * Date: 2/25/21
 */

namespace smiler\logistics\Api\XyExp;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\CurlException;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\Exception\NotSupportException;
use smiler\logistics\LogisticsAbstract;

/**
 * Class Aprche
 * @package smiler\logistics\Api\Aprche
 * 兴源物流商
 */
class XyExp extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface
{
    public $iden = 'xyexp';

    public $iden_name = '兴源物流商';

    const ORDER_COUNT = 1;

    const QUERY_TRACK_COUNT = 1;
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'form';

    public $apiHeaders = [];

    public $interface = [
        'createOrder' => 'Insure_Waybill', // 【创建订单】

        'operationPackages' => '', //修改订单重量

        'deleteOrder' => '', //删除订单

        'getPackagesLabel' => 'Load_FaceSingle', // 【打印标签|面单】

        'getTrackNumber' => '',//获取跟踪号

        'queryTrack' => 'Load_Track_Info', //轨迹查询

        'getFeeByOrder' => '', //费用查询

        'getShippingMethod' => 'Load_Freight_Channels', //获取配送方式

        'getPackagesDetail' => '', //查询订单
    ];

    /**
     * @param array $config
     * @param  $ToKenCategory  int 1.全球交易助手 2.通途 3.店小蜜 4.ECPP 5.马帮 6.速脉ERP 7.客户API 8.莱卡尼 9.芒果店长 10.赛兔 11.掘宝动力 123.奥科 ERP
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['appKey', 'url', 'appSecret'], $config);
        $this->config = $config;
        $this->config['ToKenCategory'] = $config['ToKenCategory'] ?? 7;
    }

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
            if (count($item['productList']) > self::ORDER_COUNT_SKU) {
                throw new ManyProductException($this->iden_name . '每个订单一次最多支持 ' . self::ORDER_COUNT_SKU . "个SKU产品");
            }
            $weight = 0;
            foreach ($item['productList'] as $value) {
                $productList[] = [
                    'ProductName_EN' => $value['declareEnName'] ?? '',// Y:申报英文名称Length <= 50
                    'ProductName_CN' => $value['declareCnName'] ?? '',// Y:申报中文名称Length <= 50
                    'DeclareQuantity' => (int)($value['quantity'] ?? ''),// Y:产品数量;数值必须为正整数
                    'DeclarePrice' => (float)($value['declarePrice'] ?? ''), //Y:单价
                    'CustomsCode' => $value['htsCode'] ?? '',// N:物品的 HTS Code
                    'invoice_weight' => $value['declareWeight'] ?? '',// Y:总量;Length <= 50 KG
                    'CustomsNote' => '', //N:报关备注
                    'ProductInfo' => '', //N:商品信息
                    'ProductSKU' => $value['productSku'] ?? '',// Y:产品 SKU;Length <= 100,
                    'ProductURL' => '', //N:商品链接
                    'ProductPicURL' => '', //N:商品图片链接
                ];
                $weight += $value['declareWeight'] ?? 0;
            }

            $ls[] = [
                'CustomerWaybillNumber' => $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 50
                'ServiceNumber' => '', //N:服务商单号
                'ForecastWeight' => $weight, //Y:预报重量
                'ForecastLong' => (float)($item['packageLength'] ?? ''),// Y:包裹长度（单位：cm）
                'ForecastWide' => (float)($item['packageWidth'] ?? ''), //Y:预报宽
                'ForecastHigh' => (float)($item['packageHeight'] ?? ''), //Y:预报高
                //todo 调试写死
                'FreightWayId' => $item['shippingMethodCode'] ?? 'c3e20b64-1cff-4df1-8652-8defc835d48a',// Y:serviceCode: test => UBI.CN2FR.ASENDIA.FULLLY.TRACKED
                'ParcelCategoryCode' => '', //N:包裹类型
                'ParcelCategoryName' => '', //N:包裹类型名
                'CustomerTradeCode' => '', //N:客户平台订单 ID
                'CustomerTradeNote' => $item['remark'] ?? '', // N:客户平台订单备注
                'BuyerCode' => $item['buyer_id'] ?? '', //买家平台 ID
                'BuyerFullName' => $item['recipientName'] ?? '',// Y:收件人姓名Length <= 64 '',// Y:收件人姓名Length <= 64
                'BuyerCompany' => $item['recipientCompany'] ?? '', //N:买家公司
                'BuyerPhone' => $item['recipientPhone'] ?? '', //N:收件人手机
                'BuyerMobile' => $item['recipientPhone'] ?? '', //N:收件人手机
                'BuyerFax' => '', //N:买家传真
                'BuyerEmail' => $item['recipientEmail'] ?? '',// N:收件人邮箱Length <= 128
                'BuyerZipCode' => $item['recipientPostCode'] ?? '', //N:收件人邮编
                'BuyerCountryCode' => $item['recipientCountryCode'] ?? '',// Y:收件人国家二字代码，可用值参见 6.1。Lenth = 2
                'BuyerState' => $item['recipientState'] ?? '', //N:收件人省
                'BuyerCity' => $item['recipientCity'] ?? '', //N:收件人城市
                'BuyerCounty' => '', //N:买家区县
                'BuyerAddress' => $item['recipientStreet'] ?? '',// Y:收件人街道
                'BuyerAddress1' => $item['recipientStreet1'] ?? '',// Y:收件人街道
                'BuyerTaxNo' => $item['recipientTaxNumber'] ?? '',// N:收件人个人税号Length<=32
                'SellerCode' => '', //N:卖家平台 ID
                'SellerCompany' => $item['senderCompany'] ?? '', //N:寄件人公司名
                'SellerFullName' => $item['senderName'] ?? '', //N:发件人姓名
                'SellerPhone' => $item['senderPhone'] ?? '', //Y:发件人电话
                'SellerMobile' => $item['senderPhone'] ?? '',// Y:发件人电话Length <= 32,
                'SellerFax' => '', //N:寄件人传真
                'SellerEmail' => $item['senderEmail'] ?? '', //N:发件人邮箱
                'SellerZipCode' => $item['senderPostCode'] ?? '',// N:发件人邮编Length <= 32
                'SellerCountryCode' => $item['senderCountryCode'] ?? '', //N:寄件人国家代码
                'SellerState' => $item['senderState'] ?? '', // Y:发件人省
                'SellerCity' => $item['senderCity'] ?? '',// Y:发件人城市Length<=64
                'SellerCounty' => '', //N:寄件人区县
                'SellerAddress' => $item['senderFullAddress'] ?? '',// N:发件人完整地址Length <= 200
                'SellerAddress1' => '', //N:寄件人地址 1
                'InsureStatus' => '', //N:投保状态
                'FreightWayInsureId' => '', //N:渠道保险 ID
                'InsureValue' => '', //N:投保价值
                'CustomsInfoList' => $productList,// Y:一次最多支持 5 个产品信息（超过 5 个将会忽略）
            ];
        }
        $response = $this->request(__FUNCTION__, 'post', [
            'WaybillnfoXml' => static::arrayToXml(['WaybillInfo' => $ls[0]], 'InsertWaybillService')
        ]);

        if(!empty($response['WaybillInfoList']['WaybillInfo']['WaybillId'])){
            $response['trackingNumberInfo'] = [
                'trackingNumber' => $response['WaybillInfoList']['WaybillInfo']['ServiceNumber'],
                'platform_order_id' => $response['WaybillInfoList']['WaybillInfo']['CustomerWaybillNumber'],
                'logistics_order_id' => $response['WaybillInfoList']['WaybillInfo']['WaybillId']
            ];
        }

        return $response;
    }


    /**
     * @return mixed
     * 获取物流商运输方式
     * {"Result":"信息获取成功","FreightWayInfoList":{"FreightWayInfo":[{"FreightWayId":"6da63911-d913-449a-852f-71f9cbc2a5e9","FreightWayName_CN":" 9610报关","FreightWayName_EN":" 9610BG","FreightWay_Code":"236","AgreementAccount":["     "],"ReturnCompany":["      "],"ReturnAddress":["     "],"LogisticsCompanyName":"中国邮政","FreightWay_Insure_Info_List":[]}]}}
     */
    public function getShippingMethod()
    {

        $response = $this->request(__FUNCTION__, 'get');
        return $response;
    }

    /**
     * 修改订单重量
     * @param array $params
     * @return mixed|void
     */
    public function operationPackages($params)
    {
        throw new NotSupportException($this->iden_name . "暂不支持修改订单重量");
    }

    /**
     * 删除订单
     * @param string $order_code
     * @return mixed|void
     */
    public function deleteOrder(string $order_code)
    {
        throw new NotSupportException($this->iden_name . "暂不支持删除订单");
    }

    /**
     * 修改订单状态
     * @return mixed
     */
    public function updateOrderStatus(array $params)
    {
        throw new NotSupportException($this->iden_name . "暂不支持修改订单状态");
    }

    /**
     * 获取订单费用
     * @return mixed
     */
    public function getFeeByOrder(string $order_code)
    {
        throw new NotSupportException($this->iden_name . "暂不支持获取订单费用");
    }

    /**
     * 获取订单费用明细
     * @param $order_id
     * @return mixed
     */
    public function getFeeDetailByOrder($order_id)
    {
        throw new NotSupportException($this->iden_name . "暂不支持获取订单费用明细");
    }

    /**
     * 获取订单标签 todo 只能一个一个获取面单标签
     * {"version":"Load_Report 1.0","status":"success","code":[],"url":"http:\/\/api.xyexp.com\/File\/TempPdf\/2021\/2\/25\/474e11ede76a458faa1a1edad60ea2fa.pdf","description":["\n  "]}
     * @return mixed
     */
    public function getPackagesLabel($params)
    {
        $data = [
            'ReportInfoXml' => static::arrayToXml([
                'FaceSingleId' => $params['label_id'] ?? '', //N:面单编号(若有绑定默认面单可不填)
                'FreightWayId' => $params['shippingMethodCode'] ?? 'c3e20b64-1cff-4df1-8652-8defc835d48a',
                'ServiceNumberList' => [
                    'ServiceNumber' => $this->toArray($params['trackNumber'])
                ]
            ],'WaybillPrintService')
        ];
        $response = $this->request(__FUNCTION__,'post',$data);
        return $response;
    }



    /**
     * @param $order_id
     * @return mixed
     * 获取包裹详情
     */
    public function getPackagesDetail($order_id)
    {
        throw new NotSupportException($this->iden_name . "暂不支持获取包裹详情");
    }

    /**
     * @param $interface string 方法名
     * @param array $arr 请求参数
     * @return array
     */
    public function buildParams($interface, $arr = [])
    {
        return [
            'AppKey' => $this->config['appKey'],
            'AppSecret' => $this->config['appSecret'],
            'ToKenCategory' => $this->config['ToKenCategory'],
        ];
    }

    public function request($interface, $method = 'post', $data = [])
    {
        $commonParam = $this->buildParams($interface);
        if (!empty($data)) {
            $commonParam = array_merge($commonParam, $data);
        }
        $requestUrl = $this->config['url'] . $this->interface[$interface];
        $response = $this->sendCurl($method, $requestUrl, $commonParam, $this->dataType, $this->apiHeaders);
        return $response;
    }

    public static function parseResponse($curl, $dataType, $response)
    {
        switch ($curl->responseCode) {
            case 'timeout':
                throw new CurlException('curl:请求错误');
                break;
            case 200:
                $return = static::xmlToArray($response);
                break;
            case 401:
                throw new CurlException('curl: 授权失败');
                break;
            case 404:
            default:
                //404 Error logic here
                throw new CurlException('curl: 请求失败');
                break;
        }
        return $return;
    }
}