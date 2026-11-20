<?php

namespace App\Jobs\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;
use App\Constants\OrderConstant;
use App\Services\OrderService;

class AutoChangeStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $orderId;
    protected $status;
    protected $timeCheck;
    public function __construct($orderId, $status, $timeCheck)
    {
        $this->orderId = $orderId;
        $this->status = $status;
        $this->timeCheck = $timeCheck;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $orderId = $this->orderId;
        $order = Order::with('orderStatusTime')->find($orderId);
        if($order && $order->status == OrderConstant::KEY_STATUS_RECONFIRM) {
            if($order->orderStatusTime) {
                $timeReconfirm = $order->orderStatusTime->status_5;
                if($timeReconfirm && $timeReconfirm->addMinutes($this->timeCheck) < now()) {
                    (new OrderService())->changeStatus($order, $this->status);
                }
            }
        }
    }
}
