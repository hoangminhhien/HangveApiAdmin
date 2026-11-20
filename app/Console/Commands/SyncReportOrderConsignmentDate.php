<?php

namespace App\Console\Commands;

use App\Services\ReportSaleStaffService;
use Illuminate\Console\Command;

class SyncReportOrderConsignmentDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:report-order-consignment-date {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tổng hợp doanh số nhân viên kinh doanh từ orders và consignments';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle(ReportSaleStaffService $reportSaleStaffService)
    {
        $reportSaleStaffService->syncReportSaleStaffByTime([]);
    }
}
