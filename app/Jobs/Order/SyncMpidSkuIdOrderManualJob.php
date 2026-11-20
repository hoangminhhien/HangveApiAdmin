<?php

namespace App\Jobs\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Constants\BuyderServiceConstant;
use App\Services\PlatformServices\BasePlatformService;
use App\Factories\BasePlatformFactory;
use App\Models\OrderDetail;

class SyncMpidSkuIdOrderManualJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    private $orderDetailIds;
    public function __construct($orderDetailIds)
    {
        $this->orderDetailIds = $orderDetailIds;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $orderDetailIds = $this->orderDetailIds ?? [];
        $orderDetails = OrderDetail::where('id', $orderDetailIds)->get();
        if(count($orderDetails) > 0) {
            foreach($orderDetails as $orderDetail) {
                $link = $orderDetail->link;
                $orderDetail->update([
                    'mp_id' => null,
                    'mp_sku_id' => null
                ]);
                if($link) {
                    $platform = null;
                    if (str_contains($link, 'taobao') || str_contains($link, 'tmall')) {
                        $platform = BuyderServiceConstant::SOURCE_TAOBAO;
                    }
                    if (str_contains($link, '1688')) {
                        $platform = BuyderServiceConstant::SOURCE_1688;
                    }
                    if($platform) {
                        $itemId = getPlatformIdFromUrl($link) ?? get1688IdFromRouteParam($link);
                        $service = new BasePlatformService(BasePlatformFactory::createBuyerService($platform));
                        if($platform == BuyderServiceConstant::SOURCE_1688) {
                            $payload = [
                                'offerId' => $itemId,
                                'is_sync_product_detail' => true
                            ];
                            $response = $service->getProductPlatformDetail($payload);
                            if(data_get($response, 'code') == 200) {
                                $item = $response['data'];
                                $res = json_decode(json_encode($item), true);
                                $skuList = data_get($res, 'productSkuInfos') ?? [];
                                $orderDetail->mp_id = $itemId;
                                if(count($skuList) == 1) {
                                    $orderDetail->mp_sku_id = $skuList[0]['specId'];
                                }
                                $orderDetail->save();
                            }

                        }
                        if($platform == BuyderServiceConstant::SOURCE_TAOBAO) {
                            $payload = [
                                'itemId' => $itemId,
                                'is_sync_product_detail' => true
                            ];
                            $response = $service->getProductPlatformDetail($payload);
                            if(data_get($response, 'code') == 200) {
                                $item = $response['data'];
                                $res = json_decode(json_encode($item), true);
                                $skuList = data_get($res, 'data.item.properties') ?? [];
                                if(count($skuList) == 1) {
                                    $orderDetail->update([
                                        'mp_id' => $itemId,
                                        'mp_sku_id' => $skuList[0]['mpSkuId']
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
