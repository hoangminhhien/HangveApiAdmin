<?php

namespace App\Jobs\Buyder;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\OrderDetail;
use App\Constants\OrderConstant;
use App\Services\WebhookDongDongService;
use App\Services\PlatformServices\BasePlatformService;
use App\Factories\BasePlatformFactory;

class SyncMpSkuIdByNoteChinaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $orderDetailId;
    protected $userId;
    public function __construct($orderDetailId, $userId)
    {
        $this->orderDetailId = $orderDetailId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $orderDetailId = $this->orderDetailId;
        $userId = $this->userId;
        $orderDetail = OrderDetail::find($orderDetailId);
        if($orderDetail) {
            $link = $orderDetail->link ?? null;
            if (!$link) {
                return;
            }
            $itemId = getPlatformIdFromUrl($link) ?? get1688IdFromRouteParam($link);
            $platform = getSourceFromProductLink($link);
            $service = new BasePlatformService(BasePlatformFactory::createBuyerService($platform));
            $payload = [];
            if ($platform == '1688') {
                $payload['offerId'] = $itemId;
                $payload['is_not_sync_product_detail'] = true;
            }
            if ($platform == 'taobao') {
                $payload['itemId'] = $itemId;
                $payload['is_not_sync_product_detail'] = true;
            }
            $response = $service->getProductPlatformDetail($payload);
            $item = data_get($response, 'data');
            if($item) {
                $mpSkuId = null;
                $noteChina = $orderDetail->note_china;
                $explodeNoteChina = explode(';', $noteChina);
                if($platform == 'taobao') {
                    $skuList = data_get($item, 'skuList', []);
                    foreach($skuList as $sku) {
                        $properties = data_get($sku, 'properties', []);
                        $checkClassification = true;
                        foreach($properties as $property) {
                            if(in_array($property->valueName, $explodeNoteChina) == false) {
                                $checkClassification = false;
                            }
                        }
                        if($checkClassification == true) {
                            $mpSkuId = $sku->skuId;
                            break;
                        }
                    }
                }else {
                    $productSkuInfos = data_get($item, 'productSkuInfos', []);
                    foreach($productSkuInfos as $productSkuInfo) {
                        $skuAttributes = data_get($productSkuInfo, 'skuAttributes', []);
                        $checkClassification = true;
                        foreach($skuAttributes as $skuAttribute) {
                            if(in_array($skuAttribute->value, $explodeNoteChina) == false) {
                                $checkClassification = false;
                            }
                        }
                        if($checkClassification == true) {
                            $mpSkuId = $productSkuInfo->specId;
                            break;
                        }
                    }
                }
                if($mpSkuId) {
                    $orderDetail->update([
                        'mp_id' => $itemId,
                        'mp_sku_id' => $mpSkuId,
                        'mp_sku_id_fast' => $mpSkuId,
                    ]);
                }
            }
        }
    }
}
