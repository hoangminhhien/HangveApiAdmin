<?php

namespace App\Jobs;

use App\Models\SmsLog;
use App\Services\SmsService;
use App\Services\SmsZaLoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SentSmsToCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public array $arrayData;
    public $order;
    public function __construct($arrayData, $order = null)
    {
        $this->arrayData = $arrayData;
        $this->order = $order;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->order) {
            $response = (new SmsZaLoService($this->arrayData))
                ->getAccessToken()
                ->validate()
                ->sent()
                ->getResult();
            if ($response['code'] == 500) {
                // Log::info('SMS ZaLo Error Bao Gia Don Hang ' . $response['message']);
                $array = [
                    'user_code' => $this->order->customer->code ?? '',
                    'log_type' => SmsLog::LOG_TYPE_SMS_CHANGE_STATUS_ORDER,
                    'phone_number' => $this->order->customer->phone_number ?? '',
                    'message' => 'Don hang ma ' . $this->order->code . ' da duoc bao gia. Quy khach truy cap https://hangve.com thanh toan de chung toi co the dat hang cho ban som nhat. Xin cam on!'
                ];
                SmsService::send($array);
            } else {
                // Log::info('SMS ZaLo Success Bao Gia Don Hang!');
                SmsLog::query()->create([
                    'phone_number' => $this->order->customer->phone_number ?? 0,
                    'content' => $this->arrayData['message'],
                    'user_code' => $this->order->customer->code ?? '',
                    'sms_source' => SmsLog::SMS_SOURCE_ZALO,
                    'log_type' => SmsLog::LOG_TYPE_SMS_CHANGE_STATUS_PACKAGE,
                    'response_status' => 0
                ]);
            }
        } else {
            SmsService::send($this->arrayData);
        }
    }
}
