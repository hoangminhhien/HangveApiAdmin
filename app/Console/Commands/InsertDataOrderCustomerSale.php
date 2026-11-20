<?php

namespace App\Console\Commands;

use App\Jobs\Report\InsertDataOrderCustomerSaleJob;
use App\Services\ReportCustomerOrderSale;
use Illuminate\Console\Command;

class InsertDataOrderCustomerSale extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insert:report-customer-order-sale {date?}';

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
        $job = new InsertDataOrderCustomerSaleJob($request);
        dispatch($job)->onQueue('sync-data-report');
    }
}
