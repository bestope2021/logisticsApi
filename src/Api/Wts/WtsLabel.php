<?php
/**
 *
 * User: blaine
 * Date: 2/28/21
 */

namespace smiler\logistics\Api\Wts;


use smiler\logistics\Common\LsSdkFieldMapAbstract;
use smiler\logistics\Common\PackageLabelLogisticsInterface;
use smiler\logistics\Common\ResponseDataConst;
use smiler\logistics\LogisticsAbstract;


/**
 * Class WtsLabel
 * @package smiler\logistics\Api\Wts
 * 梧桐树获取面单标签
 */
class WtsLabel extends LogisticsAbstract implements PackageLabelLogisticsInterface
{
    public $iden = 'wtsLabel';

    public $iden_name = '梧桐树面单标签';

    /**
     * curl 请求数据类型
     * @var string
     */
    public $dataType = 'form';

    public $apiHeaders = [];

    public $interface = [
        'getPackagesLabel' => 'order/FastRpt/PDF_NEW.aspx'
    ];
    /**
     * Wts constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->checkKeyExist(['username', 'url', 'password'], $config);
        $this->config = $config;
    }

    public function getPackagesLabel($params)
    {
        $data = [
            'PrintType' => $params['label_type'] ?? 'lab10_10', //PDF标签尺寸类型：1：10 * 10 标签；2：A4纸；3：10 * 15标签
            'order_id' => implode(',', $this->toArray($params['trackNumber'])),
        ];
        $fieldMap = FieldMap::packagesLabel();
        $requestUrl = $this->config['url'] . $this->interface[__FUNCTION__]."?".http_build_query($data);
        $fieldData[] = LsSdkFieldMapAbstract::getResponseData2MapData([
            'label_path_type' => ResponseDataConst::LSA_LABEL_PATH_TYPE_PDF,
            'lable_file' => $requestUrl,
            'order_no' =>  implode(',', $this->toArray($params['trackNumber'])),
            'flag' => true,
        ], $fieldMap);
        return $this->retSuccessResponseData($fieldData);
    }

    public function request($function)
    {
    }

    /**
     * 获取请求数据
     */
    public function buildParams($interface, $arr = [])
    {

    }
}