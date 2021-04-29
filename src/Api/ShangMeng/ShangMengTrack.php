<?php
/**
 * Class Aprche
 * @package smiler\logistics\Api\Aprche
 * 商盟物流商
 */

namespace smiler\logistics\Api\ShangMeng;


use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\LsSdkFieldMapAbstract;
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
 * 商盟物流商
 */
class ShangMengTrack extends LogisticsAbstract implements TrackLogisticsInterface
{
    public $iden = 'shangmengTrack';

    public $iden_name = '商盟物流商轨迹查询';

    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'form';

    public $apiHeaders = [];

    public $interface = [
        'queryTrack' => 'Load_Track_Info', //轨迹查询

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

    /**
     * array转XML
     * @param $array
     * @param string $root
     * @return string
     */
    protected static function arrayToXml($array, $root = 'xml', $encoding = 'utf-8')
    {
        $xml = "<{$root}>";
        $xml .= self::arrayToXmlInc($array);
        $xml .= "</{$root}>";
        return $xml;
    }

    protected static function arrayToXmlInc($array)
    {
        $xml = '';
        foreach ($array as $key => $val) {
            if(empty($val)) continue;
            if(is_array($val)) {
                if(is_numeric($key)){
                    $xml .= static::arrayToXmlInc($val);
                }else{
                    $xml .= "<$key>";
                    $xml .= static::arrayToXmlInc($val);
                    $xml .= "</$key>";
                }
            }else{
                $xml .= "<$key>$val</$key>";
            }
        }
        return $xml;
    }

    public function queryTrack($trackNumber)
    {
        $data = [
            'RequestXML' => static::arrayToXml([
                'AppKey' => $this->config['appKey'],
                'AppSecret' => $this->config['appSecret'],
                'Language' => 'CN', //语言只支持中文和英文 传参请以CN、EN 标识
                'ServiceNumberList' => [
                    'ServiceNumber' => implode(',', $this->toArray($trackNumber))
                ]
            ],'TrackInfoService')
        ];
        $response = $this->request(__FUNCTION__,'post',$data);
        if(!isset($response['TrackInfo']) || empty($response['TrackInfo'])){
           return $this->retSuccessResponseData();
        }
        $fieldMap1 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_ONE);
        $fieldMap2 = FieldMap::queryTrack(LsSdkFieldMapAbstract::QUERY_TRACK_TWO);
        $tmpArr = $response['TrackInfo'];
        $tmpArr['flag'] = true;
        $ls = [];
        foreach ($tmpArr['TrackDetailsList']['TrackDetails'] as $key=>$item) {
            $ls[$key] = LsSdkFieldMapAbstract::getResponseData2MapData($item, $fieldMap2);
        }
        $tmpArr['TrackDetailsList'] = $ls;
        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData($tmpArr, $fieldMap1);
        return $this->retSuccessResponseData($fieldData);
    }

    /**
     * @param $interface string 方法名
     * @param array $arr 请求参数
     * @return array
     */
    public function buildParams($interface, $arr = [])
    {

    }

    public function request($interface, $method = 'post', $data = [])
    {
        $requestUrl = $this->config['url'] . $this->interface[$interface];
        $response = $this->sendCurl($method, $requestUrl, $data, $this->dataType, $this->apiHeaders);
        return $response;
    }

    public static function parseResponse($curl, $dataType, $response, $resTitle = '', $dir = '')
    {
        return parent::parseResponse($curl, 'xml', $response, $resTitle, $dir);
    }
}