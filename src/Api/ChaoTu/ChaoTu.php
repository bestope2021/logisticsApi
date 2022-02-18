<?php
/**
 *
 * User: Ghh.Guan
 * Date: 5/21/21
 */

namespace smiler\logistics\Api\ChaoTu;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;


class ChaoTu extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface, TrackLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹,5自定义
     */
    const ORDER_COUNT = 2;
    /**
     * 一次最多查询多少个物流商
     */
    const QUERY_TRACK_COUNT = 1;

    public $iden = 'chaotu';

    public $iden_name = '超兔物流';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'xml';

    public $apiHeaders = [
        'Content-Type' => 'application/xml'
    ];

    public $interface = [

        'createOrder' => 'createAndAuditOrder', // 创建订单到已预报状态

        'queryTrack' => 'getTrack', //轨迹查询

        'deleteOrder' => 'deleteOrder', //删除订单。发货后的订单不可删除。

        'getShippingMethod' => 'getTransportWayList', //获取配送方式,

        'getPackagesLabel' => 'printOrder', // 【打印标签|面单

        'getTrackNumber' => 'lookupOrder',//获取追踪号

        'getShippingFee' => 'calculateCharge', //获取费用(运费试算)
    ];


    /**
     * ShiSun constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['userToken', 'url'], $config);
        $this->config = $config;
    }

    /**
     * 拼接array转为XML
     * @param $array
     * @param string $root
     * @return string
     */
    public static function arrayToXml($array, $root = 'ns1:callService', $encoding = 'utf-8')
    {
        $xml = '<?xml version="1.0" encoding="' . $encoding . '"?>';
        $xml .= '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://service.hop.service.ws.hlt.com/">';
        $xml .= '<soapenv:Header/>';
        $xml .= '<soapenv:Body>';
        $xml .= "<ser:{$root}>";
        $xml .= static::arrayToXmlInc($array);
        $xml .= "</ser:{$root}>";
        $xml .= "</soapenv:Body>";
        $xml .= "</soapenv:Envelope>";
        return $xml;
    }


    /**
     * 参数转为xml格式化
     * @param $array
     * @return string
     */
    public static function arrayToXmlInc($array)
    {
        $xml = '';
        foreach ($array as $key => $val) {
            if (empty($val)) continue;
            if (is_array($val)) {
                if (is_numeric($key)) {
                    $xml .= static::arrayToXmlInc($val);
                } else {
                    $xml .= "<$key>";
                    $xml .= static::arrayToXmlInc($val);
                    $xml .= "</$key>";
                }
            } else {
                $xml .= "<$key>$val</$key>";
            }
        }
        return $xml;
    }

    /**
     * 最后将xml解析为array数组
     * @param $xml
     * @return mixed
     */
    public static function xmlToArray($xml)
    {
        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $xml);
        $xml = new \SimpleXMLElement($response);
        $body = $xml->xpath('//return')[0];
        $array = json_decode(json_encode($body), true);
        return $array;
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
            $productList = $packageItems = [];
            $order_weight = $order_price = 0;
            foreach ($item['productList'] as $index => $value) {
                $productList[] = [
                    'productMemo' => $value['productSku'],//配货备注。用于配货，可打印在运单的配货信息中。length<=255
                    'pieces' => (int)($value['quantity'] ?? 0),
                    'netWeight' => round($value['declareWeight'], 3) ?? '',//净重KG
                    'name' => $value['declareEnName'] ?? '',//报关英文名0<length<100
                    'cnName' => $value['declareCnName'] ?? '',//报关中文名
                    'unitPrice' => (float)(round($value['declarePrice'], 2) ?? ''),//【必填】申报价值,默认为USD,若不填写算法逻辑为：明细申报价值 = 包裹单总价值 * 40 % / 明细总数量之和，下限3上限10；但是不能小于1（小于1则默认为1）。例如，包裹申报金额为10，含有两个明细，每个明细数量为2，每个明细申报价值 = 10 / 4 = 2.5
                    'customsNo' => $value['hsCode'] ?? '',
                ];
                $packageItems[] = [
                    'length' => round($value['length'], 3) ?? '0.000',//长,单位CM0<=value<=10000
                    'width' => round($value['width'], 3) ?? '0.000',//宽,单位CM0<=value<=10000
                    'height' => round($value['height'], 3) ?? '0.000',//高,单位CM0<=value<=10000
                    'weight' => round($value['declareWeight'], 3) ?? '0.000',//包裹预计重量(KG)0<=value<=10000
                ];
                $order_weight += $value['declareWeight'];
                $order_price += $value['declarePrice'];
            }
            $shipper = [
                'shipperCompanyName' => $item['senderCompany'] ?? '',//发件人公司名称
                'shipperName' => $item['senderName'] ?? '',//发件人姓名
                'shipperAddress' => $item['senderAddress'] ?? '',//发件人地址
                'shipperTelephone' => $item['senderPhone'] ?? '',//发件人电话
                'shipperMobile' => $item['senderPhone'] ?? '',//发件人电话
                'shipperPostcode' => $item['senderPostCode'] ?? '',//发件人邮编
                'shipperStreetNo' => $item['senderHouseNumber'] ?? '',//发件人门牌号/建筑物名称。
                'shipperStreet' => $item['senderDistrict'] ?? '',//发件人街道
                'shipperCity' => $item['senderCity'] ?? '',//发件人城市
                'shipperProvince' => $item['senderState'] ?? '',//发件人省份
            ];
            $reciper = [
                'consigneeName' => $item['recipientName'] ?? '',//收件人姓名
                'street' => ($item['recipientStreet'] ?? ' ') . ' ' . ($item['recipientStreet1'] ?? ' ') . ' ' . (empty($item['recipientStreet2']) ? '' : $item['recipientStreet2']),//收件人地址
                'city' => $item['recipientCity'] ?? '',//收件人城市
                'province' => $item['recipientState'] ?? '', //N:收件人省
                'consigneePostcode' => $item['recipientPostCode'] ?? '',//邮编
            ];
            $tax_no = $item['recipientTaxNumber'] ?? '';
            $ls[] = [
                'orderNo' => $item['customerOrderNo'] ?? '',//客户单号
                'transportWayCode' => $item['shippingMethodCode'] ?? 'CTTKUS',//运输方式代码。通过接口getTransportWayList可查询到所有运输方式。
                'cargoCode' => 'W',//货物类型。取值范围[W:包裹/D:文件]
                'originCountryCode' => $item['senderCountryCode'] ?? '',//起运国家二字简码。
                'destinationCountryCode' => $item['recipientCountryCode'] ?? '',//目的国家二字简码
                'pieces' => (int)array_sum(array_column($item['productList'], 'quantity')),//货物件数。value>=1
                'length' => empty($item['packageLength']) ? '0.000' : round($item['packageLength'], 3),//长
                'width' => empty($item['packageWidth']) ? '0.000' : round($item['packageWidth'], 3),//宽
                'height' => empty($item['packageHeight']) ? '0.000' : round($item['packageHeight'], 3),//高
                $shipper,
                $reciper,
                'weight' => empty($order_weight) ? '0.000' : round($order_weight, 3),//货物预报重量（kg）。0<=value<=1000
                'insured' => 'N',//购买保险（投保：Y，不投保：N）
                'goodsCategory' => 'O',//物品类别。取值范围[G:礼物/D:文件/S:商业样本/R:回货品/O:其他]
                'codSum' => empty($item['packageCodAmount']) ? '0.000' : round($item['packageCodAmount'], 3),//cod金额
                'codCurrency' => $item['packageCodCurrencyCode'] ?? '',//COD币种Length<=3
                'declareItems' => $productList,
                'packageItems' => $packageItems,// 包裹明细列表（选填，一票多件时使用，一票一件时长宽高直接填写在订单货物长宽高重）
                'additionalJson' => json_encode(array('rtaxNo' => $tax_no)),
            ];
            $customerOrderNo = $item['customerOrderNo'] ?? '';
        }
        $response = $this->request(__FUNCTION__, ['createOrderRequest' => $ls[0]]);
        // 处理结果
        $reqRes = $this->getReqResData();
        $fieldData = [];
        $fieldMap = FieldMap::createOrder();
        // 结果
        $flag = $response['success'] == 'true';

        //重复下单，删除原单
        if (!$flag) {
            if ((stripos($response['error']['errorInfo'], '已经存在')) || (stripos($response['error']['errorInfo'], '已存在'))) {
                $get_id_res = $this->getTrackNumber($customerOrderNo);//通过客户订单号获取orderId
                $get_id = '';
                if ($get_id_res['flag']) {
                    $get_id = empty($get_id_res['frtTrackingNumber']) ? '' : $get_id_res['frtTrackingNumber'];
                }

//                if (!empty($get_id)) {
//                    $delete_res = $this->deleteOrder($get_id);//删除原订单,通过orderId
//                    if ($delete_res) {
//                        //然后重新下单
//                        $response = $this->request(__FUNCTION__, ['createOrderRequest' => $ls[0]]);
//                        $flag = $response['success']=='true';//重新赋值条件
//                    }
//                }
            }
        }
        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : (empty($response['error']['errorInfo']) ? '未知错误' : $response['error']['errorInfo']);
        $fieldData['orderNo'] = $customerOrderNo;//客户订单号
        $fieldData['trackingNo'] = $flag ? (empty($response['trackingNo']) ? (empty($get_id_res['trackingNumber']) ? '' : $get_id_res['trackingNumber']) : $response['trackingNo']) : '';//追踪号
        $fieldData['id'] = $flag ? (empty($response['id']) ? (empty($get_id) ? '' : $get_id) : $response['id']) : '';//第三方id，用空运单号代替
        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);
    }


    public function request($function, $data = [])
    {
        $data = $this->buildParams($function, $data);
        $this->req_data = $data;
        $res = $this->sendCurl('post', $this->config['url'], $data, $this->dataType, $this->apiHeaders, 'UTF-8', $this->interface[$function]);
        $this->res_data = $res;
        return $res;
    }

    /**
     * 拼接userToken
     */
    public function buildParams($interface, $arr = [])
    {
        $arr['userToken'] = $this->config['userToken'];
        return $arr;
    }

    /**
     * 通过客户单号获取费用
     * @param string $processCode
     * @return mixed|string
     */
    public function getShippingFee(string $processCode)
    {
        if (empty($processCode)) {
            return '';
        }

        $extUrlParams = ['orderNo' => $processCode];
        $response = $this->request(__FUNCTION__, ['lookupOrderRequest' => $extUrlParams]);
        // 结果
        $flag = $response['success'] == 'true';
        if (!$flag) {
            return '';
        }
        $ret = $response['order'];
        return $ret;
    }

    /**
     * 获取追踪号信息
     * @param string $processCode
     * @param bool $is_ret
     * @return array
     */
    public function getTrackNumber(string $processCode, $is_ret = false)
    {
        $params = [
            'orderNo' => $processCode, //客户单号。length <= 32，orderId、orderNo、trackingNo不能全部为空。
        ];
        $response = $this->request(__FUNCTION__, ['lookupOrderRequest' => $params]);
        $fieldData = [];
        $fieldMap = FieldMap::getTrackNumber();
        $flag = $response['success'] == 'true';
        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : (empty($response['error']['errorInfo']) ? '未知错误' : $response['error']['errorInfo']);
        $fieldData['trackingNo'] = $flag ? ($response['order']['trackingNo'] ?? '') : '';//追踪号
        $fieldData['frt_channel_hawbcode'] = $flag ? (empty($response['order']['hawbCode']) ? (empty($response['order']['orderId']) ? '' : $response['order']['orderId']) : $response['order']['hawbCode']) : '';//尾程追踪号或者是转单号
        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
        if ($is_ret) return $fieldData['flag'] ? $this->retSuccessResponseData($ret) : $this->retErrorResponseData($fieldData['info'], $fieldData);
        return $ret;
    }


    /**
     * 获取物流商运输方式
     * @return mixed
     */
    public function getShippingMethod()
    {
        $data = [
            'Data' => [],
        ];
        $res = $this->request(__FUNCTION__, $data);
        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();
        if ($res['success'] == 'false') {
            return $this->retErrorResponseData($res['error']['errorInfo'] ?? '未知错误');
        }
        if (!empty($res['transportWays'])) {
            foreach ($res['transportWays'] as $item) {
                $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap);
            }
        }
        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 修改重量
     * @return mixed
     */
    public function operationPackages(array $pars = [])
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    /**
     * 取消订单，删除订单
     * @return mixed
     */
    public function deleteOrder(string $order_id)
    {
        $param = [
            'orderId' => $order_id,
        ];
        $response = $this->request(__FUNCTION__, $param);
        return $response['success'] == 'true';
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
     *
     * 获取订单标签(创建订单有返回并保存)
     * @return mixed
     */
    public function getPackagesLabel($params = [])
    {
        $data = [
            'oid' => $params['trackNumber'],//sy_order_no,客户ID
            'downloadPdf' => 0,//是否下载PDF，0代表直接打开（默认），1代表下载
            'printSelect' => 3,//选择打印样式“1” 地址标签打印“11” 报关单“2” 地址标签+配货信息“3” 地址标签+报关单（默认）“13”地址标签+(含配货信息)“12” 地址标签+(含配货信息)+报关单“15” 地址标签+报关单+配货信息
            'pageSizeCode' => 6,//纸张尺寸，“1”表示80.5mm × 90mm“2”表示105mm × 210mm“7”表示100mm × 150mm“4”表示102mm × 76mm“5”表示110mm × 85mm“6”表示100mm × 100mm（默认）“3”表示A4
            'showCnoBarcode' => 0,//是否显示客户单号，0代表不显示（默认），1代表显示
            'showRecycleTags' => 0,//是否显示回收标签 ，1 显示，0不显示
        ];
        $response = $this->request(__FUNCTION__, ['printOrderRequest' => $data]);
        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();
        // 结果
        $flag = $response['success'] == 'true';
        $error_info = '';
        if (!$flag) {
            $error_info = empty($response['error']['errorInfo']) ? '未知错误' : $response['error']['errorInfo'];
            return $this->retErrorResponseData($error_info);
        }
        $response['flag'] = $flag;
        $response['info'] = $flag ? '' : $error_info;
        $response['order_no'] = $params['trackNumber'] ?? '';
        $response['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_PDF;//直接返回pdf文件路径url
        $response['lable_file'] = $flag ? $response['url'] : '';
        $response['label_path_plat'] = '';//不要填写
        $response['lable_content_type'] = 1;
        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($response, $fieldMap);
        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 获取物流商轨迹
     *
     * @return mixed
     */
    public function queryTrack($trackNumber)
    {
        $trackNumberArray = $this->toArray($trackNumber);
        if (count($trackNumberArray) > self::QUERY_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "查询物流轨迹一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
        }
        $data = [
            'trackingNo' => $trackNumber,
        ];
        $response = $this->request(__FUNCTION__, $data);

        // 结果
        $flag = $response['success'] == 'true';

        // 结果
        if (!$flag) {
            return $this->retErrorResponseData($response['error']['errorInfo'] ?? '未知错误');
        }

        // 处理结果
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);
        $datas = isset($response['trace']) ? $response['trace'] : null;

        $ls = [];
        //$datas是二维数组,rPaths收件国地址
        if (!empty($datas)) {
            foreach ($datas as $key => $val) {
                $info = [
                    'status' => $val['status'],
                    'pathInfo' => $val['pathInfo'],
                    'pathTime' => $val['pathTime'],
                    'pathAddr' => $val['pathAddr'],
                ];
                $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($info, $fieldMap2);
            }
        }
        $data['flag'] = $flag;
        $data['info'] = $flag ? $response['status'] : '';
        $data['status'] = $flag ? $response['status'] : '';
        $data['tno'] = $trackNumber;
        $data['sPaths'] = $ls;
        $fieldData = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);
        return $this->retSuccessResponseData($fieldData);
    }

    public function getPackagesDetail($order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }
}