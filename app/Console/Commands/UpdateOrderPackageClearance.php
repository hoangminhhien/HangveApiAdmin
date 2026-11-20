<?php

namespace App\Console\Commands;

use App\Jobs\UpdateBillCodeOrderPackageJob;
use App\Jobs\UpdateOrderPackageClearanceJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateOrderPackageClearance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:update-order-package-clearance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        Log::info('Start UpdateOrderPackageClearance');
        $jobs = new UpdateOrderPackageClearanceJob();
        dispatch($jobs)->onQueue('sync-data-report');
        Log::info('End UpdateOrderPackageClearance');
    }
}
