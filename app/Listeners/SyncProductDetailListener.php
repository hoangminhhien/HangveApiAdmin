<?php

namespace App\Listeners;

use App\Events\SyncProductDetailEvent;
use App\Factories\BasePlatformFactory;
use App\Services\PlatformServices\BasePlatformService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SyncProductDetailListener implements ShouldQueue
{
    use InteractsWithQueue;
    public string $queue = 'sent-noti-live-chat';
    public function __construct()
    {

    }

    /**
     * @throws Exception
     */
    public function handle(SyncProductDetailEvent $event)
    {
        $source = $event->source;
        // Log::info('Start sync product ' . $source);
        $service = new BasePlatformService(BasePlatformFactory::createBuyerService($source));
        $service->createOrUpdateProductDetail($event->dataBuyer);
        // Log::info('End sync product ' . $source);
    }
}
