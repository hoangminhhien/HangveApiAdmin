<?php

namespace ThuongDo\Buyder\src\Actions\Payment;

use App\Models\OrderBuyder;

trait PaymentActionTrait
{
    /**
     * Đổi trạng thái các đơn đặt hàng trong db của Hàng Về
     * Các bảng: orders, order_buyders, order_buyder_subs
     *
     * @param array $buyderIds
     * @param string $orderBuyderStatus
     * @param string $orderBuyderSubStatus
     * @return void
     */
    private function handleChangeLocalStatuses(
        array $buyderIds,
        string $orderBuyderStatus,
        string $orderBuyderSubStatus
    ): void {
        OrderBuyder::query()->whereIn('purchase_id', $buyderIds)
            ->update(['supplier_status' => $orderBuyderStatus]);

        OrderBuyder::query()->whereIn('purchase_id', $buyderIds)
            ->with(['subOrderBuyders'])
            ->each(function ($orderBuyder) use ($orderBuyderSubStatus) {
                // 'each' để trigger model event 'updating'
                $orderBuyder->subOrderBuyders->each(function ($subOrderBuyder) use ($orderBuyderSubStatus) {
                    $subOrderBuyder->update([
                        'buyder_status' => $orderBuyderSubStatus
                    ]);
                });
            });
    }
}
