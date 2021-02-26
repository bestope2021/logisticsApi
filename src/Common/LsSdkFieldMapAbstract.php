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
class LsSdkFieldMapAbstract
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
    static function getResponseData2MapData(array $response = [], array $fieldMap = []): array
    {
        if (!$response) {
            return [];
        }
        $ret = [];
        foreach ($response as $key => $val) {
            if (!array_key_exists($key, $fieldMap)) {
                continue;
            }
            $ret[$fieldMap[$key]] = $val ?? '';
        }
        return $ret;
    }

    /**
     * 必须字段 - 创建物流包裹生成运单号
     * 可扩展
     * @return array
     */
    static function getCreateOrderFields(): array
    {
        return [
            ResponseDataConst::LSA_FLAG,
            ResponseDataConst::LSA_TIP_MESSAGE,
            ResponseDataConst::LSA_ORDER_NUM,
            ResponseDataConst::LSA_ORDER_NUM_TP,
            ResponseDataConst::LSA_TRACKING_NUM,
            ResponseDataConst::LSA_FRT_TRACKING_NUM,
            ResponseDataConst::LSA_PRE_FREIGHT,
            ResponseDataConst::LSA_EFFECTIVE_DAY,
        ];
    }

    /**
     * 必须字段 - 创建物流包裹生成运单号
     * 可扩展
     * @return array
     */
    static function getPackagesLabelFields(): array
    {
        return [
            ResponseDataConst::LSA_FLAG,
            ResponseDataConst::LSA_TIP_MESSAGE,
            ResponseDataConst::LSA_ORDER_NO,
            ResponseDataConst::LSA_LABEL_TYPE,
            ResponseDataConst::LSA_LABEL_PATH,
            ResponseDataConst::LSA_LABEL_PATH_LOCAL,
        ];
    }


    /**
     * 必须字段 - 获取物流商轨迹
     * 可扩展
     * @param int $type 几维数据
     * @return array
     */
    static function getQueryTrackFields($type = self::QUERY_TRACK_ONE): array
    {
        if ($type == self::QUERY_TRACK_ONE) {
            return [
                ResponseDataConst::LSA_FLAG,
                ResponseDataConst::LSA_TIP_MESSAGE,
                ResponseDataConst::LSA_ORDER_NO,
                ResponseDataConst::LSA_ORDER_STATUS,
                ResponseDataConst::LSA_ORDER_STATUS_MSG,
                ResponseDataConst::LSA_LOGISTICS_TRAJECTORY,
            ];
        }

        if ($type == self::QUERY_TRACK_TWO) {
            return [
                ResponseDataConst::LSA_ORDER_STATUS,
                ResponseDataConst::LSA_ORDER_STATUS_CONTENT,
                ResponseDataConst::LSA_ORDER_STATUS_TIME,
                ResponseDataConst::LSA_ORDER_STATUS_LOCATION,
            ];
        }
        return [];
    }

    /**
     * 必须字段 - 获取物流商运输方式
     * 可扩展
     * @return array
     */
    static function getShippingMethodFields(): array
    {
        return [
            ResponseDataConst::LSA_SHIP_METHOD_CODE,
            ResponseDataConst::LSA_SHIP_METHOD_EN_NAME,
            ResponseDataConst::LSA_SHIP_METHOD_CN_NAME,
            ResponseDataConst::LSA_SHIP_METHOD_TYPE,
        ];
    }


}