<?php
/**
 *
 * User: blaine
 * Date: 3/1/21
 */

namespace smiler\logistics\Api\YiKeDa;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\Exception\InvalidIArgumentException;
use smiler\logistics\Exception\ManyProductException;
use smiler\logistics\LogisticsAbstract;

class YiKeDa extends LogisticsAbstract implements BaseLogisticsInterface, PackageLabelLogisticsInterface, TrackLogisticsInterface
{
    /**
     * 一次最多提交多少个包裹,5自定义
     */
    const ORDER_COUNT = 20;
    /**
     * 一次最多查询多少个物流商
     */
    const QUERY_TRACK_COUNT = 1;

    public $iden = 'yikeda';

    public $iden_name = '易可达头程';
    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'xml';

    public $apiHeaders = [
        'Content-Type' => 'application/xml'
    ];

    public $interface = [

        'createOrder' => 'createGRN', // 创建并预报订单 todo 如果调用创建订单需要预报

        'deleteOrder' => 'delGRN', //删除订单。发货后的订单不可删除。

        'queryTrack' => 'queryTrackingStatus', //轨迹查询

        'getShippingMethod' => 'getShippingMethod', //获取配送方式

      //  'getShippingMethod' => 'getSmcode', //获取配送方式

        'getPackagesLabel' => 'printSku', // 【打印标签|面单

        'getWarehouse' => 'getWarehouse', //获取系统中仓库的仓库代码、仓库名称和所在国家/地区的信息。

        'getSmcode' => 'getSmcode',//获取中转服务方式

        'getTransferWarehouse' => 'getTransferWarehouse',//获取中转仓库

        'getSmcodeTwcToWarehouse' => 'getSmcodeTwcToWarehouse',//获取入库单相关的中转服务方式，包括目的仓、中转仓，跟分别支持的物流方式。

        'carsModel' => 'carsModel',//获取车型
    ];

    /**
     * HuaHan constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['appToken','appKey', 'url'], $config);
        $this->config = $config;
        if (!empty($config['apiHeaders'])) {
            $this->apiHeaders = array_merge($this->apiHeaders, $config['apiHeaders']);
        }
    }

    /**
     * array转XML
     * @param $array
     * @param string $root
     * @return string
     */
    public static function arrayToXml($array, $root = 'ns1:callService', $encoding = 'utf-8')
    {
        $xml = '<?xml version="1.0" encoding="' . $encoding . '"?>';
        $xml .= '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.example.org/Ec/">';
        $xml .= '<SOAP-ENV:Body>';
        $xml .= '<ns1:callService>';
        $xml .= "<paramsJson>{$array['paramsJson']}";
        $xml .= "</paramsJson>";
        $xml .= "<appToken>{$array['appToken']}";
        $xml .= "</appToken>";
        $xml .= "<appKey>{$array['appKey']}";
        $xml .= "</appKey>";
        $xml .= "<service>{$root}";
        $xml .= "</service>";
        $xml .= "</ns1:callService>";
        $xml .= "</SOAP-ENV:Body>";
        $xml .= "</SOAP-ENV:Envelope>";
        return $xml;
    }

    public static function xmlToArray($xml)
    {
        return json_decode(substr(strstr($xml,'{'),0,strrpos(strstr($xml,'{'),"</response>")),true);
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
            $productList = [];$order_weight = 0;$order_num = 0;$order_price=0;$order_volume=0;
            foreach ($item['productList'] as $key => $value) {
                $productList[] = [
                    'box_no'=>$key+1,
                    'reference_box_no'=>'',
                    'box_details'=>[
                      [
                          'product_sku'=>$value['productSku']??'',
                          'quantity'=>(int)($value['quantity'] ?? ''),// Y:产品数量;数值必须为正整数
                      ]
                    ],
                ];
                $order_weight += $value['declareWeight'];
                $order_num += $value['quantity'];
                $order_price += $value['declarePrice'];
                $order_volume=($value['length']*$value['width']*$value['height'])*0.000001;
            }
            $address = ($item['recipientStreet'] ?? ' ') . ($item['recipientStreet1'] ?? ' '). ($item['recipientStreet2'] ?? '');
            $sm_code=$item['shippingMethodCode']??'testAir';
            $warehousesmcode=$this->getWarehouseSmCode($sm_code,$item['recipientCountryCode']);
            $ls[] = [
                'reference_no' => $item['customerOrderNo'] ?? '',// Y:客户订单号，由客户自定义，同一客户不允许重复。Length <= 12
                'transit_type' => 3,//入库单类型：0：自发入库单;3：头程中转入库单;5：FBA入库单
                'receiving_shipping_type'=>0,//0：空运1：海运散货2：快递3：铁运整柜4：海运整柜5：铁运散货
            //    'warehouse_code'=>$this->getWarehouseCode($item['recipientCountryCode']),//海外仓仓库编码
            //    'sm_code'=>$this->getSmCode(),//transit_type=3特有。物流产品（整柜无需填）,我们后台设置的是快递
            //    'transit_warehouse_code'=>$this->getTransferWarehouse(),//transit_type=3特有。（非整柜：物流产品绑定的中转仓库 ， 整柜：国内中转仓库）
                'warehouse_code'=>$warehousesmcode['warehouse_code']??'',
                'sm_code'=>$sm_code??'testAir',//transit_type=3特有。物流产品（整柜无需填）,我们后台设置的是快递
                'transit_warehouse_code'=>$warehousesmcode['transit_warehouse_code']??'',
                'customs_type'=>1,//transit_type=3特有。报关项,0:EDI报关,1:委托报关,2:报关自理
                'collecting_service'=>1,//transit_type=3特有。揽收服务,0:自送货物,1:上门提货
                //收件人信息
                'collecting_address'=>[
                    'ca_first_name'=>$item['recipientLastName']??'',//揽收联系人-名
                    'ca_last_name'=>$item['recipientFirstName']??'',//揽收联系人-姓
                    'ca_address1'=>$address ?? ' ',//必填 收件人地址
                    'ca_city'=>$item['recipientCity']??'',//必填 收件人城市
                    'ca_state'=>$item['recipientState']??'',//必填 收件人省
                    'ca_country_code'=>$item['recipientCountryCode']??'',//必填 收件人国家, ISO 3166 标准
                    'ca_zipcode'=>$item['recipientPostCode']??'',//必填 收件人邮编
                    'ca_contact_phone'=>$item['recipientPhone']??'',//必填 收件人电话
                    'ca_address2'=>$item['recipientStreet1'] ?? ($item['recipientStreet2'] ?? $address),//非必填 收件人地址2
                ],//transit_type=3特有。揽收资料。必填条件：当collection_service=1（上门揽收）时必填
                //发件人信息
                'shiper_address'=>[
                    'sa_contacter'=>$item['senderName'] ?? '',//联系人
                    'sa_contact_phone' => $item['senderPhone'] ?? '', //N:发件人电话
                    'sa_address1' => $item['senderFullAddress'] ?? '',// Y:发件人完整地址Length <= 200
                    'sa_state' => $item['senderState'] ?? '', // N:发件人省
                    'sa_city' => $item['senderCity'] ?? '',// Y:发件人城市Length<=50
                    'sa_country_code' => 'CN',// Y:发件人国家简码，默认CN, $item['senderCountryCode'] ??
                ],
                'collecting_time'=>date('Y-m-d H:i:s',time()),//transit_type=3特有。揽收时间。注：揽收时间不允许小于创建时间
                'value_add_service'=>'',//transit_type=3特有增值服务，可选值:world_ease(worldease服务) origin_crt(产地证) fumigation(熏蒸 )
                'clearance_service'=>0,//transit_type=3特有。是否自有税号清关。0：否1：是 注：当warehouse_code开启了增值服务，必为1；如没开启，可选0或者1
                'import_company'=>$item['recipientTaxNumber']??'',//进口商编码目的仓库对应的国家需要提供VAT(目前英国需要提供)
                'export_company'=>$item['senderTaxNumber']??'',//transit_type=3特有。出口商编码。必填条件：clearance_service=1（自有税号清关）
                'car_model_code'=>'minivan',//$this->getCarModel(),//车型 车型接口获取。transit_type=3特有。当collecting_service=1时必填。
                'weight'=>round($order_weight,2),//重量(kg)小于等于100000   8,2
                'volume'=>round($order_volume,2),//体积(立方米)小于等于100   5,2
                //todo 调试写死
                //'transportWayCode' => $item['shippingMethodCode'] ?? 'USRNN',// Y:serviceCode: test => UBI.CN2FR.ASENDIA.FULLLY.TRACKED
                'items' => $productList,// Y:一次最多支持 20 个产品信息（超过 20 个将会忽略）
            ];

        }
        $data = $this->buildParams('createOrder', $ls[0]);

        $response = $this->sendCurl('post', $this->config['url'].$this->config['create_order_command'],$data, $this->dataType, $this->apiHeaders, 'UTF-8', 'createGRN');
        //$response = $this->request(__FUNCTION__, $ls[0]);

        // 处理结果
        $reqRes = $this->getReqResData();

        $fieldData = [];
        $fieldMap = FieldMap::createOrder();

        // 结果
        $flag = $response['ask'] == 'Success';

        $fieldData['flag'] = $flag ? true : false;
        $fieldData['info'] = $flag ? '' : ($response['message'] ?? '未知错误');

        $fieldData['orderNo'] = $ls[0]['reference_no'];
        $fieldData['trackingNo'] = $response['data']['receiving_code'] ?? '';
        $fieldData['id'] = $response['id'] ?? '';

        $ret = LsSdkFieldMapAbstract::getResponseData2MapData($fieldData, $fieldMap);
//        $this->dd($response, $ret, $reqRes);
        return $fieldData['flag'] ? $this->retSuccessResponseData(array_merge($ret, $reqRes)) : $this->retErrorResponseData($fieldData['info'], $fieldData);
    }

    /**获取车型
     * @return array|string
     */
    public function getCarModel(){
        $data = $this->buildParams('carsModel', []);
        $res = $this->sendCurl('post', $this->config['url'].$this->config['get_car_model_command'],$data, $this->dataType, $this->apiHeaders, 'UTF-8', 'carsModel');

        if($res['ask']=='Success'){
            $resultinfo=array_column($res['data'],'car_model_name','car_model_code');
            $result=empty($resultinfo)?'minivan':$resultinfo;
        }else{
            $result='minivan';//默认的
        }
        return $result;
    }

    /**按照收货国家获取仓库编码
     * @param $country
     * @return mixed|string
     */
    public function getWarehouseCode($country){
        $data = $this->buildParams('getWarehouse', []);
        $this->req_data = $data;
        $res = $this->sendCurl('post', $this->config['url'].$this->config['get_warehouse_command'],$data, $this->dataType, $this->apiHeaders, 'UTF-8', 'getWarehouse');
        $this->res_data = $res;
        if($res['ask']=='Success'){
            $resultinfo=array_column($res['data'],'warehouse_code','country_code');
            $result=empty($resultinfo[$country])?'USEA':$resultinfo[$country];
        }else{
            $result='USEA';//默认的
        }
        return $result;
    }

    /**获取入库单相关的中转服务方式，包括目的仓、中转仓，跟分别支持的物流方式
     * @param $sm_code
     * @param $country
     * @return array
     */
    public function getWarehouseSmCode($sm_code,$country){
        $data = $this->buildParams('getSmcodeTwcToWarehouse', []);
        $this->req_data = $data;
        $res = $this->sendCurl('post', $this->config['url'].$this->config['get_warehouse_smcode_command'],$data, $this->dataType, $this->apiHeaders, 'UTF-8', 'getSmcodeTwcToWarehouse');
        $this->res_data = $res;
        $result=[];
        if($res['ask']=='Success'){
           // $warehouse_code=$this->getWarehouseCode($country);

            foreach ($res['data'] as $rk=>$rv){

                foreach ($rv as $k=>$v){
                    if($v['sm_code']==$sm_code){
                        $warecodetransit=array_column($v['twc_to_warehouse'],'transit_warehouse_code','warehouse_code');
                        //判定渠道相等
                        $result=[
                            'transit_warehouse_code'=>current($warecodetransit),
                            'warehouse_code'=>array_flip($warecodetransit)[current($warecodetransit)],
                        ];
                        return $result;
                    }else{
                        $result=[
                            'transit_warehouse_code'=>'',
                            'warehouse_code'=>'',
                        ];
                        return $result;
                    }
                }
            }
        }else{
            $result['transit_warehouse_code']='';
            $result['warehouse_code']='';
            return $result;
        }

    }
    /**获取sm_code
     * @param $code
     * @return mixed|string
     */
    public function getSmCode(){
        $data = $this->buildParams('getSmcode', []);
        $this->req_data = $data;
        $res = $this->sendCurl('post', $this->config['url'].$this->config['get_smcode_command'], $data, $this->dataType, $this->apiHeaders, 'UTF-8', 'getSmcode');
        $this->res_data = $res;
        if($res['ask']=='Success'){
            $express=$res['data'];
            $result=$express[0]['sm_code'];//默认选第一个
        }else{
            $result='圆通';//默认的
        }
        return $result;
    }

    /**获取transit_warehouse_code
     * @return int|mixed
     */
    public function getTransferWarehouse(){
        $data = $this->buildParams('getTransferWarehouse', []);
        $this->req_data = $data;
        $res = $this->sendCurl('post', $this->config['url'].$this->config['get_smcode_command'], $data, $this->dataType, $this->apiHeaders, 'UTF-8', 'getTransferWarehouse');
        $this->res_data = $res;
        if($res['ask']=='Success'){
            $express=$res['data'];
            $result=$express[0]['transit_warehouse_code'];//默认选第一个
        }else{
            $result=15;//默认的,东莞仓库
        }
        return $result;
    }

    /**
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
//    public function buildParams($interface, $arr = [])
//    {
//        return array_merge(['appToken' => $this->config['appToken'],'appKey'=> $this->config['appKey']], $arr);
//    }
    public function buildParams($interface, $arr = [])
    {
        $data = [
            'appToken' => $this->config['appToken'],
            'appKey' => $this->config['appKey'],
            'service' => $this->interface[$interface],
            'paramsJson' => "{}",
        ];
        if (!empty($arr)) {
            $data['paramsJson'] = json_encode($arr, JSON_UNESCAPED_UNICODE);
        }
        return $data;
    }

    /**
     * 获取物流商运输方式
     * @return mixed
     * [{"success":"true","transportWays":[{"autoFetchTrackingNo":"Y","code":"DHLV4-OT","name":"OTTO专线","trackingNoRuleMemo":[],"trackingNoRuleRegex":[],"used":"Y"},{"autoFetchTrackingNo":"Y","code":"DHL-ALL","name":"全欧特派","trackingNoRuleMemo":[],"trackingNoRuleRegex":[],"used":"Y"}]}]
     */
    public function getShippingMethod()
    {
//        $param=[
//          'warehouseCode'=>'USEA',
//        ];
//        $res = $this->request(__FUNCTION__,$param);
        $data = $this->buildParams('getSmcodeTwcToWarehouse', []);
        $this->req_data = $data;
        $res = $this->sendCurl('post', $this->config['url'].$this->config['get_warehouse_smcode_command'], $data, $this->dataType, $this->apiHeaders, 'UTF-8', 'getSmcodeTwcToWarehouse');
        $this->res_data = $res;

        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::shippingMethod();


        if ($res['ask'] != 'Success') {
            return $this->retErrorResponseData($res['message'] ?? '未知错误');
        }

        foreach ($res['data'] as $k=>$v) {
            foreach ($v as $item){
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
            'receiving_code' => $order_id,
           // 'reason' => '易可达取消订单',
        ];
        $data = $this->buildParams('deleteOrder', $param);
        $response = $this->sendCurl('post', $this->config['url'].$this->config['get_delete_command'], $data, $this->dataType, $this->apiHeaders, 'UTF-8', 'delGRN');
        //$response = $this->request(__FUNCTION__, $param);
        return $response;
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
        $param = [
            'product_sku_arr' => implode(',', $this->toArray($params['orderNo'])),
            'print_code' => $params['label_content'] ?? 1, //选择打印样式“1” 地址标签打印 “11” 报关单 “2” 地址标签+配货信息 “3” 地址标签+报关单（默认） “13”地址标签+(含配货信息) “12” 地址标签+(含配货信息)+报关单 “15” 地址标签+报关单+配货信息
            'print_size' => $params['label_type'] ?? 1, //“1”表示80.5mm × 90mm “2”表示105mm × 210mm “7”表示100mm × 150mm “4”表示102mm × 76mm “5”表示110mm × 85mm “6”表示100mm × 100mm（默认） “3”表示A4,
        ];
        //$response = $this->request(__FUNCTION__, $data);
        $data = $this->buildParams('getPackagesLabel', $param);
        $response = $this->sendCurl('post', $this->config['url'].$this->config['get_label_command'], $data, $this->dataType, $this->apiHeaders, 'UTF-8', 'printSku');
        // 处理结果
        $fieldData = [];
        $fieldMap = FieldMap::packagesLabel();

        // 结果
        $flag = $response['ask'] == 'Success';

        if (!$flag) {
            return $this->retErrorResponseData($response['message'] ?? '未知错误');
        }

        $response['flag'] = $flag;
        $response['trackingNo'] = $params['trackNumber'][0] ?? '';
        $response['label_path_type'] = ResponseDataConst::LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF;
        $response['lable_content_type'] = $response['data']['type']??($params['label_content'] ?? 2);//1：png，2：pdf
        $response['url']=$response['data']['label_image']??'';
        $response['label_path_plat'] = '';//不要填写
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
        $param = [
            'refrence_no' => $trackNumber,
        ];
        //$response = $this->request(__FUNCTION__, $param);
        $data = $this->buildParams('queryTrack', $param);
        $response = $this->sendCurl('post', $this->config['url'].$this->config['get_track_command'], $data, $this->dataType, $this->apiHeaders, 'UTF-8', 'queryTrackingStatus');
        // 处理结果
        $fieldData = [];
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);

        // 结果
        $flag = $response['ask'] == 'Success';

        if (!$flag) {
            return $this->retErrorResponseData($response['message'] ?? '未知错误');
        }

        $data = $response['data']['trajectory_information'];

        $ls = [];
        foreach ($data['item'] as $key => $val) {
            $data['status']=$val['code'];
            $data['status_msg']=$val['code_info'];
            $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($val, $fieldMap2);
        }
        $data['item'] = $ls;
        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($data, $fieldMap1);

        return $this->retSuccessResponseData($fieldData);
    }

    public function getPackagesDetail($order_id)
    {
        $this->throwNotSupport(__FUNCTION__);
    }
}