<?php

namespace App\Providers;

use App\Mixins\ArrayMixin;
use App\Mixins\EloquentBuilderMixin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class MixinServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->instance('arr-mixin', new ArrayMixin());
        $this->app->instance('eloquent-builder-mixin', new EloquentBuilderMixin());
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Arr::mixin($this->app->make('arr-mixin'));
        Builder::mixin($this->app->make('eloquent-builder-mixin'));
    }
}
