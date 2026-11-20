<?php

namespace App\Observers;

use App\Models\AssignSupportOrder;

class AssignSupportOrderObserver
{
    /**
     * Trigger khi xóa một bản ghi trong bảng 'assign_support_orders'
     * Khi hủy hỗ trợ, xóa bản ghi đơn đặt hàng con tương ứng (order_buyder_subs)
     * Mục đích tránh lỗi khi order_buyder_subs không tìm thâấy assign_support_order tương ứng
     *
     * @param AssignSupportOrder $assignSupportOrder
     * @return void
     */
    public function deleted(AssignSupportOrder $assignSupportOrder): void
    {
        $assignSupportOrder->loadMissing([
            'orderBuyderSubs' => fn ($query) => $query->withoutGlobalScope('without_canceled')
        ]);
        $orderBuyderSub = $assignSupportOrder->orderBuyderSubs;

        $orderBuyderSub && $orderBuyderSub->delete();
    }
}
