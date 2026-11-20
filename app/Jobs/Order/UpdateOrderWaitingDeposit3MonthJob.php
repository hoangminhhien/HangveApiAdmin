<?php

namespace App\Jobs\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateOrderWaitingDeposit3MonthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $orderDB;

    public function __construct($orderDB)
    {
        $this->orderDB = $orderDB;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $orderDB = $this->orderDB;

        $customerService = \App::make('App\Services\CustomerService');
        $customerService->updateOrderWaitingDeposit3Month($orderDB);
    }
}
