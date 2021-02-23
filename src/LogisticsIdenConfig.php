<?php
/**
 *
 * User: blaine
 * Date: 2/20/21
 */

namespace smiler\logistics;


use smiler\logistics\Api\HeiMao\HeiMao;
use smiler\logistics\Api\HuaHan\HuaHan;

/**
 * Class LogisticsApiIdenConfig
 * @package smiler\LogisticsApi
 * 物流商标识配置
 */
class LogisticsIdenConfig
{

    public static $class = [
        'huahan' => HuaHan::class, //华翰物流
        'heimao' => HeiMao::class, //黑猫物流
    ];

    /**
     * @param string $name
     * @return mixed
     * 获取物流商相关类
     */
    public static function getApiObj(string $name)
    {

        return self::$class[$name];
    }
}