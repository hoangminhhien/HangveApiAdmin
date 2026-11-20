<?php

namespace App\Providers;

use App\Services\Reports\ReportProductsPipelineService;
use Illuminate\Support\ServiceProvider;

class ReportServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->registerReportProductsPipeline();
    }

    private function registerReportProductsPipeline(): void
    {
        $this->app->instance(ReportProductsPipelineService::class, new ReportProductsPipelineService());
    }
}
