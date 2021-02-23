<?php
/**
 *
 * User: blaine
 * Date: 2/22/21
 */

namespace smiler\logistics\Common;


interface BaseLogisticsInterface
{
    /**
     * @param $interface
     * @param array $data
     * @return mixed
     * 构建公共请求参数
     */
    public function buildParams($interface,$data=[]);

    /**
     * @param string $function 方法名
     * @param string $method 请求方式
     * @param array $data 请求数据
     * @return mixed
     * 物流商公共请求
     */
    public function request($function, $method = 'post', $data = []);

    /**
     * 创建物流包裹生成运单号
     * @return mixed
     */
    public function createOrder(array $params=[]);

    /**
     * 获取物流商运输方式
     * @return mixed
     */
    public function getShippingMethod();

    /**
     * 修改重量
     * @param string $params['order_id'] 系统自定义单号、物流商单号、跟踪号
     * @param float $params['weight'] 重量 单位：KG
     * @return mixed
     */
    public function operationPackages(array $params);

    /**
     * 取消订单，删除订单
     * @return mixed
     */
    public function deleteOrder(string $order_code);

    /**
     * 修改订单状态
     * @return mixed
     */
    public function updateOrderStatus(array $params);

    /**
     * 获取订单费用
     * @return mixed
     */
    public function getFeeByOrder(string $order_id);

    /**
     * @param $order_id
     * @return mixed
     * 获取包裹费用明细
     */
    public function getFeeDetailByOrder($order_id);

    /**
     * @param $order_id
     * @return mixed
     * 获取包裹详情
     */
    public function getPackagesDetail($order_id);
}