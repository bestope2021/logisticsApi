<?php
/**
 * Created by PhpStorm
 * User: Administrator:smiler
 * Date: 2021/3/5 14:27
 * Email: <2166909366@qq.com>
 */

namespace smiler\logistics\Api\Bheo;


use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\LsSdkFieldMapInterface;

/**
 * 字段映射
 * Class FieldMap
 * @package smiler\logistics\Api\Bheo
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
    /**生成唯一guid
     * @return string
     */
    public static function createGuid(){
        if (function_exists('com_create_guid')){
            return com_create_guid();
        }else{
            mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid =// chr(123)// "{"
                substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12);
            //    .chr(125);// "}"
            return strtolower($uuid);
        }
    }
    public static function createSign(&$str_arr){
        $app_secret=$str_arr['appSecret'];
        //ksort($str_arr);//按键名升序排列
        unset($str_arr['appSecret']);
        //json格式化body
        $str_arr['body']=json_encode($str_arr['body'],JSON_UNESCAPED_UNICODE);
        $b = '';
        foreach($str_arr as $key=>$value){
            $b.=$value;
        }
        $sign=substr(md5($b.$app_secret),8,16);//生成签名字符串
        return $sign;
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
            'transNo',// 查询单号可能是 客户订单号/第三方订单号|运单号/追踪号
            'label_path_type',// 面单路径类型
            'transNoPrintPath',// 面单路径URL
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
                'flag',
                'message',
                'TrackingNumber',// 查询单号可能是 客户订单号/第三方订单号|运单号/追踪号/转单号
                'TrackingStatus',// 当前状态
                'Country',// 目的国家
                'Checkpoints',// 物流轨迹明细
            ];
        }

        if ($vars[0] == self::QUERY_TRACK_TWO) {
            $field = [
                'TrackingStatus',// 订单状态（货态）
                'Message',// 订单状态（货态）描述
                'DateTime',// 订单状态（货态）时间
                'Location',// 所在地
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
            'fieldCode',// 运输方式英文
            'fieldName',// 运输方式中文
            'shipping_method_type',// 运输方式类型,是否挂号
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