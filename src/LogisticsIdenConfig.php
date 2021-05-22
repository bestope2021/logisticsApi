<?php
/**
 *
 * User: blaine
 * Date: 2/20/21
 */

namespace smiler\logistics;


use smiler\logistics\Api\BtdXms\BtdXms;
use smiler\logistics\Api\BxXms\BxXms;
use smiler\logistics\Api\CNE\Cne;
use smiler\logistics\Api\DgDhl\DgDhl;
use smiler\logistics\Api\DgPost\DgPost;
use smiler\logistics\Api\FourPX\FourPX;
use smiler\logistics\Api\HeiMao\HeiMao;
use smiler\logistics\Api\HuaHan\HuaHan;
use smiler\logistics\Api\Kjyt\Kjyt;
use smiler\logistics\Api\Kjyt\KjytLabel;
use smiler\logistics\Api\LeTian\LeTian;
use smiler\logistics\Api\Miaoxin\Miaoxin;
use smiler\logistics\Api\Miaoxin\MiaoxinLabel;
use smiler\logistics\Api\ShangMeng\ShangMeng;
use smiler\logistics\Api\ShangMeng\ShangMengTrack;
use smiler\logistics\Api\ShiHang\ShiHang;
use smiler\logistics\Api\SzDhl\SzDhl;
use smiler\logistics\Api\TianMu\TianMu;
use smiler\logistics\Api\TPost\TPost;
use smiler\logistics\Api\WanbExpress\WanbExpress;
use smiler\logistics\Api\Wts\Wts;
use smiler\logistics\Api\Wts\WtsLabel;
use smiler\logistics\Api\XyExp\XyExp;
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

    /**
     * 定义脚本执行映射关系
     * @var string[]
     */
    public static $class = [
        LogisticsIdent::LS_IDENT_HUA_HAN => HuaHan::class,// 华翰
        LogisticsIdent::LS_IDENT_BLACK_CAT => HeiMao::class,// 黑猫
        LogisticsIdent::LS_IDENT_SHI_HANG => ShiHang::class,// 世航
        LogisticsIdent::LS_IDENT_YAN_WEB => Yw::class,// 燕文
        LogisticsIdent::LS_IDENT_YAN_WEB_TRACK => YwTrack::class,// 燕文-轨迹查询
        LogisticsIdent::LS_IDENT_XY_EXP => XyExp::class,//兴源
        LogisticsIdent::LS_IDENT_XY_EXP_TRACK => XyExpTrack::class,// 兴源-轨迹查询
        LogisticsIdent::LS_IDENT_WT_TREE => Wts::class,// 梧桐树
        LogisticsIdent::LS_IDENT_WT_TREE_LABEL => WtsLabel::class,// 梧桐树-打印面单标签
        LogisticsIdent::LS_IDENT_BX_XMS => BxXms::class,// 八星
        LogisticsIdent::LS_IDENT_T_POST => TPost::class,// 通邮
        LogisticsIdent::LS_IDENT_WAN_BANG_EXPRESS => WanbExpress::class,// 万邦
        LogisticsIdent::LS_IDENT_BTD_XMS => BtdXms::class,// 宝通达
        LogisticsIdent::LS_IDENT_KUA_JING_YI_TONG => Kjyt::class,// 跨境易通
        LogisticsIdent::LS_IDENT_KUA_JING_YI_TONG_LABEL => KjytLabel::class,// 跨境易通-打印面单标签
        LogisticsIdent::LS_IDENT_SHENZHEN_DHL => SzDhl::class,// 深圳DHL
        LogisticsIdent::LS_IDENT_DONGGUAN_DHL => DgDhl::class,// 东莞DHL
        LogisticsIdent::LS_IDENT_MIAOXIN => Miaoxin::class,// 淼信
        LogisticsIdent::LS_IDENT_MIAOXIN_LABEL => MiaoxinLabel::class,// 淼信-打印面单标签
        LogisticsIdent::LS_IDENT_LETIAN_XMS => LeTian::class,// 乐天
        LogisticsIdent::LS_IDENT_DONGGUAN_POST => DgPost::class,// 东莞邮政
        LogisticsIdent::LS_IDENT_SHANG_MENG => ShangMeng::class,//商盟
        LogisticsIdent::LS_IDENT_SHANG_MENG_TRACK => ShangMengTrack::class,// 商盟-轨迹查询
        LogisticsIdent::LS_IDENT_CNE => Cne::class,// 递一国际
        LogisticsIdent::LS_IDENT_4PX => FourPX::class,// 递四方4PX
        LogisticsIdent::LS_IDENT_TIANMU => TianMu::class,// 天木头程
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