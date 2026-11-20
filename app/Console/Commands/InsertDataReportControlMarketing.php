<?php

namespace App\Console\Commands;

use App\Services\ReportsControlMarketing;
use Illuminate\Console\Command;

class InsertDataReportControlMarketing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insert:report-control-marketing {date?}';

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


    public function handle(ReportsControlMarketing $service)
    {
        try {
            echo "Start Insert". PHP_EOL;
            $request = [];
            if ($date = $this->argument('date')) {
                $request['date'] = $date;
            }
            $service->delete($request);
            $service->deleteMonth($request);
            $reportWeek = $service->cronjobInsertData($request);
            $reportMonth = $service->cronjobInsertDataMonth($request);
            $service->insert($reportWeek);
            $service->insert($reportMonth);
            echo "Finish Insert";
            \Log::info('insert:report-control-marketing Success');
        } catch (\Exception $e) {
            \Log::info($e->getMessage());
        }
    }
}
