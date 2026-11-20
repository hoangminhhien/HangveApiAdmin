<?php

namespace ThuongDo\Buyder\src\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use ThuongDo\Buyder\src\App\Http\Requests\UpdatePlatformOrderRequest;
use ThuongDo\Buyder\src\Services\ServiceHook;

class HookController extends BaseController
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->service = app()->make(ServiceHook::class);
    }

    /**
     * Nhận dữ liệu cập nhật thông tin đặt hàng từ Buyder
     *
     * @return JsonResponse
     */
    public function updatePlatformOrders(UpdatePlatformOrderRequest $request): JsonResponse
    {
        return $this->service->updatePlatformOrders($request->validated());
    }
}
