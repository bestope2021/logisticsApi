<?php
/**
 *
 * User: Ghh.Guan
 * Date: 4/2/21
 */

namespace smiler\logistics\Api\TianMu;


use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\LsSdkFieldMapInterface;

/**
 * 字段映射
 * Class FieldMap
 * @package smiler\logistics\Api\TianMu
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
            'refrence_no',// 客户订单号
            'order_id',// 第三方订单号
            'shipping_method_no',// 追踪号
            'channel_hawbcode',// 尾程追踪号
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
            'lable_file',// 面单路径URL
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
                'tno',// 查询单号可能是 客户订单号/第三方订单号|运单号/追踪号
                'status',// 订单状态
                'pathInfo',// 订单状态（货态）说明
                'sPaths',// 物流轨迹明细
            ];
        }

        if ($vars[0] == LsSdkFieldMapAbstract::QUERY_TRACK_TWO) {
            $field = [
                'status',// 订单状态（货态）
                'pathInfo',// 订单状态（货态）描述
                'pathTime',// 订单状态（货态）时间
                'pathAddr',// 所在地
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
            'enname',// 运输方式英文
            'cnname',// 运输方式中文
            'shipping_method_type',// 运输方式类型
            'note',// 备注
            'extended',// 扩展参数
        ];

        return self::getFieldMap(self::getShippingMethodFields(), $field);
    }
}