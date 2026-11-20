<?php

namespace App\Console\Commands\Order;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Jobs\Order\UpdateAdminCacheOrderList as JobUpdate;

class UpdateAdminCacheOrderList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:admin-cache-order-list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cập nhật giá trị lưu cache danh sách đơn hàng của Admin, không bao gồm filter';

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
     * Dispatch job cập nhật giá trị lưu cache danh sách đơn hàng của Admin
     *
     * @return int
     */
    public function handle()
    {
        Log::info('Command UpdateAdminCacheOrderList');
        dispatch(new JobUpdate());
    }
}
