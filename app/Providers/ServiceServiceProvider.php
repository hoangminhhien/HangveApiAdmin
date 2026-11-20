<?php

namespace App\Providers;

use App\Services\BuyderService;
use App\Services\OrderDetailService;
use App\Services\OrderPackageService;
use App\Services\PlaceOrderService;
use Illuminate\Support\ServiceProvider;

class ServiceServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(PlaceOrderService::class, PlaceOrderService::class);
        $this->app->bind(OrderDetailService::class, OrderDetailService::class);
        $this->app->bind(BuyderService::class, BuyderService::class);
        $this->app->bind(OrderPackageService::class, OrderPackageService::class);
    }
}
