<?php

namespace App\Factories;

use App\Services\PlatformServices\Platform1688Service;
use App\Services\PlatformServices\PlatformTaoBaoService;
use Exception;

class BasePlatformFactory
{
    public static function createBuyerService(string $platform)
    {
        switch ($platform) {
            case 'taobao' :
                return new PlatformTaoBaoService();
            case '1688' :
                return new Platform1688Service();
            default :
                throw new Exception('Không hỗ trợ phương thức!');
        }
    }
}
