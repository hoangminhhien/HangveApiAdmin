<?php

namespace App\Console\Commands;

use App\Services\ReportRevenueExpenditureService;
use Illuminate\Console\Command;

class InsertDataReportRevenueExpenditure extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insert:report-revenue-expenditure {date?}';

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


    public function handle(ReportRevenueExpenditureService $service)
    {
        $request = [];
        if ($date = $this->argument('date')) {
            $request['date'] = $date;
        }
        $service->syncReportRevenueExpenditureByTime($request);
    }
}
