<?php

namespace App\Listeners;

use App\Constants\Buyder\BuyderStatusConstant;
use App\Constants\TimeConstant;
use App\Events\PlaceOrder\PlaceOrderCreateLocalDataEvent;
use App\Factories\BasePlatformFactory;
use App\Jobs\Buyder\SyncOrderBuyderDetailData;
use App\Jobs\QuickCreateOrderPackageJob;
use App\Models\AssignSupportOrder;
use App\Models\Order;
use App\Models\OrderBuyderCategory;
use App\Models\OrderDetail;
use App\Models\SubOrderBuyder;
use App\Services\OrderBuyderService;
use App\Services\OrderPackageService;
use App\Services\PlatformServices\BasePlatformService;
use App\Traits\PlaceOrder\CreateLocalDataTrait;
use App\Traits\PlaceOrder\GetPlatformStatusConstantNameTrait;
use Carbon\Carbon;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class PlaceOrderCreateLocalDataListener
{
    use CreateLocalDataTrait, GetPlatformStatusConstantNameTrait;

    /**
     * Thực thi event
     *
     * @param PlaceOrderCreateLocalDataEvent $event
     * @return void
     */
    public function handle(PlaceOrderCreateLocalDataEvent $event)
    {
        // $even->platformSource là 'taobao' hoặc '1688'
        $platformSource = ucfirst($event->platformSource);
        $method = "handle{$platformSource}";
        $this->{$method}($event);
    }

    /**
     * Tạo dữ liệu trong db HangVe sau khi tạo đơn Taobao
     *
     * @param PlaceOrderCreateLocalDataEvent $event
     * @return void
     * @throws BindingResolutionException
     */
    private function handleTaobao(PlaceOrderCreateLocalDataEvent $event): void
    {
        $inputData = $event->inputData;
        $dataBuyder = $event->dataBuyder;
        $subOrderVOS = $dataBuyder['subOrderVOS'] ?? [];
        $listOrder = data_get($inputData, 'list_order', []);
        $is_auto_update = $inputData['receiver']['checked'] ? 1 : 0;
        $source = $event->source;
        $user = auth()->user();
        $userId = $user->id ?? 0;
        $orgId = $user->organization->id ?? 0;
        $buyderOrderNo = data_get($dataBuyder, 'orderNo');
        $dataBuyder['supplier_status'] = BuyderStatusConstant::ORDER_WAIT_BUYER_P;
        $orderLineList = $inputData['orderLineList'] ?? [];
        $purchaseAmount = 0;
        foreach ($orderLineList as $item) {
            $purchaseAmount += $item['purchaseAmount'];
        }
        $inputData['purchaseAmount'] = (float)($purchaseAmount / 100);
        $inputData['is_auto_update'] = $is_auto_update;
        $orderBuyder = $this->createOrderBuyder(
            $orgId, $inputData, $dataBuyder, $user,
            BuyderServiceConstant::SOURCE_TAOBAO,
        );
        $listOrderFormat = [];
        foreach ($listOrder as $list) {
            $listOrderFormat[$list['order_id']] = $list;
        }
        $listOrderFormatValue = array_values($listOrderFormat);
        $arrayInsertOrderBuyerCategory = [];
        foreach ($listOrderFormatValue as $val) {
            $arrayInsertOrderBuyerCategory[] = [
                'order_id' => $val['order_id'],
                'order_code' => $val['order_code'],
                'order_buyder_id' => $orderBuyder->id ?? 0,
                'category_id' => $val['category_id'],
            ];
        }
        if (!empty($arrayInsertOrderBuyerCategory)) {
            OrderBuyderCategory::query()->insert($arrayInsertOrderBuyerCategory);
        }

        $localOrderIds = array_unique(Arr::pluck($orderLineList, 'localOrderId'));
        $orderBuyder->orders()->attach($localOrderIds);
        $arrayInsertSubOrderBuyer = [];
        foreach ($subOrderVOS as $subOrderVO) {
            foreach ($orderLineList as $list) {
                $originalSkuId = $subOrderVO->originalSkuId ?? 0;
                $skuId = $list['skuId'] ?? 0;
                \Log::info($list);
                if ($subOrderVO->originalItemId == $list['itemId'] && $originalSkuId == $skuId) {
                    $arrayInsertSubOrderBuyer[] = [
                        'organization_id' => $orgId,
                        'source' => BuyderServiceConstant::SOURCE_TAOBAO,
                        'staff_id' => $userId,
                        'order_id' => $list['localOrderId'],
                        'sourceable_id' => $list['orderDetailId'],
                        'sourceable_type' => $list['assignSupportId'] ? AssignSupportOrder::class : OrderDetail::class,
                        'order_buyder_id' => $orderBuyder->id ?? 0,
                        'buyder_status' => BuyderStatusConstant::SUB_BUYDER_ORDER_BUILDING,
                        'order_no' => $buyderOrderNo,
                        'buyder_sub_id' => data_get($subOrderVO, 'subOrderId'),
                        'quantity' => data_get($subOrderVO, 'quantity'),
                        'domestic_post_fee' => data_get($subOrderVO, 'domesticPostFee'),
                        'taobao_purchase_id' => data_get($subOrderVO, 'taobaoPurchaseId'),
                        'taobao_sub_purchase_id' => data_get($subOrderVO, 'taobaoSubPurchaseId'),
                        'mp_id' => data_get($subOrderVO, 'originalItemId'),
                        'mp_sku_id' => data_get($subOrderVO, 'originalSkuId', 0),
                        'sku_id' => data_get($subOrderVO, 'skuId', 0),
                        'platform_total_amount' => 0,
                        'platform_goods_amount' => 0,
                        'platform_domestic_post_fee' => 0,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                }
            }
        }
        $arrayInsertOrderPackage = [];
        foreach ($arrayInsertSubOrderBuyer as $item) {
            $arrayInsertOrderPackage[$item['order_id']]['products'][] = [
                'id' => (int)$item['sourceable_id'],
                'quantity' => $item['quantity']
            ];
            Log::info('PlaceOrderCreateLocalDataListener@handleTaobao' . json_encode($listOrderFormat));
            $arrayInsertOrderPackage[$item['order_id']]['category_id'] =
                isset($listOrderFormat[$item['order_id']])
                    ? (int)$listOrderFormat[$item['order_id']]['category_id'] : 0;
            $arrayInsertOrderPackage[$item['order_id']]['package_number'] = 1;
            $arrayInsertOrderPackage[$item['order_id']]['code_po'] = '';
            $arrayInsertOrderPackage[$item['order_id']]['buyder_no'] = data_get($dataBuyder, 'orderNo');

        }
        SubOrderBuyder::query()->insert($arrayInsertSubOrderBuyer);
        if ($source == 'place_order_assign_support') {
            $this->updateAssignSupportOrderNo(
                Arr::pluck(data_get($inputData, 'orderLineList'), 'assignSupportId'),
                $buyderOrderNo
            );
        }
        $this->updateOrderTaoBao($orderBuyder, $user->id_taobao ?? 0, $arrayInsertOrderPackage);
        /*dispatch(
            new SyncOrderBuyderDetailData($orderBuyder, $user->id_taobao ?? 0, $arrayInsertOrderPackage)
        )->delay(Carbon::now()->addSeconds(10))
            ->onQueue('sync-order-platform');*/

    }

    /**
     * @throws BindingResolutionException
     * @throws \Exception
     */
    private function updateOrderTaoBao($orderBuyder, $user_id_taobao, $arrayInsertOrderPackage)
    {
        $orderBuyderService = app()->make(OrderBuyderService::class);
        $orderBuyderService->updateOrderTaoBao($orderBuyder, $user_id_taobao, $arrayInsertOrderPackage);
    }

    private function insertOrderPackage1688($arrayInsertOrderPackage)
    {
        if (!empty($arrayInsertOrderPackage)) {
            $jobs = new QuickCreateOrderPackageJob(
                $arrayInsertOrderPackage,
                false,
                '1688'
            );
            dispatch($jobs)->onQueue('sync-order-platform');
        }
    }

    /**
     * Tạo dữ liệu trong db HangVe sau khi tạo đơn 1688
     *
     * @param PlaceOrderCreateLocalDataEvent $event
     * @return void
     * @throws BindingResolutionException
     */
    private function handle1688(PlaceOrderCreateLocalDataEvent $event): void
    {
        $inputData = $event->inputData;
        $cargoParamLists = $inputData['cargoParamList'];
        $dataBuyder = $event->dataBuyder;
        $source = $event->source;
        $listOrder = data_get($inputData, 'list_order', []);
        $user = auth()->user();
        $userId = $user->id ?? 0;
        $orgId = $user->organization->id ?? 0;
        $inputData['receiver'] = data_get($inputData, 'addressParam');
        $is_auto_update = $inputData['receiver']['checked'] ? 1 : 0;
        $platformGoodsAmount = (float)(collect(data_get($dataBuyder, 'productItems'))->sum('itemAmount'));
        $inputData['purchaseAmount'] = $platformGoodsAmount;
        $inputData['is_auto_update'] = $is_auto_update;

        $dataBuyder['id'] = data_get($dataBuyder, 'baseInfo.idOfStr');
        $dataBuyder['orderNo'] = data_get($dataBuyder, 'baseInfo.id');
        $dataBuyder['supplier_status'] = BuyderStatusConstant::ORDER_WAIT_BUYER_P; /*data_get($dataBuyder, 'baseInfo.status');*/

        // tạo bản ghi trong 'order_buyders'
        $platformDomesticPostFee = (float)data_get($dataBuyder, 'baseInfo.shippingFee');
        $platformAmountData = [
            'platform_total_amount' => $platformGoodsAmount + $platformDomesticPostFee,
            'platform_goods_amount' => $platformGoodsAmount,
            'platform_domestic_post_fee' => $platformDomesticPostFee,
        ];
        $orderBuyder = $this->createOrderBuyder(
            $orgId, $inputData, $dataBuyder, $user,
            BuyderServiceConstant::SOURCE_1688,
            $platformAmountData
        );
        $listOrderFormat = [];
        foreach ($listOrder as $dataCate) {
            $orderBuyder->orderBuyderCategories()->create($dataCate);
            $listOrderFormat[$dataCate['order_id']] = $dataCate;
        }

        // tạo bản ghi trong pivot 'order_buyder_order'
        $cargoParamList = $inputData['cargoParamList'];
        $localOrderIds = array_unique(Arr::pluck($cargoParamList, 'localOrderId'));
        $orderBuyder->orders()->attach($localOrderIds);

        $buyderOrderNo = data_get($dataBuyder, 'baseInfo.idOfStr');
        $arrayInsertSubOrder = [];
        $productItems = $dataBuyder['productItems'] ?? [];
        $platformDomesticPostFeeUnit = sizeof($productItems) > 0
            ? round($platformDomesticPostFee/sizeof($productItems)) : 0;
        foreach ($productItems as $productItem) {
            foreach ($cargoParamLists as $cargoParamList) {
                if (
                    $cargoParamList['offerId'] == $productItem->productID
                    && $cargoParamList['specId'] == $productItem->specId
                ) {
                    $skuInfos = $productItem->skuInfos ?? null;
                    $classification_zh = [];
                    if ($skuInfos) {
                        foreach ($skuInfos as $skuInfo) {
                            $classification_zh[] = $skuInfo->value ?? '';
                        }
                    }
                    $arrayInsertSubOrder[] = [
                        'organization_id' => $orgId,
                        'classification_zh' => !empty($classification_zh)
                            ? implode(';', $classification_zh)
                            : '',
                        'source' => '1688',
                        'staff_id' => $userId,
                        'order_id' => $cargoParamList['localOrderId'],
                        'sourceable_id' => $cargoParamList['orderDetailId'],
                        'sourceable_type' => $cargoParamList['assignSupportId']
                            ? AssignSupportOrder::class : OrderDetail::class,
                        'order_buyder_id' => $orderBuyder->id ?? 0,
                        'buyder_status' => $dataBuyder['supplier_status'],
                        'order_no' => $buyderOrderNo,
                        'buyder_sub_id' => $buyderOrderNo,
                        'quantity' => data_get($productItem, 'quantity'),
                        'domestic_post_fee' => $platformDomesticPostFeeUnit,
                        'taobao_purchase_id' => null,
                        'taobao_sub_purchase_id' => null,
                        'mp_id' => $cargoParamList['offerId'] ?? 0,
                        'mp_sku_id' => $cargoParamList['specId'] ?? 0,
                        'platform_total_amount' => $platformDomesticPostFeeUnit + $productItem->itemAmount,
                        'platform_goods_amount' => $productItem->itemAmount ?? 0,
                        'platform_domestic_post_fee' => $platformDomesticPostFeeUnit,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }
        $arrayInsertOrderPackage = [];
        foreach ($arrayInsertSubOrder as $item) {
            $arrayInsertOrderPackage[$item['order_id']]['products'][] = [
                'id' => (int)$item['sourceable_id'],
                'quantity' => $item['quantity']
            ];
            Log::info('PlaceOrderCreateLocalDataListener@handle1688' . json_encode($listOrderFormat));
            $arrayInsertOrderPackage[$item['order_id']]['category_id'] =
                isset($listOrderFormat[$item['order_id']])
                    ? (int)$listOrderFormat[$item['order_id']]['category_id'] : 0;
            $arrayInsertOrderPackage[$item['order_id']]['package_number'] = 1;
            $arrayInsertOrderPackage[$item['order_id']]['code_po'] = $dataBuyder['orderNo'];
            $arrayInsertOrderPackage[$item['order_id']]['buyder_no'] = $dataBuyder['orderNo'];

        }
        $this->insertOrderPackage1688($arrayInsertOrderPackage);
        SubOrderBuyder::query()->insert($arrayInsertSubOrder);
        if ($source == 'place_order_assign_support') {
            $this->updateAssignSupportOrderNo(
                Arr::pluck(data_get($inputData, 'cargoParamList'), 'assignSupportId'),
                data_get($dataBuyder, 'orderNo')
            );
        }
    }
}
