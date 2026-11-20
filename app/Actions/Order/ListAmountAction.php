<?php

namespace App\Actions\Order;

use App\Actions\BaseAction;
use App\Traits\Order\OrderTrait;
use Illuminate\Http\JsonResponse;

class ListAmountAction extends BaseAction
{
    use OrderTrait;

    /**
     * Tiếp nhận dữ liệu, phân phối tới các hàm xử lý tương ứng
     *
     * @param array|null $params
     * @return JsonResponse
     */
    public function handle(?array $params): JsonResponse
    {
        // ...
        return resSuccess('List Amount');
    }
}
