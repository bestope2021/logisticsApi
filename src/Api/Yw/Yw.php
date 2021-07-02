<?php
/**
 *
 * User: blaine
 * Date: 2/23/21
 */

namespace smiler\logistics\Api\Yw;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\Exception\NotSupportException;
use smiler\logistics\LogisticsAbstract;
use function phpamqp\re;

/**
 * 燕文物流
 * @link https://ywtemplate.oss-cn-hangzhou.aliyuncs.com/api_document.zip
 * Class Yw
 * @package smiler\logistics\Api\Yw
 */
class Yw extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface
{
    protected $iden = 'yw';

    public $iden_name = '燕文物流';

    const ORDER_COUNT = 1;

    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'xml';

    public $apiHeaders = [];

    public $interface = [
        'createOrder' => 'Users/%s/Expresses', // 【创建订单】

        'getPackagesLabel' => 'Users/%s/Expresses/%s/%sLabel', // 【打印标签|面单】

        'updateOrderStatus' => 'Users/%s/Expresses/ChangeStatus/%s', //修改订单状态

        'getShippingMethod' => '/Users/%s/GetChannels'
    ];

    /**
     * HuaHan constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['apiToken', 'userId', 'url'], $config);
        if (!empty($config['apiHeaders'])) {
            $this->apiHeaders = $config['apiHeaders'];
        }
        $this->config = $config;
    }

    /**
     * 创建订单，生成跟踪号
     * @param array $params
     * @return mixed
     * @throws InvalidIArgumentException
     * @throws ManyProductException
     * {"CallSuccess":"true","CreatedExpress":{"Receiver":{"Postcode":"83404-7146","State":"ID","NationalId":[],"Email":[],"District":[],"Userid":"100000","Name":"bst-smiler","Phone":"+1480-618-534416675","Mobile":"+1480-618-534416675","Company":"shezhen","Country":"UNITED STATES","CountryType":{"Id":"115","RegionCh":"美国","RegionEn":"UNITED STATES","RegionCode":"US"},"City":"IDAHO FALLS","CityCode":[],"Address1":"2884 REDBARN LN","Address2":[],"NationalIdFullName":[],"NationalIdIssueDate":"2021-02-24T00:00:00+08:00","NationalIdExpireDate":"2021-02-24T00:00:00+08:00"},"UserOrderNumber":"T1020210224112949578614113","GoodsName":{"Id":"0","Userid":"100000","NameCh":"荣耀","NameEn":"honor","Weight":"100","DeclaredValue":"1","DeclaredCurrency":"USD","MoreGoodsName":[],"HsCode":[],"ProductBrand":[],"ProductSize":[],"ProductColor":[],"ProductMaterial":[]},"Sender":{"TaxNumber":[]},"Epcode":"UD000019245YP","Userid":"100000","ChannelType":{"Id":"457","Name":"燕文航空经济小包-特货","Status":"true","NameEn":"AIR ECONOMY MAIL-T","LimitStatus":"true"},"Channel":"燕文航空经济小包-特货","Package":"无","SendDate":"2021-02-24T00:00:00","Quantity":"1","CustomDeclarationCollection":{"CustomDeclarationType":{"Id":"0","Userid":"100000","NameCh":"荣耀","NameEn":"honor","Weight":"100","DeclaredValue":"1","DeclaredCurrency":"USD","MoreGoodsName":[],"HsCode":[],"Quantity":"1"}},"YanwenNumber":"AC002173658YN","ReferenceNo":[],"PackageNo":[],"Insure":"false","Memo":[],"TrackingStatus":"暂无信息","IsPrint":"false","CreateDate":"2021-02-24T11:29:49.9297107+08:00","MerchantCsName":[],"ProductLink":[],"UndeliveryOption":"0","IsPostPlatform":"false","BatteryStatus":"0","IsStatus":"0"},"Response":{"Userid":"100000","Operation":"Create","Success":"true","Reason":"None","ReasonMessage":"没有错误","Epcode":"UD000019245YP"}}
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
            $order_weight = $quantity = 0;
            //todo 该物流商暂时支持上传一个产品
            $productList = [
                'Userid' => $this->config['userId'], //客户号
                'NameEn' => '',// Y:申报英文名称Length <= 50
                'NameCh' => '',// N:申报中文名称Length <= 50
                'Weight' => 0,// Y:总量;Length <= 50 KG todo 单位:g
                'DeclaredValue' => 0, //Y:单价
                'DeclaredCurrency' => $value['currencyCode'] ?? 'USD',// , //申报币种，不传值默认为USD(美元)；USD-美元,AUD-澳元
                'HsCode' => $value['hsCode'] ?? '',// N:海关编码
                'MoreGoodsName' => '', //多品名. 会出现在拣货单上
                'ProductBrand' => '', //产品品牌，中俄SPSR专线此项必填
                'ProductSize' => '',  //产品尺寸，中俄SPSR专线此项必填
                'ProductColor' => '', //产品颜色，中俄SPSR专线此项必填
                'ProductMaterial' => '', //N:产品材质，中俄SPSR专线此项必填
            ];
            foreach ($item['productList'] as $value) {
                $productList['NameEn'] .= !empty($value['declareEnName']) ? $value['declareEnName'] . "," : '';
                $productList['NameCh'] .= !empty($value['declareCnName']) ? $value['declareCnName'] . "," : '';
                $productList['Weight'] += ($value['declareWeight'] ?? '') * 1000;
                $productList['DeclaredValue'] += (float)($value['declarePrice'] ?? '') * (int)($value['quantity'] ?? '');
                $productList['HsCode'] = $value['hsCode'] ?? '';
                $quantity += $value['quantity'];
                $order_weight += $value['declareWeight'];
            }
            $productList['NameEn'] = trim($productList['NameEn'], ',');
            $productList['NameCh'] = trim($productList['NameCh'], ',');
            $ls[] = [
                'Epcode' => '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 50
                'Userid' => $this->config['userId'], // 客户号
                //todo 调试写死
                'Channel' => $item['shippingMethodCode'] ?? '457',// 发货方式代码：getShippingMethod的channelType类型下Id字段；
                'UserOrderNumber' => $item['customerOrderNo'] ?? '', //N:客户订单号。todo 若下单英国，请提供平台/独立站销售订单号
                'SendDate' => $item['order_date'] ?? date("Y-m-d H:i"),// Y:发货日期
                'Quantity' => $quantity, //Y:货品总数量
                'PackageNo' => '', //N:包裹号
                'Insure' => '',//N:是否需要保险
                'Memo' => $item['remark'] ?? '', //N:Memo
                'MerchantCsName' => '',//N:店铺名称， 燕特快不含电，国家为巴西时 此属性必填
                'MRP' => '', //N:申报建议零售价
                'ExpiryDate' => '', //N:产品使用到期日
                'Sender' => [
                    'TaxNumber' => !empty($item['iossNumber']) ?$item['iossNumber']: $item['recipientTaxNumber'], //寄件人税号（VOEC No/ VAT No）。todo 若下单英国，请提供店铺VAT税号，税号规则为GB+9位数字
                ],
                'Receiver' => [ //收件人信息
                    'Userid' => $this->config['userId'], //客户号
                    'Name' => $item['recipientName'] ?? '',// Y:收件人姓名
                    'Phone' => $item['recipientPhone'] ?? '', //N:收件人电话
                    'Mobile' => $item['recipientPhone'] ?? '', //N:收件人手机 todo 收货人-座机，手机。美国专线至少填一项
                    'Email' => $item['recipientEmail'] ?? '', //Y:收货人-邮箱
                    'Company' => $item['recipientCompany'] ?? '', //N:收件人公司名
                    'Country' => $item['recipientCountryCode'] ?? '', //Y:收件人国家二字代码
                    'Postcode' => $item['recipientPostCode'] ?? '', //Y:收件人邮编
                    'State' => $item['recipientState'] ?? '', //N:收件人省
                    'City' => $item['recipientCity'] ?? '', //N:收件人城市
                    'Address1' => $item['recipientStreet'] ?? '',// Y:收件人街道
                    'Address2' => $item['recipientStreet1'] ?? '', //收货人-地址2
                    'NationalId' => $item['recipientTaxNumber'] ?? '',// N:护照ID，税号。（国家为巴西时 此属性必填）
                ],

                'GoodsName' => $productList,// Y:一次最多支持 5 个产品信息（超过 5 个将会忽略）
            ];
        }

        $requestUrl = $this->config['url'] . sprintf($this->interface[__FUNCTION__], $this->config['userId']);
        $response = $this->request($requestUrl, 'post', $ls[0]);
        if ($response['CallSuccess'] != 'true') {
            return $this->retErrorResponseData($response['Response']['ReasonMessage'] ?? '未知错误');
        }
        $reqRes = $this->getReqResData();
        $tmpArr = $response['CreatedExpress'];
        $tmpArr['flag']= $response['CallSuccess'] != 'false' ? true : false;
        $tmpArr['info']= $response['Response']['ReasonMessage'] ?? '';
        $fieldMap = FieldMap::createOrder();
        $arr = array_merge($reqRes, LsSdkFieldMapAbstract::getResponseData2MapData($tmpArr, $fieldMap));
        return $this->retSuccessResponseData($arr);
    }


    /**
     * @return mixed
     * 获取物流商运输方式
     */
    public function getShippingMethod()
    {
        $requestUrl = $this->config['url'] . sprintf($this->interface[__FUNCTION__], $this->config['userId']);
        $response = $this->request($requestUrl, 'get');
        $fieldMap = FieldMap::shippingMethod();
        if($response['CallSuccess'] != 'true'){
            return $this->retErrorResponseData();
        }
        $arr = $response['ChannelCollection']['ChannelType'];
        foreach ($arr as $key=>$value){
                $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($value, $fieldMap);
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
     * 修改订单状态,每次请求只允许调整一个运单的快件状态
     * todo  物流商要求传运单号，创建订单返回的Epcode字段值
     * todo 物流单号如果标记为删除 不能恢复
     * @return mixed
     */
    public function updateOrderStatus(array $params)
    {
        $status = $params['status'] ?? 1;//快件状态。支持的值为：1 正常；0 删除
        $requestUrl = $this->config['url'] . sprintf($this->interface[__FUNCTION__], $this->config['userId'], $status);
        $response = $this->request($requestUrl, 'post', [$params['order_id']], 'string');
        return $response;
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
     * 获取订单标签 todo 传入多个订单时，pdf返回到一个文件里面 返回是一个文件字节流
     * @return mixed
     */
    public function getPackagesLabel($order_id)
    {

        $requestUrl = $this->config['url'] . sprintf($this->interface[__FUNCTION__], $this->config['userId'], $order_id, 'A10x10LCI'); //标签大小。支持的值为：A4L, A4LI, A4LC, A4LCI, A6L, A6LI, A6LC, A6LCI, A10x10L, A10x10LI,A10x10LC, A10x10LCI。(注：L为运单，C为报关签条，I为拣货单。)
        $response = $this->request($requestUrl, 'get', [], 'ExpressType',false);

        $fieldMap = FieldMap::packagesLabel();
        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData([
            'label_path_type' => ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF,
            'lable_file' => base64_encode($response),
            'order_no' =>  $order_id,
            'flag' => $response ? true : false,
        ], $fieldMap);
        return $this->retSuccessResponseData($fieldData);
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
     * @param $interface string 方法名
     * @param array $arr 请求参数
     * @return array
     */
    public function buildParams($interface, $arr = [])
    {

    }

    public function request($requestUrl, $method = 'post', $data = [], $xml = 'ExpressType', $parseResponse = true)
    {
        $this->apiHeaders['Authorization'] = " basic " . $this->config['apiToken'];
        $this->req_data = $data;
        $response = $this->sendCurl($method, $requestUrl, $data, $this->dataType, $this->apiHeaders, 'utf-8', $xml, $parseResponse);
        $this->res_data = $response;
        return $response;
    }


}