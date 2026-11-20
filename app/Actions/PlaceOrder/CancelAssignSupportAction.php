<?php

namespace App\Actions\PlaceOrder;

use App\Constants\Buyder\BuyderStatusConstant;
use App\Models\AssignSupportOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;

class CancelAssignSupportAction extends BaseAction
{
    /**
     * Hủy đơn hỗ trợ, xóa bản ghi trong bảng 'assign_support_orders', có thể hủy nhiều đơn cùng lúc
     * Chỉ được thực hiện nếu đơn nhờ này chưa thanh toán hay ở các bước sau đó
     * Nghĩa là order_buyder.suplier_status phải bằng 0
     *
     * @param array|null $params
     * @return JsonResponse|mixed|void
     */
    public function handle(?array $params)
    {
        $assignSupportIds = data_get($params, 'assign_support_ids');

        try {
            DB::transaction(function () use ($assignSupportIds): void {
                $assignSupports = AssignSupportOrder::query()->find($assignSupportIds);

                $assignSupports->each(function ($assignSupport) {
                    $supplierStatus = data_get($assignSupport->orderBuyder, 'supplier_status');
                    if ($supplierStatus && $supplierStatus != BuyderStatusConstant::BUYDER_ORDER_WAITING_PAY) {
                        throw new \Exception('Không thể hủy hỗ trợ đơn hàng đã được thanh toán');
                    }
                });

                AssignSupportOrder::destroy($assignSupportIds);
            });

            return resSuccess('Hủy đơn hỗ trợ thành công');
        } catch (\Exception $e) {
            Log::error($e);
            return resError($e->getMessage());
        }
    }
}
