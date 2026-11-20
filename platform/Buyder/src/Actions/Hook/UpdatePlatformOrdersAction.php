<?php

namespace ThuongDo\Buyder\src\Actions\Hook;

use Illuminate\Http\JsonResponse;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;
use ThuongDo\Buyder\src\Jobs\Hook\BuyerRefund;
use ThuongDo\Buyder\src\Jobs\Hook\BuyerStatus;
use ThuongDo\Buyder\src\Jobs\Hook\PriceModify;

class UpdatePlatformOrdersAction extends BaseAction
{
    /**
     * Tiếp nhận và phân phối dữ liệu các hoạt động liên quan đến hook
     *
     * @param array|null $params
     * @return void
     */
    public function handle(?array $params): JsonResponse
    {
        $method = data_get($params, 'method');
        return $this->{$method}($params);
    }

    /**
     * Cập nhật tiền thực tế đơn đặt hàng
     * Gồm 3 loại
     *   + Tổng tiền thực tế phải thanh toán
     *   + Tổng tiền hàng thực tế
     *   + Phí ship nội địa TQ thực tế
     *
     * @param array $params
     * @return JsonResponse
     */
    private function priceModify(array $params): JsonResponse
    {
        dispatch(new PriceModify($params));
        return resSuccess('HangVe đã nhận được dữ liệu ' . data_get($params, 'messageType'));
    }

    /**
     * Cập nhật trạng thái đơn đặt hàng
     *   + order_buyders.supplier_status
     *   + order_buyder_subs.buyder_status
     *
     * @param array $params
     * @return JsonResponse
     */
    private function buyerStatus(array $params): JsonResponse
    {
        dispatch(new BuyerStatus($params));
        return resSuccess('HangVe đã nhận được dữ liệu ' . data_get($params, 'messageType'));
    }

    /**
     * Cập nhật thông tin hoàn tiền
     *
     * @param array $params
     * @return JsonResponse
     */
    private function buyerRefund(array $params): JsonResponse
    {
        dispatch(new BuyerRefund($params));
        return resSuccess('HangVe đã nhận được dữ liệu ' . data_get($params, 'messageType'));
    }
}
