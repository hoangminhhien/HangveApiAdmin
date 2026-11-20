<?php


namespace App\Constants;


class ConsignmentConstant
{
    public const STATUS_PENDING = 0;
    public const STATUS_IN_PROGRESS = 1;
    public const STATUS_DONE = 2;
    public const STATUS_CANCEL = 3;
    public const KEY_STATUS_WAITING_QUOTE = 'status_4';
    public const KEY_STATUS_WAITING_DEPOSIT = 'status_5';
    public const KEY_STATUS_DEPOSITED = 'status_6';

    public const KEY_STATUS_PENDING = 'status_'.self::STATUS_PENDING;
    public const KEY_STATUS_IN_PROGRESS = 'status_'.self::STATUS_IN_PROGRESS;
    public const KEY_STATUS_DONE = 'status_'.self::STATUS_DONE;
    public const KEY_STATUS_CANCEL = 'status_'.self::STATUS_CANCEL;

    public const STATUSES = [
        self::KEY_STATUS_PENDING => 'Chờ xử lý',
        self::KEY_STATUS_WAITING_QUOTE => 'Chờ báo giá',
        self::KEY_STATUS_WAITING_DEPOSIT => 'Chờ đặt cọc',
        self::KEY_STATUS_DEPOSITED => 'Đã đặt cọc',
        self::KEY_STATUS_IN_PROGRESS => 'Đang xử lý',
        self::KEY_STATUS_DONE => 'Đã hoàn thành',
        self::KEY_STATUS_CANCEL => 'Hủy'
    ];

    public const LIST_STATUS_ACCEPT_CANCEL = [
        self::KEY_STATUS_PENDING,
        self::KEY_STATUS_WAITING_QUOTE,
        self::KEY_STATUS_WAITING_DEPOSIT
    ];

    public const LIST_STATUS = [
        self::KEY_STATUS_PENDING => [
            'name' => self::STATUSES[self::KEY_STATUS_PENDING],
            'color' => ColorConstant::STATUS_0,
            'value' => self::KEY_STATUS_PENDING
        ],
        self::KEY_STATUS_WAITING_QUOTE => [
            'name' => self::STATUSES[self::KEY_STATUS_WAITING_QUOTE],
            'color' => ColorConstant::STATUS_4,
            'value' => self::KEY_STATUS_WAITING_QUOTE
        ],
        self::KEY_STATUS_WAITING_DEPOSIT => [
            'name' => self::STATUSES[self::KEY_STATUS_WAITING_DEPOSIT],
            'color' => ColorConstant::STATUS_5,
            'value' => self::KEY_STATUS_WAITING_DEPOSIT
        ],
        self::KEY_STATUS_DEPOSITED => [
            'name' => self::STATUSES[self::KEY_STATUS_DEPOSITED],
            'color' => ColorConstant::STATUS_6,
            'value' => self::KEY_STATUS_DEPOSITED
        ],
        self::KEY_STATUS_IN_PROGRESS => [
            'name' => self::STATUSES[self::KEY_STATUS_IN_PROGRESS],
            'color' => ColorConstant::STATUS_1,
            'value' => self::KEY_STATUS_IN_PROGRESS
        ],
        self::KEY_STATUS_DONE => [
            'name' => self::STATUSES[self::KEY_STATUS_DONE],
            'color' => ColorConstant::STATUS_2,
            'value' => self::KEY_STATUS_DONE
        ],
        self::KEY_STATUS_CANCEL => [
            'name' => self::STATUSES[self::KEY_STATUS_CANCEL],
            'color' => ColorConstant::STATUS_3,
            'value' => self::KEY_STATUS_CANCEL
        ],
    ];

    public const STATUSES_COLOR = [
        ColorConstant::STATUS_0,
        ColorConstant::STATUS_4,
        ColorConstant::STATUS_5,
        ColorConstant::STATUS_6,
        ColorConstant::STATUS_1,
        ColorConstant::STATUS_2,
        ColorConstant::STATUS_3,
    ];

    public const IN_TRANSIT = ['cn-vi' => 'Trung Quốc - Việt Nam'];

    public const STATUES_SHOW_DETAILS = [
        self::KEY_STATUS_PENDING,
        self::KEY_STATUS_IN_PROGRESS,
        self::KEY_STATUS_DONE,
    ];

}
