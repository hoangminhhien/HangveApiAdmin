<?php

namespace App\Jobs\Report;

use App\Services\ReportSaleStaffService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncReportOrderConsignmentYearJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $data;
    protected array $users;

    public function __construct($data, $users)
    {
        $this->data = $data;
        $this->users = $users;
    }

    public function handle(ReportSaleStaffService $reportSaleStaffService)
    {
        try {
            $reportSaleStaffService->processDataReportYear($this->data, $this->users);
            Log::info('SyncReportOrderConsignmentYearJob Success');
        } catch (\Exception $exception) {
            Log::info('SyncReportOrderConsignmentYearJob Error ' . $exception->getMessage());
        }
    }
}
