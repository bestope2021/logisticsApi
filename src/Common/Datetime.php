<?php
/**
 * Created by PhpStorm
 * User: Administrator:smiler
 * Date: 2021/5/19 10:06
 * Email: <2166909366@qq.com>
 */

namespace smiler\logistics\Common;

/**
 * 时间相关操作
 * Class Datetime
 * @package smiler\logistics\Common
 */
class Datetime
{

    /**
     * 13位
     */
    const TS_BIT_JAVA = 13;
    /**
     * 10位
     */
    const TS_BIT_PHP = 10;

    /**
     * 默认时区
     */
    const TZ_UTC = 'UTC';

    /**
     * 时间戳 （中国时区-8小时）
     * @param int $bit 位数
     * @param string $timezone 时区，默认 UTC
     * @return false|string
     */
    static function getUTCTimestamp($bit = self::TS_BIT_JAVA, $timezone = self::TZ_UTC)
    {
        date_default_timezone_set($timezone);
        $microtime = microtime(true) * 1000;
        return substr($microtime, 0, $bit);
    }
}