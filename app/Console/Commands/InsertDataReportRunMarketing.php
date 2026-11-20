<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InsertDataReportRunMarketing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insert:report-run-marketing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Báo cáo đối soát marketing 2025';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        \Log::info('InsertDataReportRunMarketing 03-02-2025');
        $reportRunMarketingService = \App::make('App\Services\Reports\ReportRunMarketing');
        $reportRunMarketingService->cronData();
        $reportRunMarketingService->cronDataByTimeSignLabel(); // Lọc theo thời gian gán nhãn
    }
}
