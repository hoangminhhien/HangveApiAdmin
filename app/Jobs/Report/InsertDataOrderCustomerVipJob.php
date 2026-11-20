<?php

namespace App\Jobs\Report;

use App\Services\ReportOrderCustomerVip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InsertDataOrderCustomerVipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ReportOrderCustomerVip $service)
    {
        try {
            $service->cronjobInsertData($this->data);
            Log::info('insert:report-order-customer-vip Success');
        } catch (\Exception $exception) {
            Log::info('insert:report-order-customer-vip Error ' . $exception->getMessage());
        }
    }
}
