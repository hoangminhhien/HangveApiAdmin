<?php

namespace App\Services;

use App\Http\Resources\Rate\RateResource;
use Illuminate\Http\JsonResponse;

class OrderRateService extends BaseService
{
    protected string $_resource = RateResource::class;

    /**
     * @param array $data
     * @return JsonResponse
     * @throws \Exception
     */
    public function list(array $data): JsonResponse
    {
        $perPage = $data["per_page"] ?? 15;
        $orderCode = $data["order_code"] ?? null;
        $customerCode = $data["customer_code"] ?? null;
        $dateFrom = $data["date_from"] ?? null;
        $dateTo = $data["date_to"] ?? null;

        $this->throwModel();
        $data = $this->_model->newQuery()->with('order', 'customer');
        if ($orderCode) {
            $data = $data->where("order_code", "LIKE", "%$orderCode%");
        }
        if ($customerCode) {
            $data = $data->where("customer_code", "LIKE", "%$customerCode%");
        }
        if ($dateFrom && $dateTo) {
            $data = $data->whereBetween("date", [$dateFrom, $dateTo]);
        }
        $data = $data->orderBy("created_at", "DESC")->paginate($perPage);
        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }

    /**
     * @param string $id
     * @return JsonResponse
     * @throws \Exception
     */
    public function delete(string $id): JsonResponse
    {
        try {
            $this->_model->newQuery()->where("id", $id)->delete();
            return resSuccess();
        } catch (\Exception $exception) {
            return resError($exception->getMessage());
        }
    }

}
