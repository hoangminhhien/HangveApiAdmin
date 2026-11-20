<?php

namespace App\Actions\PlaceOrder;

use App\Models\AssignSupportOrder;
use Illuminate\Http\JsonResponse;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;

class CheckIsAssignedSupportAction extends BaseAction
{
    /**
     * Kiểm tra đơn đã được nhờ nhân viên này chưa
     * Luôn trả về 200, nhưng
     *   + Nếu đã nhờ hỗ trợ rôồi rồi thì data sẽ là mảng chứa tên các nhân viên đã được nhờ
     *   + Nếu không có nhân viên nào đã được nhờ hỗ trợ thì data là mảng rỗng
     *
     * @param array|null $params
     * @return void
     */
    public function handle(?array $params): JsonResponse
    {
        $assignSupportOrders = AssignSupportOrder::query()
            ->with(['assignee'])
            ->where('order_detail_id', data_get($params, 'order_detail_id'))
            ->whereIn('assignee_id', data_get($params, 'users'))
            ->get();

        if ($assignSupportOrders->count()) {
            $assigned = $assignSupportOrders
                ->map(fn ($assignSupportOrder) => $assignSupportOrder->assignee->name)
                ->unique();
            $assignedNames = implode(', ', $assigned->toArray());

            return resSuccessWithinData(
                $assigned,
                "Các nhân viên sau đã được nhờ hỗ trợ đơn hàng này: {$assignedNames}. Bạn có muốn tiếp tục?"
            );
        }

        return resSuccess('Bạn có thể nhờ các nhân viên này hỗ trợ');
    }
}
