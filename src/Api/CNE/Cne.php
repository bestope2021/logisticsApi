<?php
/**
 * Created by PhpStorm
 * User: Administrator:smiler
 * Date: 2021/5/18 11:32
 * Email: <2166909366@qq.com>
 */

namespace smiler\logistics\Api\CNE;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\Datetime;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

class Cne extends LogisticsAbstract implements BaseLogisticsInterface, TrackLogisticsInterface, PackageLabelLogisticsInterface
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

    // 定义API是否授权
    static $isApiAuth = true;

    // 定义标识
    public $iden = 'CNE';
    public $iden_name = '递一国际';

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
        'createOrder' => 'PreInputSet', // POST:创建订单
        'getShippingMethod' => 'EmsKindList',// POST:获取产品服务信息
        'getPackagesLabel' => 'LabelPrint', // POST:标签获取接口
        'queryTrack' => 'ClientTrack', // POST:包裹单个跟踪接口
    ];

    /**
     * 定义请求错误码
     * @var string[]
     */
    protected $errorCode = [
        '1' => '操作成功',
        '0' => '操作失败',
        '-8' => '授权失败',
        '-9' => '系统错误',
        '-102' => '运单号不存在',
        '-5555' => 'json字符串解析失败,非标准json或存在特殊字符',
        '' => '未知错误',
    ];

    /**
     * HuaHan constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['url', 'icID', 'secret', 'labelUrl', 'trackUrl'], $config);
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

        foreach ($params as $item) {
            $productList = [];
            $totalValueCode = 'USD';
            foreach ($item['productList'] as $value) {
                $productList[] = [
                    'cxGCodeA' => $value['productSku'] ?? '',// N:物品SKU,0-63 字符
                    'cxGoodsA' => $value['declareEnName'] ?? '',// Y:物品英文描述,0-63 字符
                    'cxGoods' => $value['declareCnName'] ?? '',// Y:海关申报物品描述,0-63 字符
                    'ixQuantity' => (int)($value['quantity'] ?? ''),// Y:海关申报物品数量
                    'fxPrice' => (float)($value['declarePrice'] ?? 0), // Y:海关申报单价
                    'cxMoney' => $value['currencyCode'] ?? $totalValueCode,// N:成交币种
                    'hsCode' => $value['hsCode'] ?? '',// N:海关编码
                    'cxgURL' => $value['productUrl'] ?? '',// N:产品链接
                    'cxOrigin' => $value['originCountry'] ?? 'CN', // N:原产地国家代码
                ];
            }
            $address = ($item['recipientStreet'] ?? ' ') . ($item['recipientStreet1'] ?? ' '). ($item['recipientStreet2'] ?? '');
            $ls[] = [
                'cRNo' => $item['customerOrderNo'] ?? '',// Y:参考号,0-30 字符。(传用户系统订单号，只允许数字和字母，中划线，其他符号不接受）
                'nItemType' => $item['nItemType'] ?? '1',// Y:快件类型，默认为1。取值为：0(文件),1(包裹),2(防水袋)
                'cEmsKind' => $item['shippingMethodCode'] ?? '',// Y:快递类别,渠道接口API
                'cDes' => $item['recipientCountryCode'] ?? '',// Y:ISO 二字代码
                'fWeight' => (float)($item['predictionWeight'] ?? ''),// Y:重量，公斤，3 位小数
                'iLong' => (float)($item['packageLength'] ?? ''),// N:长，厘米
                'iWidth' => (float)($item['packageWidth'] ?? ''),// N:宽，厘米
                'iHeight' => (float)($item['packageHeight'] ?? ''),// N:高，厘米
                'iItem' => 1,// Y:件数，默认1,此为单个订单包裹数量，非SKU 数量
                'nPayWay' => ($item['payWay'] ?? 1),// Y:付款方式，默认为1。取值为：0(月结),1(现付),2(到付)
                'cAddrFrom' => FieldMap::platformMap($item['platformSource'] ?? ''),  //Y:订单对应平台
                'cRTaxCode' => '',// N:进口清关VAT税号-境外海关清关（更新）
                'cSTaxCode' => '',// N:出口清关税号-中国海关出口清关（新增）
                'cMemo' => $item['remark'] ?? '', //N:包裹备注
                // 收件人信息
                'cReceiver' => $item['recipientName'] ?? '',// Y:收件人,3-63 字符
                'cRUnit' => $item['recipientCompany'] ?? '', //N:收件单位,0-254 字符
                'cRCountry' => $item['recipientCountryCode'] ?? '', //Y:收件国家【必须为英文】,0-126 字符
                'cRProvince' => $item['recipientState'] ?? '', //N:收件人州/省
                'cRCity' => $item['recipientCity'] ?? '', //Y:收件城市,3-126 字符
                'cRAddr' => $address ?? '',// Y:收件地址,5-254 字符
                'cRPostcode' => $item['recipientPostCode'] ?? '', //Y:收件邮编,0-15 字符
                'cRPhone' => $item['recipientPhone'] ?? ($item['recipientMobile'] ?? ''), //N:收件电话,0-63 字符
                'cREMail' => $item['recipientEmail'] ?? '',// N:收件电邮,0-63 字符

                // 发件人信息
                'cSender' => $item['senderName'] ?? '', //N:发件人,0-30 字符。
                'cSUnit' => $item['senderName'] ?? '', //N:发件单位,0-127 字符。
                'cSCountry' => $item['senderCountryCode'] ?? '', // N:发件国家,0-63 字符
                'cSProvince' => $item['senderState'] ?? '', // N:发件省州,0-30 字符
                'cSCity' => $item['senderCity'] ?? '',// N:发件城市,0-63 字符
                'cSAddr' => $item['senderFullAddress'] ?? '',// N:发件地址,0-127 字符
                'cSPostcode' => $item['senderPostCode'] ?? '',// N:发件邮编,0-15 字符
                'cSPhone' => $item['senderPhone'] ?? '', // N:发件电话,0-63 字符
                'cSEMail' => $item['senderEmail'] ?? '',//N:发件电邮,0-63 字符

                'GoodsList' => $productList,// Y:包裹件内明细
            ];
        }

        $pars = ['RecList' => $ls ?? []];
        $response = $this->request(__FUNCTION__, $pars);

        $reqRes = $this->getReqResData();
//        $this->dd($response);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::createOrder();

        // 结果
        $code = $response['ReturnValue'] ?? '';
        $flag = ($code > 0);
        $info = $response['cMess'] ?? '';
        $data = $response['ErrList'][0] ?? [];

        if(!empty($data)){
            //错误信息,0-63 字符，空为无错误，如果有cMess 信息，就是获取到了cNo 都是无效的
            $info = $data['cMess'] ?? '';
            $flag = true;
            !empty($info) && $flag = false;
        }

        $fieldData['flag'] = $flag;
        $fieldData['info'] = $info;

        $fieldData['customerOrderNo'] = $data['cRNo'] ?? '';// 参考号,0-30 字符
        $fieldData['syOrderNo'] = (string)$data['iID'] ?? '';// 整数，说明此条记录被处理。（记录ID，一条预录单记录有一个唯一的不可更改的iID,系统识别码，对于记录的删除、修改则以此识别。）
        $fieldData['trackingNumber'] = $data['cNo'] ?? '';// 运单号，记录在系统内部的运单号，唯一

        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);

        return $flag ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);

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

        switch ($function) {
            case 'getPackagesLabel':// 面单
                $url = $this->config['labelUrl'] ?? '';
                break;

            case 'queryTrack':// 轨迹
                $url = $this->config['trackUrl'] ?? '';
                break;

            default:// 默认
                $url = $this->config['url'] ?? '';
                break;
        }

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
        return array_merge([
            'RequestName' => $this->interface[$interface] ?? '',
            'icID' => $this->config['icID'] ?? '',
            'TimeStamp' => $this->config['timestamp'],
            'MD5' => $this->getMd5Sign(),
        ], $arr);
    }

    /**
     * 获取签名字符串
     * icId + timeStamp + secret 进行md5加密后的32位小写字符串（icId、secret为客户账号和密钥）
     * @return string
     */
    protected function getMd5Sign()
    {
        return md5($this->config['icID'] . $this->config['timestamp'] . $this->config['secret']);
    }

    /**
     * 获取订单标签
     * @return mixed
     */
    public function getPackagesLabel($params)
    {
        $data = [
            'cNoList' => [
                $params['cNo'] ?? '',// Y:运单号
            ],
            'fileType' => 'pdf',// Y:文件类型图片：png、jpeg、jpgPDF文件：pdf
            'labelType' => 'label10x10',// Y:打印类型 label10x10, label10x15
            'pickList' => '0',// Y:拣货单 0：不包含拣货单; 1：包含拣货单
        ];

        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        if (empty($response)) {
            return $this->retErrorResponseData('标签暂未生成');
        }
        $item = [];

        $flag = ($response['ReturnValue'] > 0);

        $item['flag'] = $flag;
        $item['info'] = $response['cMess'] ?? '';
        $item['order_no'] = $params['cNo'];
        $item['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF;
        $item['lable_file'] = $flag ? (base64_encode(file_get_contents($response['labelUrlList'][0] ?? ''))) : '';

        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 获取物流商轨迹
     * @return mixed
     */
    public function queryTrack($trackNumber)
    {
        $data = [
            'cNo' => $trackNumber,// Y:运单号
            'lan' => 'en',// cn 中文;en 外文
        ];

        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        $code = $response['ReturnValue'] ?? '';
        $flag = ($code == 1);
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
            'status' => $response['Response_Info']['status'] ?? '',// 订单状态
            'content' => '',// 订单状态（货态）说明
            'details' => $response['trackingEventList'] ?? [],// 物流轨迹明细
        ];

        $ls = [];
        if($data['details']){
            $status = '';
            $content = '';
            foreach ($data['details'] as $key => $val) {
                if($key == 0){
                    $status = $val['state'];
                    $content = $val['details'];
                }
                $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
            }
            $data['status'] = $data['status'] ?: $status;
            $data['content'] = $content;
            $data['details'] = $ls;
            $fieldData = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);
        }

        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 删除订单
     * @param string $processCode
     * @return mixed|void
     */
    public function deleteOrder(string $processCode)
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    /**
     * 获取物流商运输方式
     * @return mixed
     *
     */
    public function getShippingMethod()
    {
        $response = $this->request(__FUNCTION__, []);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();

        $code = $response['ReturnValue'] ?? '';
        $flag = ($code > 0);

        // 结果
        if (!$flag) {
            return $this->retErrorResponseData($this->errorCode[$code]);
        }
        foreach ($response['List'] as $item) {
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
}