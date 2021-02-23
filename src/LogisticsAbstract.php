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

    protected $iden_name;

    const ORDER_COUNT = 5;

    const ORDER_COUNT_SKU = 5;

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
     */
    final public function sendCurl($method = 'post', $url = '', $data = [], $dataType = 'json', $header = [], $encoding = 'utf-8', $root = 'xml')
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
//        var_dump($response);exit;
        switch ($curl->responseCode) {
            case 'timeout':
                throw new CurlException('请求错误');
                break;

            case 200:
                //success logic here
                switch (strtolower($dataType)) {
                    case 'xml':
                        $data = static::xmlToArray($response);
                        break;

                    case 'form':
                    case 'json':
                    default:
                        $data = json_decode($response, true);
                        break;
                }
                $return = $data;
                break;

            case 401:
                //404 Error logic here
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
            if (is_array($val)) {
                $xml .= self::arrayToXmlInc($val);
            } else {
                if (is_numeric($val)) {
                    $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
                } else {
                    $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
                }
            }
        }
        return $xml;
    }


    public function __call($name, $arguments)
    {
        throw new BadFunctionCallException("未实现该方法");
    }
}