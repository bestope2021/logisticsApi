<?php
/**
 *
 * User: blaine
 * Date: 2/21/21
 */

namespace smiler\logistics;


use smiler\logistics\Exception\InvalidIArgumentException;

trait LogisticsTool
{
    public function checkKeyExist($key, $arr)
    {

        $msg = '';
        $keys = $this->toArray($key);

        foreach ($keys as $item) {
            if (!array_key_exists($item, $arr) || empty($arr[$item])) {
                $msg .= $item . ",";
            }
        }

        if (!empty($msg)) {
            throw new InvalidIArgumentException(trim($msg, ',') . " 不能为空");
        }
    }

    /**
     * 字符串转换为数组
     * @param $string
     * @return array
     */
    public function toArray($string)
    {
        $arr = [];
        if (is_string($string) && strpos(',', $string) === false) {
            return [$string];
        }

        if (is_string($string) && strpos(',', $string) !== false) {
            return explode(',', $string);
        }
        if (is_array($string)) {
            return $string;
        }
        return $arr;
    }
}