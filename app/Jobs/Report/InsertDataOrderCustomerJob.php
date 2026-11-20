<?php

namespace App\Jobs\Report;

use App\Services\ReportCustomerOrders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InsertDataOrderCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data;
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ReportCustomerOrders $service)
    {
        try {
            if($this->data == "sync-many") {
                $service->cronjobInsertDataMany();
            }else {
                $service->cronjobInsertData();
            }
            Log::info('insert:report-order-customer Success');
        } catch (\Exception $e) {
            Log::info($e->getMessage());
        }
    }
}
