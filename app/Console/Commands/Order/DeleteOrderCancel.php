<?php

namespace App\Console\Commands\Order;

use App\Models\Order;
use App\Models\OrderStatusTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeleteOrderCancel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:delete-order-cancel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Xóa order đã hủy trên 6 tháng';

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
     * Hàm này thực hiện việc xóa order ở trạng thái hủy đã quá 6 tháng
     * @return int
     */
    public function handle()
    {
        Log::info('vào job chạy xóa order');
        $start = microtime(true);

        OrderStatusTime::query()
            ->select([
                'order_status_times.id',
                'order_status_times.order_id',
                'order_status_times.status_6'
            ])
            ->join('orders', 'orders.id', '=', 'order_status_times.order_id')
            ->where('orders.status', Order::ORDER_STATUS_CANCEL)
            ->where(function ($q) {
                $q->where('order_status_times.status_6', '<=', now()->subMonths(6))
                    ->orWhereNull('order_status_times.status_6');
            })
            ->orderBy('order_status_times.id')
            ->chunk(1000, function ($rows) {

                $orderIds = $rows->pluck('order_id');
                $statusTimeIds = $rows->pluck('id');

                Order::query()->whereIn('id', $orderIds)->delete();
                OrderStatusTime::query()->whereIn('id', $statusTimeIds)->delete();
            });


        $end = microtime(true);

        $this->info('Total time: '.round($end - $start, 2).' seconds');

    }
}
