<?php

namespace ThuongDo\Buyder\src\Actions\CancelOrder;

use App\Constants\Buyder\BuyderStatusConstant;
use App\Constants\TimeConstant;
use App\Jobs\Buyder\SyncOrderBuyderDetailData;
use App\Models\OrderBuyder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;

class CancelOrderTaobaoAction extends BaseAction
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
        try {
            DB::transaction(function() use ($params): void {
                $cancelType = data_get($params, 'cancel_type');
                $cancelData = data_get($params, 'data');

                // không thể hủy đặt hàng các đơn đã thanh toán (order_buyders.supplier_status > 0)
                $orderBuyders = OrderBuyder::query()
                    ->whereIn('buyder_id', Arr::pluck($cancelData, 'buyder_id'))
                    ->get();
                $paidOrderBuyders = $orderBuyders->filter(
                    fn ($orderBuyder) => $orderBuyder->supplier_status != BuyderStatusConstant::BUYDER_ORDER_WAITING_PAY
                )->count();
                if ($paidOrderBuyders) {
                    throw new \Exception('Đơn hàng đã thanh toán không được hủy');
                }

                // tiến hành hủy đơn
                $this->cancelOrdersTaobao($cancelData, $cancelType);

                // cập nhật lý do hủy đặt hàng
                $this->updateCloseReason(
                    Arr::flatten(Arr::pluck($cancelData, 'buyder_sub_ids')),
                    $cancelType
                );

                // dispatch job để cập nhật lại tổng tiền của order_buyders
                $userIdTaobao = auth()->user()->id_taobao;
                foreach ($orderBuyders as $orderBuyder) {
                    dispatch(
                        new SyncOrderBuyderDetailData($orderBuyder, $userIdTaobao)
                    )->delay(Carbon::now()->addSeconds(TimeConstant::QUEUE_MIN_DELAY_TIME));
                }
            });

            return resSuccess('Hủy đơn thành công');
        } catch (\Exception $e) {
            Log::error("Lỗi khi hủy đơn Taobao: {$e}");
            return resError('Hủy đơn không thành công', 400);
        }
    }
}
