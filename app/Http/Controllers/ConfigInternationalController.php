<?php

namespace App\Http\Controllers;

use App\Services\ConfigInternationalShipService;

use App\Services\ActivityService;


use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use GuzzleHttp\Client as Guzzle;
use App\Models\Config;
use App\Models\Warehouse;
use App\Constants\ConfigConstant;
use App\Constants\WarehouseConstant;
use App\Models\ConfigInternationalShip;
use Illuminate\Support\Carbon;

class ConfigInternationalController extends Controller
{
    public function __construct(ConfigInternationalShipService $service)
    {
        $this->_service = $service;
    }

    public function listGroupPack(Request $request)
    {
        $data_result = CenterApiConstant::ARR_MODULE;
        return resSuccessWithinData($data_result);
    }

    public function getPriceListCurrent(Request $request)
    {
        $configInternationalShipModel = \App::make('App\Models\ConfigInternationalShip');
        $provinceModel = \App::make('App\Models\Province');

        $configInternationalShipKg = null;
        $configInternationalShipM3 = null;

        $dateCurrent = date('Y-m-d H:i:s', time());
        $official = $request->input('official') ?? ConfigInternationalShip::OFFICIAL_1;

        $configInternationalShipKg = $configInternationalShipModel
                                                        ->where('official', $official)
                                                        ->where('type', 'kg')
                                                        ->where('date_start', '<', $dateCurrent)
                                                        ->orderBy('date_start', 'desc')
                                                        ->first();

        $configInternationalShipM3 = $configInternationalShipModel
                                                        ->where('official', $official)
                                                        ->where('type', 'm3')
                                                        ->where('date_start', '<', $dateCurrent)
                                                        ->orderBy('date_start', 'desc')
                                                        ->first();
        $data_result = [
            'kg' => null,
            'm3' => null,
        ];

        if (!is_null($configInternationalShipKg)) {
            $arr_province = json_decode($configInternationalShipKg->list_province, true);
            $arr_provinceDB = $provinceModel->whereIn('id', $arr_province)->get();

            $arr_provinceDB_pluck = \Arr::pluck($arr_provinceDB, 'name', 'id');
            $arr_province_return = [];
            foreach ($arr_province as $key => $province_id) {
                array_push($arr_province_return, $arr_provinceDB_pluck[$province_id]);
            }

            $matrix_price = json_decode($configInternationalShipKg->matrix_price, true);
            $matrix_price_custom = [];
            foreach ($matrix_price as $key => $item) {
                unset($item[1]);
                unset($item[2]);
                array_push($matrix_price_custom, $item);
            }
            $data_result['kg'] = [
                'matrix_price'        => $matrix_price,
                'matrix_price_custom' => $matrix_price_custom,
                'list_province'       => $arr_province_return
            ];
        }
        if (!is_null($configInternationalShipM3)) {
            $arr_province = json_decode($configInternationalShipM3->list_province, true);
            $arr_provinceDB = $provinceModel->whereIn('id', $arr_province)->get();

            $arr_provinceDB_pluck = \Arr::pluck($arr_provinceDB, 'name', 'id');
            $arr_province_return = [];
            foreach ($arr_province as $key => $province_id) {
                array_push($arr_province_return, $arr_provinceDB_pluck[$province_id]);
            }

            $matrix_price = json_decode($configInternationalShipM3->matrix_price, true);
            $matrix_price_custom = [];
            foreach ($matrix_price as $key => $item) {
                unset($item[1]);
                unset($item[2]);
                array_push($matrix_price_custom, $item);
            }

            $data_result['m3'] = [
                'matrix_price'        => $matrix_price,
                'matrix_price_custom' => $matrix_price_custom,
                'list_province'       => $arr_province_return
            ];
        }
        $feeInternationalOfficialM3 = getFeeInternationalOfficialM3();
        if($feeInternationalOfficialM3 == 0) {
            $config = Config::firstOrCreate(
                ['key' =>  ConfigConstant::FEE_INTERNATIONAL_OFFICIAL_M3],
                [
                    'key' =>  ConfigConstant::FEE_INTERNATIONAL_OFFICIAL_M3,
                    'category' => ConfigConstant::CATEGORY_SERVICE,
                    'name' => 'Phí VCQT chính ngạch tối thiểu tính theo m3',
                    'value' => 0
                ]
            );
            $feeInternationalOfficialM3 = data_get($config, 'value') ?? 0;
        }
        $data_result = array_merge($data_result, [
            'min_fee_international' => $feeInternationalOfficialM3
        ]);

        return resSuccessWithinData($data_result);
    }


    public function storeRequest(): array
    {
        return [
            'type'          => 'required|in:kg,m3',
            'date_start'    => 'required|date_format:Y-m-d',
            'matrix_price'  => 'required|array',
            'list_province' => 'required|array',
            'list_province.*' => 'required',
            'official' => 'required|in:1,2'
        ];
    }

    public function storeMessage(): array
    {
        return [];
    }

    public function store(): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__);
        try {
            \DB::beginTransaction();
            $params = request()->all();

            $result = $this->_service->store($params);
            \DB::commit();
            return resSuccessWithinData($result);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }


    public function updateRequest(string $id): array
    {
        return [
            'date_start'    => 'required|date_format:Y-m-d',
            'matrix_price'  => 'required|array',
            'list_province' => 'required|array',
            'list_province.*' => 'required',
            'official' => 'required|in:1,2'
        ];
    }

    public function updateMessage(): array
    {
        return [

        ];
    }

    public function update(string $id): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__, $id);

        $params = request()->only([
            'date_start',
            'matrix_price',
            'list_province',
            'official'
        ]);

        // $userOnline = \Auth::user();
        // $configInterModel = ConfigInternationalShip::query();
        // $configInterDB = $configInterModel->where('id', '=', $id)->first();
        // $arr_matrix_prices = json_decode($configInterDB->matrix_price, true);

        // foreach ($arr_matrix_prices as $key => $arr_matrix_price ) {

        //     foreach($params['matrix_price'] as $k => $item) {
        //         if($key == $k) {
        //             for ($i = 3; $i <= count($arr_matrix_price) ; $i++) {
        //                 if(isset($item[$i]) && $arr_matrix_price[$i] != $item[$i]) {
        //                     $content = 'Cập nhật  phí vận chuyển áp dunng từ ngày ' . Carbon::parse($params['date_start'])->format('d-m-Y') . ' từ ' . $arr_matrix_price[$i] . ' thành ' . $item[$i];
        //                     ActivityService::setConfigLog($userOnline, $content);

        //                 }
        //             }
                     
        //         };
        //     }
        // }

        try {
            \DB::beginTransaction();
            $record = $this->_service->update($id, $params);
            \DB::commit();
            return resSuccessWithinData($record);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__, $id);

            $userOnline = \Auth::user();
            $configInterModel = ConfigInternationalShip::query();
            $configInterDB = $configInterModel->where('id', '=', $id)->first();
        
            // thêm log xóa bảng phí
            $content = 'Xóa phí vận chuyển áp dunng từ ngày ' . Carbon::parse($configInterDB->date_start)->format('d-m-Y');
            ActivityService::setConfigLog($userOnline, $content);

        try {
            \DB::beginTransaction();
            $result = $this->_service->destroy($id);
            \DB::commit();
            return $result;
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function listPriceNoAuth(Request $request): JsonResponse
    {
        $validationRules = [
            'date' => 'nullable|date_format:Y-m-d'
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $date = $request->input('date');
        $date = is_null($date) ? date('Y-m-d') : $date;
        $dataCurent = date('Y-m-d H:i:s', strtotime($date));
        // Chỉ áp dụng cho chính ngạch
        $configInternationalShipKg = ConfigInternationalShip::query()
                                                        ->where('official', ConfigInternationalShip::OFFICIAL_2)                              
                                                        ->where('type', 'kg')
                                                        ->where('date_start', '<=', $dataCurent)
                                                        ->orderBy('date_start', 'desc')
                                                        ->first();
        $configInternationalShipVolume = ConfigInternationalShip::query()
                                                        ->where('official', ConfigInternationalShip::OFFICIAL_2)                              
                                                        ->where('type', 'm3')
                                                        ->where('date_start', '<=', $dataCurent)
                                                        ->orderBy('date_start', 'desc')
                                                        ->first();
        $listConfigKg = [];
        $listConfigVolume = [];
        if($configInternationalShipKg) {
            $matrixPriceKg = json_decode($configInternationalShipKg->matrix_price, true);
            $listConfigKg = array_map(function($item) {
                return [
                    'min' => $item[1],
                    'max' => $item[2],
                    'value' => $item[3]
                ];
            }, $matrixPriceKg);
        }
        if($configInternationalShipVolume) {
            $matrixPriceVolume = json_decode($configInternationalShipVolume->matrix_price, true);
            $listConfigVolume = array_map(function($item) {
                return [
                    'min' => $item[1],
                    'max' => $item[2],
                    'value' => $item[3]
                ];
            }, $matrixPriceVolume);
        }
        return resSuccessWithinData([
            'list_config_kg' => $listConfigKg,
            'list_config_volume' => $listConfigVolume
        ]);
    }

    public function getListWarehouse()
    {
        $warehouseDBs = Warehouse::query()->where('country', 'vi')
                    ->where('code', '!=', WarehouseConstant::CODE_HP)
                    ->where('status', WarehouseConstant::STATUS_ACTIVE)
                    ->get();

        $arr_result = [];
        foreach ($warehouseDBs as $key => $item) {
            $temp = [
                'id' => (int) $item->id,
                'name_v2' => $item->custom_name_v2 ?? '',
                'province' => $item->province->name ?? '',
                'code' => $item->code
            ];
            array_push($arr_result, $temp);
        }

        return resSuccessWithinData([
            'arr_result' => $arr_result,
        ]);
    }
}
