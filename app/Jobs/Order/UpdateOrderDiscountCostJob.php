<?php

namespace App\Jobs\Order;

use App\Models\Order;
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
use Illuminate\Support\Facades\Log;

class UpdateOrderDiscountCostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $level;
    protected $discountPercent;
    public function __construct($level, $discountPercent)
    {
        $this->level = $level;
        $this->discountPercent = $discountPercent;
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
            ->chunk(100, function($orders)
            {
                foreach($orders as $order){
                    $customer = $order->customer;
                    if($customer->level == $this->level){
                        (new OrderService())->updateOrderSupplierWhenChangeDiscountCost($order, $this->discountPercent);
                    }
                }
            });
    }
}
