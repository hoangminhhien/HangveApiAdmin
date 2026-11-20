<?php

namespace App\Jobs\Report;

use App\Services\ReportRevenueExpenditureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InsertDataReportRevenueExpenditureMonthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ReportRevenueExpenditureService $service)
    {
        try {
            $service->cronjobInsertDataMonth($this->data);
            // Log::info('InsertDataReportRevenueExpenditureMonthJob Success');
        } catch (\Exception $exception) {
            Log::info('InsertDataReportRevenueExpenditureMonthJob Error ' . $exception->getMessage());
        }
    }
}
