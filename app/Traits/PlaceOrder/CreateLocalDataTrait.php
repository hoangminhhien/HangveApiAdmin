<?php

namespace App\Traits\PlaceOrder;

use App\Constants\TimeConstant;
use App\Models\AssignSupportOrder;
use App\Models\OrderBuyder;
use App\Models\OrderDetail;
use App\Models\SubOrderBuyder;
use App\Models\User;

trait CreateLocalDataTrait
{
    private function createOrderBuyder(
        string $orgId,
        array $inputData,
        array $dataBuyder,
        $user,
        string $platformSource,
        array $platformAmountData = []
    ): OrderBuyder {
        $purchase_id = $dataBuyder['subOrderVOS'][0]->taobaoPurchaseId ?? 0;
        if ($platformSource == '1688') {
            $purchase_id = $dataBuyder['orderNo'];
        }
        $orderBuyder = OrderBuyder::query()->create([
            'organization_id' => $orgId,
            'source' => $platformSource,
            'is_auto_update' => $inputData['is_auto_update'] ?? 0,
            'taobao_user_id' => $platformSource == 'taobao'
                ? data_get($user, 'id_taobao')
                : data_get($user, 'id_1688'),
            'buyder_id' => data_get($dataBuyder, 'id'),
            'buyder_no' => data_get($dataBuyder, 'orderNo'),
            'supplier_status' => data_get($dataBuyder, 'supplier_status'),
            'delay_time' => data_get($inputData, 'delayTime') ?? TimeConstant::UPDATE_LOGISTIC_CODE_PERIOD,
            'creator_id' => data_get($user, 'id'),
            'order_source' => data_get($inputData, 'orderSource'),
            'purchase_id' => $purchase_id,
            'purchase_amount' => data_get($inputData, 'purchaseAmount'),
            'platform_total_amount' => data_get($platformAmountData, 'platform_total_amount', 0),
            'platform_goods_amount' => data_get($platformAmountData, 'platform_goods_amount', 0),
            'platform_domestic_post_fee' => data_get($platformAmountData, 'platform_domestic_post_fee', 0),
            'receiver' => json_encode(data_get($inputData, 'receiver'))
        ]);

        return $orderBuyder;
    }

    private function createOrderBuyderSubs(
        string $orgId, string $userId, array $orderLineList, $index,
        array $assignSupportIds, array $orderDetailIds,
        OrderBuyder $orderBuyder, string $buyderOrderNo,
        array $vosData, string $orderBuyderStatus, string $platformSource,
        array $subPlatformAmountData = []
    ): void {
        SubOrderBuyder::query()->create([
            'organization_id' => $orgId,
            'source' => $platformSource,
            'staff_id' => $userId,
            'order_id' => $orderLineList[$index]['localOrderId'],
            'sourceable_id' => $assignSupportIds[$index] ?? $orderDetailIds[$index],
            'sourceable_type' => $assignSupportIds[$index] ? AssignSupportOrder::class : OrderDetail::class,
            'order_buyder_id' => $orderBuyder->id,
            'buyder_status' => $orderBuyderStatus,
            'order_no' => $buyderOrderNo,
            'buyder_sub_id' => data_get($vosData, 'subOrderId'),
            'quantity' => data_get($vosData, 'quantity'),
            'domestic_post_fee' => data_get($vosData, 'domesticPostFee'),
            'taobao_purchase_id' => data_get($vosData, 'taobaoPurchaseId'),
            'taobao_sub_purchase_id' => data_get($vosData, 'taobaoSubPurchaseId'),
            'mp_id' => data_get($vosData, 'itemId'),
            'mp_sku_id' => data_get($vosData, 'skuId'),
            'platform_total_amount' => data_get($subPlatformAmountData, 'platform_total_amount', 0),
            'platform_goods_amount' => data_get($subPlatformAmountData, 'platform_goods_amount', 0),
            'platform_domestic_post_fee' => data_get($subPlatformAmountData, 'platform_domestic_post_fee', 0),
        ]);
    }

    /**
     * Chuẩn bị data để tạo kiện
     *
     * @param OrderBuyder $orderBuyder
     * @param array{codePo: string, logisticCode: string, purchaseOrderOuterId: string} $dataBuyder
     * @return array{package_number: int, products: array, code_po: string, bill_code: string, buyder_no: string}
     */
    private function prepareDataToCreatePackage(OrderBuyder $orderBuyder, array $dataBuyder): array
    {
        $products = $orderBuyder->subOrderBuyders->map(function ($subOrderBuyder) {
            $subOrderBuyder->loadMissing(['sourceable']);
            $sourceable = $subOrderBuyder->sourceable;

            if ($sourceable instanceof OrderDetail) {
                $orderDetail = $sourceable;
            } else if ($sourceable instanceof AssignSupportOrder) {
                $sourceable->loadMissing('orderDetail');
                $orderDetail = $sourceable->orderDetail;
            }

            return [
                'id' => data_get($orderDetail, 'id'),
                'quantity' => data_get($subOrderBuyder, 'quantity')
            ];
        });

        return [
            'package_number' => 1,
            'products' => $products->toArray(),
            'code_po' => data_get($dataBuyder, 'codePo'), // mã đặt hàng
            'bill_code' => data_get($dataBuyder, 'logisticCode'), // mã vận đơn
            'buyder_no' => data_get($dataBuyder, 'purchaseOrderOuterId'), // mã đặt hàng thật sự
        ];
    }

    /**
     * Cập nhật mã đặt hàng (assign_support_orders.order_no) nếu request đặt hàng gửi lên từ 'Đơn hàng hỗ trợ'
     *
     * @param array $assignSupportIds
     * @param string $orderNo
     * @return void
     */
    private function updateAssignSupportOrderNo(array $assignSupportIds, string $orderNo): void
    {
        AssignSupportOrder::query()
            ->whereIn('id', $assignSupportIds)
            ->update(['order_no' => $orderNo]);
    }
}
