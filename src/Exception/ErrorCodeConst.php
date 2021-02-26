<?php
/**
 * Created by PhpStorm
 * User: Administrator:smiler
 * Date: 2021/2/26 15:43
 * Email: <2166909366@qq.com>
 */

namespace smiler\logistics\Exception;


/**
 * 统一错误码定义常量
 * Class ErrorCodeConst
 * @package smiler\logistics\Exception
 */
class ErrorCodeConst
{
    protected static $unknownError = '%s.未知错误';

    /**
     * 定义错误码映射
     * @var string[]
     */
    protected static $codeMap = [
        0 => 'success',
        10000 => '内部异常',
        10001 => '内部异常(%s)',
    ];

    /**
     * 返回错误码，和提示
     * @param int $code 状态码
     * @param string $msg 扩展提示信息
     * @param array $data 数据
     * @return array[状态码，提示信息，数据]
     */
    static function getErrorCodeInfo($code = 0, $msg = '', $data = []): array
    {
        if (!array_key_exists($code, self::$codeMap)) {
            $content = self::$unknownError;
        } else {
            $content = self::$codeMap[$code];
        }

        if (!empty($msg)) {
            $content .= '()';
            $content = sprintf($content, $msg);
        }

        return [$code, $content, $data];
    }
}