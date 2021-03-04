<?php
/**
 *
 * User: blaine
 * Date: 2/20/21
 */

namespace smiler\logistics;


use smiler\logistics\Api\BxXms\BxXms;
use smiler\logistics\Api\TPost\TPost;
use smiler\logistics\Api\Wts\Wts;
use smiler\logistics\Api\Wts\WtsLabel;
use smiler\logistics\Api\XyExp\XyExp;
use smiler\logistics\Api\HeiMao\HeiMao;
use smiler\logistics\Api\HuaHan\HuaHan;
use smiler\logistics\Api\ShiHang\ShiHang;
use smiler\logistics\Api\XyExp\XyExpTrack;
use smiler\logistics\Api\Yw\Yw;
use smiler\logistics\Api\Yw\YwTrack;

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
        'shihang' =>  ShiHang::class, //世航国际物流
        'yw' => Yw::class,         //燕文物流
        'ywTrack' => YwTrack::class, //燕文物流轨迹查询
        'xyexp' => XyExp::class, //兴源物流商
        'xyexpTrack' => XyExpTrack::class, //兴源物流商轨迹查询
        'wts'        => Wts::class, //梧桐树物流商
        'wtsLabel'   => WtsLabel::class, //梧桐树打印面单标签
        'bxxms'      => BxXms::class, //八星物流商
        'tpost'      => TPost::class, //通邮物流商
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