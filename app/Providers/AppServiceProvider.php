<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // không cho phép lazy loading ở local, mục đích giúp code chặt chẽ hơn trước khi deploy
        // tạm thời không prevent ở prod/staging do có thể gây lỗi trong các case đặc biệt, vd code đang có sẵn, hoặc
        // hot fix...
        // Model::preventLazyLoading($this->app->environment('local'));
    }
}
