<?php

namespace App\Console\Commands\Buyder;

use App\Jobs\Buyder\SyncTaobaoGeneralData;
use Illuminate\Console\Command;

class SyncTaobaoPlaceOrderGeneralData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:place-order-taobao-general-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Đồng bộ dữ liệu đặt hàng Taobao từ Buyder cho từng platform_id';

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
     * Cập nhật dữ liệu đặt hàng nội bộ
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Job cập nhật dữ liệu đặt hàng Taobao từ Buyder');
        dispatch(new SyncTaobaoGeneralData());
    }
}
