<?php

namespace App\Jobs\Deposit;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ChargeApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $amount;
    protected $userByCode;
    protected $bank;
    protected $content;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($amount, $userByCode, $bank, $content)
    {
        $this->amount     = $amount;
        $this->userByCode = $userByCode;
        $this->bank       = $bank;
        $this->content    = $content;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \Log::info("Deposit.ChargeApp");

        // Khởi tạo lại giá trị như hàm cũ
        $amount     = $this->amount;
        $userByCode = $this->userByCode;
        $bank       = $this->bank;
        $content    = $this->content;

        // Gọi service
        $customerService = \App::make('App\Services\CustomerService');
        $customerService->chargeFromApp($amount, $userByCode, $bank, $content);
    }
}
