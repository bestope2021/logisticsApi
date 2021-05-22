<?php
/**
 * Created by PhpStorm
 * User: Administrator:smiler
 * Date: 2021/3/5 14:27
 * Email: <2166909366@qq.com>
 */

namespace smiler\logistics\Api\Wts;


use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\LsSdkFieldMapInterface;

/**
 * 字段映射
 * Class FieldMap
 * @package smiler\logistics\Api\HeiMao
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
        $fieldKey = [
            'flag',// 处理状态： true 成功，false 失败
            'info',// 提示信息
            'refrence_no',// 客户订单号
            'shipping_method_no',// 第三方订单号
            'channel_hawbcode',// 追踪号
            'frt_channel_hawbcode',// 尾程追踪号
            'prediction_freight',// 预估费用
            'effective_days',// 跟踪号有效期天数
            'extended',// 扩展参数
        ];


        return self::getFieldMap(self::getCreateOrderFields(), $fieldKey);
    }

    /**
     * 获取订单标签
     * @param mixed ...$vars
     * @return mixed
     */
    public static function packagesLabel(...$vars)
    {
        $fieldKey = [
            'flag',// 处理状态： true 成功，false 失败
            'info',// 提示信息
            'order_no',// 查询单号可能是 客户订单号/第三方订单号|运单号/追踪号
            'label_path_type',// 面单路径类型
            'lable_file',// 面单路径URL
            'label_path_plat',// 平台路径
            'lable_content_type',// 面单类型
            'extended',// 扩展参数
        ];

        return self::getFieldMap(self::getPackagesLabelFields(), $fieldKey);
    }

    /**
     * 获取物流商轨迹
     * todo: 待配置
     * @param mixed ...$vars
     * @return mixed
     */
    public static function queryTrack(...$vars)
    {
        if ($vars[0] == LsSdkFieldMapAbstract::QUERY_TRACK_ONE) {
            $fieldKey = [
                'flag',// 处理状态： true 成功，false 失败
                'info',// 提示信息
                'trackingNumber',// 查询单号可能是 客户订单号/第三方订单号|运单号/追踪号
                'Status',// 订单状态
                'trackContent',// 订单状态（货态）说明
                'details',// 物流轨迹明细
            ];
        }

        if ($vars[0] == LsSdkFieldMapAbstract::QUERY_TRACK_TWO) {
            $fieldKey = [
                'track_kind',// 订单状态（货态）
                'track_content',// 订单状态（货态）描述
                'track_date',// 订单状态（货态）时间
                'track_location',// 所在地
            ];
        }

        return self::getFieldMap(self::getQueryTrackFields($vars[0]), $fieldKey);
    }

    /**
     * 获取物流商运输方式
     * @param mixed ...$vars
     * @return mixed
     */
    public static function shippingMethod(...$vars)
    {
        $fieldKey = [
            'product_id',// 运输方式代码
            'product_shortname',// 运输方式英文
            'product_shortname',// 运输方式中文
            'express_type',// 运输方式类型
            'product_tracknoapitype',// 备注
            'extended',// 扩展参数
        ];

        return self::getFieldMap(self::getShippingMethodFields(), $fieldKey);
    }
}