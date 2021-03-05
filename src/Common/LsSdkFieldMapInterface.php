<?php
/**
 * Created by PhpStorm
 * User: Administrator:smiler
 * Date: 2021/3/5 14:30
 * Email: <2166909366@qq.com>
 */

namespace smiler\logistics\Common;

/**
 * 定义接口映射字段实现方法
 * Interface LsSdkFieldMapInterface
 * @package smiler\logistics\Common
 */
interface LsSdkFieldMapInterface
{
    /**
     * 创建订单
     * @param mixed ...$vars
     * @return mixed
     */
    public static function createOrder(...$vars);

    /**
     * 获取订单标签
     * @param mixed ...$vars
     * @return mixed
     */
    public static function packagesLabel(...$vars);

    /**
     * 获取物流商轨迹
     * @param mixed ...$vars
     * @return mixed
     */
    public static function queryTrack(...$vars);

    /**
     * 获取物流商运输方式
     * @param mixed ...$vars
     * @return mixed
     */
    public static function shippingMethod(...$vars);
}