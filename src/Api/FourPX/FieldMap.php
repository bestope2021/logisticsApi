<?php
/**
 * Created by PhpStorm
 * User: Administrator:smiler
 * Date: 2021/5/19 9:10
 * Email: <2166909366@qq.com>
 */

namespace smiler\logistics\Api\FourPX;


use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\LsSdkFieldMapInterface;

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
            'customerOrderNo',// 客户订单号
            'syOrderNo',// 第三方订单号
            'trackingNumber',// 追踪号
            'frtTrackingNumber',// 尾程追踪号
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
            'order_no',// 查询单号可能是 客户订单号/第三方订单号|运单号/追踪号
            'label_path_type',// 面单路径类型
            'label_file',// 面单路径URL
            'label_path_plat',// 平台路径
            'label_content_type',// 面单类型
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
        if ($vars[0] == self::QUERY_TRACK_ONE) {
            $field = [
                'flag',// 处理状态： true 成功，false 失败
                'info',// 提示信息
                'trackingNumber',// 查询单号可能是 客户订单号/第三方订单号|运单号/追踪号
                'status',// 订单状态
                'content',// 订单状态（货态）说明
                'details',// 物流轨迹明细
            ];
        }

        if ($vars[0] == self::QUERY_TRACK_TWO) {
            $field = [
                'businessLinkCode',// 订单状态（货态）
                'trackingContent',// 订单状态（货态）描述
                'occurDatetime',// 订单状态（货态）时间
                'occurLocation',// 所在地
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
            'logistics_product_code',// 运输方式代码
            'logistics_product_name_en',// 运输方式英文
            'logistics_product_name_cn',// 运输方式中文
            'transport_mode',// 运输方式类型
            'remark',// 备注
            'extended',// 扩展参数
        ];

        return self::getFieldMap(self::getShippingMethodFields(), $field);
    }
}