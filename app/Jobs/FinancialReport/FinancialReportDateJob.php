<?php

namespace App\Jobs\FinancialReport;

use App\Services\FinancialReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FinancialReportDateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle(FinancialReportService $financialReportService)
    {
        try {
            $financialReportService->calculatorReportDay($this->data);
            Log::info('FinancialReportDateJob Success');
        } catch (\Exception $exception) {
            Log::info('FinancialReportDateJob Error ' . $exception->getMessage());
        }
    }
}
