<?php

namespace App\Http\Controllers;

use App\Constants\WarehouseConstant;
use App\Http\Resources\ListResource;
use App\Http\Resources\Warehouse\WarehouseGroupByCountryResource;
use App\Http\Resources\Warehouse\WarehouseListResource;
use App\Http\Resources\Warehouse\WarehouseResource;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use Illuminate\Http\JsonResponse;

class WarehouseController extends Controller
{
    /**
     * WarehouseController constructor.
     * @param  WarehouseService  $warehouseService
     */
    public function __construct(WarehouseService $warehouseService)
    {
        $this->_service = $warehouseService;
    }

    /**
     * @param  string  $country
     * @return JsonResponse
     */
    public function getListByCountry(string $country): JsonResponse
    {
        return resSuccessWithinData(
            new WarehouseListResource(
                Warehouse::query()->where('country', $country)->where('status', 1)->get()
            )
        );
    }

    /**
     * @param  string  $country
     * @return JsonResponse
     */
    public function getListGroupByProvince(string $country): JsonResponse
    {
        return resSuccessWithinData(
            new WarehouseGroupByCountryResource(
                // Bỏ kho HP
                Warehouse::query()->where('country', $country)
                    ->where('code', '!=', WarehouseConstant::CODE_HP)
                    ->where('status', WarehouseConstant::STATUS_ACTIVE)
                    ->get()->groupBy('province_id')
            )
        );
    }

    // Lấy kho VN kèm tỉnh thành
    public function getListByCountryVN()
    {
        $warehouseModel = \App::make('App\Models\Warehouse');
        $warehouses = $warehouseModel->where('country', 'vi')->where('status','!=', 0)->get();
        $arr_warehouses = [];
        foreach ($warehouses as $key => $warehouse) {
            $temp = [
                'id'          => $warehouse->id,
                'name'        => $warehouse->custom_name,
                'province_id' => $warehouse->province_id,
                'name_v2'        => $warehouse->custom_name_v2 ?? '',
            ];
            array_push($arr_warehouses, $temp);
        }

        $data_result = [
            'arr_warehouses'  => $arr_warehouses
        ];
        return resSuccessWithinData($data_result);
    }
}
