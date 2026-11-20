<?php

namespace App\Console\Commands;

use App\Jobs\Report\SyncReportOrderProvinceJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncReportOrderProvince extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:report-order-province';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync data: Báo cáo tỉnh thành phố';

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
        $colorError = "\033[1m\033[31m";
        $colorSuccess = "\033[32m";
        echo 'Start: sync:report-order-province'.PHP_EOL;
        try {
            dispatch(new SyncReportOrderProvinceJob());
            echo $colorSuccess.": Dispatch job done \033[0m".PHP_EOL;
            echo "End: sync:report-order-province by date".PHP_EOL;
            Log::info('sync:report-order-province Success');
        } catch (\Exception $e) {
            echo $colorError."Error: sync:report-order-province".PHP_EOL;
            Log::error($e->getMessage());
        }
    }
}
