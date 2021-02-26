<?php
/**
 *
 * User: blaine
 * Date: 2/20/21
 */

namespace smiler\logistics;


use smiler\logistics\Exception\BadFunctionCallException;
use smiler\logistics\Exception\CurlException;
use smiler\logistics\Exception\InvalidIArgumentException;

abstract class LogisticsAbstract
{
    use LogisticsTool;

    protected $iden;
    protected $iden_name;

    /**
     * 创建订单可上传的数量
     */
    const ORDER_COUNT = 5;

    /**
     * 单个订单最多有几个产品
     */
    const ORDER_COUNT_SKU = 5;

    /**
     * 单次查询物流轨迹的数量
     */
    const QUERY_TRACK_COUNT = 30;

    /**
     * @var array
     * 物流商方法名
     */
    protected $interface = [];

    /**
     * @param $config ['url'] string 物流商api地址
     * @param $config ['header'] array 物流商header
     * @var array 物流商相关配置
     */
    protected $config = [];


    /**
     * 统一发送请求
     * @param $parseResponse bool 是否解析响应数据 todo 有些物流商接口物流面单响应返回的字节流
     */
    final public function sendCurl($method = 'post', $url = '', $data = [], $dataType = 'json', $header = [], $encoding = 'utf-8', $root = 'xml', $parseResponse = true)
    {
        $curl = new Curl();
        $method = strtolower($method);
        switch ($method) {
            case 'get':
                $params = is_array($data) ? $data : [];
                $http = $curl->setGetParams($params);
                break;

            case 'delete':
                $http = $curl;
                break;

            case 'post':
            default:
                switch (strtolower($dataType)) {
                    case 'form':
                        $params = is_array($data) ? $data : [];
                        $http = $curl->setPostParams($params);
                        break;

                    case 'xml':
                        $params = static::arrayToXml($data, $root, $encoding);// 转xml格式
                        $http = $curl->setRawPostData($params);
                        break;

                    case 'json':
                    default:
                        $params = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : json_encode([]);
                        $http = $curl->setRequestBody($params);
                        break;
                }
                break;

        }
        $response = $http->setHeaders($header)->setOption(CURLOPT_SSL_VERIFYPEER, false)->$method($url);
        if (!$parseResponse) {
            return $response;
        }
//        var_dump($response);exit;
        return static::parseResponse($curl, $dataType, $response);

    }

    protected static function parseResponse($curl, $dataType, $response)
    {
        switch ($curl->responseCode) {
            case 'timeout':
                throw new CurlException('curl:请求错误');
                break;
            case 200:
                switch (strtolower($dataType)) {
                    case 'xml':
                        $return = static::xmlToArray($response);
                        break;

                    case 'form':
                    case 'json':
                    default:
                        $return = json_decode($response, true);
                        break;
                }
                break;
            case 401:
                throw new CurlException('curl: 授权失败');
                break;

            case 404:
            default:
                //404 Error logic here
                throw new CurlException('curl: 请求失败');
                break;
        }
        return $return;
    }

    protected static function xmlToArray($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }

    /**
     * array转XML
     * @param $array
     * @param string $root
     * @return string
     */
    protected static function arrayToXml($array, $root = 'xml', $encoding = 'utf-8')
    {
        $xml = '<?xml version="1.0" encoding="' . $encoding . '"?>';
        $xml .= "<{$root}>";
        $xml .= self::arrayToXmlInc($array);
        $xml .= "</{$root}>";
        return $xml;
    }

    protected static function arrayToXmlInc($array)
    {
        $xml = '';
        foreach ($array as $key => $val) {
            is_numeric($key) && $key = "item id=\"$key\"";
            $xml .= "<$key>";
            $xml .= (is_array($val) || is_object($val)) ? static::arrayToXmlInc($val) : $val;
            list($key,) = explode(' ', $key);
            $xml .= "</$key>";
        }
        return $xml;
    }


    public function __call($name, $arguments)
    {
        throw new BadFunctionCallException("未实现" . $name . "方法");
    }
}