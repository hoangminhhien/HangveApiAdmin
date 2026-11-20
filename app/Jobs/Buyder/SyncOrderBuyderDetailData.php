<?php

namespace App\Jobs\Buyder;

use App\Constants\Buyder\BuyderStatusConstant;
use App\Factories\BasePlatformFactory;
use App\Models\Order;
use App\Models\OrderBuyder;
use App\Services\OrderPackageService;
use App\Services\PlatformServices\BasePlatformService;
use App\Traits\PlaceOrder\GetBuyderRequestResource;
use App\Traits\PlaceOrder\GetPlatformStatusConstantNameTrait;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use ThuongDo\Buyder\src\Services\ServiceRequest;

class SyncOrderBuyderDetailData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use GetBuyderRequestResource;
    use GetPlatformStatusConstantNameTrait;

    /**
     * Bản chi của OrderBuyder vừa được khởi tạo
     *
     * @var OrderBuyder
     */
    private OrderBuyder $orderBuyder;

    /**
     * ID đặt hàng của nhân viên
     *
     * @var string
     */
    private string $userId;
    private array $arrayInsertOrderPackage;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(OrderBuyder $orderBuyder, string $userId, array $arrayInsertOrderPackage)
    {
        $this->orderBuyder = $orderBuyder;
        $this->userId = $userId;
        $this->arrayInsertOrderPackage = $arrayInsertOrderPackage;
    }

    /**
     * @return void
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function handle()
    {
        $source = $this->orderBuyder->source;
        $closeReason = $this->getOrderBuyderSubsClosedStatus($source);
        $params['orderId'] = $this->orderBuyder->buyder_no ?? 0;
        $service = new BasePlatformService(BasePlatformFactory::createBuyerService($source));
        $result = $service->detailOrderPlatform($params);
        if ($result && !empty($result->code)) {
            if ($result->code == 200) {
                Log::info('detailOrderPlatform ' . json_encode($result));
                // đầu tiên update các sub_order_buyders.classification_zh
                $subOrderVos = data_get($result, 'data.0.subOrderVO');
                $this->orderBuyder->loadMissing('subOrderBuyders');
                $subOrderBuyders = $this->orderBuyder->subOrderBuyders;
                $platformGoodsAmount = 0;
                $platformTotalAmount = 0;
                $purchaseOrderOuterId = '';
                $subOrderBuyders->each(function ($subOrderBuyder) use (
                    $subOrderVos, $closeReason, &$purchaseOrderOuterId, &$platformGoodsAmount, &$platformTotalAmount
                ) {
                    $vos = Arr::first($subOrderVos, fn ($vo) => (
                        data_get($vo, 'skuId') == data_get($subOrderBuyder, 'mp_sku_id')
                    ));
                    // nếu đơn con không ở trạng thái 'đã hủy' thì cộng dồn tiền hàng và tổng tiền vào đơn cha
                    if (data_get($vos, 'subOrderStatus') != $closeReason) {
                        $purchaseAmount = (float)(data_get($vos, 'purchaseAmount') / 100);
                        $platformGoodsAmount += $purchaseAmount;
                        $platformTotalAmount += $purchaseAmount;
                    }
                    // lấy classification_zh
                    $zh = json_decode(data_get($vos, 'optionFields.zh'));
                    // lấy tiền hàng thực tế, phí ship thực tế, tổng tiền thực tế của đơn con

                    if ($purchaseOrderOuterId == '') {
                        $purchaseOrderOuterId = data_get($vos, 'purchaseOrderOuterId');
                    }

                    $vosPlatformGoodsAmount = (float)(data_get($vos, 'purchaseAmount') / 100);
                    $vosPlatformDomesticPostFee = (float)(data_get($vos, 'domesticPostFee') / 100);
                    $subOrderBuyder->update([
                        'classification_zh' => implode(';', get_object_vars($zh)),
                        'platform_total_amount' => $vosPlatformGoodsAmount + $vosPlatformDomesticPostFee,
                        'platform_goods_amount' => $vosPlatformGoodsAmount,
                        'platform_domestic_post_fee' => $vosPlatformDomesticPostFee,
                        'buyder_status' => BuyderStatusConstant::ORDER_WAIT_BUYER_P, /*data_get($vos, 'subOrderStatus')*/
                        'sub_purchase_order_id' => data_get($vos, 'subPurchaseOrderId')
                    ]);
                });

                $platformDomesticPostFee = $platformTotalAmount
                    ? (float)(data_get($result, 'data.0.domesticPostFee') / 100)
                    : 0;
                $arrayUpdate = [
                    'platform_total_amount' => $platformTotalAmount,
                    'platform_goods_amount' => $platformGoodsAmount,
                    'platform_domestic_post_fee' => $platformDomesticPostFee,
                ];
                if ($purchaseOrderOuterId) {
                    $arrayUpdate['purchase_order_outer_id'] = $purchaseOrderOuterId;
                }

                if (!empty($this->arrayInsertOrderPackage)) {
                    $orderPackageService = app()->make(OrderPackageService::class);
                    foreach ($this->arrayInsertOrderPackage as $key => $item) {
                        $item['code_po'] = $purchaseOrderOuterId;
                        $order = Order::query()->find($key);
                        $orderPackageService->quickCreatePackage($item, $order);
                    }
                }
                // Log::info('orderBuyder Update ' . json_encode($arrayUpdate));
                $this->orderBuyder->update($arrayUpdate);
            } else {
                Log::error('Cập nhật dữ liệu thật bại, mã đặt hàng buyder_id: ' . $this->orderBuyder->buyder_no);
            }
            return;
        }
        Log::error('Cập nhật dữ liệu thật bại, mã đặt hàng buyder_id: ' . $this->orderBuyder->buyder_no );
    }
}
