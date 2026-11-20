<?php

namespace App\Jobs\Report;

use App\Services\ReportsClosingRateStaff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InsertDataReportClosingRateStaffJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle(ReportsClosingRateStaff $service)
    {
        try {
            $service->delete($this->data);
            $service->cronjobInsertData($this->data);
            Log::info('insert:report-closing-rate-staff Success');
        } catch (\Exception $e) {
            Log::info('insert:report-closing-rate-staff Error ' .$e->getMessage());
        }
    }
}
