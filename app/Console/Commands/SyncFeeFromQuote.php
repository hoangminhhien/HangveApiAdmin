<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncFeeFromQuote extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:fee-from-quote';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Đồng bộ phí dịch vụ từ báo giá qua với các kiện đi chính ngạch đã nhận hàng mà official_service_fee = 0';

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
        $orderPackageService = \App::make('App\Services\OrderPackageService');
        $orderPackageService->syncFeeFromQuote();
    }
}
