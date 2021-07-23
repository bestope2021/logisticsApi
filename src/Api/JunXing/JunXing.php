<?php
/**
 *
 * User: blaine
 * Date: 2/22/21
 */

namespace smiler\logistics\Api\JunXing;


use smiler\logistics\Api\JunXing\FieldMap;
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
 * 骏兴头程物流
 * @link http://szdbf.rtb56.com/usercenter/manager/api_document.aspx
 * Class JunXing
 * @package smiler\logistics\Api\JunXing
 */
class JunXing extends LogisticsAbstract implements TrackLogisticsInterface, PackageLabelLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹
     */
    const ORDER_COUNT = 1;
    /**
     * 一次最多查询多少个跟踪号
     */
    const QUERY_TRACK_COUNT = 1;
    /**
     * 一次最多删除多少个跟踪号
     */
    const DEL_TRACK_COUNT = 200;

    public $iden = 'junxing';

    public $iden_name = '骏兴头程物流';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'json';

    public $apiHeaders = [];

    public $interface = [
        'createOrder' => 'createorder', // 【创建订单】

        'getPackagesLabel' => 'getnewlabel', // 【打印标签|面单】

        'getTrackNumber' => 'gettrackingnumber',//获取跟踪号

        'queryTrack' => 'gettrack', //轨迹查询

        'deleteOrder' => 'removeorder', //删除订单

        'getShippingMethod' => 'getshippingmethod', //获取配送方式
    ];

    /**
     * HuaHan constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['appId', 'appSecret'], $config);
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
            $customs = [];//海关信息
            $order_weight = 0;
            $order_price = 0;
            foreach ($item['productList'] as $value) {
                $customs[] = [
                    'cargoNameCn' => $value['declareCnName'] ?? '',// Y:申报英文名称Length <= 50
                    'cargoNameEn' => $value['declareEnName'] ?? '',// N:申报中文名称Length <= 50
                    'declareQty' => (int)($value['quantity'] ?? ''),// Y:产品数量;数值必须为正整数
                    'unitPrice' => round((float)($value['declarePrice'] ?? ''), 3), //Y:申报单价
                    'currency' => $value['currencyCode'] ?? 'USD',// , //申报币种，不传值默认为USD(美元)；USD-美元,AUD-澳元
                ];
                $OrderSingInfoVOs[] = [
                    'length' => round((float)($value['length'] ?? ''), 3),//长度
                    'width' => round((float)($value['width'] ?? ''), 3),//宽度
                    'height' => round((float)($value['height'] ?? ''), 3),//高度
                    'vol' => round((float)($value['length'] * $value['width'] * $value['height'] ?? ''), 3),//体积
                    'weight' => round((float)($value['netWeight'] ?? ''), 3),//单件重
                    'cartonNo' => '',//箱号
                ];
                $order_price += round((float)($value['declarePrice'] * $value['quantity']), 3);
                //    $product_type = $value['modelType'] ?? '';
                $order_weight += $value['declareWeight'];
            }
            //货物类型
            $product_type = $this->getOptionDetail(__FUNCTION__, ['tableName' => 'd_goods_type']);
            //包装类型
            $package_type = $this->getOptionDetail(__FUNCTION__, ['tableName' => 'd_package_type']);
            //付款方式
            $pay_type = $this->getOptionDetail(__FUNCTION__, ['tableName' => 'd_payType']);
            //税金支付
            $payment_type = $this->getOptionDetail(__FUNCTION__, ['tableName' => 'd_payment']);
            //报关方式
            $declare_type = $this->getOptionDetail(__FUNCTION__, ['tableName' => 'd_declare_method']);
            $data = [
                'cargoName' => $item['productList'][0]['declareCnName'] ?? '',// 产品名称
                'customs' => $customs,//海关信息
                'OrderSingInfoVOs' => $OrderSingInfoVOs,//单件实体定义
                'declareType' => 1,//报关类型(1出口,2进口)
                'declareValue' => $order_price ?? '', //报关价格
                'destNo' => $item['recipientCountryCode'] ?? '', //Y:收件人国家二字代码
                'goodsType' => empty($product_type) ? '普货' : $product_type,//货物类型【值通过对接简易资料接口获取】
                'packageType' => empty($package_type) ? 'WPX' : $package_type,//包装类型【值通过对接简易资料接口获取】,WPX,DOC,PAK
                'payType' => empty($pay_type) ? 'PP' : $pay_type,//付款方式【值通过对接简易资料接口获取】
                'payment' => empty($payment_type) ? '税金支付' : $payment_type,//税金支付【值通过对接简易资料接口获取】
                'declareMethod' => empty($declare_type) ? '' : $declare_type,//报关方式【值通过对接简易资料接口获取】
                'pcs' => 1,//件数
                'receiverAddr' => $item['recipientStreet'] ?? '',//收件人地址
                'receiverCity' => $item['recipientCity'] ?? '',//收件人城市
                'receiverCompanyName' => $item['recipientName'] ?? '',//公司名
                'receiverCountry' => $item['recipientCountryCode'] ?? '',//收货国家
                //'receiverCountry' => 'GB',//收货国家
                'receiverEmail' => $item['recipientEmail'] ?? '',//邮箱
                'receiverName' => $item['recipientName'] ?? '',//收件人姓名
                'receiverPostcode' => $item['recipientPostCode'] ?? '',//邮编
                'receiverProvince' => $item['recipientState'] ?? '', //N:收件人省
                'receiverTel' => $item['recipientPhone'] ?? '', //N:收件人电话
                'refNo' => $item['customerOrderNo'] ?? '',//客户原单号
                'remark' => '', //备注
                'senderAddr' => $item['senderFullAddress'] ?? '',// N:发件人完整地址Length <= 200
                'senderCity' => $item['senderCity'] ?? '',// Y:发件人城市Length<=64
                'senderCompanyName' => $item['senderCompany'] ?? '', // N:发件人公司名
                'senderCountry' => $item['senderCountryCode'] ?? '', // Y:发件人国家二字码
                'senderEmail' => $item['senderEmail'] ?? '', //N:发件人邮箱
                'senderName' => $item['senderName'] ?? '', //Y:发件人姓名
                'senderPostcode' => $item['senderPostCode'] ?? '',// N:发件人邮编Length <= 32
                'senderProvince' => $item['senderState'] ?? '', // Y:发件人省
                'senderTel' => $item['senderPhone'] ?? '', //Y:发件人电话
                'transNo' => '',//转单号
                'transType' => $item['shippingMethodCode'] ?? 'JX中英卡航特快不包税',//运输渠道，字符串
                //'transType' => 'JX中英卡航特快不包税',
                'universalCompile' => '',
                'waybillNo' => '',//转单号
                'weight' => round($order_weight, 3),//申报总重量
            ];

            $ls[] = $data;
        }

        $response = $this->createOrderNumber(__FUNCTION__, $ls[0]);

        $reqRes = $this->getReqResData();

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::createOrder();

        // 结果
        $flag = empty($response['result_code']) ? 1 : 0;
        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['message'] ?? ($response['message'] ?? ''));
        $resBody = empty($response['result_code']) ? json_decode($response['body'], true) : [];

        // 获取追踪号
        if ($flag && !empty($resBody)) {
            $trackNumberResponse = $this->getTrackNumber($resBody['waybillNo']);
            if($trackNumberResponse['flag']){
                $fieldData['trackingNo'] = $trackNumberResponse['trackingNumber']?? '';//追踪号
                $fieldData['frt_channel_hawbcode'] = $trackNumberResponse['frtTrackingNumber'] ?? '';//尾程追踪号
            }
        }

        $fieldData['order_id'] = $ls[0]['refNo'] ?? '';
        $fieldData['refrence_no'] = $ls[0]['refNo'] ?? '';//$resBody['waybillNo']
        $fieldData['trackingNo'] = $resBody['waybillNo'] ?? '';//追踪号
        $fieldData['frt_channel_hawbcode'] = $trackNumberResponse['frtTrackingNumber'] ?? '';//转单号

        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);

        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);

    }

    /**骏兴头程下单接口
     * @param string $function
     * @param array $data
     * @return mixed
     * @throws \smiler\logistics\Exception\CurlException
     */
    public function createOrderNumber($function, $data = [])
    {
        $this->req_data = $data;
        $apiHeaders = $this->buildHeaders(1);//生成头部信息
        $response = $this->sendCurlNew('post', $this->config['create_order_url'], $data, $this->dataType, $apiHeaders);
        $this->res_data = $response;
        return $response;
    }

    /**骏兴头程获取追踪号
     * @param $function
     * @param array $data
     * @return mixed
     * @throws \smiler\logistics\Exception\CurlException
     */
    public function getTraceNum($function, $data = [])
    {
        $this->req_data = $data;
        $apiHeaders = $this->buildHeaders(2);//生成头部信息
        $response = $this->sendCurlNew('post', $this->config['trace_number_url'], $data, $this->dataType, $apiHeaders);
        $this->res_data = $response;
        return $response;
    }
    /**
     * 获取跟踪号
     * @param $processCode
     * @param $is_ret
     * @return array
     */
    public function getTrackNumber(string $processCode, $is_ret = false)
    {
        $params = [
            'queryNos' => $processCode,// $reference_no, //查询号码【一般是运单号码；也支持参考编号】
            'queryType' => 1,//查询类型【1是运单号；2是客户单单号即参考编号】
        ];
        $response = $this->getTraceNum(__FUNCTION__, $params);
        $fieldData = [];
        $fieldMap = FieldMap::getTrackNumber();
        $flag = empty($response['result_code']) ? 1 : 0;
        $trackNumber = $flag ? json_decode($response['body'], true) : '';
        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['message'] ?? ($response['message'] ?? '未知错误'));
        $fieldData['trackingNo'] = $flag ? $processCode : '';//追踪号
        $fieldData['frt_channel_hawbcode'] = $flag ? $trackNumber['transNo'] : '';//尾程追踪号
        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
        if ($is_ret) return $fieldData['flag'] ? $this->retSuccessResponseData($ret) : $this->retErrorResponseData($fieldData['info'], $fieldData);
        return $ret;
    }

    /**删除订单，最多200个订单
     * @param $function
     * @param array $data
     * @return mixed
     * @throws \smiler\logistics\Exception\CurlException
     */
    public function cancelOrder($function, $data = [])
    {
        $this->req_data = $data;
        $apiHeaders = $this->buildHeaders(6);//生成头部信息
        $response = $this->sendCurlNew('post', $this->config['del_order_url'], $data, $this->dataType, $apiHeaders);
        $this->res_data = $response;
        return $response;
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
        $apiHeaders = $this->buildHeaders(3);//生成头部信息
        $response = $this->sendCurlNew('post', $this->config['get_trail_url'], $data, $this->dataType, $apiHeaders);
        $this->res_data = $response;
        return $response;
    }

    public function getTransport($function, $data = [])
    {
        $this->req_data = $data;
        $apiHeaders = $this->buildHeaders(7);//生成头部信息
        $response = $this->sendCurlNew('post', $this->config['get_trans_url'], $data, $this->dataType, $apiHeaders);
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
        $res = '';
        $apiHeaders = $this->buildHeaders(5);//生成头部信息
        $response = $this->sendCurlNew('post', $this->config['get_label_print_url'], $data, $this->dataType, $apiHeaders);
        $this->res_data = $response;
        return $response;
    }

    /**获取税号，支付类型等详情
     * @param $function
     * @param array $data
     * @return mixed
     * @throws \smiler\logistics\Exception\CurlException
     */
    public function getOptionDetail($function, $data = [])
    {
        $this->req_data = $data;
        $res = '';
        $apiHeaders = $this->buildHeaders(4);//生成头部信息
        $response = $this->sendCurlNew('post', $this->config['get_option_detail_url'], $data, $this->dataType, $apiHeaders);
        $this->res_data = $response;
        if ((empty($response['result_code'])) && (!empty($response['body']))) {
            $bodyStr = json_decode($response['body'], true);
            $detailArr = array_combine(array_column($bodyStr, 'isDefault'), array_column($bodyStr, 'fieldName'));
            $res = $detailArr[1];//只取默认值
        }
        return $res;
    }

    /**下单生成头文件
     * 组装生成头部信息，包括签名
     */
    public function buildHeaders($type)
    {
        $header = [];
        $header['appid'] = $this->config['appId'];
        $header['body'] = $this->req_data;
        if ($type == 1) {
            $header['command'] = $this->config['create_order_command'];//创建订单/下单
        } elseif ($type == 2) {
            $header['command'] = $this->config['trace_num_command'];//获取追踪号
        } elseif ($type == 3) {
            $header['command'] = $this->config['get_trail_command'];//获取轨迹
        } elseif ($type == 4) {
            $header['command'] = $this->config['get_option_detail_command'];//获取包装，货物，付款，税金，报关等详情
        } elseif ($type == 5) {
            $header['command'] = $this->config['get_label_print_command'];//获取面单
        } elseif ($type == 6) {
            $header['command'] = $this->config['del_order_command'];//取消订单
        } elseif ($type == 7) {
            $header['command'] = $this->config['get_trans_command'];//获取运输方式
        }
        $header['device_id'] = $this->config['deviceId'];
        $header['encrypt_type'] = $this->config['encrypt_type'];
        $header['version'] = $this->config['version'];
        $header['appSecret'] = $this->config['appSecret'];
        $header['sign'] = FieldMap::createSign($header);
        $header['reqUuid'] = FieldMap::createGuid();
        $header['isEncoded'] = $this->isEncoded($this->req_data);
        //$header['token'] = '';//暂时不需要token
        //$header['Content-Type'] = 'application/x-www-form-urlencoded';//表单提交
        return $header;
    }


    /**加密body
     * @param $encoded
     * @return false|string
     */
    public function encodedBody($encoded)
    {
        if (!empty($encoded)) {
            return urlencode(mb_convert_encoding(json_encode($this->req_data, JSON_UNESCAPED_UNICODE), 'UTF-8', 'GB2312'));
        } else {
            return json_encode($this->req_data, JSON_UNESCAPED_UNICODE);
        }
    }

    /**判定body中是否有特殊字符，并进行编码
     * @param $req_data
     */
    public function isEncoded($req_data)
    {
        //被查找的字符串
        $string = json_encode($req_data, JSON_UNESCAPED_UNICODE);

        //指定的字符串
        $arr = '/[#|%|+|-]+/u';//匹配模式

        preg_match_all($arr, $string, $wordsFound);

        //获取匹配到的字符串，array_unique()函数去重。如需获取总共出现次数，则不需要去重
        $wordsFound = array_unique($wordsFound[0]);

        if (count($wordsFound) >= 1) {
            return true;
        } else {
            return false;
        }

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
            'wayBillNos' => $order_code, //运单号码,[以,如有多个隔开]
            'amendment' => '【佰事德批量删除订单】',//取消原因
        ];
        $response = $this->cancelOrder(__FUNCTION__, $data);
        return $response;
    }

    /**
     * 获取面单
     * @return mixed
     * {"body":"{\"transNoPrintPath\":\"http://113.106.90.123:21000/pic/1d108be9-a362-4fa3-9159-40309a3c700d.pdf\",\"transNo\":\"Q1Qezzp2662TT11\"}","message": "请求成功","result_code": 0}
     */
    public function getPackagesLabel($params)
    {
        $trackNumberArray = $this->toArray($params['trackingNumber']);
        if (count($trackNumberArray) > self::QUERY_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "查询面单一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
        }
        $data = [
            'WaybillNo' => $params['trackingNumber'],//运单号码
        ];
        $response = $this->getLabelPrint(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();
        if (!empty($response['result_code'])) {
            return $this->retErrorResponseData($response['message'] ?? '未知错误');
        }
        $bodyData = json_decode($response['body'], true);

        if (count($bodyData) == count($bodyData, 1)) {
            $fieldData['flag'] = true;
            $fieldData['labelType'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_PDF;
            $fieldData['labelPathPlat'] = $bodyData['transNoPrintPath'];
            $fieldData['labelPath'] = $bodyData['transNoPrintPath'];
            $fieldData[] = $fieldData;
        } else {
            foreach ($bodyData as $item) {
                $fieldData['flag'] = true;
                $fieldData['labelType'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_PDF;
                $fieldData['labelPathPlat'] = $item['transNoPrintPath'];
                $fieldData['labelPath'] = $item['transNoPrintPath'];
                //$fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
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
        $response = $this->getTransport(__FUNCTION__, []);
        if (!empty($response['result_code'])) {
            $this->retErrorResponseData($response['message']??'骏兴头程物流商获取运输方式接口异常，获取失败！');
        }
        if ((empty($response['result_code'])) && (!empty($response['body']))) {
            $res = json_decode($response['body'], true);
            foreach ($res as $item) {
                $item['code'] = $item['fieldCode'];//strtoupper(LogisticsIdent::LS_IDENT_JUNXING);//全大写
                $item['shipping_method_type'] = '';
                $item['remark'] = LogisticsIdent::LS_IDENT_JUNXING;
                $item['extended'] = $item['isCustomerBinding'] == true ? '是绑定到客户' : '不是绑定到客户';
                $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
            }
        }

        return $this->retSuccessResponseData($fieldData);
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
            throw new InvalidIArgumentException($this->iden_name . "查询物流轨迹一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
        }
        $data = [
            'queryNo' => $trackNumber,
        ];
        $response = $this->getTrail(__FUNCTION__, $data);
        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        if (!empty($response['result_code'])) {
            return $this->retErrorResponseData($response['message'] ?? '未知错误');
        }

        $data = json_decode($response['body'], true);

        $ls = [];
        foreach ($data['datas'] as $key => $val) {

            $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
        }
        $data['datas'] = $ls;
        $data['flag'] = $response['result_code'] == 0 ? true : false;
        $data['message'] = $response['message'];
        $fieldData = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);

        return $this->retSuccessResponseData($fieldData);
    }
}