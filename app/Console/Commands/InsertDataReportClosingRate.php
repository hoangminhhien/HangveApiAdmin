<?php

namespace App\Console\Commands;

use App\Jobs\Report\InsertDataReportClosingRateJob;
use App\Services\ReportsClosingRate;
use Illuminate\Console\Command;

class InsertDataReportClosingRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insert:report-closing-rate {date?}';

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
        $job = new InsertDataReportClosingRateJob($request);
        dispatch($job)->onQueue('sync-data-report');
    }
}
