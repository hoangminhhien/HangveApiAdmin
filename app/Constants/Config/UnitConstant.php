<?php

namespace App\Constants\Config;

use App\Constants\Base\BaseConstant;

class UnitConstant extends BaseConstant
{
    /**
     * Đơn vị đo lường
     */
    public const UNIT_MEASUERMENT_KG = 'kg';
    public const UNIT_MEASUREMENT_M3 = 'm3';
    public const AVAILABLE_UNIT_MEASUREMENT = [
        self::UNIT_MEASUERMENT_KG,
        self::UNIT_MEASUREMENT_M3,
    ];

    /**
     * Đơn vị đếm
     */
    public const UNIT_COUNT_BO = 'bộ';
    public const UNIT_COUNT_CAI = 'cái';
    public const UNIT_COUNT_CHAI = 'chai';
    public const UNIT_COUNT_DOI = 'đôi';
    public const UNIT_COUNT_HOP = 'hộp';
    public const UNIT_COUNT_LO = 'lọ';
    public const UNIT_COUNT_MANH = 'mảnh';
    public const UNIT_COUNT_QUYEN = 'quyển';
    public const UNIT_COUNT_TUI = 'túi';
    public const AVAILABLE_UNIT_COUNT = [
        self::UNIT_COUNT_BO,
        self::UNIT_COUNT_CAI,
        self::UNIT_COUNT_CHAI,
        self::UNIT_COUNT_DOI,
        self::UNIT_COUNT_HOP,
        self::UNIT_COUNT_LO,
        self::UNIT_COUNT_MANH,
        self::UNIT_COUNT_QUYEN,
        self::UNIT_COUNT_TUI,
    ];
}
