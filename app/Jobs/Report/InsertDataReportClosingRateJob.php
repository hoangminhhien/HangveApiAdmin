<?php

namespace App\Jobs\Report;

use App\Services\ReportsClosingRate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InsertDataReportClosingRateJob implements ShouldQueue
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
    public function handle(ReportsClosingRate $service)
    {
        try {
            $service->delete($this->data);
            $service->cronjobInsertData($this->data);
            Log::info('insert:report-closing-rate Success');
        } catch (\Exception $e) {
            Log::info($e->getMessage());
        }
    }
}
