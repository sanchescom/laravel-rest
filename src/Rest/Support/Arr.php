<?php

namespace App\Rest\Support;

use Illuminate\Support\Arr as BaseArr;
use Sanchescom\Support\Json;

class Arr extends BaseArr
{
    /**
     * @param array $array
     *
     * @throws \Sanchescom\Support\Exceptions\UnableEncodeJsonException
     *
     * @return string
     */
    public static function asJson(array $array)
    {
        return Json::encode(self::emptyObject($array));
    }

    /**
     * Transforming empty array nodes to objects.
     *
     * @param array $array
     *
     * @return array
     */
    public static function emptyObject(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = empty($value) ? (object)$value : self::emptyObject($value);
            }
        }

        return $array;
    }
}
