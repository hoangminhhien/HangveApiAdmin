<?php

namespace App\Console\Commands;

use App\Jobs\Report\InsertDataOrderCustomerJob;
use Illuminate\Console\Command;

class InsertDataOrderCustomer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insert:report-order-customer {type?}';

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
        $type = $this->argument('type');

        $job = new InsertDataOrderCustomerJob($type);
        dispatch($job)->onQueue('sync-data-report');
    }
}
