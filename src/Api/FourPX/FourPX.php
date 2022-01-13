<?php
/**
 * Created by PhpStorm
 * User: Administrator:smiler
 * Date: 2021/5/19 9:08
 * Email: <2166909366@qq.com>
 */

namespace smiler\logistics\Api\FourPX;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\Datetime;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

class FourPX extends LogisticsAbstract implements BaseLogisticsInterface, TrackLogisticsInterface, PackageLabelLogisticsInterface
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
    const METHOD_DELETE = 'DELETE';

    /**
     * 成功标识
     */
    const SUCCESS_IDENT = 1;

    /**
     * 重复标识
     */
    const ORDER_REPEAT = 'DS000056';

    // 定义API是否授权
    static $isApiAuth = true;

    // 定义标识
    public $iden = '4PX';
    public $iden_name = '递四方4PX';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'json';
    public $apiHeaders = [
        'Content-Type' => 'application/json; charset=UTF-8',
        'Accept' => '*/*',// application/json
    ];
    public $interface = [
        'createOrder' => 'ds.xms.order.create', // POST:创建订单
        'getShippingMethod' => 'ds.xms.logistics_product.getlist',// POST:获取产品服务信息
        'getPackagesLabel' => 'ds.xms.label.get', // POST:标签获取接口
        'queryTrack' => 'tr.order.tracking.get', // POST:包裹单个跟踪接口
        'searchOrder' => 'ds.xms.order.get',// POST:搜索订单
        'deleteOrder' => 'ds.xms.order.cancel',// POST:删除订单
        'createBag' => 'ds.xms.bag.create',// POST:直发授权-完成装袋
        'getBagLabel' => 'ds.xms.bag_label.get',// POST:直发授权-袋标签
        'operationPackages'=>'ds.xms.order.updateweight',//POST:更新预报重量,红清加过了
    ];

    //退货处理方式 Y:境内异常处理策略(退件：Y；销毁：N；其他：U；)
    const RETURN_PROCESS_LIST = [
        'N',//销毁
        'Y'//退回
    ];

    /*
     * 定义sign
     */
    /**
     * 定义版本号
     * @var string[]
     */
    public $version = [
        'createOrder' => '1.1.0', // POST:创建订单
        'getShippingMethod' => '1.0.0',// POST:获取产品服务信息
        'getPackagesLabel' => '1.1.0', // POST:标签获取接口
        'queryTrack' => '1.0.0', // POST:包裹单个跟踪接口
        'searchOrder' => '1.1.0',// POST:搜索订单
        'deleteOrder' => '1.0.0',// POST:删除订单
        'createBag' => '1.0.0',// POST:直发授权-完成装袋
        'getBagLabel' => '2.0.0',// POST:直发授权-袋标签
        'operationPackages'=>'1.0.0',//POST:更新预报重量
    ];
    protected $_sign = '';
    /**
     * 定义URL连接参数
     * @var string
     */
    protected $_urlExtStr = '';
    /**
     * 定义请求错误码
     * @var string[]
     */
    protected $errorCode = [
        'DS000000' => '失败',
        'DS000003' => '异常',
        'DS000004' => '参数异常',
        'DS000005' => '单号不能为空',
        'DS000006' => '操作太频繁请稍后操作',
        'DS000007' => '处理中',
        'DS000008' => '响应获取写入错误',
        'DS000009' => '业务类型不为空',
        'DS000010' => '没有定义',
        'DS000013' => '客户ID不能空',
        'DS000014' => '此单号不允许取消',
        'DS000015' => '请求编号不能为空',
        'DS000016' => '取消原因不能空',
        'DS000017' => '找不到这个订单',
        'DS000018' => '更新订单失败',
        'DS000019' => '返回的标签格式异常',
        'DS000020' => '请求不能为空',
        'DS000021' => '委托单的创建时间起始时间不能空',
        'DS000022' => '委托单的创建时间终止时间不能空',
        'DS000023' => '时间差异不能超过7天',
        'DS000024' => '数据转化失败',
        'DS000025' => '查询转单号异常',
        'DS000026' => '查询订单详情异常',
        'DS000027' => '获取XMSMARKCODE异常',
        'DS000028' => '数据参数异常',
        'DS000029' => '时间格式正确',
        'DS000030' => '请求ByDyType不正确',
        'DS000031' => '此产品无法截获',
        'DS000032' => '非预测状态无法截获',
        'DS000033' => '命令不能为空',
        'DS000034' => '命令原因不能为空',
        'DS000035' => '命令无效',
        'DS000036' => '拦截失败订单被截获',
        'DS000037' => '取消拦截失败订单未截获',
        'DS000038' => '命令细节不能为空',
        'DS000039' => '请求正文JSON格式异常',
        'DS000040' => '用户信息不能为空',
        'DS000041' => '站点代码不能为空',
        'DS000042' => '传递类型无效',
        'DS000043' => '自取代码不能为空',
        'DS000044' => '无效的业务类型',
        'DS000045' => '客户信息不完整要配置xms',
        'DS000046' => '此票证的标签不存在',
        'DS000047' => '此订单不是svip标签',
        'DS000056' => '重复下单',// todo: 下单时判断重复处理业务逻辑即可
        '' => '未知错误',
        0 => '失败',
        1 => '成功',
        2 => '部分成功',
    ];

    /**
     * HuaHan constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['url', 'appKey', 'appSecret'], $config);
        $this->config = $config;
        $this->config['timestamp'] = Datetime::getUTCTimestamp();
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

            $productList = $declareProductList = [];
            $totalValueCode = 'USD';
            $totalValueValue = 0;
            $isElectricity = 'N';
            foreach ($item['productList'] as $value) {
                $quantity = (int)($value['quantity'] ?? 1);
                $declarePrice = (float)($value['declarePrice'] ?? 0);
                $currencyCode = $value['currencyCode'] ?? $totalValueCode;
                // Y:货物列表
                $productList[] = [
                    'sku_code' => $value['productSku'] ?? '',// N:SKU（客户自定义SKUcode）（数字或字母或空格）
                    'product_description' => $value['declareEnName'] ?? '',// Y:商品描述
                    'product_name' => $value['declareCnName'] ?? '',// Y:商品名称
                    'qty' => (int)($value['quantity'] ?? ''),// Y:数量（单位为pcs）
                    'currency' => $currencyCode,// Y:币别（按照ISO标准三字码，目前只支持USD）
                    'product_unit_price' => $declarePrice, // Y:商品单价（按对应币别的法定单位，最多4位小数点）
                ];
                // Y:海关申报列表信息
                $declareProductList[] = [
                    'declare_product_name_en' => $value['declareEnName'] ?? '',// N:申报品名（英语）
                    'declare_product_name_cn' => $value['declareCnName'] ?? '',// N:申报品名(当地语言)
                    'declare_product_code_qty' => (int)($value['quantity'] ?? ''),// Y:申报数量
                    'unit_gross_weight' => (float)($value['declareWeight'] ?? '') * 1000,// Y:单件商品毛重（默认以g为单位）
                    'material' => $value['productMaterial'] ?? '', // N:材质
                    'uses' => $value['productPurpose'] ?? '', // N:用途

                    'origin_country' => $value['originCountry'] ?? 'CN',// N:原产地（ISO标准2字码）点击查看详情字码）点击查看详情
                    'contry_export' => $value['originCountry'] ?? 'CN',// N:出口国（ISO标准2字码）点击查看详情
                    'contry_import' => $value['originCountry'] ?? 'CN',// N:进口国（ISO标准2字码）

                    'hscode_export' => $value['hsCode'] ?? '',// N:出口国海关编码
                    'hscode_import' => $value['hsCode'] ?? '',// N:进口国海关编码

                    'currency_export' => $currencyCode,// Y:币别（按照ISO标准，目前只支持USD）点击查看详情
                    'declare_unit_price_export' => $declarePrice, // Y:出口国申报单价（按对应币别的法定单位，最多4位小数点）

                    'currency_import' => $currencyCode,// Y:币别（按照ISO标准，目前只支持USD）
                    'declare_unit_price_import' => $declarePrice, // Y:进口国申报单价（按对应币别的法定单位，最多4位小数点）

                    'brand_export' => $value['brand'] ?? '', // Y:出口国品牌
                    'brand_import' => $value['brand'] ?? '', // Y:进口国品牌

                    'sales_url' => $value['productUrl'] ?? '',// N:商品销售URL
                ];
                $totalValueCode = $currencyCode;
                $totalValueValue += ($quantity * $declarePrice);
            }
            $address = ($item['recipientStreet'] ?? ' ') .' ' . ($item['recipientStreet1'] ?? ' ') .' '. (empty($item['recipientStreet2']) ? '' : $item['recipientStreet2']);
            $ls[] = [
                '4px_tracking_no' => $item['trackingNumber'] ?? '',// N:4PX跟踪号（预分配号段的客户可传此值）
                'ref_no' => $item['customerOrderNo'] ?? '',// Y:参考号（客户自有系统的单号，如客户单号）
                'business_type' => $item['businessType'] ?? 'BDS',// Y:业务类型(4PX内部调度所需，如需对接传值将说明，默认值：BDS。)
                // todo: 待确认
                'duty_type' => $item['dutyType'] ?? 'P',// Y:税费费用承担方式(可选值：U、P); DDU由收件人支付关税：U; DDP 由寄件方支付关税：P; （如果物流产品只提供其中一种，则以4PX提供的为准）
                'cargo_type' => '5',// N:货物类型（礼品：1;文件：2;商品货样:3;其它：5；默认值：5）

                'vat_no' => $item['recipientTaxNumber'] ?? '',// N:VAT税号(数字或字母)
                'eori_no' => '',// N:IOR号码(数字或字母)
                'ioss_no' => $item['iossNumber'] ?? '',// 欧盟税号（ioss税号）
                'buyer_id' => '',// N:买家ID(数字或字母)
                'sales_platform' => '',// N:销售平台
                'trade_id' => '',// N:交易号ID(数字或字母)
                'seller_id' => '',// N:卖家ID(数字或字母)

                // Y:物流服务信息
                'logistics_service_info' => [
                    'logistics_product_code' => $item['shippingMethodCode'] ?? '',// Y:物流产品代码
                    'customs_service' => 'N',// N:单独报关（否：N ;单独报关且退税：YRR ;单独报关不退税：YNR） 默认值：N
                    'signature_service' => 'N',// N:签名服务（Y/N)；默认值：N
                    'value_added_services' => '',// N:其他服务（待完善)
                ],

                // 退件信息
                'return_info' => [
                    'is_return_on_domestic' => self::RETURN_PROCESS_LIST[$item['returnProcess']]??'N',// Y:境内异常处理策略(退件：Y；销毁：N；其他：U；) 默认值：N；
                    // YN:境内退件接收地址信息（处理策略为Y时必须填写地址信息）
                    'domestic_return_addr' => [
                        'first_name' => $item['returnFirstName'] ?? '',// N:名/姓名
                        'last_name' => $item['returnLastName'] ?? '',// N:姓
                        'company' => $item['returnCompany'] ?? '',// N:公司名
                        'phone' => $item['returnPhone'] ?? '',// N:电话（必填）
                        'phone2' => $item['returnPhone'] ?? '',// N:电话2
                        'email' => $item['returnEmail'] ?? '',// N:邮箱
                        'post_code' => $item['returnPostCode'] ?? '',// N:邮编
                        'country' => $item['returnCountryCode'] ?? '',// N:国家（国际二字码 标准ISO 3166-2 ）
                        'state' => $item['returnState'] ?? '',// N:州/省
                        'city' => $item['returnCity'] ?? '',// N:城市
                        'district' => $item['returnDistrict'] ?? '',// N:区、县
                        'street' => $item['returnAddress'] ?? '',// N:街道/详细地址
                        'house_number' => $item['returnHouseNumber'] ?? '',// N:门牌号
                    ],
                    'is_return_on_oversea' => 'N',// Y:境外异常处理策略(退件：Y；销毁：N；其他：U；) 默认值：N；
                    // YN:境外退件接收地址信息（处理策略为Y时必须填写地址信息）
                    'oversea_return_addr' => [
                        'first_name' => $item['returnOutFirstName'] ?? '',// N:名/姓名
                        'last_name' => $item['returnOutLastName'] ?? '',// N:姓
                        'company' => $item['returnOutCompany'] ?? '',// N:公司名
                        'phone' => $item['returnOutPhone'] ?? '',// N:电话（必填）
                        'phone2' => $item['returnOutPhone'] ?? '',// N:电话2
                        'email' => $item['returnOutEmail'] ?? '',// N:邮箱
                        'post_code' => $item['returnOutPostCode'] ?? '',// N:邮编
                        'country' => $item['returnOutCountryCode'] ?? '',// N:国家（国际二字码 标准ISO 3166-2 ）
                        'state' => $item['returnOutState'] ?? '',// N:州/省
                        'city' => $item['returnOutCity'] ?? '',// N:城市
                        'district' => $item['returnOutDistrict'] ?? '',// N:区、县
                        'street' => $item['returnOutAddress'] ?? '',// N:街道/详细地址
                        'house_number' => $item['returnOutHouseNumber'] ?? '',// N:门牌号
                    ],
                ],

                // Y:包裹列表
                'parcel_list' => [
                    'weight' => (float)($item['predictionWeight'] ?? '') * 1000,// Y:预报重量（g）
                    'length' => (float)($item['packageLength'] ?? ''),// N:包裹长（cm）
                    'width' => (float)($item['packageWidth'] ?? ''),// N:包裹宽（cm）
                    'height' => (float)($item['packageHeight'] ?? ''),// N:包裹高（cm）
                    'parcel_value' => (float)$totalValueValue,// Y:包裹申报价值（最多4位小数）
                    'currency' => $totalValueCode,// Y:币别（按照ISO标准三字码，目前只支持USD）
                    'include_battery' => $isElectricity,// Y:是否含电池（Y/N）
                    'product_list' => $productList, // Y货物列表（投保、查验、货物丢失作为参考依据）
                    'declare_product_info' => $declareProductList ?? [],// Y:海关申报列表信息(每个包裹的申报信息，方式1：填写申报产品代码和申报数量；方式2：填写其他详细申报信息)
                ],

                'is_insure' => 'N',// Y:是否投保(Y、N)
                'insurance_info' => [],// YN:投保信息（投保时必须填写）

                // Y:发件人信息
                'sender' => [
                    'first_name' => $item['senderFirstName'] ?? '',// N:名/姓名
                    'last_name' => $item['senderLastName'] ?? '',// N:姓
                    'company' => $item['senderName'] ?? '',// N:公司名
                    'phone' => $item['senderPhone'] ?? '',// N:电话（必填）
                    'phone2' => '',// N:电话2
                    'email' => $item['senderEmail'] ?? '',// N:邮箱
                    'post_code' => $item['senderPostCode'] ?? '',// N:邮编
                    'country' => $item['senderCountryCode'] ?? '',// N:国家（国际二字码 标准ISO 3166-2 ）
                    'state' => $item['senderState'] ?? '',// N:州/省
                    'city' => $item['senderCity'] ?? '',// N:城市
                    'district' => $item['senderDistrict'] ?? '',// N:区、县
                    'street' => $item['senderFullAddress'] ?? '',// N:街道/详细地址
                    'house_number' => '',// N:门牌号
                ],

                // Y:收件人信息
                'recipient_info' => [
                    'first_name' => $item['recipientName'] ?? '',// N:名/姓名
                    'last_name' => '',// N:姓
                    'company' => $item['recipientCompany'] ?? '',// N:公司名
                    'phone' => $item['recipientPhone'] ?? ($item['recipientMobile'] ?? ''),// N:电话（必填）
                    'phone2' => $item['recipientMobile'] ?? '',// N:电话2
                    'email' => $item['recipientEmail'] ?? '',// N:邮箱
                    'post_code' => $item['recipientPostCode'] ?? '',// N:邮编
                    'country' => $item['recipientCountryCode'] ?? '',// N:国家（国际二字码 标准ISO 3166-2 ）
                    'state' => $item['recipientState'] ?? '',// N:州/省
                    'city' => $item['recipientCity'] ?? '',// N:城市
                    'district' => '',// N:区、县
                    'street' => $address ?? '',// N:街道/详细地址
                    'house_number' => '',// N:门牌号
                ],

                // Y:货物到仓方式信息
                'deliver_type_info' => [
                    'deliver_type' => '1',// Y:到仓方式（上门揽收：1；快递到仓：2；自送到仓:3；自送门店：5）
                    'warehouse_code' => '',// N:收货仓库/门店代码
                ],

                // Y:投递信息
                'deliver_to_recipient_info' => [
                    'deliver_type' => 'HOME_DELIVERY',// N:投递类型：HOME_DELIVERY-投递到门；SELF_PICKUP_STATION-投递门店（自提点）；SELF_SERVICE_STATION-投递自提柜(自助点） 默认：HOME_DELIVERY
                    'station_code' => '',// N:自提门店/自提点的信息(选择自提时必传，点击获取详情)
                ],
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
        $data = $response['data'] ?? [];
        $error = $response['errors']??'';
        $flag = empty($error);
        $info = $response['errors'][0]['error_msg'] ?? ($response['msg'] ?? '');

        $fieldData['flag'] = $flag;
        $fieldData['info'] = $info;

        $fieldData['customerOrderNo'] = $data['ref_no'] ?? '';//客户单号/客户参考号
        $fieldData['syOrderNo'] = $data['4px_tracking_no'] ?? '';// 直发委托单号
        $fieldData['trackingNumber'] = '';
        $fieldData['frtTrackingNumber'] = $data['logistics_channel_no'] ?? '';// 物流渠道号码。如果结果返回为空字符，表示暂时没有物流渠道号码，请稍后主动调用查询直发委托单接口查询

        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);

        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);

    }

    /**
     * 发起请求
     * @param string $function 方法
     * @param array $data 数据
     * @param string $method 请求方式
     * @return mixed
     */
    public function request($function, $data = [], $method = self::METHOD_POST)
    {
        $data = $this->buildParams($function, $data);

        $this->getSignAndReqParams($function, $data);

        $url = ($this->config['url'] ?? '') . '?' . $this->_urlExtStr . '&language=cn';

        $response = $this->sendCurl($method, $url, $data, $this->dataType, $this->apiHeaders);
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
     * 获取签名字符串和请求参数
     * sign=app_key+format+method+timestamp+v+body+AppSecret值
     */
    protected function getSignAndReqParams($interface = '', $body = [])
    {
        $signDt = [
            'app_key' => $this->config['appKey'] ?? '',
            'format' => $this->dataType ?? 'json',
            'method' => $this->interface[$interface] ?? '',
            'timestamp' => $this->config['timestamp'],
            'v' => $this->version[$interface] ?? '1.0.0',
        ];

        // 生成sign
        $extStr = json_encode($body, JSON_UNESCAPED_UNICODE) . ($this->config['appSecret']);
        $signStr = str_replace('=', '', http_build_query($signDt, '', ''));
//        var_dump($signStr . $extStr);
        $this->_sign = md5($signStr . $extStr);

        // 生成URL扩展参数
        $this->_urlExtStr = http_build_query(array_merge($signDt, ['sign' => $this->_sign]));
    }

    /**
     * 查询订单
     * @param string $request_no
     * @param bool $isOnly
     * @return array|bool|mixed|string
     */
    public function searchOrder(string $request_no = '', $isOnly = true)
    {
        if (empty($request_no)) {
            return false;
        }

        $dt = [
            'request_no' => $request_no,
            'start_time_of_create_consignment' => null,
            'end_time_of_create_consignment' => null,
            'consignment_status' => null,
        ];

        $response = $this->request(__FUNCTION__, $dt);
        // 结果
        $flag = !empty($response['data']);

        $msg = $response['msg'];

        $data = $response['data'][0] ?? [];

        $ret = $data['consignment_info'] ?? [];

        return ['flag'=> $flag,'data'=> $ret,'msg' => $msg];
    }

    /**
     * 删除订单
     * @param string $processCode
     * @return mixed|void
     */
    public function deleteOrder(string $request_no)
    {
        if (empty($request_no)) {
            return false;
        }

        $dt = [
            'request_no' => $request_no,
            'cancel_reason' => '取消订单或需要重新下单',
        ];

        $response = $this->request(__FUNCTION__, $dt);

        // 结果
        $flag = $response['result'] == self::SUCCESS_IDENT;

        return $flag;
    }

    /**
     * 获取订单标签
     * @return mixed
     */
    public function getPackagesLabel($params)
    {
        $request_no = $params['request_no'] ?? '';
        $data = [
            'request_no' => $request_no,// Y:请求单号（支持4PX单号、客户单号和面单号）
            'response_label_format' => 'pdf',// N:返回面单的格式（PDF：返回PDF下载链接；IMG：返回IMG图片下载链接） 默认为PDF；
            'label_size' => 'label_100x100',// N:打印类型 label10x10, label10x15
            'is_print_declaration_list' => 'N',// N:是否打印报关单（Y：打印；N：不打印） 默认为N；
            'create_package_label' => 'N',// N:是否单独打印配货单（Y：打印；N：不打印） 默认为N。
        ];

        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        if (empty($response)) {
            return $this->retErrorResponseData('标签暂未生成');
        }
        $item = [];

        $flag = ($response['result'] == self::SUCCESS_IDENT);
        $data = $response['data'] ?? [];

        $item['flag'] = $flag;
        $item['info'] = $response['errors'][0]['error_msg'] ?? ($response['msg'] ?? '');
        $item['order_no'] = $request_no;
        $item['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_PDF;
        $item['label_file'] = $data['label_url_info']['logistics_label'] ?? '';// 面单链接

        $item['extended'] = $flag ? [
            ResponseDataConst::LSA_LABEL_CUSTOM_PATH => $data['label_url_info']['custom_label'] ?? '',// 报关标签链接
            ResponseDataConst::LSA_LABEL_PACKAGE_PATH => $data['label_url_info']['package_label'] ?? '',// 配货标签链接
            ResponseDataConst::LSA_LABEL_INVOICE_PATH => $data['label_url_info']['invoice_label'] ?? '',// DHL发票链接
            ResponseDataConst::LSA_LABEL_BARCODE_TYPE => ResponseDataConst::LSA_LABEL_BARCODE_TYPE_STRING,// 面单码类型
            ResponseDataConst::LSA_LABEL_BARCODE => $data['label_barcode'] ?? '',// 面单码
        ] : [];//扩展参数
        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
        return $this->retSuccessResponseData($fieldData);
    }
    /**
     * 修改订单重量
     * @param array $params
     * @return mixed|void
     */
    public function operationPackages($params)
    {
        $data = [
            'request_no' => $params['request_no'] ?? '',
            'weight' =>empty($params['weight'])?0:$params['weight']*1000,//单位是g
        ];
        $response = $this->request(__FUNCTION__, $data);
        if (empty($response)) {
            return $this->retErrorResponseData('修改订单重量异常');
        }
        // 结果
        if ($response['result'] != self::SUCCESS_IDENT) {
            return $this->retErrorResponseData($response['msg'] ?? '未知错误');
        }
        return $this->retSuccessResponseData($response);
    }

    /**
     * 获取物流商轨迹
     * @return mixed
     */
    public function queryTrack($trackNumber)
    {
        $data = [
            'deliveryOrderNo' => $trackNumber['sy_order_no'],// Y:物流单号
        ];

        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        $code = $response['result'] ?? '';
        $flag = ($code == self::SUCCESS_IDENT);
        $info = '';
        // 结果
        if (!$flag) {
            $info = $this->errorCode[$code];
            return $this->retErrorResponseData($info);
        }

        $data = [
            'flag' => $flag,// 处理状态： true 成功，false 失败
            'info' => $info ?? '',// 提示信息
            'trackingNumber' => $trackNumber,// 查询单号可能是 客户订单号/第三方订单号|运单号/追踪号
            'status' => '',// 订单状态
            'content' => '',// 订单状态（货态）说明
            'details' => $response['data']['trackingList'] ?? [],// 物流轨迹明细
        ];

        $ls = [];
        if ($data['details']) {
            $status = '';
            $content = '';
            foreach ($data['details'] as $key => $val) {
                if ($key == 0) {
                    $status = $val['businessLinkCode'];
                    $content = $val['trackingContent'];
                }
                $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
            }
            $data['status'] = $status;
            $data['content'] = $content;
            $data['details'] = $ls;
            $fieldData = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);
        }

        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 获取物流商运输方式
     * @return mixed
     *
     */
    public function getShippingMethod()
    {
        // 运输方式：1 所有方式；2 国际快递；3 国际小包；4 专线；5 联邮通；6 其他；
        $map = [
            2 => '国际快递',
            3 => '国际小包',
            4 => '专线',
            5 => '联邮通',
            6 => '其他',
        ];
        $dt = [
            'transport_mode' => '1',
        ];
        $response = $this->request(__FUNCTION__, $dt);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();

        $resultCode = $response['result'] ?? '';
        $flag = ($resultCode == self::SUCCESS_IDENT);
        $errorCode = $response['errors'][0]['error_code'] ?? '';

        // 结果
        if (!$flag) {
            return $this->retErrorResponseData($this->errorCode[$errorCode]);
        }
        foreach ($response['data'] as $item) {
            $item['transport_mode'] = $map[$item['transport_mode'] ?? ''] ?? '其他';
            $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
        }
        return $this->retSuccessResponseData($fieldData);
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
     * 完成装袋
     * @param array $params
     * @return array
     */
    public function createBag($params = [])
    {
        $dt = [
            'request_id' => $this->getRand(),// Y:随机值，用于一次请求的幂等，无业务语义
            'bag_code' => $params['bagCode'] ?? '',// N:袋子号
            'partition' => $params['partition'] ?? '',// N:装袋分区代码
            'finish_bagging_time' => $params['finishBaggingTime'] ?? '',// Y:完成装袋时间(东8)
            'pieces' => $params['pieces'] ?? '',// Y:袋子里单票数量
            'bag_weight' => $params['bagWeight'] ?? '',// Y:袋子重量（单位:g）
        ];

        $orderList = $params['orderList'] ?? [];// Y:订单列表
        if (!empty($orderList)) {
            $list = [];
            foreach ($orderList as $item) {
                $list[] = [
                    'order_no' => $item['orderNo'] ?? '',// Y:单号
                    'weight' => $item['weight'] ?? '',// Y:订单重量（单位:g）
                ];

            }
            $dt['order_list'] = $list;
        }

        $response = $this->request(__FUNCTION__, $dt);

        $resultCode = $response['result'] ?? '';
        $flag = ($resultCode == self::SUCCESS_IDENT);
        $data = $response['data'] ?? [];

        $errorCode = $response['errors'] ?? [];

        if ($data) {
            $retData = [
                'requestId' => $data['request_id'] ?? '',// 请求唯一识别号
                'bagCode' => $data['bag_code'] ?? '',// 袋子号
                'bagLabelURL' => $data['bag_label_url'] ?? '',// 袋标签，下载地址
                'bagLabelType' => 'pdf',// 袋标签类型，默认为PDF文件链接
            ];
        }

        // 异常
        if (!empty($errorCode)) {
            $errors = [];
            foreach ($errorCode as $item) {
                $errors[] = '订单号:' . $item['referenceCode'] . ':' . $item['errorMsg'];
            }
            $errorInfo = join('; ', $errors);
        }

        // 结果
        if (!$flag) {
            return $this->retErrorResponseData($errorInfo ?? ($response['msg'] ?? '未知错误'));
        }

        return $this->retSuccessResponseData($retData ?? []);
    }

    /**
     * 生成唯一标识
     * @return string
     */
    protected function getRand()
    {
        return uniqid('bst' . $this->iden, true);
    }

    /**
     * 袋标签
     * @param array $params
     * @return array
     */
    public function getBagLabel($params = [])
    {
        $dt = [
            'requestId' => $this->getRand(),// Y: 随机值，用于一次请求的幂等，无业务语义
            'referenceCode' => $params['selectNo'] ?? '',// Y:袋子号或单号
        ];
        $response = $this->request(__FUNCTION__, $dt);

        $resultCode = $response['result'] ?? '';
        $flag = ($resultCode == self::SUCCESS_IDENT);
        $errorCode = $response['errors'][0]['error_code'] ?? '';
        $data = $response['data'] ?? [];

        if ($data) {
            $retData = [
                'requestId' => $data['requestId'] ?? '',// 请求唯一识别号
                'selectNo' => $data['bagCode'] ?? '',// 袋子号或单号
                'bagLabelURL' => $data['bagLabelURL'] ?? '',// 袋标签，下载地址
                'bagLabelType' => 'pdf',// 袋标签类型，默认为PDF文件链接
            ];
        }

        // 结果
        if (!$flag) {
            return $this->retErrorResponseData($this->errorCode[$errorCode]);
        }

        return $this->retSuccessResponseData($retData ?? []);
    }

}