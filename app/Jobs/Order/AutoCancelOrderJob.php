<?php

namespace App\Jobs\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Config;
use App\Constants\ConfigConstant;
use App\Constants\OrderConstant;
use App\Models\Order;
use Carbon\Carbon;
use App\Services\ActivityService;
use App\Services\OrderService;

class AutoCancelOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \Log::info('---------- Start run AutoCancelOrderJob --------------');
        $config = Config::where('key',  ConfigConstant::AUTO_CANCEL_ORDER)->first();
        if($config && $config->value) {
            $statuses = json_decode($config->value);
            foreach($statuses as $item) {
                if($item->value == OrderConstant::KEY_STATUS_WAITING_DEPOSIT) {
                    if(is_numeric($item->day) && $item->day > 0) {
                        Order::where('status', $item->value)->whereHas('orderStatusTime', function($q) use ($item) {
                            $q->where('status_1', '<', Carbon::now()->subDays($item->day));
                        })
                        ->chunk(1000, function($orders) use ($item)
                        {
                            foreach($orders as $key => $order) {
                                $this->updateCancelOrder($order, OrderConstant::KEY_STATUS_WAITING_DEPOSIT, $item->day);
                            }
                        });
                    }
                }
                if($item->value == OrderConstant::KEY_STATUS_RECONFIRM) {
                    if(is_numeric($item->day) && $item->day > 0) {
                        Order::where('status', $item->value)->whereHas('orderStatusTime', function($q) use ($item) {
                            $q->where('status_5', '<', Carbon::now()->subDays($item->day));
                        })->chunk(1000, function($orders) use ($item)
                        {
                            foreach($orders as $key => $order) {
                                $this->updateCancelOrder($order, OrderConstant::KEY_STATUS_RECONFIRM, $item->day);
                            }
                        });
                    }
                }
            }
        }
        \Log::info('---------- End run AutoCancelOrderJob --------------');
    }

    private function updateCancelOrder($order, $status, $day)
    {
        $order->update(['status' => OrderConstant::KEY_STATUS_CANCEL]);
        (new ActivityService)->setOrderLog(
            $order,
            trans(
                "activity.auto_cancel_order",
                ['status' => OrderConstant::STATUSES[$status], 'day' => $day]
            ),
            $order->id
        );
        (new OrderService)->setStatusTime($order->id, $order->status);
    }
}
