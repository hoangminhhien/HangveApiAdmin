<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;
use App\Constants\OrderConstant;
use App\Jobs\SubUpdateTotalPrice;

class UpdateTotalPriceWhenChangeExchangeRate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $exchangeRate;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($exchangeRate)
    {
        $this->exchangeRate = $exchangeRate;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Order::query()
        // ->with(['details', 'orderSuppliers'])
        ->whereIn('status', [OrderConstant::KEY_STATUS_WAITING_QUOTE, OrderConstant::KEY_STATUS_WAITING_DEPOSIT])
        ->whereNull('thuongdo_id')
        ->chunk(100, function($orders)
        {
            dispatch(new SubUpdateTotalPrice($orders, $this->exchangeRate));
        });
    }
}
