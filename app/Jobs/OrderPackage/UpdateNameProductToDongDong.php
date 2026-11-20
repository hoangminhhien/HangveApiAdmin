<?php

namespace App\Jobs\OrderPackage;

use App\Services\PlatformServices\Platform1688Service;
use App\Services\PlatformServices\PlatformTaoBaoService;
use App\Services\WebhookDongDongService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use App\Models\OrderPackage;
use App\Constants\PackageConstant;
use Illuminate\Support\Facades\Log;

class UpdateNameProductToDongDong implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $orderDetails;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($orderDetails)
    {
        $this->orderDetails = $orderDetails;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \Log::info("-------Start queue sync name product--------");
        $taobaoService = app()->make(PlatformTaoBaoService::class);
        $s1688Service = app()->make(Platform1688Service::class);
        if(!empty($this->orderDetails)) {
            $dataProducts = [];
            foreach ($this->orderDetails as $orderDetail) {
                $link = $orderDetail['link'];
                $itemId = getPlatformIdFromUrl($link) ?? get1688IdFromRouteParam($link);
                $source = detectPlatform($link);

                if($source == '1688') {
                    $payload = [
                        'itemId' => $itemId,
                        'is_not_sync_product_detail' => true
                    ];
                    $response = $s1688Service->getProductPlatformDetail($payload);
                    if ($response && $response['code'] == 200) {
                        $results = $response['data'];
                        $dataProducts[] = [
                            'id' => $orderDetail['order_detail_id'],
                            'name_zh' => $results->subjectOrigin ?? '',
                        ];
                    }
                }
                if(in_array($source, ['taobao', 'tmall'])) {
                    $payload = [
                        'itemId' => $itemId,
                        'is_not_sync_product_detail' => true
                    ];
                    $results = $taobaoService->getProductPlatformDetail($payload);
                    if ($results && $results['code'] == 200) {
                        $data = $results['data'];
                        $title = $data->title ?? '';
                        $dataProducts[] = [
                            'id' => $orderDetail['order_detail_id'],
                            'name_zh' => $title ?? '',
                        ];
                    }
                }
            }
            $dongdongService = app()->make(WebhookDongDongService::class);
            $dongdongService->updateNameOrderDetails($dataProducts);
        }

    }
}
