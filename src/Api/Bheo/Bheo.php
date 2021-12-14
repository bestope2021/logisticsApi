<?php
/**
 *
 * User: blaine
 * Date: 2/22/21
 */

namespace smiler\logistics\Api\Bheo;


use smiler\logistics\Api\Bheo\FieldMap;
use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;
use smiler\logistics\LogisticsIdent;

/**
 * 出口易物流
 * @link http://openapi.ck1info.com/v1/Help/Api/POST-v1-directExpressOrders
 * Class Bheo
 * @package smiler\logistics\Api\Bheo
 */
class Bheo extends LogisticsAbstract implements TrackLogisticsInterface, PackageLabelLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹
     */
    const ORDER_COUNT = 20;
    /**
     * 一次最多查询多少个跟踪号
     */
    const QUERY_TRACK_COUNT = 2000;
    /**
     * 一次最多删除多少个跟踪号
     */
    const DEL_TRACK_COUNT = 20;

    public $iden = 'ChuKou1';

    public $iden_name = '出口易物流';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'json';

    public $apiHeaders = [];

    public $successData = [];

    public $interface = [

        'createOrder' => 'createorder', // 【创建订单】

        'getPackagesLabel' => 'getnewlabel', // 【打印标签|面单】

        'getTrackNumber' => 'gettrackingnumber',//获取跟踪号

        'queryTrack' => 'gettrack', //轨迹查询

        'deleteOrder' => 'removeorder', //删除订单

        'getShippingMethod' => 'getshippingmethod', //获取配送方式

        'operationPackages' => 'directExpressOrders',//更新重量接口
    ];

    /**
     * HuaHan constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['access_token'], $config);
        $this->config = $config;
    }

    /**
     * 创建订单，生成跟踪号
     * @param array $params
     * @return mixed
     * @throws InvalidIArgumentException
     * @throws ManyProductException
     * {
     * "body": "{\"transNoPrintPath\":\"\",\"transNo\":\"\",\"orderPrintPath\":\"http://124.71.29.231:8090/group1/M00/13/47/wKgADF_R3juAeiq3AACpsjpAgG8049.pdf\",\"waybillNo\":\"test20201210_004\"}",
     * "message": "请求成功",
     * "result_code": 0
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
            $order_weight = 0;
            $order_price = 0;
            foreach ($item['productList'] as $value) {
                $skus[] = [
                    'Sku' => $value['productSku'] ?? '',// N:产品 SKU;Length <= 100
                    'Quantity' => (int)($value['quantity'] ?? ''),// Y:产品数量;数值必须为正整数
                    'Weight' => ceil(($value['grossWeight'] / $value['quantity'])) ?? ceil(($value['netWeight'] / $value['quantity'])),//单件重量(g)[取值是向上取整的]
                    'DeclareValue' => round((float)($value['declarePrice'] / $value['quantity'] ?? ''), 2), //Y:单件申报价值(USD) 【已过时】
                    'NewDeclareValue' => [
                        'Value' => round((float)($value['declarePrice'] / $value['quantity'] ?? ''), 2),//金额
                        'Currency' => $value['currencyCode'] ?? 'USD',// , //申报币种，不传值默认为USD(美元)；USD-美元,AUD-澳元
                    ],//单件申报价值信息,非必填
                    'DeclareNameEn' => $value['declareEnName'] ?? '',// Y:申报英文名称Length <= 100
                    'DeclareNameCn' => $value['declareCnName'] ?? '',// N:申报中文名称Length <= 100
                    'ProductName' => $value['declareCnName'] ?? '',//商品名称
                    'Price' => round((float)($value['declarePrice'] / $value['quantity'] ?? ''), 4),
                    'HsCode' => $value['hsCode'] ?? '',// N:海关编码
                ];
                $order_price += round((float)($value['declarePrice'] * $value['quantity']), 4);//订单总价格
                $order_weight += $value['declareWeight'];//订单总重量
                $currency = $value['currencyCode'] ?? 'USD';//申报销售产品币种
            }
            $shipToAddress = [
                'TaxId' => $item['recipientTaxNumber'] ?? '',//收件人税号或者VAT税号,例如巴西税号：自然人税号称为CPF码，格式为CPF:000.000.000.00；法人税号称为CNPJ码，格式为CNPJ:00.000.000/0000-00
                'Country' => $item['recipientCountryCode'] ?? '',//收货国家
                'Province' => $item['recipientState'] ?? '', //N:收件人省
                'City' => $item['recipientCity'] ?? '',//收件人城市
                'Street1' => $item['recipientStreet'] ?? '',//收件人地址1
                'Street2' => ($item['recipientStreet1'] ?? ' ') . ' ' . (empty($item['recipientStreet2']) ? '' : $item['recipientStreet2']),//收件人地址2//2021/12/14
                'Postcode' => $item['recipientPostCode'] ?? '',//邮编
                'Contact' => $item['recipientName'] ?? '',//收件人姓名
                'Company' => $item['recipientName'] ?? '',//公司名
                'Phone' => $item['recipientPhone'] ?? '', //N:收件人电话
                'Email' => $item['recipientEmail'] ?? '',//邮箱
            ];
            $exportsInfo = [
                'EoriCode' => '',
                'Country' => '',
                'Province' => '',
                'City' => '',
                'Street1' => '',
                'Street2' => '',
                'Postcode' => '',
                'Contact' => '',
                'Company' => '',
                'Phone' => '',
                'Email' => '',
            ];
            $packages = [
                'PackageId' => $item['customerOrderNo'],
                'ServiceCode' => $item['shippingMethodCode'] ?? 'CUE',//运输渠道，字符串
                'ShipToAddress' => $shipToAddress,
                'Weight' => round($item['predictionWeight'] * 1000, 2),//重量
                'Length' => round($item['packageLength'], 2),//长度
                'Width' => round($item['packageWidth'], 2),//宽度
                'Height' => round($item['packageHeight'], 2),//高度
                'Skus' => $skus,
                'ExportsInfo' => $exportsInfo ?? [],//经济运营商(出口)
                'ImportsInfo' => $exportsInfo ?? [],//经济运营商(进口)
                'SellPrice' => round($order_price, 4),//售价
                'SellPriceCurrency' => $currency,//销售币种
                'SalesPlatform' => $item['platformSource'] ?? '',//销售平台
                'OtherSalesPlatform' => $item['platformSource'] ?? '',//其他销售平台
                'ImportTrackingNumber' => '',//导入的跟踪号，如果服务允许导入跟踪号时有效
                'Custom' => '',//客户自定义，可以用于打印在地址标签Custom区域
                'Remark' => '',//备注；可用于打印配货清单，有多个以|分隔的值才会打印配货清单
                'VatCode' => '',//Vat税号或者IOSS号码
            ];
            //查询处理点列表
            //  $handleDelivery = $this->getHandleMethod(1, []);
            $data = [
                'Location' => '',
                //'Location'=>$handleDelivery??'',//处理点 如不填则使用商家默认，
                'Package' => $packages,//包裹信息
                'Remark' => '',//备注
            ];

            $ls[] = $data;
        }

        $response = $this->createOrderNumber(__FUNCTION__, $ls[0]);//第一次直接同步获取，如果没有获取到就调用异步接口

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::createOrder();


        //直接报错返回错误信息 2021/12/10
        if (empty($response['Status'])) {
            //直接有错误，返回
            $fieldData['flag'] = false;
            $fieldData['info'] = empty($response['Errors'][0]['Message']) ? '获取追踪号失败,平台未返回！' : $response['Errors'][0]['Message'];
            $resBody = $response;//直接赋值
        } else {
            // 否则返回结果
            $flag = $response['Status'] == 'Created';//有创建成功标识
            if (!$flag) {
                //异步调用获取订单追踪号接口  2021/11/20，芳强提出的bug，经物流商沟通，确需调用此接口
                $response = $this->asyncGetOrderStatus($ls[0]['Package']['PackageId']);//输入订单号参数
                $flag = $response['Status'] == 'Created';//有创建成功标识
            }
            $fieldData['flag'] = $flag ? true : false;
            $fieldData['info'] = $flag ? '' : (empty($response['CreateFailedReason']) ? (empty($response['Errors'][0]['Message']) ? '获取追踪号失败,平台未返回！' : $response['Errors'][0]['Message']) : $response['CreateFailedReason']);
            $resBody = $response ?? [];
            $reqRes = $this->getReqResData();
            // 获取追踪号
            if ($flag && !empty($resBody)) {
                if (empty($resBody['TrackingNumber'])) {
                    $fieldData['flag'] = false;
                    $fieldData['info'] = empty($resBody['CreateFailedReason']) ? (empty($resBody['Errors'][0]['Message']) ? '获取追踪号失败,平台未返回！' : $resBody['Errors'][0]['Message']) : $resBody['CreateFailedReason'];//创建订单时直接返回了
                }
                $fieldData['channel_hawbcode'] = $resBody['Ck1PackageId'];//转单号
            }
        }

        $fieldData['order_id'] = empty($resBody['Ck1PackageId']) ? '' : $resBody['Ck1PackageId'];//出口易处理号
        $fieldData['refrence_no'] = $ls[0]['Package']['PackageId'] ?? '';//$resBody['waybillNo']
        $fieldData['shipping_method_no'] = empty($resBody['TrackingNumber']) ? '' : $resBody['TrackingNumber'];//追踪号
        $fieldData['channel_hawbcode'] = empty($resBody['Ck1PackageId']) ? '' : $resBody['Ck1PackageId'];//转单号获取尾程追踪号

        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);

    }

    /**出口易下单接口
     * @param string $function
     * @param array $data
     * @return mixed
     * @throws \smiler\logistics\Exception\CurlException
     */
    public function createOrderNumber($function, $data = [])
    {
        $this->req_data = $data;
        $apiHeaders = $this->buildHeaders();//生成头部信息
        $response = $this->sendCurl('post', $this->config['create_order_url'], $data, $this->dataType, $apiHeaders);
        $this->res_data = $response;
        return $response;
    }

    /**删除订单，最多1个订单,单个取消
     * @param $function
     * @param array $data
     * @return mixed
     * @throws \smiler\logistics\Exception\CurlException
     */
    public function cancelOrder($function, $data = [])
    {
        $this->req_data = $data;
        $apiHeaders = $this->buildHeaders();//生成头部信息
        $response = $this->sendCurl('get', $this->config['delete_order_url'] . '/' . $data['packageId'] . '/cancel?idType=' . $data['idType'], [], $this->dataType, $apiHeaders);
        $this->res_data = $response;
        return $response;
    }


    /**
     * 当首次获取追踪号失败时，则异步调用此获取追踪号接口再次获取
     * @param $order_no
     * @return mixed
     */
    public function asyncGetOrderStatus($order_no)
    {
        $apiHeaders = $this->buildHeaders();//生成头部信息
        $response = $this->sendCurl('get', $this->config['delete_order_url'] . '/' . $order_no . '/status', [], $this->dataType, $apiHeaders);
        $this->res_data = $response;
        return $response;
    }


    /**
     * 修改订单重量
     * @param array $params
     * @return mixed|void
     */
    public function operationPackages(array $pars = [])
    {
        $params = [
            'PackageId' => $pars['order_id'] ?? '',// Y:客户单号（或者系统订单号，或者服务商单号都可以）
            'Weight' => empty($pars['weight']) ? 0 : round($pars['weight'] * 1000, 2),// N:包裹总重量（单位：g）,系统接收后自动四舍五入至2位小数
        ];
        if (empty($params)) {
            throw new InvalidIArgumentException("请求参数不能为空");
        }
        $this->req_data = $params;
        $apiHeaders = $this->buildHeaders();//生成头部信息
        $response = $this->sendCurl('put', $this->config['update_weight_url'], $params, $this->dataType, $apiHeaders);//put方式
        $this->res_data = $response;
        if (!empty($response)) {
            return $this->retErrorResponseData('更新重量失败');
        }
        return $this->retSuccessResponseData([]);
    }

    /**获取轨迹
     * @param $function
     * @param array $data
     * @return mixed
     * @throws \smiler\logistics\Exception\CurlException
     */
    public function getTrail($function, $data = [])
    {
        $this->req_data = $data;
        $apiHeaders = $this->buildHeaders();//生成头部信息
        $response = $this->sendCurl('post', $this->config['get_trail_url'], $data, $this->dataType, $apiHeaders);
        $this->res_data = $response;
        return $response;
    }

    /**获取面单
     * @param $function
     * @param array $data
     * @return mixed|string
     * @throws \smiler\logistics\Exception\CurlException
     */
    public function getLabelPrint($function, $data = [])
    {
        $this->req_data = $data;
        $apiHeaders = $this->buildHeaders();//生成头部信息
        $response = $this->sendCurl('post', $this->config['get_label_print_url'], $data, $this->dataType, $apiHeaders);
        $this->res_data = $response;
        return $response;
    }

    /**获取运输方式和处理点
     * @param $type
     * @param array $data
     * @return string
     */
    public function getHandleMethod($type, $data = [])
    {
        $this->req_data = $data;
        $res = [];
        if ($type == 1) {
            //获取运输方式列表
            $apiHeaders = $this->buildHeaders();//生成头部信息
            $response = $this->sendCurl('get', $this->config['get_trans_url'], $data, $this->dataType, $apiHeaders);
            if (is_array($response)) {
                $res = $response;
            }
        } else {
            //获取处理点
            $apiHeaders = $this->buildHeaders();//生成头部信息
            $response = $this->sendCurl('get', $this->config['get_handle_point_url'], $data, $this->dataType, $apiHeaders);
            if (is_array($response)) {
                $res = $response;
            }
        }
        $this->res_data = $response;
        return $res;
    }

    /**下单生成头文件
     * 组装生成头部信息，包括签名
     */
    public function buildHeaders()
    {
        $header = [];
        $header['Authorization'] = 'Bearer ' . $this->config['access_token'];
        $header['Content-Type'] = 'application/json; charset=utf-8';
        return $header;
    }


    /**
     * 删除订单
     *{"body": "{\"hasError\":true,\"successWayBillNos\":[\"21212121qqq\",\"rwe212121\",\"rerw1212131\",\"ewrerw12212121\",\"test1254000\",\"212121qwqwq\"],\"errorWayBillNoMaps\":{\"test20200911aa\":\"该运单在oms中不存在，请仔细检查！\"}}","message": "请求成功","result_code": 0}
     * @param string $order_code
     * @return mixed|void
     */
    public function deleteOrder(string $order_code)
    {
        $delOrderArray = $this->toArray($order_code);
        if (count($delOrderArray) > self::DEL_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "取消订单一次最多删除" . self::DEL_TRACK_COUNT . "个物流单号");
        }
        $data = [
            'packageId' => $order_code, //运单号码,[以,如有多个隔开]
            'idType' => 'PackageId',
        ];
        $response = $this->cancelOrder(__FUNCTION__, $data);
        // 结果
        $flag = empty($response) ? 1 : 0;
        return $flag;
    }

    /**
     * 获取面单
     * @return mixed
     * {"body":"{\"transNoPrintPath\":\"http://113.106.90.123:21000/pic/1d108be9-a362-4fa3-9159-40309a3c700d.pdf\",\"transNo\":\"Q1Qezzp2662TT11\"}","message": "请求成功","result_code": 0}
     */
    public function getPackagesLabel($params)
    {
        $trackNumberArray = $this->toArray($params['syOrderNo']);//用订单号和IdType一一对应,9.27用出口易处理号Ck1PackageId去获取
        if (count($trackNumberArray) > self::QUERY_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "查询面单一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
        }
        $data = [
            'PackageIds' => $trackNumberArray,//直发包裹Id列表,最多2000个
            'PrintFormat' => 'ClassicLabel',//ClassicLabel或者ClassicA4(经典标签纸或者经典A4纸)
            'PrintContent' => 'AddressCostomsSplit',//打印地址、报关单与配货清单（只支持ClassicLabel），包裹的remark有多个以|分隔的值才会打印配货清单
            'CustomPrintOptions' => ['Custom'],//RefNo,CustomsTitleEn,CustomsTitleCn,Custom,Remark,Sku
            'IdType' => 'Ck1PackageId',//PackageId和Ck1PackageId
        ];

        $response = $this->getLabelPrint(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        if (empty($response['Label'])) {
            return $this->retErrorResponseData('未知错误');
        }
        $bodyData = $response;

        if (count($bodyData) == count($bodyData, 1)) {
            $fieldData['flag'] = true;
            $fieldData['labelType'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF;
            $fieldData['labelPathPlat'] = '';//不要赋值，会出问题，导致上传不成功
            $fieldData['labelPath'] = $bodyData['Label'];//一维数组
            $fieldData[] = $fieldData;
        } else {
            foreach ($bodyData as $item) {
                $fieldData['flag'] = true;
                $fieldData['labelType'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF;
                $fieldData['labelPathPlat'] = $item['Label'];;
                $fieldData['labelPath'] = $item['Label'];;//二维数组
            }
        }

        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 获取物流商运输方式
     * @return mixed
     * {"body": "[{\"fieldName\":\"dhl\",\"fieldCode\":\"dhl\",\"isCustomerBinding\":true},{\"fieldName\":\"V5测试路线\",\"fieldCode\":\"V5-CS\",\"isCustomerBinding\":true},{\"fieldName\":\"演示指定路线\",\"fieldCode\":\"YSZDLX\",\"isCustomerBinding\":true}]","message": "请求成功","result_code": 0}
     */
    public function getShippingMethod()
    {
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();//字段映射
        $response = $this->getHandleMethod(1, []);
        if (!is_array($response)) {
            $this->retErrorResponseData();
        }
        $res = $response;
        foreach ($res as $item) {
            $item['code'] = $item['ServiceCode'];//strtoupper(LogisticsIdent::LS_IDENT_BHEO);//全大写
            $item['fieldCode'] = $item['ServiceCode'];
            $item['fieldName'] = $item['ServiceName'];
            $item['shipping_method_type'] = $item['IsTracking'];
            $item['extended'] = $item['InService'] == true ? '可用' : '不可用';
            $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
        }
        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 获取跟踪号
     * @param $processCode
     * @param $is_ret
     * @return array
     */
    public function getTrackNumber(string $processCode, $is_ret = false)
    {
        $apiHeaders = $this->buildHeaders();//生成头部信息
        $response = $this->sendCurl('get', $this->config['update_weight_url'] . '/' . $processCode . '/status', [], $this->dataType, $apiHeaders);
        if (empty($response['Status'])) {
            return $this->retErrorResponseData(empty($response['CreateFailedReason']) ? (empty($response['Errors'][0]['Message']) ? '获取追踪号失败,平台未返回！' : $response['Errors'][0]['Message']) : $response['CreateFailedReason']);
        }
        if ((!empty($response['IsFinalTrackingNumber'])) && ($response['IsFinalTrackingNumber'] == true)) {
            $response['TransferNumber'] = $response['TrackingNumber'];//转单号信息
        } else {
            $response['TransferNumber'] = '';//暂无转单号
        }
        return $this->retSuccessResponseData($response);
    }

    /**
     * 获取物流商轨迹queryNo
     * {"result_code":0,"message":"请求成功","solution":null,"body":{"datas":[{"trackRecord":"","scanTime":"2018-11-19 08:39","statusNo":"QG","isFinish":"0","operationPerson":"D33099","uploadDate":"2018-11-19 11:23","contact":"","location":"Pending clearance TH KERRY","id":0,"status":"清关中"},{"trackRecord":"","scanTime":"2018-11-19 01:39","statusNo":"HB","isFinish":"0","operationPerson":"D33099","uploadDate":"2018-11-19 11:23","contact":"","location":"Flight arrive TH KERRY","id":0,"status":"航班到达"},{"trackRecord":"","scanTime":"2018-11-19 00:10","statusNo":"QF","isFinish":"0","operationPerson":"D33099","uploadDate":"2018-11-19 11:21","contact":"","location":"Flight departed Head office","id":0,"status":"航班起飞"},{"trackRecord":"","scanTime":"2018-11-17 12:51","statusNo":"DF","isFinish":"0","operationPerson":"D29984","uploadDate":"2018-11-17 12:51","contact":"","location":"In Transit AT SZX Operating Center","id":0,"status":"出货"},{"trackRecord":"","scanTime":"2018-11-17 12:42","statusNo":"PU","isFinish":"0","operationPerson":"sys","uploadDate":"2018-11-17 12:42","contact":"","location":"Receive shipment SZX Operating Center","id":0,"status":"收件"}],"expectTime":"2018-12-29 15:57:04","transNo":"SHX660423527TH","status":"清关中","waybillNo":"SHX660423527TH"}}
     * @return mixed
     */
    public function queryTrack($trackNumber)
    {
        $trackNumberArray = $this->toArray($trackNumber);
        if (count($trackNumberArray) > self::QUERY_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "查询物流轨迹一次最多查询" . self::ORDER_COUNT . "个物流单号");
        }
        $data = [
            'TrackingNumbers' => $trackNumberArray,
        ];
        $response = $this->getTrail(__FUNCTION__, $data);
        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        if (empty($response[0])) {
            return $this->retErrorResponseData('未知错误');
        }

        $data = $response[0];

        $ls = [];
        foreach ($data['Checkpoints'] as $key => $val) {

            $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
        }
        $data['Checkpoints'] = $ls;
        $data['flag'] = isset($data) ? true : false;
        $data['message'] = '获取成功';
        $fieldData = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);
        return $this->retSuccessResponseData($fieldData);
    }
}