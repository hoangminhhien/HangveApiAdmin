<?php

namespace App\Jobs\Report;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\ReportSaleStaffService;

class InsertDataOrderPerformance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ReportSaleStaffService $service)
    {
        try {
            $service->cronjobInsertDataOrderPerformance();
            \Log::info('insert:report-order-performance Success');
        } catch (\Exception $e) {
            \Log::info($e);
        }
    }
}
