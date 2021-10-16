<?php
/**
 * Created by PhpStorm
 * User: Administrator:smiler
 * Date: 2021/3/8 12:09
 * Email: <2166909366@qq.com>
 */

namespace smiler\logistics\Api\LeTianExp;


use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\LsSdkFieldMapInterface;

/**
 * 字段映射
 * Class FieldMap
 * @package smiler\logistics\Api\LeTianExp
 */
class FieldMap extends LsSdkFieldMapAbstract implements LsSdkFieldMapInterface
{
    /**
     * 创建订单
     * @param mixed ...$vars
     * @return mixed
     */
    public static function createOrder(...$vars)
    {
        $field = [
            'flag',// 处理状态： true 成功，false 失败
            'info',// 提示信息
            'orderNo',// 客户订单号
            'id',// 第三方订单号
            'trackingNo',// 追踪号
            'frt_channel_hawbcode',// 尾程追踪号
            'prediction_freight',// 预估费用
            'effective_days',// 跟踪号有效期天数
            'extended',// 扩展参数
        ];

        return self::getFieldMap(self::getCreateOrderFields(), $field);
    }

    /**
     * 获取订单标签
     * @param mixed ...$vars
     * @return mixed
     */
    public static function packagesLabel(...$vars)
    {
        $field = [
            'flag',// 处理状态： true 成功，false 失败
            'info',// 提示信息
            'trackingNo',// 查询单号可能是 客户订单号/第三方订单号|运单号/追踪号
            'label_path_type',// 面单路径类型
            'url',// 面单路径URL
            'label_path_plat',// 平台路径
            'lable_content_type',// 面单类型
            'extended',// 扩展参数
        ];

        return self::getFieldMap(self::getPackagesLabelFields(), $field);
    }

    /**
     * 获取物流商轨迹
     * @param mixed ...$vars
     * @return mixed
     */
    public static function queryTrack(...$vars)
    {
        if ($vars[0] == LsSdkFieldMapAbstract::QUERY_TRACK_ONE) {
            $field = [
                'flag',// 处理状态： true 成功，false 失败
                'info',// 提示信息
                'trackingNo',// 查询单号可能是 客户订单号/第三方订单号|运单号/追踪号
                'status',// 订单状态
                'status_msg',// 订单状态（货态）说明
                'fromDetail',// 物流轨迹明细
            ];
        }

        if ($vars[0] == LsSdkFieldMapAbstract::QUERY_TRACK_TWO) {
            $field = [
                'pathCode',// 订单状态（货态）
                'pathInfo',// 订单状态（货态）描述
                'pathTime',// 订单状态（货态）时间
                'pathLocation',// 所在地或者详情内容描述
            ];
        }

        return self::getFieldMap(self::getQueryTrackFields($vars[0]), $field);
    }

    /**
     * 获取物流商运输方式
     * @param mixed ...$vars
     * @return mixed
     */
    public static function shippingMethod(...$vars)
    {
        $field = [
            'code',// 运输方式代码
            'name',// 运输方式英文
            'name',// 运输方式中文
            'shipping_method_type',// 运输方式类型
            'remark',// 备注
            'extended',// 扩展参数
        ];

        return self::getFieldMap(self::getShippingMethodFields(), $field);
    }

    /**
     * 获取追踪号
     * @param mixed ...$vars
     * @return mixed
     */
    public static function getTrackNumber(...$vars)
    {
        $field = [
            'flag',// 处理状态： true 成功，false 失败
            'info',// 提示信息
            'trackingNo',// 追踪号
            'frt_channel_hawbcode',// 尾程追踪号
        ];

        return self::getFieldMap(self::getTrackNumberFields(), $field);
    }


}