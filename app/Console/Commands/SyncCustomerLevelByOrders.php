<?php

namespace App\Console\Commands;

use App\Jobs\Report\SyncCustomerLevelJob;
use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCustomerLevelByOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:customer-level-by-orders {customer_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cập nhật customer level từ dữ liệu orders';

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
        $colorError = "\033[1m\033[31m";
        $colorSuccess = "\033[32m";
        echo 'Start: Sync customer level'.PHP_EOL;
        $customerId = $this->argument('customer_id');
        try {
            Customer::query()
                ->with(["customerOffers"])
                ->where(function ($query) use ($customerId) {
                    if ($customerId) {
                        $query->where("id", $customerId);
                    }
                })
                ->chunk(500, function ($inspectors) use ($colorSuccess) {
                    foreach ($inspectors as $inspector) {
                        dispatch(new SyncCustomerLevelJob($inspector->id, $inspector))->delay(now()->addMinutes(5));
                        echo $colorSuccess.$inspector->id.": Dispatch job done: sync:customer-level-by-orders \033[0m".PHP_EOL;
                    }
                });
            echo "Finish: Sync customer level".PHP_EOL;
            Log::info('sync:customer-level-by-orders Success');
        } catch (\Exception $e) {
            echo $colorError."Error: Sync customer level".PHP_EOL;
            Log::error($e->getMessage());
        }
    }
}
