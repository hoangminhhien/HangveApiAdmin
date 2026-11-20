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
use App\Services\NotificationService;

class PushNotiWarningConfirmOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    
    protected $orderId;
    protected $time;
    public function __construct($orderId, $time)
    {
        $this->orderId = $orderId;
        $this->time = $time;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $orderId = $this->orderId;
        $order = Order::find($orderId);
        if($order && $order->status == OrderConstant::KEY_STATUS_RECONFIRM) {
            $content = __('noti.order_change_status_confirm_out_time', ['order_code' => $order->code, 'time' => $this->time]);
            $data_order = [
                'order_code' => $order->code,
                'order_id' => $order->id,
                'content' => $content
            ];
            $data = [
                'group' => 2,
                'name' => 2016,
                'data' => json_encode($data_order),
                'time_create' => time(),
                'status' => 1
            ];
            if ($order->staff_quotation_id) {
                (new NotificationService())->storeNoti($order->staff_quotation_id, $data);
            }
            if ($order->staff_group_id) {
                (new NotificationService())->storeNoti($order->staff_group_id, $data);
            }
        }
    }
}
