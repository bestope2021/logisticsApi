<?php
/**
 *
 * User: blaine
 * Date: 2/22/21
 */

namespace smiler\logistics\Common;

/**
 * Interface TrackLogisticsInterface
 * @package smiler\logistics
 * 物流轨迹
 */
interface TrackLogisticsInterface
{
    /**
     * 获取物流商轨迹
     * @return mixed
     */
    public function queryTrack($trackNumber);
}