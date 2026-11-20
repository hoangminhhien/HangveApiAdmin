<?php


namespace App\Helpers;


class AccountingHelper
{
    /**
     * Lấy giá trị tiền
     * @param  float  $result
     * @param  int|null  $precision
     * @return float
     */
    public static function getCosts($result = 0, ?int $precision = 0): float
    {
        $result = round($result, $precision);
        return $result;
    }
}