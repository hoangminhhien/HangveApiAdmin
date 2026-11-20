<?php

namespace App\Constants;

class BuyderServiceConstant
{
    public const SOURCE_TAOBAO = 'taobao';
    public const SOURCE_1688 = '1688';
    public const SYNC_TAOBAO = 'sync_taobao';

    public const SYNC_1688 = 'sync_1688';

    public const AVAILABLE_SERVICES = [
        self::SYNC_TAOBAO,
        self::SYNC_1688
    ];
    public const SERVICE_IMAGES = [
        self::SYNC_TAOBAO => '@/static/images/header-top/taobao.png',
        self::SYNC_1688 => '@/static/images/header-top/1688.png',
    ];
    public const COUNTRY_EN = 'en';
    public const AVAILABLE_SOURCES = [
        self::SOURCE_TAOBAO,
        self::SOURCE_1688
    ];
    public const DOMAIN_TAOBAO = 'taobao.com';
    public const DOMAIN_TMALL = 'tmall.com';
    public const DOMAIN_1688 = '1688.com';
}
