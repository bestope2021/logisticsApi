<?php
/**
 *
 * User: blaine
 * Date: 2/20/21
 */

namespace smiler\logistics;


use smiler\logistics\Common\Logs;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\Exception\BadFunctionCallException;
use smiler\logistics\Exception\CurlException;
use smiler\logistics\Exception\NotSupportException;

abstract class LogisticsAbstract
{
    use LogisticsTool;

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
    protected $iden;
    protected $iden_name;
    protected $req_data;
    protected $res_data;
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
//        var_dump($url, $header, $params);
        $title = Logs::getTitle();
        $reqTitle = "第三方请求: {$title}";
        $resTitle = "第三方响应: {$title}";
        Logs::info($reqTitle, "请求头", $header, $this->iden);
        Logs::info($reqTitle, "请求方式@URL: {$method}@{$url}", $params ?? [], $this->iden);
        $response = $http->setHeaders($header)->setOption(CURLOPT_SSL_VERIFYPEER, false)->setOption(CURLOPT_TIMEOUT, 180)->$method($url);
        Logs::info($resTitle, "请求方式@URL: {$method}@{$url}", $response, $this->iden);
        if (!$parseResponse) {
            return $response;
        }
//        var_dump($response);die;
        return static::parseResponse($curl, $dataType, $response, $resTitle, $this->iden);

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

    protected static function parseResponse($curl, $dataType, $response, $resTitle = '', $dir = '')
    {
        switch ($curl->responseCode) {
            case 'timeout':
                Logs::warning($resTitle, "CURL请求超时", $response, $dir);
                throw new CurlException('curl:请求超时');
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
                Logs::warning($resTitle, "CURL授权失败", $response, $dir);
                throw new CurlException('curl: 授权失败');
                break;

            case 404:
            default:
                Logs::error($resTitle, "CURL请求失败", $response, $dir);
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

    public function __call($name, $arguments)
    {
        throw new BadFunctionCallException("未实现" . $name . "方法");
    }

    final public function throwNotSupport($function)
    {
        throw new NotSupportException($this->iden_name . "暂不支持" . $function);
    }

    /**
     * 设置请求和响应参数, 默认为空
     * @param array $req_data 请求参数
     * @param array $res_data 响应参数
     */
    final public function setReqResData($req_data = [], $res_data = [])
    {
        $this->req_data = $req_data;
        $this->res_data = $res_data;
    }

    /**
     * 获取请求和响应参数
     * @return array [请求参数，响应参数]
     */
    final public function getReqResData()
    {
        return [
            ResponseDataConst::LSA_CURL_REQ_DATA => $this->req_data,
            ResponseDataConst::LSA_CURL_RES_DATA => $this->res_data
        ];
    }

    /**
     * 统一返回API接口数据 - 失败
     * @param string $info 提示信息
     * @param array $data 数据
     * @return array
     */
    final public function retErrorResponseData($info = 'error', $data = [])
    {
        return $this->retResponseData(1, $info, $data);
    }

    /**
     * 统一返回API接口数据
     * @param int $code 状态码，0：成功，非0：失败，
     * @param string $info 提示信息
     * @param array $data 数据
     * @return array
     */
    final public function retResponseData($code = 0, $info = 'success', $data = [])
    {
        return [
            'code' => $code,
            'info' => $info,
            'data' => $data,
        ];
    }

    /**
     * 统一返回API接口数据 - 成功
     * @param array $data 数据
     * @param string $info 提示信息
     * @return array
     */
    final public function retSuccessResponseData($data = [], $info = 'success')
    {
        return $this->retResponseData(0, $info, $data);
    }

    /**
     * 打印json数据
     * @param mixed ...$ver
     */
    final public function dd(...$ver)
    {
        echo json_encode($ver, JSON_UNESCAPED_UNICODE);
        exit;
    }
}