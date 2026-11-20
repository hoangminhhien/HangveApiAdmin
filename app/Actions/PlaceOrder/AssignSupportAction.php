<?php

namespace App\Actions\PlaceOrder;

use App\Models\AssignSupportOrder;
use App\Models\OrderDetail;
use Illuminate\Support\Arr;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;

class AssignSupportAction extends BaseAction
{
    /**
     * Gán người hỗ trợ đơn, tạo bản ghi trong bảng 'assign_support_orders'
     *
     * @param array|null $params
     * @return void
     */
    public function handle(?array $params): void
    {
        $authUser = auth()->user();
        $orgId = $authUser->organization()->first()->id;
        $assigneeIds = data_get($params, 'assignee_ids', []);
        $orderDetailId = data_get($params, 'order_detail_id');
        $quantity = data_get($params, 'quantity');
        $orderId = OrderDetail::query()->findOrFail($orderDetailId)->order->id;

        // TODO: Upgrade trait Uuid, tránh query trong loop chỉ để tận dụng model observer
        Arr::each($assigneeIds, function ($assigneeId) use ($orderId, $orgId, $orderDetailId, $authUser, $quantity) {
            $data = [
                'order_id' => $orderId,
                'organization_id' => $orgId,
                'order_detail_id' => $orderDetailId,
                'assigner_id' => $authUser->id,
                'assignee_id' => $assigneeId,
                'quantity' => $quantity,
            ];

            AssignSupportOrder::query()->create($data);
        });
    }
}
