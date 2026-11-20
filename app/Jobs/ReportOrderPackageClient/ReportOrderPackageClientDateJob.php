<?php

namespace App\Jobs\ReportOrderPackageClient;

use App\Services\ReportOrderPackageClientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReportOrderPackageClientDateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle(ReportOrderPackageClientService $reportOrderPackageClientService)
    {
        try {
            $reportOrderPackageClientService->calculatorReportDay($this->data);
            Log::info('ReportOrderPackageClientDateJob Success');
        } catch (\Exception $exception) {
            Log::info('ReportOrderPackageClientDateJob Error ' . $exception->getMessage());
        }
    }
}
