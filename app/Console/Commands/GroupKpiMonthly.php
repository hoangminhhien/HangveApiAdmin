<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class GroupKpiMonthly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'group:kpi-monthly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tạo kpi nhóm hàng tháng';

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
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', 0);

        $groupService = \App::make('App\Services\GroupService');
        $groupService->generateGroupKpiMonthly();
    }
}
