<?php


namespace App\Constants;


class SupplierConstant
{
    public const TYPE_ONLINE = 'online';
    public const TYPE_OFFLINE = 'offline';
    public const SUPPLIER_TYPE = [
        self::TYPE_ONLINE => 'Online',
        self::TYPE_OFFLINE => 'Offline',
    ];
}
