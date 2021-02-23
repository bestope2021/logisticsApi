<?php
/**
 *
 * User: blaine
 * Date: 2/22/21
 */

namespace smiler\logistics\Common;

/**
 * Interface PackageLabelLogisticsInterface
 * @package smiler\logistics
 * 物流标签
 */
interface PackageLabelLogisticsInterface
{
    /**
     * 获取订单标签
     * @param $params['trackNumber']  物流运单号、系统单号、物流商单号 todo 以物流商文档为准
     * @param $params['label_type'] 标签尺寸类型 todo 以物流商文档为准
     * @param $params['label_content_type'] 标签内容类型 todo 以物流商文档为准
     * @return mixed
     */
    public function getPackagesLabel($params);
}