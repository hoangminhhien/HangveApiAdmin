<?php

namespace App\Jobs\Report;

use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCustomerLevelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // override the queue tries
    // configuration for this job
    public $tries = 4;

    protected $customer;

    protected $customerId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($customerId, $customer)
    {
        $this->customerId = $customerId;
        $this->customer = $customer;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(OrderService $orderService)
    {
        $colorError = "\033[1m\033[31m";
        $colorSuccess = "\033[32m";
        try {
            $customerId = $this->customerId;
            $customer = $this->customer;

            $checkDone = $orderService->processDataUpdateLevel($customerId, $customer);
            if ($checkDone) {
                echo $colorSuccess.$customerId.": Done update level \033[0m".PHP_EOL;
            } else {
                echo $colorError.$customerId.": Error update level \033[0m".PHP_EOL;
                Log::error(
                    'SYNC_CUSTOMER_LEVEL# Lỗi cập nhật level từ order',
                    [
                        'line' => __LINE__,
                        'method' => __METHOD__,
                        'error_message' => "Error SyncCustomerLevelJob: ".$customerId,
                    ]
                );
            }
        } catch (\Exception $exception) {
            if ($this->attempts() > 3) {
                // hard fail after 3 attempts
                Log::error(
                    'SYNC_CUSTOMER_LEVEL# Lỗi cập nhật level từ order',
                    [
                        'line' => __LINE__,
                        'method' => __METHOD__,
                        'error_message' => $exception->getMessage(),
                    ]
                );
            }

            // requeue this job to be executes
            // in 3 minutes (180 seconds) from now
            $this->release(180);
            return;
        }
    }
}
