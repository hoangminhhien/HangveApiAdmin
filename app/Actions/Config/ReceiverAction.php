<?php

namespace App\Actions\Config;

use App\Models\Config;
use Illuminate\Http\JsonResponse;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class ReceiverAction extends BaseAction
{
    /**
     * Tiếp nhận các dữ liệu liên quan tới config địa chỉ nhận hàng (receiver)
     *
     * @param array|null $params
     * @return void
     */
    public function handle(?array $params)
    {
        $method = data_get($params, 'method');

        return $this->{$method}($params);
    }

    /**
     * Lấy danh sách địa chỉ nhận hàng
     *
     * @return JsonResponse
     */
    public function listReceivers(array $params): JsonResponse
    {
        $receivers = Config::query()->firstWhere('category', 'receivers');
        $receivers->value = json_decode($receivers->value);
        $receivers->listSources = BuyderServiceConstant::AVAILABLE_SOURCES;

        return resSuccessWithinData($receivers);
    }

    /**
     * Cập nhật địa chỉ nhận hàng
     *
     * @return JsonResponse
     */
    public function updateReceivers(array $params): JsonResponse
    {
        Config::query()
            ->where('category', 'receivers')
            ->update([
                'value' => json_encode(data_get($params, 'value'))
            ]);

        return resSuccess('Cập nhật địa chỉ nhận hàng thành công');
    }
}
