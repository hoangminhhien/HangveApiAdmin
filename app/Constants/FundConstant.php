<?php


namespace App\Constants;


class FundConstant
{
    const
        MONEY_FUND_TYPE_CHARGE   = 1,
        MONEY_FUND_TYPE_WITHDRAW = 2;

    const
        FUND_TYPE_PAY_CHARGE = 1; // initDefault for fund type pay

    const
        FUND_TYPE_PAY_CHARGE_CODE = 1, // initDefault for fund type pay
        FUND_TYPE_PAY_CHARGE_CONFIRM_CODE = 11; // initDefault for fund type pay

    const
        LOG_TYPE_UPDATE = 1,
        LOG_TYPE_DELETE = 2;

    const LOG_TYPES = [
        self::LOG_TYPE_UPDATE => 'Update',
        self::LOG_TYPE_DELETE => 'Delete'
    ];
}