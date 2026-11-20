<?php

namespace App\Jobs\Report;

use App\Services\ReportProvinceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncReportOrderProvinceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // override the queue tries
    // configuration for this job
    public int $tries = 4;

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
    public function handle(ReportProvinceService $reportProvinceService)
    {
        $colorError = "\033[1m\033[31m";
        $colorSuccess = "\033[32m";
        try {
            $startDate = date("Y").'-01-01';
            $endDate = date("Y-m-d");

            $checkDone = $reportProvinceService->processDataReport($startDate, $endDate);
            if ($checkDone) {
                echo $colorSuccess.$startDate.'_'.$endDate.": Done report_order_province \033[0m".PHP_EOL;
            } else {
                echo $colorError.$startDate.'_'.$endDate.": Error report_order_province \033[0m".PHP_EOL;
                Log::error(
                    'SYNC_DATA_ORDER_PROVINCE# Lỗi kiểm tra dữ liệu khi tổng hợp báo cáo tỉnh thành phố',
                    [
                        'line' => __LINE__,
                        'method' => __METHOD__,
                        'error_message' => "Error SyncReportOrderProvinceJob: ".$startDate.'_'.$endDate,
                    ]
                );
            }
        } catch (\Exception $exception) {
            if ($this->attempts() > 3) {
                // hard fail after 3 attempts
                Log::error(
                    'SYNC_DATA_ORDER_PROVINCE# Lỗi khi tổng hợp báo cáo tỉnh thành phố',
                    [
                        'line' => __LINE__,
                        'method' => __METHOD__,
                        'error_message' => $exception->getMessage(),
                    ]
                );
            }

            // requeue this job to be executes
            // in 3 minutes (180 seconds) from now
            $this->release(180);
            return;
        }
    }
}
