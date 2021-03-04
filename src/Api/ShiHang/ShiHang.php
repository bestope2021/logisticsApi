<?php
/**
 *
 * User: blaine
 * Date: 2/23/21
 */

namespace smiler\logistics\Api\ShiHang;


use smiler\logistics\Api\HeiMao\HeiMao;

/**
 * 世航国际物流
 * @link http://shgj.rtb56.com/usercenter/manager/api_document.aspx#createorder
 * Class ShiHang
 * @package smiler\logistics\Api\ShiHang
 */
class ShiHang extends HeiMao
{
    public $iden = "shihang";

    public $iden_name = "世航国际物流";
}