<?php

namespace App\Jobs\Report;

use App\Services\ReportCustomerOrderSale;
use App\Services\Reports\ReportCustomerConsultService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InsertDataOrderCustomerSaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var mixed
     */
    public $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle(ReportCustomerConsultService $service)
    {
        try {
            $service->cronjobInsertData($this->data);
            $service->cronjobInsertDataMonth($this->data);
            Log::info('insert:report-customer-order-sale Success');
        } catch (\Exception $e) {
            Log::info($e->getMessage());
        }
    }
}
