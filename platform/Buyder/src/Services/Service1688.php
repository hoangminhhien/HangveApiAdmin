<?php

namespace ThuongDo\Buyder\src\Services;

use Illuminate\Http\JsonResponse;
use ThuongDo\Buyder\src\Actions\CancelOrder\CancelOrder1688Action;
use ThuongDo\Buyder\src\Actions\ConnectChat\ConnectChat1688Action;
use ThuongDo\Buyder\src\Actions\Payment\Payment1688Action;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrder1688Action;
use ThuongDo\Buyder\src\Interfaces\ServiceActionInterface;
use ThuongDo\Buyder\src\Traits\AuthServiceTrait;

class Service1688 implements BaseService, ServiceActionInterface
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

        $action = app()->make(PlaceOrder1688Action::class);
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

        $action = app()->make(CancelOrder1688Action::class);
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

        $action = app()->make(Payment1688Action::class);
        return $action->handle(array_merge($params, [
            'platform_id' => $platformId
        ]));
    }

    /**
     * @return void
     */
    public function connectChat(array $params): JsonResponse
    {
        $platformId = $this->checkIfUserHavePlatformId(__CLASS__);

        if ($platformId instanceof JsonResponse) {
            return $platformId;
        }

        $action = app()->make(ConnectChat1688Action::class);
        return $action->handle(array_merge($params, [
            'platform_id' => $platformId
        ]));
    }
}
