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

class ReportOrderPackageClientMonthJob implements ShouldQueue
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
            $reportOrderPackageClientService->calculatorReportMonth($this->data);
            Log::info('ReportOrderPackageClientMonthJob Success');
        } catch (\Exception $exception) {
            Log::info('ReportOrderPackageClientMonthJob Error ' . $exception->getMessage());
        }
    }
}
