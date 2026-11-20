<?php

namespace App\Console\Commands;

use App\Jobs\Report\InsertDataOrderReportConsignmentJob;
use App\Services\ReportOrdersConsignment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class InsertDataOrderReportConsignment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insert:report-order-consignment {date?}';

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
        $job = new InsertDataOrderReportConsignmentJob($request);
        dispatch($job)->onQueue('sync-data-report');
    }
}
