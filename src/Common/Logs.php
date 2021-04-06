<?php
/**
 * Created by PhpStorm
 * User: Administrator:smiler
 * Date: 2021/4/6 11:20
 * Email: <2166909366@qq.com>
 */

namespace smiler\logistics\Common;


class Logs
{
    // 定义日志根目录
    const LOG_DIR_DF = 'common';
    // 定义默认文件目录
    const FILE_SUFFIX = '.log';
    // 文件后缀
    const DATE_TIME_FORMAT = 'Y-m-d H:i:s';
    // 时间日期格式
    const SOURCE_TYPE_SDK = 'php-sdk';

    // 来源类型
    const LEVEL_INFO = 'info';

    // [info|warning|error|debug]
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_DEBUG = 'debug';
    static $_root = '../';
    static $_title = '';

    /**
     * 设置日志根目录
     * @param string $path
     */
    static function setRootPath($path = '')
    {
        !empty($path) && self::$_root = $path;
    }

    /**
     * info 级别日志
     * @param string $title 标题
     * @param string $message 提示
     * @param array $data 数据
     * @param string $dir 目录
     * @param string $suffix 文件后缀
     * @return false|int
     */
    static function info($title = '', $message = '', $data = [], $dir = self::LOG_DIR_DF, $suffix = self::FILE_SUFFIX)
    {
        return self::saveLog($title, $message, $data, self::LEVEL_INFO, $dir, $suffix);
    }

    /**
     * 日志输入保存
     * @param string $title 标题
     * @param string $message 提示
     * @param array $data 数据
     * @param string $level 级别
     * @param string $dir 目录
     * @param string $suffix 文件后缀
     * @return false|int
     */
    protected static function saveLog($title = '', $message = '', $data = [], $level = self::LEVEL_INFO, $dir = self::LOG_DIR_DF, $suffix = self::FILE_SUFFIX)
    {
        $timestamp = time();
        $date = date('Ymd', $timestamp);
        $datetime = date(self::DATE_TIME_FORMAT, $timestamp);
        $str = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
        $ip = self::getClientIp();
        $sourceType = self::SOURCE_TYPE_SDK;
        $info = <<<EOF
{$datetime} [{$sourceType}] [{$ip}] [{$level}] [$title] $message $str

EOF;
        $path = self::getRootPath() . '/logs/log_sdk_' . $dir . '/';
        $file = $path . $date . $suffix;
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        return file_put_contents($file, $info, FILE_APPEND);
    }

    /**
     * 获取客户端IP
     * @return mixed|string
     */
    static function getClientIp()
    {
        try {
            if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
                $ip = $_SERVER["HTTP_CLIENT_IP"];
            } else {
                if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
                    $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
                } else {
                    if (!empty($_SERVER["REMOTE_ADDR"])) {
                        $ip = $_SERVER["REMOTE_ADDR"];
                    } else {
                        if (!empty($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown")
                        ) {
                            $ip = $_SERVER['REMOTE_ADDR'];
                        } else {
                            $ip = "ip:unknown";
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $ip = "ip:unknown";
        }
        return $ip;
    }

    /**
     * 获取日志根目录
     * @return string
     */
    static function getRootPath()
    {
        return rtrim(self::$_root, '/');
    }

    /**
     * 获取标题
     * @return string
     */
    static function getTitle()
    {
        return rtrim(self::$_title);
    }

    /**
     * 设置标题
     * @param string $title
     */
    static function setTitle($title = '')
    {
        !empty($title) && self::$_title = $title;
    }

    /**
     * warning 级别日志
     * @param string $title 标题
     * @param string $message 提示
     * @param array $data 数据
     * @param string $dir 目录
     * @param string $suffix 文件后缀
     * @return false|int
     */
    static function warning($title = '', $message = '', $data = [], $dir = self::LOG_DIR_DF, $suffix = self::FILE_SUFFIX)
    {
        return self::saveLog($title, $message, $data, self::LEVEL_WARNING, $dir, $suffix);
    }

    /**
     * error 级别日志
     * @param string $title 标题
     * @param string $message 提示
     * @param array $data 数据
     * @param string $dir 目录
     * @param string $suffix 文件后缀
     * @return false|int
     */
    static function error($title = '', $message = '', $data = [], $dir = self::LOG_DIR_DF, $suffix = self::FILE_SUFFIX)
    {
        return self::saveLog($title, $message, $data, self::LEVEL_ERROR, $dir, $suffix);
    }

    /**
     * debug 级别日志
     * @param string $title 标题
     * @param string $message 提示
     * @param array $data 数据
     * @param string $dir 目录
     * @param string $suffix 文件后缀
     * @return false|int
     */
    static function debug($title = '', $message = '', $data = [], $dir = self::LOG_DIR_DF, $suffix = self::FILE_SUFFIX)
    {
        return self::saveLog($title, $message, $data, self::LEVEL_DEBUG, $dir, $suffix);
    }
}