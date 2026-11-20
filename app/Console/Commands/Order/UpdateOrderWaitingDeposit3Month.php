<?php

namespace App\Console\Commands\Order;

use Illuminate\Console\Command;

class UpdateOrderWaitingDeposit3Month extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:order-waiting-deposit-3-month';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cập nhật các đơn chờ đặt cọc quá 3 tháng sang hủy';

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
        \Log::info('Command UpdateOrderWaitingDeposit3Month');
        $customerService = \App::make('App\Services\CustomerService');
        $customerService->getOrderWaitingDeposit3Month();
    }
}
