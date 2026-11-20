<?php

namespace App\Actions\Config;

use App\Constants\Config\UnitConstant;
use App\Constants\ResourceConstant;
use App\Http\Resources\PaginateJsonResource;
use App\Models\ConfigShippingEstimatedFee;
use Illuminate\Http\JsonResponse;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;

class ShippingEstimatedFeeAction extends BaseAction
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
        unset($params['method']);

        return $this->{$method}($params);
    }

    /**
     * Lấy danh sách hoặc một bản ghi config phí vận chuyển tạm tính
     * Các trường gửi kèm theo request
     *   + page: Trang hiện tại, pagination
     *   + per_page: Số record/page, pagination
     *   + category_name: Tên danh mục, filter
     *   + is_get_all: Quyết định có lấy tất cả bản ghi không
     *
     * @return JsonResponse
     */
    private function getConfigShippingEstimatedFee(): JsonResponse
    {
        $request = request();
        $categoryName = data_get($request, 'category_name');
        $isGetAll = (bool)data_get($request, 'is_get_all');
        $page = data_get($request, 'page', 1);
        $perPage = data_get($request, 'per_page', ResourceConstant::PER_PAGE_MIN);

        $data = ConfigShippingEstimatedFee::query()
            ->select(['id as config_id', 'category_code', 'category_name', 'weight', 'measurement_unit', 'count_unit'])
            ->when(
                $categoryName,
                fn ($query) => $query->where('category_name', 'LIKE', "%{$categoryName}%")
            )
            ->when(
                $isGetAll,
                fn ($query) => $query->get()
            )->when(
                !$isGetAll,
                fn ($query) => $query->paginate($perPage, ['*'], 'page', $page)
            );

        return $isGetAll
            ? resSuccessWithinData($data)
            : resSuccessWithinData(new PaginateJsonResource($data));
    }

    /**
     * Chỉnh sửa config phí vận chuyển tạm tính
     *
     * @param array $data
     * @return JsonResponse
     */
    private function updateConfigShippingEstimatedFee(array $data): JsonResponse
    {
        $config_id = data_get($data, 'config_id');
        unset($data['config_id']);
        ConfigShippingEstimatedFee::query()
            ->where('id', $config_id)
            ->update($data);

        return resSuccess('Cập nhật cài đặt phí vận chuyển tạm tính thành công');
    }

    /**
     * Lấy danh sách đơn vị (đo lường và đếm)
     *
     * @return JsonResponse
     */
    private function getConfigUnits(): JsonResponse
    {
        $data = [
            'measurement_units' => UnitConstant::AVAILABLE_UNIT_MEASUREMENT,
            'count_units' => UnitConstant::AVAILABLE_UNIT_COUNT,
        ];

        return resSuccessWithinData($data);
    }
}
