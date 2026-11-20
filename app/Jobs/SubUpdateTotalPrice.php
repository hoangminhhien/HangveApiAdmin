<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\OrderService;
class SubUpdateTotalPrice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $exchangeRate;
    protected $orders;

    public function __construct($orders, $exchangeRate)
    {
        $this->orders = $orders;
        $this->exchangeRate = $exchangeRate;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            foreach ($this->orders as $order)
            {
                $order->exchange_rate = $this->exchangeRate;
                $order->save();
                (new OrderService())->updateOrderSupplierWhenChangeExchangeRate($order);
            }
        } catch (\Throwable $th) {
            \Log::error($th);
        }
    }
}
