<?php

namespace App\Jobs\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Customer;
use App\Constants\OrderConstant;
use App\Services\OrderService;
use App\Services\ConfigService;
use App\Constants\ConfigConstant;

class UpdateOrderPercentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $customerId;
    protected $offerRate;
    public function __construct($customerId, $offerRate)
    {
        $this->customerId = $customerId;
        $this->offerRate = $offerRate;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $customer = Customer::with(['orders' => function($q){
            return $q->whereIn('status', [OrderConstant::KEY_STATUS_WAITING_QUOTE, OrderConstant::KEY_STATUS_WAITING_DEPOSIT])
                    ->whereNull('thuongdo_id');
        }])->find($this->customerId);

        if($customer){
            foreach($customer->orders as $order){
                if($order->is_fix_order_percent == 1) {
                    continue;
                }
                if($this->offerRate && $this->offerRate > 0) {
                    $orderPercent = $this->offerRate;
                }else {
                    $orderPercent = (new ConfigService())->getResultFromValueByBetweenMinMax(
                        ConfigConstant::SERVICE_ORDER_FEE,
                        $order->order_cost
                    );
                }
                \Log::info($orderPercent);
                $order->order_percent = $orderPercent;
                $order->save();
                (new OrderService())->updateOrderSupplierWhenChangeExchangeRate($order);
            }
        }
    }
}
