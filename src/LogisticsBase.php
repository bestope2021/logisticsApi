<?php
/**
 *
 * User: blaine
 * Date: 2/20/21
 */

namespace smiler\logistics;





use smiler\logistics\Common\BaseLogisticsInterface;
use smiler\logistics\Common\Logs;
use smiler\logistics\Exception\InvalidIArgumentException;

class LogisticsBase
{
    /**
     * @var array
     */
    private static $instances = [];
    /**
     * LogisticsApiBase constructor.
     * @param string $name 物流商标识
     * @param array $config 物流商api配置
     */
    private function __construct()
    {

    }

    /**
     * @param string $name
     * @param array $config
     * @return BaseLogisticsInterface
     * @throws InvalidIArgumentException
     */
    final public static function getInstance(string $name,array $config=[], $rootPath = '/log_ls_sdk/', $title = '')
    {
//        if(empty($config)){
//            throw new InvalidIArgumentException('物流商配置参数不能为空');
//        }

        if(!array_key_exists($name,LogisticsIdenConfig::$class)){
            throw new InvalidIArgumentException('物流商标识未找到');
        }

        Logs::setRootPath($rootPath);
        Logs::setTitle($title);

        if(!isset(self::$instances[$name])){
            $obj = LogisticsIdenConfig::getApiObj($name);
            self::$instances[$name] = new $obj($config);
        }

        return self::$instances[$name];
    }

    private function __clone() {}

    private function __wakeup() {}
}