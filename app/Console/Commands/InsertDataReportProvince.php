<?php

namespace App\Console\Commands;

use App\Jobs\Report\SyncReportOrderProvinceJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class InsertDataReportProvince extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insert:report-province';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        Log::info('Chạy báo cáo tỉnh thành phố');
        dispatch(new SyncReportOrderProvinceJob())->onQueue('sync-data-report');
    }
}
