<?php

namespace App\Http\Controllers;

use App\Http\Requests\Config\UpdateConfigShippingEstimatedFeeRequest;
use App\Http\Requests\Config\UpdateReceiversRequest;
use App\Jobs\Order\UpdateOrderDiscountCostJob;
use App\Models\Holiday;
use App\Services\ConfigService;
use App\Models\ConfigShippingTime;
use App\Models\ConfigShippingRange;
use App\Models\ConfigShippingValue;
use App\Models\Province;
use App\Services\FcmTokenService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use App\Constants\ConfigConstant;
use App\Models\Config;
use App\Jobs\Order\UpdateOrderByCustomerJob;
use App\Jobs\UpdateTotalPriceWhenChangeExchangeRate;
use App\Models\ConfigLog;
use App\Models\ConfigInternationalShip;
use App\Constants\OrderConstant;
use App\Constants\ActivityConstant;

class ConfigController extends Controller
{
    public function __construct(ConfigService $service)
    {
        $this->_service = $service;
    }
    // sửa phí

    /**
     * @throws BindingResolutionException
     */
    public function updateValue($key, Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $record = App::make('App\Models\Config')->where('key', '=', $key)->first();
            $params = $request->all();
            $userOnline = \Auth::user();
            if($key == ConfigConstant::CURRENCY_EXCHANGE_RATE) {
                $checkEdit = $userOnline->checkUserHasPer('setting.edit-config-exchange-rate');
                if (!$checkEdit) {
                    $result = ['error' => 'Bạn không có quyền chỉnh sửa sửa tỉ giá'];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
                if (isset($params['value']) && $params['value'] != $record->value) {
                    $fcmTokenService = app()->make(FcmTokenService::class);
                    $title = 'Hệ thống cập nhật tỷ giá';
                    $body = 'Hệ thống đã cập nhật tỷ giá mới từ ' . $params['value'] . 'đ thành ' . $record->value . 'đ';
                    $dataSent = [
                        'type' => 'configs',
                        'body' => 'Thay đổi tỷ giá'
                    ];
                    Log::info($body);
                    $fcmTokenService->sendMessageForApp([], $title, $body, $dataSent);
                    $this->saveLog([
                        'type' => ConfigConstant::CURRENCY_EXCHANGE_RATE,
                        'value_old' => $record->value,
                        'value_new' => $params['value']
                    ]);
                }

                $this->_service->sendNotiForCustomerWhenChangeExchangeRate($params['value'],  $record->value);
                dispatch(new UpdateTotalPriceWhenChangeExchangeRate($params['value']));

            }
            if($key == ConfigConstant::CUSTOMER_LEVEL) {
                $levelCurrents = json_decode($record->value, true);
                $levelNew = $params['value'];
                if($levelNew == $levelCurrents) {
                    return resSuccess();
                }
                $dataCurrent = [];
                foreach ($levelCurrents as $k => $level) {
                    if($k != 0) {
                        $dataCurrent[$level['level']] = $level['result']['order'];
                    }
                }
                $dataNew = [];
                foreach ($levelNew as $k => $level) {
                    if($k != 0) {
                        $dataNew[$level['level']] = $level['result']['order'];
                    }
                }
                $dataChange = [];
                foreach ($dataNew as $k1 => $value) {
                    foreach ($dataCurrent as $k2 => $current) {
                        if($k1 == $k2 && $value != $current) {
                            $dataChange[$k1] = $value;
                        }
                    }
                }
                foreach ($dataChange as $key => $value) {
                    dispatch(new UpdateOrderDiscountCostJob($key, $value))->onQueue('export-1800');
                }
            }
            if($key == ConfigConstant::CURRENCY_CONSIGNMENT_RATE) {
                if (!$record) {
                    $record = Config::query()->create([
                        'category' => 'consignment_rate',
                        'name' => 'Đặt cọc đơn ký gửi',
                        'key' => 'consignment_rate',
                        'value' => 0,
                    ]);
                }
                if (isset($params['value']) && $params['value'] != $record->value) {
                    $this->saveLog([
                        'type' => ConfigConstant::CURRENCY_CONSIGNMENT_RATE,
                        'value_old' => $record->value,
                        'value_new' => $params['value']
                    ]);
                }
            }
            $record->value = $params['value'];
            $record->save();
            if($key == ConfigConstant::SERVICE_ORDER_FEE) {
                dispatch(new UpdateOrderByCustomerJob())->onQueue('export-1800');
            }

            DB::commit();
            return resSuccess();
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }


    }

    // thêm phí vc logic mới

    public function createShippingtime(Request $request): JsonResponse
    {
        try {
            $userOnline = \Auth::user();
            $data = $request->only('type', 'date_start', 'date_end', 'configPSs', 'dislpay_order');

            $data['type'] = $data['type'];
            $data['date_start'] = $data['date_start'];

            $data_config_time = [
                'organization_id' => $userOnline->organization_id,
                'name' =>   $data['type'] == 1 ? 'Kg' : 'M3',
                'type' =>  $data['type'],
                'date_start' => $data['date_start'],
            ];
            $configTimeNew = ConfigShippingTime::query()->create($data_config_time);

            // thêm log tạo mới khung
            $logService = \App::make('App\Services\ActivityService');
            $content = 'Thêm khung giá mới áp dụng từ ngày ' . $configTimeNew->date_start;
            $logService->setConfigLog($userOnline, $content);


            $configPSs = $data['configPSs'];

            $arrayConfigRanger = [];
            foreach ($configPSs as $key => $configPS) {
                $arrayConfigRanger[] = [
                    'id' => Str::uuid(),
                    'organization_id' => $userOnline->organization_id,
                    'config_shipping_time_id' =>  $configTimeNew->id,
                    'value' => json_encode($configPS),
                    'dislpay_order' => $configPS['dislpay_order'],
                    'created_at'   => date('Y-m-d H:i:s')
                ];

            }

            $configRangeNew = ConfigShippingRange::query()->insert($arrayConfigRanger);



            return resSuccess('Thêm thành công');
        } catch (\Exception $e) {

            return resError($e->getMessage());
        }
    }

    public function getDataShipping(Request $request)
    {


        $configShippingTimeModel = ConfigShippingTime::query();

        $provinceModel = Province::query();

        // $rangerModel = \App::make('App\Models\ConfigShippingRange');
        // $valueModel = \App::make('App\Models\ConfigShippingValue');

        $rangerModel = ConfigShippingRange::query();
        $valueModel = ConfigShippingValue::query();


        $data_result = []; // kg

        $data_result_volume = []; // M3

        $now = Carbon::now();

        $configPSs = $configShippingTimeModel->where('type', '=', '1')->where('date_start', '<', $now)
                                                                    ->orderBy('date_start', 'DESC')
                                                                    ->first();

        if(!is_null($configPSs)) {
            $rangerFull = $rangerModel->where('config_shipping_time_id', $configPSs->id)->get();

            $valueModelFull = $valueModel->where('config_shipping_time_id', $configPSs->id)->get();

            $collection = collect($valueModelFull->toArray());

            $grouped = $collection->groupBy('province_name');

            $grouped->all();

            $data_result = [];

            $arr_1 = [];
            $text = "Khối lượng (tính/m3)";
            if ($configPSs->type == 1) {
                $text = "Trọng lượng (tính/Kg)";
            }
            array_push($arr_1, $text);

            foreach ($rangerFull as $key => $ranger) {
                $valueDecode = json_decode($ranger['value']);
                array_push($arr_1, $valueDecode->name);
            }

            array_push($data_result, $arr_1);

            foreach ($grouped as $name => $group) {
                $arr_temp = [];
                array_push($arr_temp, $name);

                foreach ($group as $key_1 => $item) {
                    // array_push($arr_temp, $item['value']);
                    // array_push($arr_temp, (number_format($item['value'], 0, ',', '.') . ' đ'));

                    if($item['value'] == '') {
                        array_push($arr_temp, $item['value']);
                    } else {
                        array_push($arr_temp, (number_format($item['value'], 0, ',', '.') . ' đ'));
                    }
                }

                array_push($data_result, $arr_temp);
            }
        }

        // cân nặng

        $timeModel = \App::make('App\Models\ConfigShippingTime');
        $rangerModelV = \App::make('App\Models\ConfigShippingRange');
        $valueModelV = \App::make('App\Models\ConfigShippingValue');

        $configPSVolumn = $timeModel->where('type', '=', '0')->where('date_start', '<', $now)->orderBy('date_start', 'DESC')->first();

        if(!is_null($configPSVolumn)) {

            $rangerFullVolume = $rangerModelV->where('config_shipping_time_id', $configPSVolumn->id)->get();

            $valueModelFullVolume = $valueModelV->where('config_shipping_time_id', $configPSVolumn->id)->get();

            $collectionVolume = collect($valueModelFullVolume->toArray());


            $groupedVolume = $collectionVolume->groupBy('province_name');

            $groupedVolume->all();


            $data_result_volume = [];

            $arr_V = [];
            $textV = "Khối lượng (tính/m3)";
            if ($configPSVolumn->type == 0) {
                $textV = "Khối lượng (tính/M3)";
            }
            array_push($arr_V, $textV);

            foreach ($rangerFullVolume as $key => $ranger) {
                $valueDecode = json_decode($ranger['value']);
                array_push($arr_V, $valueDecode->name);
            }

            array_push($data_result_volume, $arr_V);

            foreach ($groupedVolume as $name => $group) {
                $arr_temp_v = [];
                array_push($arr_temp_v, $name);

                foreach ($group as $key_1 => $item) {
                    // array_push($arr_temp_v, $item['value']);
                    if($item['value'] == '') {
                        array_push($arr_temp_v, $item['value']);
                    } else {
                        array_push($arr_temp_v, (number_format($item['value'], 0, ',', '.') . ' đ'));
                    }
                    // array_push($arr_temp_v, (number_format($item['value'], 0, ',', '.') . 'đ'));

                }

                array_push($data_result_volume, $arr_temp_v);
            }

        }

        return resSuccessWithinData([
            'data_result' => $data_result,
            'data_result_volume' => $data_result_volume,
        ]);

    }

    public function getListTime(Request $request)
    {
        $select_v2 = [
            'config_shipping_times.id',
            'config_shipping_times.type',
            'config_shipping_times.date_start',
        ];

        $configTimes = ConfigShippingTime::query()->select($select_v2)->orderBy('created_at', 'DESC')->get();
        $arrayDateStart = [];

        foreach ($configTimes as $key => $item) {
            $arrayDateStart[] = [
                'id' => $item->id,
                'type' => $item->type,
                // 'date_start' => $item->date_start,
                'date_start' => Carbon::parse($item->date_start)->format('d-m-Y'),

            ];
        }


        $data_result = [
            'arr_DateStart' => $arrayDateStart,
        ];

        return resSuccessWithinData($data_result);
    }

    public function detailConfigShipping(Request $request, $id)
    {
        $configShippingTime = ConfigShippingTime::query();
        $configShippingRange = ConfigShippingRange::query();
        $detailTime = $configShippingTime->where('id', '=', $id)->first();
        $detailRange = $configShippingRange->where('config_shipping_time_id', '=', $id)->get();

        $arrRange = [];
        foreach ($detailRange as $key => $item) {
            $arrRange[] = [
                'id' => $item->id,
                'value' => json_decode($item->value, true)
            ];
        }

        $data_result = [
            'id' => $detailTime->id,
            'type' => (int)$detailTime->type,
            'date_start' => $detailTime->date_start,
            'dataValue' => $arrRange
        ];

        return resSuccessWithinData($data_result);

    }

    public function updateConfigShipping(Request $request)
    {
        try {
            $userOnline = \Auth::user();

            $data = $request->only('province_id', 'data_values', 'type', 'date_start');

            $data['province_id'] = $data['province_id'];
            $data['type'] = $data['type'];
            $data['date_start'] = $data['date_start'];

            $dataValues = $data['data_values'];
            $provinModel = \App::make('App\Models\Province');
            $provinceDB = $provinModel->where('id', $data['province_id'])->first();

            $rangerModel = \App::make('App\Models\ConfigShippingRange');

            $valueModel = \App::make('App\Models\ConfigShippingValue');




            $arrayDataValues = [];
            foreach ($dataValues as $key => $dataValue) {
                $valueDB = $valueModel->where('config_shipping_range_id', $dataValue['id'])
                                        ->where('province_id', $data['province_id'])
                                        ->first();
                if(is_null($valueDB)) {
                    $rangerDB = $rangerModel->where('id', $dataValue['id'])->first();
                    $arrayDataValues[] = [
                        'id' => Str::uuid(),
                        'province_id' =>  $data['province_id'],
                        'province_name' =>  $provinceDB->name,
                        'type' =>  $data['type'],
                        'date_start' =>  $data['date_start'],
                        'organization_id' => $userOnline->organization_id,
                        'config_shipping_time_id' => $rangerDB->config_shipping_time_id,
                        'config_shipping_range_id' => $dataValue['id'],
                        'value_range' => json_encode($dataValue['value']),
                        'value' => $dataValue['value']['value_v3'],
                        'created_at'   => date('Y-m-d H:i:s')
                    ];
                }

            }

            $configValueNew = ConfigShippingValue::query()->insert($arrayDataValues);


            // thêm log tạo mới khung
            $logService = \App::make('App\Services\ActivityService');
            $content = 'Thêm phí vận chuyển cho tỉnh/thành phố ' . $provinceDB->name . ' áp dụng từ ngày ' . Carbon::parse($data['date_start'])->format('d-m-Y');
            $logService->setConfigLog($userOnline, $content);

            return resSuccess('Thêm thành công');
        } catch (\Exception $e) {

            return resError($e->getMessage());
        }
    }

    // lấy khung giá trị
    public function getListFrame(Request $request, $id)
    {
        $valueModel = \App::make('App\Models\ConfigShippingValue');

        $timeModel = \App::make('App\Models\ConfigShippingTime');

        $rangeModel = \App::make('App\Models\ConfigShippingRange');


        $rangeDBs = $rangeModel->where('config_shipping_time_id', '=', $id)
                                ->get();

        $arrValue = [];

        foreach ($rangeDBs as $key => $item) {
            $arrValue[] = [
                'id' => $item->id,
                'value' => json_decode($item->value, true)
            ];
        }

        $data_start_value = $timeModel->where('id', '=', $id)->first();

        $data_result = [
            'arrValue' => $arrValue,
            'date_start' => $data_start_value->date_start,
            'type' =>  (int)$data_start_value->type,

        ];

        return resSuccessWithinData($data_result);
    }

    public function updateFrame(Request $request)
    {
        try {
            $timeConfigModel = ConfigShippingTime::query();
            $valueConfigModel = ConfigShippingValue::query();
            $userOnline = \Auth::user();


            $data = $request->only('data_values', 'id_time', 'date_start');

            $dataValues = $data['data_values'];
            $idTime = $data['id_time'];
            $data['date_start'] = $data['date_start'];

            // thêm log cập nhật ngày áp dụng
            $logTimeDB = ConfigShippingTime::query()->where('id', '=', $idTime)->first();
            $logService = \App::make('App\Services\ActivityService');
            $content = 'Cập nhật ngày áp dụng ' . Carbon::parse($logTimeDB->date_start)->format('d-m-Y') . ' thành ' . Carbon::parse($data['date_start'])->format('d-m-Y');
            if($logTimeDB->date_start != $data['date_start']) {
                $logService->setConfigLog($userOnline, $content);
            };


            $valueDBs = ConfigShippingValue::query()->where('config_shipping_time_id', '=', $data['id_time'])
                                                        ->groupBy('province_id')
                                                        ->get();

            $timeConfigDB = $timeConfigModel->where('id', $data['id_time'])
                                  ->update(['date_start'=> $data['date_start']]);

            $valueConfigDB = $valueConfigModel->where('config_shipping_time_id', $data['id_time'])
                                  ->update(['date_start'=> $data['date_start']]);


            foreach ($dataValues as $key => $dataValue) {

                $userOnline = \Auth::user();

                if(is_null($dataValue['id'])) {
                    $arr_Ranger = [
                        'id' => Str::uuid(),
                        'organization_id' => $userOnline->organization_id,
                        'config_shipping_time_id' => $data['id_time'],
                        'value' => json_encode($dataValue['value']),
                        'dislpay_order' => $dataValue['value']['dislpay_order'],
                        'created_at'   => date('Y-m-d H:i:s')
                    ];
                    $configRangeNew = ConfigShippingRange::query()->create($arr_Ranger);

                    foreach ($valueDBs as $key => $valueDB) {
                        $dataTempValue = [
                            'id' => Str::uuid(),
                            'organization_id' => $userOnline->organization_id,
                            'config_shipping_time_id' => $data['id_time'],
                            'config_shipping_range_id' => $configRangeNew->id,
                            'province_id' => $valueDB->province_id,
                            'province_name' => $valueDB->province_name,
                            'value' => '',
                            'value_range' => $configRangeNew->value,
                            'date_start' => $data['date_start'],
                        ];

                        $configValueNew = ConfigShippingValue::query()->create($dataTempValue);
                    }

                } else {

                    $valueModel = ConfigShippingValue::query();

                    $rangeModel = ConfigShippingRange::query();


                    $valueDB = $valueModel->where('config_shipping_range_id', $dataValue['id'])
                                    ->update(['value_range'=> $dataValue['value']]);

                    $rangeDB = $rangeModel->where('id', $dataValue['id'])
                                    ->update(['value'=> $dataValue['value']]);

                }


            }

            return resSuccess('Sửa thành công');
        } catch (\Exception $e) {

            return resError($e->getMessage());
        }
    }

    public function getProvinValue(Request $request)
    {
        $data = $request->only('province_id', 'config_shipping_time_id');

        $timeModel = \App::make('App\Models\ConfigShippingTime');

        $valueModel = \App::make('App\Models\ConfigShippingValue');

        $valueDBs = $valueModel->where('config_shipping_time_id', '=', $data['config_shipping_time_id'])
                                ->where('province_id', '=', $data['province_id'])
                                ->get();

        $arrValue = [];
        foreach ($valueDBs as $key => $item) {
            $arrValue[] = [
                'id' => $item->id,
                'value' => json_decode($item->value_range, true),
                'value_shipping' => $item->value
            ];
        }

        $data_start_value = $timeModel->where('id', '=', $data['config_shipping_time_id'])->first();

        $data_result = [
            'arrValue' => $arrValue,
            'date_start' => $data_start_value->date_start,
            'type' =>  (int)$data_start_value->type,

        ];

        return resSuccessWithinData($data_result);

    }

    public function editProvinValue(Request $request)
    {
        try {

            $data = $request->only('data_values');

            $dataValues = $data['data_values'];

            foreach ($dataValues as $key => $dataValue) {

                $valueModel = ConfigShippingValue::query();


                // thêm log sửa phí theo tỉnh

                $provinceModel = \App::make('App\Models\Province');

                $valueDBLog = $valueModel->where('id', $dataValue['id'])->first();

                $nameContentLog = json_decode($valueDBLog->value_range)->name;

                $provinceDBLog = $provinceModel->where('id', '=', $valueDBLog->province_id)->first();

                $userOnline = \Auth::user();
                $logService = \App::make('App\Services\ActivityService');

                // $content = 'Cập nhật phí vận chuyển ' . $nameContentLog.  ' tình/ thành phố  ' . $provinceDBLog->name . ' từ ' . $valueDBLog->value . ' thành ' . $dataValue['value_shipping'];

                if($valueDBLog->value != '') {
                    $content = 'Cập nhật phí vận chuyển ' . $nameContentLog.  ' ở  ' . $provinceDBLog->name . ' từ ' . number_format($valueDBLog->value, 0, ',', '.') . ' đ' . ' thành ' . number_format($dataValue['value_shipping'], 0, ',', '.') . ' đ' . ' từ ngày ' .  Carbon::parse($valueDBLog->date_start)->format('d-m-Y');
                } else {
                    $content = 'Cập nhật phí vận chuyển ' . $nameContentLog.  ' ở  ' . $provinceDBLog->name . ' từ ' . $valueDBLog->value . ' thành ' . $dataValue['value_shipping'] . ' từ ngày ' . Carbon::parse($valueDBLog->date_start)->format('d-m-Y');
                }

                if($valueDBLog->value != $dataValue['value_shipping']) {
                    $logService->setConfigLog($userOnline, $content);
                }


                // xử lí

                $valueDB = $valueModel->where('id', $dataValue['id'])
                                    ->update(['value'=> $dataValue['value_shipping']]);

                $valueDB = $valueModel->where('id', $dataValue['id'])
                                    ->update(['value_range'=> $dataValue['value']]);
            }

            return resSuccess('Sửa thành công');
        } catch (\Exception $e) {

            return resError($e->getMessage());
        }
    }

    public function deleteShippingValue(Request $request, $id)
    {
        $userOnline = \Auth::user();

        $timeModel = ConfigShippingTime::query();

        $rangeModel = ConfigShippingRange::query();

        $valueModel = ConfigShippingValue::query();

        $timeDB = $timeModel->where('id', '=', $id)->first();

        $rangeDBs = $rangeModel->where('config_shipping_time_id', '=', $id)->get();

        $valueDBs = $valueModel->where('config_shipping_time_id', '=', $id)->get();

        // thêm log xóa
        $logService = \App::make('App\Services\ActivityService');
        $content = 'Xóa bảng phí áp dựng từ ngày ' . Carbon::parse($timeDB->date_start)->format('d-m-Y');
        $logService->setConfigLog($userOnline, $content);


        try {
            \DB::beginTransaction();

            foreach($rangeDBs as $key => $rangeDB) {
                $rangeDB->delete();
            }

            foreach($valueDBs as $key => $valueDB) {
                $valueDB->delete();
            }

            $timeDB->delete();

            \DB::commit();
            return resSuccessWithinData('Thành công');
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function listProvince(Request $request)
    {
        $provinceModel = \App::make('App\Models\Province');
        $provinceVN = $provinceModel->where('country', 'vi')->orderBy('name', 'asc')->get();
        $province_arr = \Arr::pluck($provinceVN, 'name', 'id');

        $arr_sort = [
            'Hà Nội',
            'Hồ Chí Minh',
            'Đà Nẵng',
            'Hải Phòng'
        ];

        // Chế cháo
        $arr_provin_priority = [];
        foreach ($arr_sort as $key => $province_name ) {
            $province_id = array_search($province_name, $province_arr);
            $arr_provin_priority[$province_id] = $province_name;
            unset($province_arr[$province_id]);
        }
        // $result = array_merge($arr_provin_priority, $province_arr);

        // Trả về
        $data_result = [];
        foreach ($arr_provin_priority as $key => $item) {
            $temp = [
                'id'   => $key,
                'name' => $item,
            ];
            array_push($data_result, $temp);
        }
        foreach ($province_arr as $key => $item) {
            $temp = [
                'id'   => $key,
                'name' => $item,
            ];
            array_push($data_result, $temp);
        }

        return resSuccessWithinData($data_result);
    }

    /**
     * Lấy danh sách địa chỉ nhận hàng
     *
     * @return JsonResponse
     */
    public function listReceivers(): JsonResponse
    {
        return $this->_service->listReceivers();
    }

    /**
     * Cập nhật địa chỉ nhận hàng
     *
     * @return JsonResponse
     */
    public function updateReceivers(UpdateReceiversRequest $request): JsonResponse
    {
        return $this->_service->updateReceivers($request->validated());
    }

    public function reasonCancelOrder()
    {
        try {
            $reason = Config::where('key', ConfigConstant::REASON_CANCEL_ORDER)->first();
            return resSuccessWithinData(json_decode($reason && $reason->value ? $reason->value : ""));
        } catch (\Throwable $th) {
            \Log::error($th);
            return resError();
        }
    }

    public function updateReasonlOrder(Request $request)
    {
        $value = data_get($request->all(), 'value');
        try {
            Config::where('key', ConfigConstant::REASON_CANCEL_ORDER)->update(['value' => json_encode($value)]);
            return resSuccess();
        } catch (\Throwable $th) {
            \Log::error($th);
            return resError();
        }
    }

    /**
     * Lấy danh sách hoặc một bản ghi config phí vận chuyển tạm tính
     *
     * @return JsonResponse
     */
    public function getConfigShippingEstimatedFee(): JsonResponse
    {
        return $this->_service->getConfigShippingEstimatedFee();
    }

    /**
     * Chỉnh sửa config phí vận chuyển tạm tính
     *
     * @param UpdateConfigShippingEstimatedFeeRequest $request
     * @return JsonResponse
     */
    public function updateConfigShippingEstimatedFee(UpdateConfigShippingEstimatedFeeRequest $request): JsonResponse
    {
        return $this->_service->updateConfigShippingEstimatedFee($request->validated());
    }

    /**
     * Lấy danh sách đơn vị (đo lường và đếm)
     *
     * @return JsonResponse
     */
    public function getConfigUnits(): JsonResponse
    {
        return $this->_service->getConfigUnits();
    }

    public function noteOrder()
    {
        try {
            $note = Config::where('key', ConfigConstant::NOTE_ORDER)->first();
            return resSuccessWithinData(json_decode($note && $note->value ? $note->value : ""));
        } catch (\Throwable $th) {
            \Log::error($th);
            return resError();
        }
    }

    public function reasonOrderRefund()
    {
        try {
            $reason = Config::where('key', ConfigConstant::REASON_ORDER_REFUND)->first();
            return resSuccessWithinData(json_decode($reason && $reason->value ? $reason->value : ""));
        } catch (\Throwable $th) {
            \Log::error($th);
            return resError();
        }
    }

    public function updateNoteOrder(Request $request)
    {
        $value = data_get($request->all(), 'value');
        try {
            Config::updateOrCreate(
                ['key' => ConfigConstant::NOTE_ORDER],
                ['value' => json_encode($value)]
            );
            return resSuccess();
        } catch (\Throwable $th) {
            \Log::error($th);
            return resError();
        }
    }

    public function updateReasonOrderRefund(Request $request)
    {
        $value = data_get($request->all(), 'value');
        try {
            Config::updateOrCreate(
                ['key' => ConfigConstant::REASON_ORDER_REFUND],
                ['value' => json_encode($value)]
            );
            return resSuccess();
        } catch (\Throwable $th) {
            \Log::error($th);
            return resError();
        }
    }

    public function saveLog(array $data)
    {
        $data['user_id'] = getCurrentUserId();
        ConfigLog::create($data);
    }

    public function configLog($key)
    {
        $data = ConfigLog::where('type', $key)->with(['user'])->orderBy('created_at', 'desc')->paginate();
        $_paginateResource = \App\Http\Resources\PaginateJsonResource::class;
        $_resource = \App\Http\Resources\Config\ListConfigLogResource::class;
        return resSuccessWithinData(new $_paginateResource($data, $_resource));
    }

    public function createHoliday(Request $request)
    {
        $data = $request->only('date');
        if(!$data['date']){
            return resError('Vui lòng chọn ngày nghỉ lễ', 422);
        }
        if(Holiday::query()->where('date', $data['date'])->exists()){
            return resError('Ngày nghỉ lễ đã tồn tại', 422);
        }
        Holiday::query()->create($data);
        return resSuccess();
    }

    public function holiday(Request $request)
    {
        $year = Carbon::now()->year;
        $data = Holiday::whereYear('date', $year)->orderBy('created_at', 'desc')->paginate();
        $_paginateResource = \App\Http\Resources\PaginateJsonResource::class;
        $_resource = \App\Http\Resources\Config\ListHolidayResource::class;
        return resSuccessWithinData(new $_paginateResource($data, $_resource));
    }

    public function destroyHoliday(Request $request, $id)
    {
        if(Holiday::query()->find($id)) {
            Holiday::destroy($id);
            return resSuccess();
        }

        return resError();
    }

    public function configAutoCancelOrder()
    {
        $data = [
            [
                'name' => 'Chờ đặt cọc',
                'value' => OrderConstant::KEY_STATUS_WAITING_DEPOSIT,
                'day' => ''
            ],
            [
                'name' => 'Cần xác nhận lại',
                'value' => OrderConstant::KEY_STATUS_RECONFIRM,
                'day' => ''
            ]
            ];
        $config = Config::firstOrCreate(
            ['key' =>  ConfigConstant::AUTO_CANCEL_ORDER],
            [
                'key' =>  ConfigConstant::AUTO_CANCEL_ORDER,
                'value' => json_encode($data)
            ]
        );
        return resSuccessWithinData(json_decode($config && $config->value ? $config->value : ""));
    }

    public function updateConfigAutoCancelOrder(Request $request)
    {
        $validationRules = [
            'data' => 'required|array',
            'data.*.name' => 'required',
            'data.*.value' => "required|in:status_1,status_5",
            'data.*.day' => 'nullable|numeric|min:1',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();
        $data = $params['data'];
        $config = Config::updateOrCreate(
            ['key' =>  ConfigConstant::AUTO_CANCEL_ORDER],
            [
                'key' =>  ConfigConstant::AUTO_CANCEL_ORDER,
                'value' => json_encode($data)
            ]
        );
        return resSuccess();
    }

    public function updateFeeInternationalOfficalM3(Request $request)
    {
        $validationRules = [
            'value' => 'required|integer|min:0',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $value = $request->input('value');
        try {
            \DB::beginTransaction();
            $config = Config::where('key', ConfigConstant::FEE_INTERNATIONAL_OFFICIAL_M3)->first();
            if($config && $config->value != $value) {
                $logService = \App::make('App\Services\ActivityService');
                $content = 'đã thay đổi phí VCQT tối thiểu từ ' .number_format($config->value,0,".",","). ' thành '.number_format($value,0,".",",");
                $typeLog = ActivityConstant::CONFIG_LOG_OFFICIAL;
                $logService->setConfigLog(getCurrentUser(), $content, $typeLog);
                $this->saveLog([
                    'type' => ConfigConstant::FEE_INTERNATIONAL_OFFICIAL_M3,
                    'value_old' => $config->value,
                    'value_new' => $value
                ]);
                $config->update(['value' => $value]);
            }
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }
}
