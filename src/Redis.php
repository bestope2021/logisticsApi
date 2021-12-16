<?php

namespace smiler\logistics;

/**
 * Redis缓存驱动
 * 要求安装phpredis扩展：https://github.com/nicolasff/phpredis
 */
class Redis
{
    /**
     * 返回实例化redis
     * @return mixed
     */
    public static function redis()
    {
        return \Yii::$app->redis;
    }


    /**
     * @desc 设置string key value
     * @author 1
     * @version v2.1
     * @date: 2020/11/03
     * @param $key
     * @param $value
     * @param int $expire 设置缓存时间
     * @param bool $is_encryption 是否加密
     * @return bool
     */
    public static function set($key, $value, $expire = 60, $is_encryption = true)
    {
        if ($is_encryption) {
            $value = json_encode($value);
            $value = gzcompress($value);
        }
        $r[] = self::redis()->set($key, $value);
        if ($expire) {
            $r[] = self::redis()->expire($key, $expire);
        }
        return self::check_back($r);
    }

    /**
     * @desc 获取key value
     * @author 1
     * @version v2.1
     * @date: 2020/11/03
     * @param $key
     * @param bool $is_encryption
     * @return mixed
     */
    public static function get($key, $is_encryption = true)
    {
        $value = self::redis()->get($key);
        if ($is_encryption && $value) {
            $value = gzuncompress($value);
            return json_decode($value, true);
        }
        return '';
    }


    /**
     * @desc 设置hash key value
     * @author 1
     * @version v2.1
     * @date: 2020/11/03
     * @param $hKey |hash名称
     * @param $key
     * @param $value
     * @param int $expire 缓存时间
     * @param bool $is_encryption 是否加密
     * @return bool
     */
    public static function hset($hKey, $key, $value, $expire = 60, $is_encryption = true)
    {
        if ($is_encryption) {
            $value = json_encode($value);
            $value = gzcompress($value);
        }
        $r[] = self::redis()->hset($hKey, $key, $value);
        if ($expire) {
            $r[] = self::redis()->expire($hKey, $expire);
        }
        return self::check_back($r);
    }

    /**
     * @desc 获取hash key value
     * @author 1
     * @version v2.1
     * @date: 2020/11/03
     * @param $hKey |hash名称
     * @param $key
     * @param bool $is_encryption 是否加密
     * @return bool
     */
    public static function hget($hKey, $key, $is_encryption = true)
    {
        $value = self::redis()->hget($hKey, $key);
        if ($is_encryption && $value) {
            $value = gzuncompress($value);
            return json_decode($value, true);
        }
        return $value;
    }

    /**
     * @desc 删除redis key
     * @author 1
     * @version v2.1
     * @date: 2020/11/03
     * @param $key
     * @return mixed
     */
    public static function del($key)
    {
        return self::redis()->del($key);
    }

    /**
     * @desc 上锁
     * @param $key
     * @param $data
     * @param int $expire
     * @return mixed
     * @author: Ghh <ggx9110@163.com>
     * @datetime: 2021/7/22 10:25
     */
    public static function setnx($key,$data=6,$expire=0)
    {

        $exists = self::redis()->exists($key);
        if(!$exists){
            self::redis()->setnx($key,$data);
            if($expire) self::redis()->expire($key,$expire);
        }
        return $exists;
    }

    /**
     * @desc 返回结果
     * @author 1
     * @version v2.1
     * @date: 2020/11/03
     * @param $rs
     * @return bool
     */
    public static function check_back($rs)
    {
        if ($rs) {
            foreach ($rs as $va) {
                if (!$va) {
                    return false;
                }
            }
            return true;
        }
        return true;
    }



}
