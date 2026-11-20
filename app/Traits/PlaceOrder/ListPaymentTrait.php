<?php

namespace App\Traits\PlaceOrder;

use App\Constants\Buyder\BuyderStatusConstant;
use App\Constants\Buyder\OrderBuyderConstant;
use App\Constants\OrderConstant;
use App\Models\OrderBuyder;
use App\Models\OrderDetail;
use App\Models\SubOrderBuyder;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Collection;

trait ListPaymentTrait
{
    /**
     * Dữ liệu về đơn hàng to, chung format đối với Taobao và 1688
     *
     * @param OrderBuyder $orderBuyder
     * @return array
     */
    private function getGeneralInfo(OrderBuyder $orderBuyder): array
    {
        return [
            'order_buyder_platform_source' => data_get($orderBuyder, 'source'),
            'order_buyder_id' => data_get($orderBuyder, 'buyder_id'),
            'is_xadal' => data_get($orderBuyder, 'is_xadal'),
            'order_buyder_no' => data_get($orderBuyder, 'buyder_no'),
            'order_buyder_outer_id' => data_get($orderBuyder, 'purchase_order_outer_id'),
            'order_buyder_staff' => data_get($orderBuyder, 'staffOrder.name'),
            'order_buyder_time' => Carbon::parse(data_get($orderBuyder, 'created_at'))->format('H:i d/m/Y'),
            'order_buyder_receiver' => json_decode(data_get($orderBuyder, 'receiver')),
            'order_buyder_amount' => (float)data_get($orderBuyder, 'purchase_amount'),
            'order_buyder_status' => BuyderStatusConstant::LIST_ORDER_STATUS_NAME[data_get($orderBuyder, 'supplier_status')] ?? '',
        ];
    }

    private function getRefundableData($rawRefundableMilestone): array
    {
        $now = Carbon::now();

        if ($rawRefundableMilestone <= $now) {
            $refundRemainingTime = '00:00';
            $isRefundable = false;
        } else {
            $rawRefundRemainingTime = $rawRefundableMilestone->diff($now);
            $refundRemainingTime = Carbon::parse(sprintf(
                "%d:%d",
                $rawRefundRemainingTime->h,
                $rawRefundRemainingTime->i
            ))->format('H:i');
            $isRefundable = true;
        }

        return [$refundRemainingTime, $isRefundable];
    }

    private function getOrderBuyderSubsData(OrderBuyder $orderBuyder, &$offerId, &$sellerId): Collection
    {
        return $orderBuyder->subOrderBuyders->map(function ($subOrderBuyder) use (
            &$offerId, &$sellerId
        ) {
            /*$sourceable = $subOrderBuyder->sourceable;*/

            /*if ($sourceable instanceof OrderDetail) {
                $orderDetail = $sourceable;
                $orderDetail->loadMissing(['item']);
            } else {
                $orderDetail = $subOrderBuyder->orderDetailSub;
            }*/
            $orderDetail = $subOrderBuyder->orderDetailSub ?? [];
            $item = $orderDetail->item ?? [];
            $supplier = $subOrderBuyder->orderDetailSub->supplier ?? [];
            if (!$offerId || $sellerId) {
                $offerId = data_get($item, 'num_iid');
                $sellerId = data_get($item, 'seller_id');
            }
            $productPrice = data_get($orderDetail, 'unit_price_cny');
            $shippingCost = data_get($subOrderBuyder, 'domestic_post_fee');
            $productQuantity = (int)data_get($subOrderBuyder, 'quantity');
            $orderDetailQuantity = $orderDetail->quantity;
            if ($productQuantity > $orderDetailQuantity) {
                $productQuantity = $orderDetailQuantity;
            }
            $platform_goods_amount = data_get($subOrderBuyder, 'platform_goods_amount');
            $platform_domestic_post_fee = data_get($subOrderBuyder, 'platform_domestic_post_fee');
            $options = [
                'platform_goods_amount' => $platform_goods_amount,
                'platform_domestic_post_fee' => $platform_domestic_post_fee,
                'platform_total_amount' => ($platform_goods_amount * $productQuantity) + $platform_domestic_post_fee,
            ];

            $createdAt = data_get($subOrderBuyder, 'created_at');
            $rawRefundableMilestone = Carbon::parse($createdAt)->addHours(OrderBuyderConstant::REFUNDABLE_TIME);

            $refundableData = $this->getRefundableData($rawRefundableMilestone);
            $refundRemainingTime = $refundableData[0];
            $isRefundable = $refundableData[1];

            return $this->getSubInfo(
                $orderDetail,
                $subOrderBuyder,
                $supplier,
                $productPrice,
                $productQuantity,
                $shippingCost,
                $refundRemainingTime,
                $isRefundable,
                $options
            );
        });
    }

    private function getSubInfo(
        OrderDetail $orderDetail,
        SubOrderBuyder $subOrderBuyder,
        $supplier,
        float $productPrice,
        int $productQuantity,
        ?float $shippingCost,
        string $refundRemainingTime,
        bool $isRefundable,
        array $options = []
    ): array {
        $order_buyder_sub_product_image = str_replace('_.webp', '', data_get($orderDetail, 'image'));
        return [
            'order_detail_id' => data_get($orderDetail, 'id'),
            'order_id' => data_get($orderDetail, 'order_id'),
            'order_code' => $orderDetail->order->code ?? '',
            'order_status' => $orderDetail->order->status ?? '',
            'order_status_text' => OrderConstant::STATUSES[$orderDetail->order->status] ?? '',
            'order_buyder_sub_id' => data_get($subOrderBuyder, 'buyder_sub_id'),
            'order_buyder_sub_supplier' => data_get($supplier, 'name'),
            'order_buyder_sub_supplier_id' => data_get($supplier, 'code'),
            'order_buyder_sub_quantity' => $productQuantity,
            'order_buyder_sub_product_name' => data_get($orderDetail, 'name'),
            'offer_id' => $orderDetail->item->num_iid ?? 0,
            'order_buyder_sub_product_link' => data_get($orderDetail, 'link'),
            'order_buyder_sub_product_image' => $order_buyder_sub_product_image,
            'order_buyder_sub_product_classification' => data_get($orderDetail, 'classification'),
            'order_buyder_sub_product_classification_zh' => data_get($subOrderBuyder, 'classification_h'),
            'order_buyder_sub_product_price' => $productPrice,
            'order_buyder_sub_shipping_cost' => $shippingCost,
            'order_buyder_sub_total_price' => $productPrice * $productQuantity,
            'order_buyder_platform_sub_product_price' => (float)$options['platform_goods_amount'] ?? 0,
            'order_buyder_platform_sub_shipping_cost' => (float)$options['platform_domestic_post_fee'] ?? 0,
            'order_buyder_platform_sub_total_price' => (float)$options['platform_total_amount'] ?? 0,
            'order_buyder_sub_status' => BuyderStatusConstant::LIST_ORDER_STATUS_NAME[data_get($subOrderBuyder, 'buyder_status')] ?? '',
            'order_buyder_sub_refund_status' => null,
            'order_buyder_sub_refund_remaining_time' => $refundRemainingTime,
            'order_buyder_sub_is_refundable' => $isRefundable,
            'order_buyder_sub_note' => data_get($orderDetail, 'note'),
        ];
    }
}
