<?php

namespace App\Console\Commands;

use App\Jobs\Report\InsertDataOrderCustomerVipJob;
use Illuminate\Console\Command;

class InsertDataOrderCustomerVipAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insert:report-order-customer-vip-all {date?}';

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
        $request = [];
        if ($date = $this->argument('date')) {
            $request['date'] = $date;
        }
        $request['not_group_id'] = 999999999;
        $job = new InsertDataOrderCustomerVipJob($request);
        dispatch($job)->onQueue('sync-data-report');
    }
}
