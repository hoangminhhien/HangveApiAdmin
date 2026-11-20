<?php

namespace App\Console\Commands\OneWarehouse;

use Illuminate\Console\Command;

class SyncAndPushToOne extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-and-push-to-one:start-1';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Đồng bộ data TD và đẩy sang kho';

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
        return;
        $auto_push = true;
        if ($auto_push) {

            // Khách hàng
            for ($i=2100; $i < 2500; $i++) {
                $job = new \App\Jobs\SyncTD\SyncCustomer($i);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }

            // Địa chỉ khách hàng
            for ($i=1250; $i < 1500; $i++) {
                $job = new \App\Jobs\SyncTD\SyncCustomerDelivery($i);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }

            // Nhà cung cấp
            for ($i=3800; $i < 4200; $i++) {
                $job = new \App\Jobs\SyncTD\SyncSupplier($i);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }

            // Đơn order
            for ($i=0; $i < 500; $i++) {
                $job = new \App\Jobs\SyncTD\SyncOrder($i);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }

            // Kiện kí gửi
            $maxPage = 200;
            $typePack = 2;
            for ($i=0; $i < $maxPage; $i++) {
                $job = new \App\Jobs\SyncTD\SyncPackage($i, $typePack);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }

            // Kiện không xác định
            $maxPage = 20;
            $typePack = 3;
            for ($i=0; $i < $maxPage; $i++) {
                $job = new \App\Jobs\SyncTD\SyncPackage($i, $typePack);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }

            // Đồng bộ sang kho
            $job = new \App\Jobs\OneWarehouse\SyncAllPackToOne();
            dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            return;
        }
    }
}
