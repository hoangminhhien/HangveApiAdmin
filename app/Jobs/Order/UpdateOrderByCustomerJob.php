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
use App\Services\ConfigService;
use App\Constants\ConfigConstant;

class UpdateOrderByCustomerJob implements ShouldQueue
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
        Order::query()
        ->with(['customer', 'customer.offer'])
        ->whereIn('status', [OrderConstant::KEY_STATUS_WAITING_QUOTE, OrderConstant::KEY_STATUS_WAITING_DEPOSIT])
        ->whereNull('thuongdo_id')
        ->where('is_fix_order_percent', 0)
        ->chunk(100, function($orders)
        {
            foreach($orders as $order){
                $customer = $order->customer;
                if($customer){
                    $offer = $customer->offer;
                    if($offer == null || !data_get($offer, 'offer_rate')) {
                        $orderPercent = (new ConfigService())->getResultFromValueByBetweenMinMax(
                            ConfigConstant::SERVICE_ORDER_FEE,
                            $order->order_cost
                        );
                        \Log::info($orderPercent);
                        $order->order_percent = $orderPercent;
                        $order->save();
                        (new OrderService())->updateOrderSupplierWhenChangeExchangeRate($order);
                    }
                }
            }
        });
    }
}
