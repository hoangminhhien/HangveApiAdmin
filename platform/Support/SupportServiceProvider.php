<?php

namespace ThuongDo\Support;

use Illuminate\Support\ServiceProvider;

class SupportServiceProvider extends ServiceProvider
{
    public function register()
    {
    }

    public function boot()
    {
        $this->registerHelpers();
    }

    private function registerHelpers(): void
    {
        foreach(\glob(\base_path('/platform/Support/Helpers/*.php')) as $file) {
            require_once($file);
        }
    }
}
