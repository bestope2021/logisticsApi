<?php
/**
 * Created by PhpStorm
 * User: Administrator:smiler
 * Date: 2021/2/26 12:06
 * Email: <2166909366@qq.com>
 */

namespace smiler\logistics\Common;

/**
 * 定义统一返回字段
 * Class ResponseDataConst
 * @package smiler\logistics\Common
 */
class ResponseDataConst
{
    // 定义提示 - 替换字符串
    const LSA_MSG_PARAMS_COUNT = '一次最多支持提交 %s 个订单';
    const LSA_MSG_PARAMS_SKU_COUNT = '每个订单一次最多支持 %s 个SKU产品';

    // 定义业务公共参数
    const LSA_SDK_REQ_DATA = 'sdk_req_data';// SDK请求参数
    const LSA_SDK_RES_DATA = 'sdk_res_data';// SDK响应参数

    const LSA_CURL_REQ_DATA = 'req_data';// 第三方请求参数
    const LSA_CURL_RES_DATA = 'res_data';// 第三方响应参数

    const LSA_DO_STATUS = 'doStatus'; // 操作状态
    const LSA_DO_STATUS_MSG = 'doStatusMsg';//操作状态说明

    const LSA_FLAG = 'flag';// 处理状态： true 成功，false 失败
    const LSA_TIP_MESSAGE = 'tipMsg';// 提示信息

    // 订单
    const LSA_ORDER_NO_TYPE = 'customerOrderNo';//查询单号类型，默认为客户订单号（值为查询单号字段，如：customerOrderNo），实际可根据业务改变
    const LSA_ORDER_NO = 'orderNo';//查询单号可能是 客户订单号/第三方订单号|运单号/追踪号
    const LSA_ORDER_NUM = 'customerOrderNo';// 客户订单号
    const LSA_ORDER_NUM_TP = 'syOrderNo';// 第三方订单号|运单号
    const LSA_TRACKING_NUM = 'trackingNumber';// 追踪号
    const LSA_FRT_TRACKING_NUM = 'frtTrackingNumber';// 尾程追踪号
    const LSA_PRE_FREIGHT = 'predictionFreight';// 预估费用
    const LSA_EFFECTIVE_DAY = 'effectiveDays';// 跟踪号有效期天数[默认15天]
    const P_EXTENDED = 'extended ';// 扩展参数|array
    const P_LABEL_CUSTOM_PATH = 'customLabel';// 报关标签链接|base64
    const P_LABEL_PACKAGE_PATH = 'packageLabel';// 配货标签链接|base64
    const P_LABEL_INVOICE_PATH = 'invoiceLabel';// DHL发票链接|base64

    // 轨迹
    const LSA_ORDER_STATUS = 'status';// 订单状态（货态）
    const LSA_ORDER_STATUS_MSG = 'statusMsg';// 订单状态（货态）说明
    const LSA_ORDER_STATUS_TIME = 'statusTime';// 订单状态（货态）时间
    const LSA_ORDER_STATUS_CONTENT = 'statusContent';// 订单状态（货态）描述
    const LSA_ORDER_STATUS_LOCATION = 'statusLocation';// 所在地
    const LSA_LOGISTICS_TRAJECTORY = 'logisticsTrackingDetails';// 物流轨迹明细

    // 面单
    const LSA_LABEL_DATA = 'labelData';// 面单变量数据
    const LSA_LABEL_PATH_LOCAL = 'labelPathPlat';// 平台路径
    const LSA_LABEL_PATH = 'labelPath';// 面单路径URL|base64
    const LSA_LABEL_BASE64_PREFIX = 'data:image/jpg;base64,';// base64图片编码前缀
    const LSA_LABEL_TYPE = 'labelType';// 面单路径URL|base64  类型
    const LSA_LABEL_PATH_TYPE_PDF = 'pdf';// 面单路径URL|base64  类型
    const LSA_LABEL_PATH_TYPE_BYTE_STREAM_PDF = 'byte_stream_pdf';// 字节流类型
    const LSA_LABEL_PATH_TYPE_IMG_BASE64 = 'img_base64';// 面单路径URL|base64  类型
    const LSA_LABEL_CONTENT_TYPE = 'labelContentType';// 面单内容类型
    const LSA_LABEL_TYPE_1 = 1;// 面单类型 - 运单
    const LSA_LABEL_TYPE_2 = 2;// 面单类型 - 报关单/发票

    // 邮寄方式
    const LSA_SHIP_METHOD_CODE = 'shippingMethodCode';// 运输方式代码
    const LSA_SHIP_METHOD_EN_NAME = 'shippingMethodEnName';// 运输方式英文
    const LSA_SHIP_METHOD_CN_NAME = 'shippingMethodCnName';// 运输方式中文
    const LSA_SHIP_METHOD_TYPE = 'shippingMethodType';// 运输方式类型
    const LSA_SHIP_METHOD_REMARK = 'remark';// 备注
}