<?php

namespace ThuongDo\Buyder\src\Services;

use Illuminate\Http\JsonResponse;
use ThuongDo\Buyder\src\Actions\CancelOrder\CancelOrderTaobaoAction;
use ThuongDo\Buyder\src\Actions\Payment\PaymentTaobaoAction;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderTaobaoAction;
use ThuongDo\Buyder\src\Interfaces\ServiceActionInterface;
use ThuongDo\Buyder\src\Traits\AuthServiceTrait;

class ServiceTaobao implements BaseService, ServiceActionInterface
{
    use AuthServiceTrait;

    /**
     * Đặt hàng
     *
     * @param array $params
     * @return JsonResponse
     */
    public function placeOrder(array $params): JsonResponse
    {
        $platformId = $this->checkIfUserHavePlatformId(__CLASS__);

        if ($platformId instanceof JsonResponse) {
            return $platformId;
        }

        $action = app()->make(PlaceOrderTaobaoAction::class);
        return $action->handle(array_merge($params, [
            'platform_id' => $platformId
        ]));
    }

    /**
     * Hủy đặt hàng
     *
     * @param array $params
     * @return JsonResponse
     */
    public function cancelOrder(array $params): JsonResponse
    {
        $platformId = $this->checkIfUserHavePlatformId(__CLASS__);

        if ($platformId instanceof JsonResponse) {
            return $platformId;
        }

        $action = app()->make(CancelOrderTaobaoAction::class);
        return $action->handle(array_merge($params, [
            'platform_id' => $platformId
        ]));
    }

    /**
     * Thanh toán
     *
     * @param array $params
     * @return JsonResponse
     */
    public function handlePayment(array $params): JsonResponse
    {
        $platformId = $this->checkIfUserHavePlatformId(__CLASS__);

        if ($platformId instanceof JsonResponse) {
            return $platformId;
        }

        $action = app()->make(PaymentTaobaoAction::class);
        return $action->handle(array_merge($params, [
            'platform_id' => $platformId
        ]));
    }
}
