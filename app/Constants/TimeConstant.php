<?php


namespace App\Constants;


class TimeConstant
{
    public const DATE = 'Y-m-d';
    public const TIME = 'H:i:s';
    public const HOUR_MINUTE = 'H:i';
    public const DATETIME = self::DATE.' '.self::TIME;

    public const DATE_VI = 'd-m-Y';
    public const DATETIME_VI = self::DATE_VI.' '.self::TIME;
    public const DATETIME_BY_HI_DAY= 'H:i '. self::DATE_VI;
    public const DATETIME_BY_DAY_HI= self::DATE_VI . ' H:i';
    public const DATETIME_BY_DAY_HIS= self::DATE_VI . ' H:i:s';

    public const DATE_MAX = '2099-01-01';

    /**
     * Thời gian thực hiện một công việe nào đó, tính theo giây
     */

    public const QUEUE_MIN_DELAY_TIME = 15;
    public const QUEUE_DELAY_TIME = 60;

    public const CRONJOB_MIN_PERIODIC_TIME = 300;
    public const CRONJOB_PERIODIC_TIME = 3600;

    /**
     * Thời gian delay job cập nhật mã vận đơn (order_buyders.logistic_number), tính theo phút
     */
    public const UPDATE_LOGISTIC_CODE_PERIOD = 720;
    public const REUPDATE_LOGISTIC_CODE_PERIOD = 60;
}
