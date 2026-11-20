<?php

namespace App\Jobs\ReportOrderClient;

use App\Services\ReportOrderClientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReportOrderClientYearJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle(ReportOrderClientService $reportOrderClientService)
    {
        try {
            $reportOrderClientService->calculatorReportYear($this->data);
            Log::info('ReportOrderClientYearJob Success');
        } catch (\Exception $exception) {
            Log::info('ReportOrderClientYearJob Error ' . $exception->getMessage());
        }
    }
}
