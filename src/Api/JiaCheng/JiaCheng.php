<?php
/**
 *
 * User: Ghh.Guan
 * Date: 4/21/21
 */

namespace smiler\logistics\Api\JiaCheng;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

class JiaCheng extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface, TrackLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹,5自定义
     */
    const ORDER_COUNT = 2;
    /**
     * 一次最多查询多少个物流商
     */
    const QUERY_TRACK_COUNT = 1;
    public $iden = 'jiacheng';
    public $iden_name = '佳成物流';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'form';

    public $apiHeaders = [

    ];

    public $interface = [

        'createOrder' => 'orders', // 创建并预报订单
        'getShippingMethod' => 'products', //获取配送方式
        'getPackagesLabel' => 'geterplabels', //获取面单
        'deleteOrder' => 'cancelorder', //取消订单
        'operationPackages' => 'updateweight', //修改重量
        'getTrackNumber' => 'turns', //获取追踪号
    ];

    /**
     * JiaCheng constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['customerid','customer', 'url'], $config);
        $this->config = $config;
        if (!empty($config['apiHeaders'])) {
            $this->apiHeaders = array_merge($this->apiHeaders, $config['apiHeaders']);
        }
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
            $productList = [];
            $invoiceinformation = [];
            $order_weight = 0;
            foreach ($item['productList'] as $key => $value) {
                $invoiceinformation[] = [
                    'chinesename' => $value['declareCnName'] ?? '',//中文品名
                    'englishname' => $value['declareEnName'] ?? '',//英文品名
                    'hscode' => $value['hsCode'] ?? '',//HS编码
                    'inpieces' => (int)($value['quantity'] ?? '') ?? '',//内件数
                    'unitpriceamount' => (float)($value['declarePrice'] ?? ''),//单价金额
                    'declarationamount' => (float)($value['declarePrice'] ?? '') * (int)($value['quantity'] ?? ''),//申报金额
                    'declarationcurrency' => $value['currencyCode'] ?? 'USD',//申报币种
                ];
                $order_weight += $value['declareWeight'];
                $productList[] = [
                    'chinesename' => $value['declareCnName'] ?? '',//中文品名
                    'englishname' => $value['declareEnName'] ?? '',//英文品名
                    'hscode' => $value['hsCode'] ?? '',//HS编码
                    'inpieces' => (int)($value['quantity'] ?? '') ?? '',//内件数
                    'unitpriceamount' => (float)($value['declarePrice'] ?? ''),//单价金额
                    'declarationamount' => (float)($value['declarePrice'] ?? '') * (int)($value['quantity'] ?? ''),//申报金额
                    'declarationcurrency' => $value['currencyCode'] ?? 'USD',//申报币种
                ];
            }
            $detailpackage = [
                'actualweight' => $order_weight??'',//实重
                'length' => round($item['packageLength'], 3)??'',//长
                'width' => round($item['packageWidth'], 3)??'',//宽
                'height' => round($item['packageHeight'], 3)??'',//高
                'item' => $productList
            ];


            $address = ($item['recipientStreet'] ?? ' ') .'   '. ($item['recipientStreet1'] ?? ' ')  .'   '. (empty($item['recipientStreet2']) ? '' : $item['recipientStreet2']);

            $senderinformation = [
                'sendername' => $item['senderName'] ?? '',//发件人姓名
                'senderchinesename' => $item['senderCompany'] ?? '',//发件人中文公司
                'sendercompany' => $item['senderCompany'] ?? '',//发件人英文公司
                'senderphone' => $item['senderPhone'] ?? '',//发件人电话
                'sendercountry' => $item['senderCountryCode'] ?? '',//发件人国家
                'sendercity' => $item['senderCity'] ?? '',//发件人城市
                'senderpostcode' => $item['senderPostCode'] ?? '',//发件人邮编
                'senderaddress' => $item['senderFullAddress'] ?? '',//发件人地址
                'senderemail' => $item['senderEmail'] ?? '',//发件邮箱
                'sendertaxnumber' => $item['recipientTaxNumber']?? '',//发件税号(英国脱欧需求)
                'senderiosscode' => $item['iossNumber']??'',//发件税号(英国脱欧需求)
            ];
            $recipientinformation = [
                'recipientname' => $item['recipientName'] ?? '',//收件人姓名
                'recipientphone' => $item['recipientPhone'] ?? '',//收件人电话
                'recipientcountry' => $item['recipientCountryCode'] ?? '',//收件人国家从国家信息里面做验证
                'recipientpostcode' => $item['recipientPostCode'] ?? '',//收件人邮编
                'recipientcity' => $item['recipientCity'] ?? '',//收件人城市
                'recipientstate' => $item['recipientState'] ?? '',//收件人州名
                'recipienttown' => '',//收件人城镇
                'recipienthousenumber' => '',//门牌号
                'recipientaddress' => $address,//收件人地址
            ];

            $weightinformation = [
                'totalpackages' => 1,//总包裹数
                'itemtype' => '包裹',//物品类型 文件  包裹  纸箱  pak袋
                'totalweight' => $order_weight,//实重
                'totalvolumeweight' => $order_weight,//体积重
                'totalchargeableweight' => $order_weight,//结算重量
            ];

            $specialservice = [];

            $ls[] = [
                'referencenumber' => $item['customerOrderNo'] ?? '',// 参考号码或者订单号
                'productid' => $item['shippingMethodCode'] ?? '',// 产品名称id
                'senderinformation' => [$senderinformation],// 发件人信息
                'recipientinformation' => [$recipientinformation],// 收件人件人信息
                'invoiceinformation' => $invoiceinformation,// 商品申报信息
                'weightinformation' => [$weightinformation],//包裹信息
                'detailpackage' => [$detailpackage],//包裹明细
                'specialservice' => $specialservice,//目前只针对SA的cod服务需要用到，其他一般为空
            ];
        }

        $data = $this->setCustomerInfo($ls);

        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $reqRes = $this->getReqResData();
        $fieldData = [];
        $fieldMap = FieldMap::createOrder();
        list($order_info,$label_path,$status_info) = $response['data']??[];
        // 结果
        $flag = $status_info['resultcode'] == 'success';

        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($status_info['msg'] ?? '未知错误');
        $fieldData['orderNo'] = $ls[0]['referencenumber'];
        $fieldData['trackingNo'] = $order_info['waybillnumber'] ?? '';
        $fieldData['id'] = $order_info['waybillnumber'] ?? '';

        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
//        $this->dd($response, $ret, $reqRes);
        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);
    }

    public function setCustomerInfo($data = [])
    {
        return [
            'IsPushLabel' => false,
            'Data' => [
                "apiplatform" => 'BST',
                "jcexkey" => 'NET2',
                "customerid" => $this->config['customerid'],
                "customer" => $this->config['customer'],
                "linkcustomer" => '',
                "packages" => $data,
            ]
        ];
    }

    /**
     * 通过客户单号获取费用
     * @param string $processCode
     * @return mixed|string
     */
    public function getShippingFee(string $processCode)
    {
        if(empty($processCode)){
            return '';
        }
        $param = [
            'orderNo' => $processCode,
        ];
        $response = $this->request(__FUNCTION__, $param);

        // 结果
        $flag=$response['success']==true;
        if(!$flag){
            return '';
        }
        $ret = $response['order'];
        return $ret;
    }

    /**统一请求
     * @param string $function
     * @param array $data
     * @return mixed
     */
    public function request($function, $data = [])
    {
        $data = $this->buildParams($function, $data);
        $this->req_data = $data;
        $res = $this->sendCurl('post', $this->config['url'], $data, $this->dataType, $this->apiHeaders, 'UTF-8', $this->interface[$function]);
        $this->res_data = $res;

        return $res;
    }

    /**
     * 获取请求数据
     */
    public function buildParams($interface, $arr = [])
    {
        if(is_array($arr)) $arr = base64_encode(urlencode(json_encode($arr,JSON_UNESCAPED_UNICODE)));
        return ['service' => $this->interface[$interface],'data_body' => $arr];
    }

    /**
     * 获取物流商运输方式
     * @return mixed
     */
    public function getShippingMethod()
    {
        $data = 'JC';
        $res = $this->request(__FUNCTION__,$data);
        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();
        $products = $res['products']??[];
//        $this->dd($res);
        if (empty($products)) {
            return $this->retErrorResponseData($res['errorInfo'] ?? '未知错误');
        }
        foreach ($res['products'] as $item) {
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
        $data = [
            'customer' => $this->config['customer'],
            'waybillnumber' => $pars['waybillnumber'],
            'weight' => $pars['weight'],
        ];
        $response = $this->request(__FUNCTION__, $data);
        $flag = ($response['code']==0);
        // 结果
        if (!$flag) {
            return $this->retErrorResponseData($response['Error']['Error'] ?? '0x000' . '.' . $response['Error']['Message'] ?? '未知错误');
        }
        return $this->retSuccessResponseData([]);
    }

    /**
     * 取消订单，删除订单
     * @return mixed
     */
    public function deleteOrder(string $order_id)
    {
        $data = [
            'customer' => $this->config['customer'],
            'waybillnumber' => [$order_id],
        ];
        $response = $this->request(__FUNCTION__, $data);
        $flag = $response[0]['status']??false;
        return $flag;
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
     * 获取订单标签
     * @return mixed
     */
    public function getPackagesLabel($params = [])
    {
        $data = [
            'customer' => $this->config['customer'],
            'labeltype' => '10_10',
            'billno' => $params,
        ];
        $response = $this->request(__FUNCTION__, $data);
        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        $label_url = $response['rtnUrl']??'';
        // 结果
        $flag = !empty($label_url);

        if (!$flag) {
            return $this->retErrorResponseData($response['error']['errorInfo'] ?? '未知错误');
        }

        $response['flag'] = $flag;
        $response['trackingNo'] = $params ?? '';
        $response['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_PDF;
        $response['url'] = $label_url;

        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($response, $fieldMap);
//        $this->dd($fieldData);
        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * 获取物流商轨迹
     * @return mixed
     * {"success":"true","trace":{"pathAddr":"深圳SZ","pathInfo":"包裹已进入八星深圳SZ, 已分拣出仓（Outbound）","pathTime":"2021-03-01T17:08:37+08:00","rcountry":"US","status":"5","tno":"90110US009482375","sPaths":[{"pathAddr":"深圳SZ","pathInfo":"包裹已进入八星深圳SZ, 收货完成（Inbound）","pathTime":"2021-03-01T17:08:25+08:00","pathType":"0"},{"pathAddr":"深圳SZ","pathInfo":"包裹已进入八星深圳SZ, 已分拣出仓（Outbound）","pathTime":"2021-03-01T17:08:37+08:00","pathType":"0"}]}}
     */
    public function queryTrack($trackNumber)
    {
        $trackNumberArray = $this->toArray($trackNumber);
        if (count($trackNumberArray) > self::QUERY_TRACK_COUNT) {
            throw new InvalidIArgumentException($this->iden_name . "查询物流轨迹一次最多查询" . self::QUERY_TRACK_COUNT . "个物流单号");
        }
        $data = [
            'trackingNo' => $trackNumber,
            'orderNo' => '',
        ];
        $response = $this->request(__FUNCTION__, $data);

        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        // 结果
        $flag = $response['success'] == 'true';

        if (!$flag) {
            return $this->retErrorResponseData($response['error']['errorInfo'] ?? '未知错误');
        }

        $data = $response['trace'];

        $ls = [];
        $s_paths = $data['sPaths']??[];
        if(!empty($s_paths)){
            foreach ($s_paths as $key => $val) {
                if(empty($val)) continue;
                $val['pathAddr'] = (!isset($val['pathAddr']) || empty($val['pathAddr']))?'':$val['pathAddr'];
                $val['pathInfo'] = (!isset($val['pathInfo']) || empty($val['pathInfo']))?'':$val['pathInfo'];
                $val['pathTime'] = (!isset($val['pathTime']) || empty($val['pathTime']))?'':$val['pathTime'];
                $val['pathType'] = (!isset($val['pathType']) || empty($val['pathType']))?'':$val['pathType'];
                $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
            }
            $data['sPaths'] = $ls;
            $fieldData = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);
        }

        return $this->retSuccessResponseData($fieldData);
    }

    public function getPackagesDetail($order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }

    /**获取追踪号
     * @param string $order_id
     * @return mixed
     */
    public function getTrackNumber(string $order_id)
    {
        $data = [
            'customer' => $this->config['customer'],
            'customerid' => $this->config['customerid'],
            'waybillnumber' => $order_id,
        ];
        $response = $this->request(__FUNCTION__, $data);
        if (!isset($response['waybillnumber'])) {
            return $this->retErrorResponseData('未知错误');
        }
        return $this->retSuccessResponseData($response);
    }
}