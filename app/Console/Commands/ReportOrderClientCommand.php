<?php

namespace App\Console\Commands;

use App\Services\ReportOrderClientService;
use Illuminate\Console\Command;

class ReportOrderClientCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insert:report-order-client {date?}';

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

    public function handle(ReportOrderClientService $reportOrderClientService)
    {
        $request = [];
        if ($date = $this->argument('date')) {
            $request['date'] = $date;
        }
        $reportOrderClientService->syncReport($request);
    }
}
