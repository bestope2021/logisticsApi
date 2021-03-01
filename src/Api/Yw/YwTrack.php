<?php
/**
 *
 * User: blaine
 * Date: 2/24/21
 */

namespace smiler\logistics\Api\Yw;


use smiler\logistics\Common\TrackLogisticsInterface;
use smiler\logistics\LogisticsAbstract;

class YwTrack extends LogisticsAbstract implements TrackLogisticsInterface
{
    public $iden = 'ywTrack';

    public $iden_name = '燕文物流轨迹查询';

    const QUERY_TRACK_COUNT = 30;

    public $apiHeaders = [];

    public $interface = [
    ];

    public function __construct($config)
    {
        $this->checkKeyExist(['userId', 'url'], $config);
        if(!empty($config['apiHeaders'])){
            $this->apiHeaders = $config['apiHeaders'];
        }
        $this->config = $config;
    }

    /**
     * @param $trackNumber
     * @return mixed|void
     * 物流轨迹查询
     * {"code":0,"message":"success","result":[{"tracking_number":"UD000019259YP","waybill_number":null,"exchange_number":null,"checkpoints":null,"tracking_status":"NOTFOUND","last_mile_tracking_expected":false,"origin_country":null,"destination_country":null}],"requestTime":"2021-02-24T16:19:21.4627273+08:00","elapsedMilliseconds":{"total":60}}
     */
    public function queryTrack($trackNumber)
    {
        $trackNumber = implode(',',$this->toArray($trackNumber));

        $this->apiHeaders['Authorization'] = $this->config['userId'];

        $requestUrl = trim($this->config['url'],'/')."/api/tracking?nums=".$trackNumber;

        $response = $this->sendCurl('get',$requestUrl,[],'form',$this->apiHeaders);
        var_dump($response);
        return $response;
    }
}