<?php

namespace App\Jobs\Report;

use App\Services\ReportSaleStaffService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncReportOrderConsignmentDateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $data;
    protected array $users;

    public function __construct($data, $users)
    {
        $this->data = $data;
        $this->users = $users;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ReportSaleStaffService $reportSaleStaffService)
    {
        try {
            $reportSaleStaffService->processDataReport($this->data, $this->users);
            Log::info('SyncReportOrderConsignmentDateJob Success');
        } catch (\Exception $exception) {
            Log::info('SyncReportOrderConsignmentDateJob Error ' . $exception->getMessage());
        }
    }
}
