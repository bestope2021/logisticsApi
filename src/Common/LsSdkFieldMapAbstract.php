<?php
/**
 * Created by PhpStorm
 * User: Administrator:smiler
 * Date: 2021/2/26 14:20
 * Email: <2166909366@qq.com>
 */

namespace smiler\logistics\Common;

/**
 * 组装SDk与物流商字段映射数据
 * Class ActionFieldMap
 * @package smiler\logistics\Common
 */
abstract class LsSdkFieldMapAbstract
{
    // 追踪数据格式类型 - 一维
    const QUERY_TRACK_ONE = 1;
    // 追踪数据格式类型 - 二维
    const QUERY_TRACK_TWO = 2;

    /**
     * 返回SDK统一对应的格式数据
     * @param array $response 物流商返回数据 - 一维数组
     * @param array $fieldMap 字段映射关系 - 一维数据
     * @return array
     */
    final static function getResponseData2MapData(array $response = [], array $fieldMap = []): array
    {
        if (!$response) {
            return [];
        }
        $ret = [];
        array_walk($fieldMap, function ($key, $val) use ($response, &$ret) {
            $ret[$val] = $response[$key] ?? '';
        });
        return $ret;
    }

    /**
     * 生成新数组
     * @param array $key 基类 key
     * @param array $val 子类 val
     * @return array
     */
    final static function getFieldMap($key = [], $val = [])
    {
        return array_combine($key, $val);
    }

    /**
     * 必须字段 - 创建物流包裹生成运单号
     * 可扩展
     * @return array
     */
    final static function getCreateOrderFields(): array
    {
        return [
            ResponseDataConst::LSA_FLAG,// 处理状态： true 成功，false 失败
            ResponseDataConst::LSA_TIP_MESSAGE,// 提示信息
            ResponseDataConst::LSA_ORDER_NUM,// 客户订单号
            ResponseDataConst::LSA_ORDER_NUM_TP,// 第三方订单号
            ResponseDataConst::LSA_TRACKING_NUM,// 追踪号
            ResponseDataConst::LSA_FRT_TRACKING_NUM,// 尾程追踪号
            ResponseDataConst::LSA_PRE_FREIGHT,// 预估费用
            ResponseDataConst::LSA_EFFECTIVE_DAY,// 跟踪号有效期天数
        ];
    }

    /**
     * 必须字段 - 获取订单标签
     * 可扩展
     * @return array
     */
    final static function getPackagesLabelFields(): array
    {
        return [
            ResponseDataConst::LSA_FLAG,// 处理状态： true 成功，false 失败
            ResponseDataConst::LSA_TIP_MESSAGE,// 提示信息
            ResponseDataConst::LSA_ORDER_NO,// 查询单号可能是 客户订单号/第三方订单号|运单号/追踪号
            ResponseDataConst::LSA_LABEL_TYPE,// 面单路径类型
            ResponseDataConst::LSA_LABEL_PATH,// 面单路径URL
            ResponseDataConst::LSA_LABEL_PATH_LOCAL,// 平台路径
            ResponseDataConst::LSA_LABEL_CONTENT_TYPE,// 面单类型
        ];
    }


    /**
     * 必须字段 - 获取物流商轨迹
     * 可扩展
     * @param int $type 几维数据
     * @return array
     */
    final static function getQueryTrackFields($type = self::QUERY_TRACK_ONE): array
    {
        if ($type == self::QUERY_TRACK_ONE) {
            return [
                ResponseDataConst::LSA_FLAG,// 处理状态： true 成功，false 失败
                ResponseDataConst::LSA_TIP_MESSAGE,// 提示信息
                ResponseDataConst::LSA_ORDER_NO,// 查询单号可能是 客户订单号/第三方订单号|运单号/追踪号
                ResponseDataConst::LSA_ORDER_STATUS,// 订单状态
                ResponseDataConst::LSA_ORDER_STATUS_MSG,// 订单状态（货态）说明
                ResponseDataConst::LSA_LOGISTICS_TRAJECTORY,// 物流轨迹明细
            ];
        }

        if ($type == self::QUERY_TRACK_TWO) {
            return [
                ResponseDataConst::LSA_ORDER_STATUS,// 订单状态（货态）
                ResponseDataConst::LSA_ORDER_STATUS_CONTENT,// 订单状态（货态）描述
                ResponseDataConst::LSA_ORDER_STATUS_TIME,// 订单状态（货态）时间
                ResponseDataConst::LSA_ORDER_STATUS_LOCATION,// 所在地
            ];
        }
        return [];
    }

    /**
     * 必须字段 - 获取物流商运输方式
     * 可扩展
     * @return array
     */
    final static function getShippingMethodFields(): array
    {
        return [
            ResponseDataConst::LSA_SHIP_METHOD_CODE,// 运输方式代码
            ResponseDataConst::LSA_SHIP_METHOD_EN_NAME,// 运输方式英文
            ResponseDataConst::LSA_SHIP_METHOD_CN_NAME,// 运输方式中文
            ResponseDataConst::LSA_SHIP_METHOD_TYPE,// 运输方式类型
            ResponseDataConst::LSA_SHIP_METHOD_REMARK,// 备注
        ];
    }


}