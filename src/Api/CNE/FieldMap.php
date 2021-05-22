<?php
/**
 * Created by PhpStorm
 * User: Administrator:smiler
 * Date: 2021/5/18 11:32
 * Email: <2166909366@qq.com>
 */

namespace smiler\logistics\Api\CNE;


use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\LsSdkFieldMapInterface;
use think\migration\command\seed\Run;

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
                'state',// 订单状态（货态）
                'details',// 订单状态（货态）描述
                'date',// 订单状态（货态）时间
                'place',// 所在地
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
            'oName',// 运输方式代码
            'oName',// 运输方式英文
            'cName',// 运输方式中文
            'shipping_method_type',// 运输方式类型
            'Description',// 备注
            'extended',// 扩展参数
        ];

        return self::getFieldMap(self::getShippingMethodFields(), $field);
    }

    /**
     * 平台映射关系
     * 固定参数：WISH,EBAY,ALIEXPRESS,AMAZON,DHGATE,JD,CDISCOUNT,LAZADA,TOPHATTER,JOOM,SHOPEE,MAGENTO,SHOPIFY,1688, VOVA；自营店铺：MYSHOP；其他：OTHER
     * @param null $id
     * @return mixed|string
     */
    public static function platformMap($id = null){
        $map = [
            '' => 'OTHER',
            2 => 'AMAZON',
            3 => 'EBAY',
            4 => 'WISH',
            5 => 'SHOPIFY',
            6 => 'LAZADA',
            7 => 'SHOPEE',
            9 => 'ALIEXPRESS',
//            2 => 'DHGATE',
//            2 => 'JD',
//            2 => 'CDISCOUNT',
//            2 => 'TOPHATTER',
//            2 => 'JOOM',
//            2 => 'MAGENTO',
//            2 => '1688',
//            2 => 'VOVA',
//            2 => 'MYSHOP',
        ];
        return $map[$id] ?? '';
    }
}