<?php

namespace App\Jobs;

use App\Constants\PackageConstant;
use App\Models\Customer;
use App\Models\OrderPackage;
use App\Models\SmsLog;
use App\Services\CustomerService;
use App\Services\SmsService;
use App\Services\SmsZaLoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SentSmsOrderPackageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(CustomerService $customerService)
    {
        $customerService->smsZaLoKienHangVeVietNam();
    }
}
