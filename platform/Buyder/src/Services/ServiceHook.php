<?php

namespace ThuongDo\Buyder\src\Services;

use Illuminate\Http\JsonResponse;
use ThuongDo\Buyder\src\Actions\Hook\UpdatePlatformOrdersAction;
use ThuongDo\Buyder\src\Traits\HookTrait;

class ServiceHook implements BaseService
{
    use HookTrait;

    /**
     * Nhận dữ liệu cập nhật thông tin đặt hàng từ Buyder
     *
     * @return JsonResponse
     */
    public function updatePlatformOrders(array $data): JsonResponse
    {
        $action = app()->make(UpdatePlatformOrdersAction::class);
        return $action->handle(array_merge($data, [
            'method' => $this->getUpdatePlatformOrderMethod(data_get($data, 'messageType'))
        ]));
    }
}
