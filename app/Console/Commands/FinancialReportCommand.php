<?php

namespace App\Console\Commands;

use App\Jobs\FinancialReport\FinancialReportDateJob;
use App\Services\FinancialReportService;
use Illuminate\Console\Command;

class FinancialReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insert:financial-report {date?}';

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

    public function handle(FinancialReportService $financialReportService)
    {
        $request = [];
        if ($date = $this->argument('date')) {
            $request['date'] = $date;
        }
        $financialReportService->syncReport($request);
    }
}
