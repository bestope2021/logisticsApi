<?php
/**
 * Created by PhpStorm
 * User: Administrator:smiler
 * Date: 2021/3/12 11:52
 * Email: <2166909366@qq.com>
 */

namespace smiler\logistics\Api\WanbExpress;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

/**
 * Class WanbExpress
 * @package smiler\logistics\Api\WanbExpress
 * @link http://apidoc.wanbexpress.com/
 */
class WanbExpress extends LogisticsAbstract implements BaseLogisticsInterface, TrackLogisticsInterface, PackageLabelLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹
     */
    const ORDER_COUNT = 1;
    /**
     * 一次最多查询多少个跟踪号
     */
    const QUERY_TRACK_COUNT = 1;
    // 定义请求方式
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';

    // 定义API是否授权
    static $isApiAuth = true;

    // 定义标识
    public $iden = 'WanbExpress';
    public $iden_name = '万邦';

    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'json';

    public $apiHeaders = [
        'Content-Type' => 'application/json; charset=UTF-8',
        'Accept' => 'application/json',
    ];

    public $interface = [
        'authApi' => 'api/whoami',// GET:验证API授权
        'createOrder' => 'api/parcels', // POST:创建订单
        'getPackagesLabel' => 'api/parcels/%s/label', // GET:打印标签|面单 => %s:{processCode}
        'getTrackNumber' => 'api/parcels/%s',// GET:获取跟踪号 => %s:{processCode}
        'queryTrack' => 'api/trackPoints?trackingNumber=%s', // GET:轨迹查询 => %s:{trackingNumber}
        'operationPackages' => 'api/parcels/%s/customerWeight', // PUT:修改包裹预报重量 => %s:{processCode}
        'getShippingMethod' => 'api/services',// GET:获取产品服务信息
        'searchOrder' => 'api/parcels?referenceId=%s',// 搜索包裹 => %s:{referenceId}客户订单号
        'deleteOrder' => 'api/parcels/%s',// 删除订单 => %s:{processCode}
    ];

    /**
     * HuaHan constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['url', 'accountNo', 'token'], $config);
        $this->config = $config;
        // 设置请求头
        $this->apiHeaders['Authorization'] = $this->getAuthorization($this->config);
    }

    /**
     *  获取鉴权参数
     * @param array $config 配置
     * @param string $str Hc-OweDeveloper AccountNo;Token;Nounce
     * @return string
     */
    protected function getAuthorization($config = [], $Authorization = 'Hc-OweDeveloper %s;%s;%s')
    {
        return sprintf($Authorization, $config['accountNo'] ?? '', $config['token'] ?? '', $this->getNounce());
    }


    /**
     * 生成随机数
     * 一个随机数，只能为字母、数字、短横线(-)或者下划线(_)等
     * @return string
     */
    protected function getNounce()
    {
        return md5(microtime(true) . mt_rand(0, 999999));
    }

    /**
     * 创建订单，生成跟踪号
     * @param array $params
     * @return mixed
     * @throws InvalidIArgumentException
     * @throws ManyProductException
     *
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

        $syOrderNo = [];

        foreach ($params as $item) {

            // 处理号
            $syOrderNo[$item['customerOrderNo'] ?? ''] = $item['syOrderNo'] ?? '';

            $productList = [];
            $totalValueCode = 'USD';
            $totalValueValue = 0;
            foreach ($item['productList'] as $value) {
                $quantity = (int)($value['quantity'] ?? 1);
                $declarePrice = (float)($value['declarePrice'] ?? 0);
                $currencyCode = $value['currencyCode'] ?? $totalValueCode;
                $productList[] = [
                    'GoodsId' => $value['productSku'] ?? '',// N:产品 SKU;Length <= 100
                    'GoodsTitle' => $value['declareCnName'] ?? '',// Y:货物描述
                    'DeclaredNameEn' => $value['declareEnName'] ?? '',// Y:申报英文名称Length <= 50
                    'DeclaredNameCn' => $value['declareCnName'] ?? '',// N:申报中文名称Length <= 50
                    'Quantity' => (int)($value['quantity'] ?? ''),// Y:件数
                    'WeightInKg' => $value['declareWeight'] ?? '',// Y:单件重量(KG)
                    'DeclaredValue' => [
                        'Code' => $currencyCode,// Y:货币类型	USD, GBP, CNY
                        'Value' => $declarePrice, // Y:单价金额
                    ],
                    'HSCode' => $value['hsCode'] ?? '',// N:海关编码
                    'CaseCode' => $value['invoiceRemark'] ?? '', // N:配货信息
                    'SalesUrl' => $value['productUrl'] ?? '',// N:销售地址
                    'IsSensitive' => false, // N:是否为敏感货物/带电/带磁等
                    'Brand' => $value['brand'] ?? '', // N:品牌
                    'Model' => $value['modelType'] ?? '', // N:型号
                    'MaterialCn' => $value['productMaterial'] ?? '', // N:材质（中文）
                    'MaterialEn' => '', // N:材质（英文）
                    'UsageCn' => $value['productPurpose'] ?? '', // N:用途（中文）
                    'UsageEn' => '', // N:用途（英文）
                ];
                $totalValueCode = $currencyCode;
                $totalValueValue += ($quantity * $declarePrice);
            }
            $ls[] = [
                'ReferenceId' => $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 50
                'SellingPlatformOrder' => null,// N:销售平台订单信息 SellingPlatform => 销售平台官网首页链接,OrderId => 销售平台订单号
                'ShippingMethod' => $item['shippingMethodCode'] ?? 'REGPOST',// Y:发货产品服务代码
                'TrackingNumber' => $item['trackingNumber'] ?? '',// N:预分配挂号
                'WeightInKg' => (float)($item['predictionWeight'] ?? ''),// Y:包裹总重量（单位：kg）
                'TotalValue' => [// 包裹申报金额(20210413); 包裹总金额
                    'Code' => $totalValueCode,// Y:货币类型
                    'Value' => (float)$totalValueValue,// Y:金额
                ],
                'TotalVolume' => [// 包裹尺寸
                    'Length' => (float)($item['packageLength'] ?? ''),// N:长（单位：cm）
                    'Width' => (float)($item['packageWidth'] ?? ''),// N:宽（单位：cm）
                    'Height' => (float)($item['packageHeight'] ?? ''),// N:高（单位：cm）
                    'Unit' => 'CM',// Y:计量单位 CM=厘米, M=米
                ],
                'Notes' => $item['remark'] ?? '', //N:包裹备注
                'WithBatteryType' => 'NOBattery', //Y:包裹带电类型：NOBattery 不带电；WithBattery 带电；Battery 纯电池 todo: 暂时默认
                'BatchNo' => '',// N:批次号或邮袋号
                'WarehouseCode' => $item['LsWarehouseCode'] ?? 'SZ',// Y:交货仓库代码，请参考查询仓库接口
                'ItemType' => 'SPX',// Y:包裹类型: DOC 文件;SPX 包裹
                'TradeType' => 'B2C',// N:订单交易类型(B2B, B2C)，默认为 B2C
                'AutoConfirm' => true,// N:自动确认交运包裹，或此值为true，则无须再调用确认交运包裹接口
                'ShipperInfo' => [// 发件人信息
                    'Name' => $item['senderName'] ?? '', //Y:发件人姓名
                    'CountryCode' => $item['senderCountryCode'] ?? '', // Y:发件人国家二字码
                    'Province' => $item['senderState'] ?? '', // Y:发件人省
                    'City' => $item['senderCity'] ?? '',// Y:发件人城市Length<=64
                    'Address' => $item['senderFullAddress'] ?? '',// N:发件人完整地址Length <= 200
                    'Postcode' => $item['senderPostCode'] ?? '',// N:发件人邮编Length <= 32
                    'ContactInfo' => $item['senderPhone'] ?? ($item['senderPhone'] ?? ($item['senderEmail'] ?? '')), // N:联系信息(邮箱 或者 电话)
                    'Taxations' => [[
                        'TaxType' => $item['iossNumber'] ?'IOSS':'VatNo',//IOSS: 欧盟2021税改后，进入欧盟货物需要提供 IOSS
                        'Number' => !empty($item['iossNumber']) ?$item['iossNumber']: $item['recipientTaxNumber'],// 欧盟税号（ioss税号）
                    ]]
                ],
                'ShippingAddress' => [// 发件人信息
                    'Contacter' => $item['recipientName'] ?? '',// Y:收件人姓名
                    'Company' => $item['recipientCompany'] ?? '', //N:收件人公司名
                    'Country' => $item['recipientCountry'] ?? '', //N:收件人国家
                    'CountryCode' => $item['recipientCountryCode'] ?? '', //Y:收件人国家二字代码
                    'Province' => $item['recipientState'] ?? '', //Y:收件人州/省
                    'City' => $item['recipientCity'] ?? '', //Y:收件人城市
                    'Street1' => $item['recipientStreet'] ?? '',// Y:收件人街道
                    'Street2' => $item['recipientStreet1'] ?? '',// Y:收件人街道
                    'Street3' => $item['recipientStreet2'] ?? '',// Y:收件人街道
                    'Postcode' => $item['recipientPostCode'] ?? '', //Y:收件人邮编
                    'Tel' => $item['recipientPhone'] ?? ($item['recipientMobile'] ?? ''), //N:收件人联系电话
                    'Email' => $item['recipientEmail'] ?? '',// N:收件人邮箱Length <= 128
                    'TaxId' => $item['recipientTaxNumber'] ?? '',  //N:税号
                ],

                'ItemDetails' => $productList,// Y:包裹件内明细
            ];
        }

        $pars = $ls[0] ?? [];
        $response = $this->request(__FUNCTION__, $pars);

        $reqRes = $this->getReqResData();
//        $this->dd($response);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::createOrder();

        // 结果
        $data = $response['Data'] ?? [];
        $flag = $response['Succeeded'] == true;
        $info = '';
        $errorCode = $response['Error']['Code'] ?? '0x000';

        // 重复订单号
        if($errorCode == '0x100005' || $errorCode == 0x100005){
            // 根据客户订单号查询处理号
            $detail = $this->searchOrder($pars['ReferenceId'], false);
            // 存在
            if($detail){
                $shCode = $detail['ShippingMethod']['Code'] ?? '';
                if($pars['ShippingMethod'] == $shCode){
                    $flag = true;
                    $info = '';
                    $data = [
                        'ProcessCode' => $detail['ProcessCode'] ?? '',
                        'IndexNumber' => $detail['IndexNumber'] ?? '',
                        'ReferenceId' => $detail['ReferenceId'] ?? '',
                        'TrackingNumber' => $detail['FinalTrackingNumber'] ?? '',
                        'IsVirtualTrackingNumber' => true,
                        'IsRemoteArea' => false,
                        'Status' => 'Confirmed',
                    ];
                }else{
                    // 进行删除操作
                    $delFlag = $this->deleteOrder($detail['ProcessCode']);
                    if($delFlag){
                        $response = $this->request(__FUNCTION__, $pars);
                        // 结果
                        $data = $response['Data'] ?? [];
                        $flag = $response['Succeeded'] == true;
                    }
                }
            }
        }

        if (!$flag) {
            $info = ($errorCode ?? '0x000') . '.' . ($response['Error']['Message'] ?? '未知错误');
        }

        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $info;

        $fieldData['ProcessCode'] = $data['ProcessCode'] ?? '';// 包裹处理号：第三方单号
        $fieldData['ReferenceId'] = $data['ReferenceId'] ?? '';// 客户订单号
        $fieldData['IndexNumber'] = $data['IndexNumber'] ?? '';// 检索号
        // 注意： 部分渠道在创建包裹时不一定能够立即返回跟踪号，您需要调用获取包裹接口来查询包裹信息并试图获得跟踪号
        $fieldData['TrackingNumber'] = $data['TrackingNumber'] ?? '';// 跟踪号
        // 如果为 true，请先打单发货，待我司操作之后才会分配最终的派送单号。您需要视平台标记发货方式而定，有选择性地调用 获取包裹 接口来查询包裹真实派送单号。一般只有美国USPS渠道才会出现此情况。如果为false，可忽略。
        $fieldData['IsVirtualTrackingNumber'] = $data['IsVirtualTrackingNumber'] ?? false;// 是否为虚拟跟踪号

        // 重新获取追踪号
        if ($flag && empty($fieldData['TrackingNumber'])) {
            $fieldData['TrackingNumber'] = $this->getTrackNumber($fieldData['ProcessCode']);
        }

        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);

        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);

    }

    /**
     * 发起请求
     * @param string $function 方法
     * @param array $data 数据
     * @param string $method 请求方式
     * @param array $extUrlParams URL替换参数
     * @return mixed
     */
    public function request($function, $data = [], $method = self::METHOD_POST, $extUrlParams = [], $parseResponse = true, $encoding = 'utf-8', $root = 'xml')
    {
        $data = $this->buildParams($function, $data);
//        $this->req_data = $data;
        $_this = $this;
        if ($function != 'authApi') {
            $this->req_data = $data;
            $_this = $this->authApi();
        }
        $url = $this->config['url'] . $this->interface[$function];
        !empty($extUrlParams) && $url = vsprintf($url, $extUrlParams);
//        $this->dd($url);
//        var_dump($url);
//        var_dump($extUrlParams);
        $response = $_this->sendCurl($method, $url, $data, $this->dataType, $this->apiHeaders, $encoding, $root, $parseResponse);
//        var_dump($response);
        $this->res_data = $response;
        return $response;
    }

    /**
     * @param $interface string 方法名
     * @param array $arr 请求参数
     * @return array
     */
    public function buildParams($interface, $arr = [])
    {
        return $arr;
    }

    /**
     * 获取验证API授权结果
     * @return $this
     */
    protected function authApi()
    {
        $res = $this->request(__FUNCTION__, [], self::METHOD_GET);
        if ($res['Succeeded'] !== true) {
            self::$isApiAuth = false;
        } else {
            self::$isApiAuth = true;
        }

        if (!self::$isApiAuth) {
            throw new InvalidIArgumentException($this->iden_name . "(API授权失败)");
        }
        return $this;
    }

    /**
     * 获取跟踪号
     * @param $reference_no
     * @param bool $isOnly 是否仅返回追踪号
     * @return array|mixed
     */
    public function getTrackNumber(string $processCode, $isOnly = true)
    {
        if(empty($processCode)){
            return '';
        }

        $extUrlParams = [$processCode];

        $response = $this->request(__FUNCTION__, [], self::METHOD_GET, $extUrlParams);

        // 结果
        $flag = $response['Succeeded'] == true;

        if(!$flag){
            return '';
        }

        if($isOnly){
            $ret = $response['Data']['FinalTrackingNumber'] ?? '';
        }else{
            $ret = $response['Data'];
        }

        return $ret;
    }

    /**
     * 查询订单
     * @param string $referenceId
     * @param bool $isOnly
     * @return array|bool|mixed|string
     */
    public function searchOrder(string $referenceId = '', $isOnly = true){
        if(empty($referenceId)){
            return false;
        }

        $extUrlParams = [$referenceId];

        $response = $this->request(__FUNCTION__, [], self::METHOD_GET, $extUrlParams);


        // 结果
        $flag = $response['Succeeded'] == true;

        if(!$flag){
            return [];
        }

        $data = $response['Data'] ?? [];

        if(!$flag){
            return [];
        }

        // 结果
        $flag = $data['TotalItemCount'] == 1;

        if(!$flag){
            return [];
        }

        $Elements = $data['Elements'][0] ?? [];
        if($isOnly){
            $ret = $Elements['ProcessCode'] ?? '';
        }else{
            $ret = $Elements;
        }

        return $ret;
    }

    /**
     * 获取物流商运输方式
     * @return mixed
     *
     */
    public function getShippingMethod()
    {
        $response = $this->request(__FUNCTION__, [], self::METHOD_GET);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();
        // 结果
        if ($response['Succeeded'] != true) {
            return $this->retErrorResponseData($response['Error']['Error'] ?? '0x000' . '.' . $response['Error']['Message'] ?? '未知错误');
        }
        foreach ($response['Data']['ShippingMethods'] as $item) {
            $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
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
        $extUrlParams = [$params['ProcessCode']];
        $data = [
            'WeightInKg' => round($params['weight'],3),//预报重量(单位:KG),保留3位小数
            'AutoConfirm' => true,//是否在修改完预报重量后自动确认交运，默认为 false此值设置为true，则无须再次调用确认交运包裹接口
        ];

        $response = $this->request(__FUNCTION__, $data, self::METHOD_PUT, $extUrlParams);
        if (empty($response)) {
            return $this->retErrorResponseData('修改订单重量异常');
        }
        // 结果
        if ($response['Succeeded'] != true) {
            return $this->retErrorResponseData($response['Error']['Error'] ?? '0x000' . '.' . $response['Error']['Message'] ?? '未知错误');
        }
        return $this->retSuccessResponseData([]);
    }

    /**
     * 删除订单
     * @param string $processCode
     * @return mixed|void
     */
    public function deleteOrder(string $processCode)
    {

        if(empty($processCode)){
            return false;
        }

        $extUrlParams = [$processCode];

        $response = $this->request(__FUNCTION__, [], self::METHOD_DELETE, $extUrlParams);

        // 结果
        $flag = $response['Succeeded'] == true;

        return $flag;
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
     * 获取订单标签
     * @return mixed
     * {"code":0,"info":"success","data":[{"flag":"","tipMsg":"","orderNo":"","labelPathType":"pdf","labelPath":"http://szdbf.rtb56.com/api-lable/pdf/20210305/aad7b262-e3c0-49db-872b-adeb1431b633.pdf","labelPathPlat":"","labelType":"1"}]}
     */
    public function getPackagesLabel($params)
    {
        $extUrlParams = [$params['ProcessCode']];

        $response = $this->request(__FUNCTION__, [], self::METHOD_GET, $extUrlParams, false);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        if (empty($response)) {
            return $this->retErrorResponseData('标签暂未生成');
        }
        $item = [];

        $item['flag'] = true;
        $item['order_no'] = $params['ProcessCode'];
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
        $extUrlParams = [$trackNumber];

        $response = $this->request(__FUNCTION__, [], self::METHOD_GET, $extUrlParams);

        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        // 结果
        if ($response['Succeeded'] != true) {
            return $this->retErrorResponseData($response['Error']['Error'] ?? '0x000' . '.' . $response['Error']['Message'] ?? '未知错误');
        }

        $data = $response['Data'];

        if ($data['Match'] == 'Unknown') {
            return $this->retErrorResponseData('未能匹配到任何跟踪号');// 未能匹配到任何处理号、跟踪号或者客单号
        }

        $ls = [];
        foreach ($data['TrackPoints'] as $key => $val) {
            $val['Time'] = date('Y-m-d H:i:s', strtotime($val['Time']));
            $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
        }
        $data['details'] = $ls;
        $fieldData = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);

        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * @param $order_id
     * @return mixed
     * 获取包裹详情
     */
    public function getPackagesDetail($order_id)
    {
        $data = [
            'reference_no' => $order_id,
        ];
        $response = $this->request(__FUNCTION__, $data);
        return $response;
    }

    /**
     * 获取UTC格式化时间
     * @param string|int $date 时间|时间戳
     * @param string $format 格式化时间格式
     * @return false|string
     */
    protected function getUTCDateTime($date = '', $format = 'Y-m-d\TH:i:s\Z')
    {
        if ($date) {
            $time = is_numeric($date) ? $date : strtotime($date);
        } else {
            $time = time();
        }
        date_default_timezone_set("UTC");
        $str = date($format, $time);
        return $str;
    }
}