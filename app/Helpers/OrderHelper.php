<?php

namespace App\Helpers;

use App\Models\Config;
use App\Models\CustomerOffer;
use Illuminate\Support\Facades\Auth;

class OrderHelper
{
    public static function calculatorConsignmentRate($cost): array
    {
        $config = Config::query()->where('key', 'consignment_rate')->first();
        $customerOffer = CustomerOffer::query()->where('customer_id', Auth::user()->id ?? 0)->first();
        $customer_consignment_rate = $customerOffer->consignment_rate ?? 0;
        $config_consignment_rate = $config->value ?? 0;
        $rate = 0;
        if ($customer_consignment_rate != 0) {
            $rate = (int)$customer_consignment_rate;
        } elseif ($config_consignment_rate != 0) {
            $rate = (int)$config_consignment_rate;
        }
        $cost = $cost * $rate / 100;
        return [
            'percent_cost' => $cost,
            'percent_value' => $rate
        ];
    }
}
