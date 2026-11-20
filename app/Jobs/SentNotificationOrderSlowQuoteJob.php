<?php

namespace App\Jobs;

use App\Models\Holiday;
use App\Services\EcommerceService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SentNotificationOrderSlowQuoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public array $params;
    public function __construct($params)
    {
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws BindingResolutionException
     */
    public function handle(EcommerceService $ecommerceService)
    {
        \Log::info('Run SentNotificationOrderSlowQuoteJob');
        $now = Carbon::now();
        $time = $now->format('H:i');
        $today = $now->format('Y-m-d');
        $dayOfWeek = $now->dayOfWeek; // 0 = Chủ Nhật
        $morningShift = ($time >= '08:30' && $time < '12:00');
        $afternoonShift = ($time >= '13:50' && $time < '17:30');
        //  Loại Chủ Nhật
        if ($dayOfWeek === 0) {
            return false;
        }
        // Loại ngày nghỉ lễ
        if (Holiday::query()->whereDate('date', $today)->exists()) {
            return false;
        }
        if($morningShift || $afternoonShift) {
            // Log::info('start SentNotificationOrderSlowQuoteJob');
            $ecommerceService->sentNotificationOrderSlowQuote($this->params);
            // Log::info('end SentNotificationOrderSlowQuoteJob');
        }
    }
}
