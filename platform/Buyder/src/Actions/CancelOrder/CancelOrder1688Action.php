<?php

namespace ThuongDo\Buyder\src\Actions\CancelOrder;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;

class CancelOrder1688Action extends BaseAction
{
    use CancelOrderTrait;

    /**
     * Hủy đặt hàng
     * Thứ tự thực hiện
     *   + Chuẩn bị data
     *   + Bắn API qua Buyder hủy đơn
     *   + Nếu hủy thành công thì
     *     - Cập nhật lý do hủy đặt hàng (order_buyder_subs.close_reason)
     *     - Cập nhật mã đặt hàng về null (assign_support_orders.order_no)
     *     - Và xóa kiện tương ứng với order_buyder_sub đó
     *
     * @param array|null $params array{
     *   cancel_type: numeric,
     *   data: array {
     *     platform_source: string,
     *     buyder_id: string,
     *     buyder_sub_ids: string
     *   }
     * }
     * @return JsonResponse
     */
    public function handle(?array $params): JsonResponse
    {
        // xử lý trùng lặp dữ liệu FE gửi lên
        $params = $this->getParamsWithUniqueBuyderIds($params);

        try {
            DB::transaction(function() use ($params): void {
                $cancelType = data_get($params, 'cancel_type');
                $cancelData = data_get($params, 'data');
                $platformId = data_get($params, 'platform_id');

                // không thể hủy đặt hàng các đơn đã thanh toán (order_buyders.supplier_status > 0)
                $this->checkWhetherOrdersPaid($cancelData);

                // tiến hành hủy đơn
                $this->cancelOrders1688($cancelData, $cancelType, $platformId);

                // cập nhật lý do hủy đặt hàng
                $this->updateCloseReason(
                    Arr::flatten(Arr::pluck($cancelData, 'buyder_sub_ids')),
                    $cancelType
                );
            });

            return resSuccess('Hủy đơn thành công');
        } catch (\Exception $e) {
            Log::error("Lỗi khi hủy đơn 1688: {$e}");
            return resError('Hủy đơn không thành công', 400);
        }
    }
}
