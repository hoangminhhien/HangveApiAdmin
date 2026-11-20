<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\BagStatusConstant;
use App\Constants\ConsignmentConstant;
use App\Models\OrderPackage;
use App\Models\Order;

use App\Models\LogWebhook;

class QuoteController extends Controller
{
    public function __construct()
    {
    }

    public function writeLog($params)
    {
        $logWebhookModel = \App::make('App\Models\LogWebhook');
        unset($params['token2000a']);
        $data_log = [
            'direction'   => LogWebhook::LOGWEBHOOK_DIRECTION_IN,
            'log_name'    => isset($params['event_name']) ? $params['event_name'] : 'no-name',
            'description' => 'QuoteController@quoteSyncStatusPack',
            'body'        => json_encode($params, JSON_UNESCAPED_UNICODE),
            'from_ip'     => request()->ip()
        ];
        $logWebhookModel->create($data_log);
    }

    public function quoteSeachOrder(Request $request)
    {
        $validationRules = [
            'order_code' => 'required',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();
        $orderModel = \App::make('App\Models\Order');
        $orderDB = $orderModel->where('code', $params['order_code'])
                                ->where('status', '<>', OrderConstant::KEY_STATUS_DONE)
                                ->first();
        $typeOder = 1; // Đơn order

        if (is_null($orderDB)) {
            if (env('APP_URL') != 'https://api-admin.hangve.com') {
                if (env('APP_NAME') != 'HangVe') {
                    $orderModel = \App::make('App\Models\Consignment');
                    $orderDB = $orderModel->where('code', $params['order_code'])
                                        ->where('status', '<>', ConsignmentConstant::KEY_STATUS_DONE)
                                        ->first();
                    $typeOder = 2; // Đơn Consigment
                }
            }
        }

        if (is_null($orderDB)) {
            $result = ['error' => 'Đơn hàng không tồn tại'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $data_order = [
            'hangve_order_id'   => (int) $orderDB->id,
            'hangve_order_code' => $orderDB->code,
            'hangve_order_type' => $typeOder,
            'arr_packs'         => [],

        ];

        // Check xem có kiện chưa
        $packModel = \App::make('App\Models\OrderPackage');
        $packDBs = $packModel->where('order_id', (int) $orderDB->id)
                            // ->where('order_type', 'App\Models\Order')
                            ->where('status', '<>', PackageConstant::STATUS_CANCEL)
                            ->whereNull('deleted_at')
                            ->where('bill_code', '<>', '不需要物流')
                            ->whereNotNull('bill_code')
                            ->whereRaw("TRIM(bill_code) <> ''");
                            // ->get();

        if ($typeOder == 1) {
            $packDBs = $packDBs->where('order_type', 'App\Models\Order');
        }
        if ($typeOder == 2) {
            $packDBs = $packDBs->where('order_type', 'App\Models\Consignment');
        }
        $packDBs = $packDBs->get();

        if (count($packDBs) > 0) {
            $arr_packs = [];
            foreach ($packDBs as $key => $packDB) {
                $temp['hangve_pack_id']        = (int) $packDB->id;
                $temp['hangve_pack_bill_code'] = $packDB->bill_code;
                $temp['hangve_pack_status']    = $packDB->status;
                $temp['hangve_pack_number']    = $packDB->package_number;

                $temp['hangve_is_inspection']  = $packDB->is_inspection;  // Kiếm đếm
                $temp['hangve_is_insurance']   = $packDB->is_insurance;   // Bảo hiểm
                $temp['hangve_is_woodworking'] = $packDB->is_woodworking; // Đóng gỗ
                $temp['hangve_is_shock_proof'] = $packDB->is_shock_proof; // Chống sốc

                array_push($arr_packs, $temp);
            }

            $data_order['arr_packs'] = $arr_packs;
        }

        $data_result = [
            'data_order'  => $data_order
        ];

        return resSuccessWithinData($data_result);
    }

    public function quoteSeachPack(Request $request)
    {
        $validationRules = [
            'bill_code' => 'required',
            'hangve_order_id' => 'nullable',
            'hangve_order_code' => 'nullable',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();
        $packModel = \App::make('App\Models\OrderPackage');
        $packDB = $packModel->where('bill_code', $params['bill_code'])
                                ->where('bill_code', '<>', '不需要物流')
                                ->where('status', '<>', PackageConstant::STATUS_RECEIVED_GOODS);
                                // ->first();

        if (isset($params['hangve_order_id'])) {
            if ($params['hangve_order_id'] > 0) {
                $packDB = $packDB->where('order_id', $params['hangve_order_id']);
            }
        }

        $packDB = $packDB->first();

        if (is_null($packDB)) {
            $result = ['error' => 'Kiện không tồn tại'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Gán
        $temp['hangve_pack_id']        = (int) $packDB->id;
        $temp['hangve_pack_bill_code'] = $packDB->bill_code;
        $temp['hangve_pack_status']    = $packDB->status;
        $temp['hangve_pack_number']    = $packDB->package_number;

        $temp['hangve_is_inspection']  = $packDB->is_inspection;  // Kiếm đếm
        $temp['hangve_is_insurance']   = $packDB->is_insurance;   // Bảo hiểm
        $temp['hangve_is_woodworking'] = $packDB->is_woodworking; // Đóng gỗ
        $temp['hangve_is_shock_proof'] = $packDB->is_shock_proof; // Chống sốc

        $data_pack = [
            'hangve_order_id'   => 0,
            'hangve_order_code' => null,
            'hangve_order_type' => 0,
            'arr_packs'         => [
                $temp
            ],
        ];

        // Check lấy đúng đơn cần tìm chưa
        if (isset($params['hangve_order_id'])) {
            if ($params['hangve_order_id'] > 0) {
                if (
                    (int) $packDB->order_id !==  (int)$params['hangve_order_id'] ||
                    $packDB->order_code != $params['hangve_order_code']
                ) {
                   $result = ['error' => 'Kiện với đơn hàng không hợp lệ'];
                   return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
            }
        }

        if ($packDB->order_type == 'App\Models\Order') {
            // Check xem có đơn Order chưa
            $orderModel = \App::make('App\Models\Order');
            $orderDB = $orderModel->where('id', (int) $packDB->order_id)->first();

            if (!is_null($orderDB)) {
                $data_pack['hangve_order_id']   = (int) $orderDB->id;
                $data_pack['hangve_order_code'] = $orderDB->code;
                $data_pack['hangve_order_type'] = 1;
            }
        }

        if ($packDB->order_type == 'App\Models\Consignment') {
            if (env('APP_URL') != 'https://api-admin.hangve.com') {
                if (env('APP_NAME') != 'HangVe') {
                    // Check xem có đơn Order chưa
                    $orderModel = \App::make('App\Models\Consignment');
                    $orderDB = $orderModel->where('id', (int) $packDB->order_id)->first();

                    if (!is_null($orderDB)) {
                        $data_pack['hangve_order_id']   = (int) $orderDB->id;
                        $data_pack['hangve_order_code'] = $orderDB->code;
                        $data_pack['hangve_order_type'] = 2;
                    }
                }
            }
        }

        $data_result = [
            'data_pack'  => $data_pack
        ];

        return resSuccessWithinData($data_result);
    }

    public function quoteListPack(Request $request)
    {
        $validationRules = [
            'arr_order_ids' => 'required|array',
        ];
        $validationMessages = [

        ];
        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $packModel = \App::make('App\Models\OrderPackage');
        $packDBs = $packModel->whereIn('order_id', $params['arr_order_ids'])
                            ->where('order_type', 'App\Models\Order')
                            ->where('status', '<>', PackageConstant::STATUS_CANCEL)
                            ->whereNull('deleted_at')
                            ->where('bill_code', '<>', '不需要物流')
                            ->whereNotNull('bill_code')
                            ->whereRaw("TRIM(bill_code) <> ''")
                            ->limit(5000)
                            ->offset(0)
                            ->get();

        $data_result = [];
        if (count($packDBs) > 0) {

            $users = collect($packDBs->toArray());
            $grouped = $users->groupBy('order_id');

            foreach ($grouped as $key_1 => $item_1) {
                $temp = [
                    'hangve_order_id' => (int) $key_1,
                    'arr_packs'       => []
                ];

                $arr_packs = [];
                foreach ($item_1 as $key_2 => $packDB) {
                    $item_2['hangve_pack_id']        = (int) $packDB['id'];
                    $item_2['hangve_pack_bill_code'] = $packDB['bill_code'];
                    $item_2['hangve_pack_status']    = $packDB['status'];

                    $item_2['hangve_is_inspection']  = $packDB['is_inspection'];  // Kiếm đếm
                    $item_2['hangve_is_insurance']   = $packDB['is_insurance'];   // Bảo hiểm
                    $item_2['hangve_is_woodworking'] = $packDB['is_woodworking']; // Đóng gỗ
                    $item_2['hangve_is_shock_proof'] = $packDB['is_shock_proof']; // Chống sốc

                    array_push($arr_packs, $item_2);
                }
                $temp['arr_packs'] = $arr_packs;
                array_push($data_result, $temp);
            }
        }
        return resSuccessWithinData($data_result);
    }

    public function quoteSyncPack(Request $request)
    {
        $validationRules = [
            'hangve_pack_bill_code' => 'required'
        ];
        $validationMessages = [

        ];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();
        $this->writeLog($params);

        try {
            $orderPackage = OrderPackage::query()->where('bill_code', $params['hangve_pack_bill_code'])
                ->first();
            if(!empty($orderPackage) && $orderPackage->is_block_package == 1) {
                $data_sync = [
                    'origin_code' => $orderPackage->bill_code,
                    'is_block_package' => 1,
                ];
                $payload = [
                    'event_name' => 'hangve_block_package',
                    'data' => $data_sync
                ];
                $syncPackJob = new \App\Jobs\CN\SyncPackJobCN($payload);
                dispatch($syncPackJob)->onQueue('export-1800');
            }
            return resSuccessWithinData([]);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function quoteSyncStatusPack(Request $request)
    {
        $validationRules = [
            'hangve_pack_bill_code' => 'required',
            'status'                => 'required',
        ];
        $validationMessages = [

        ];
        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();
        // unset($params['token2000a']);
        // \Log::info('quoteSyncStatusPack : ' . request()->ip() . ' - ' . json_encode($params, JSON_UNESCAPED_UNICODE));
        $this->writeLog($params);

        try {
            \DB::beginTransaction();

            $status_new = $this->convertStatusQuoteToStatusHV($params['status']);
            if (!is_null($status_new)) {
                // \Log::info('Tìm thấy trạng thái bên Quote đẩy');
                $packRepo = \App::make('App\Models\OrderPackage');
                $arr_status = [
                    PackageConstant::STATUS_RECEIVED_GOODS,
                    PackageConstant::STATUS_CANCEL,
                ];
                $packDB = $packRepo->where('bill_code', $params['hangve_pack_bill_code'])
                                    ->whereNotIn('status', $arr_status)
                                    ->first();
                if (!is_null($packDB)) {
                    // Chek
                    $status_current = $packDB->status;
                    $statusArr = explode("_", $status_current);
                    $indexStatus = (int) $statusArr[1];
                    if ($indexStatus < 10) {
                        if ($packDB->status != $status_new) {
                            \Log::info('Cập nhật quote status');
                            $packDB->status = $status_new;
                            $packDB->save();
                        } else {
                            // \Log::info('Không cập nhật quote status');
                        }
                    } else {
                        \Log::info('Quá trạng thái Đến kho VN rồi, ko cần cập nhật');
                    }
                }
            }
            \DB::commit();
            return resSuccessWithinData([]);
        } catch (Exception $e) {
           \DB::rollback();
            throw $e;
        }
    }

    public function convertStatusQuoteToStatusHV($status_quote)
    {
        // Chốt
        // CN - HV
        $arr_convert_status = [
            BagStatusConstant::TO_WAREHOUSE    => PackageConstant::STATUS_IN_CHINA,

            BagStatusConstant::IN_TRANSIT_TQ   => PackageConstant::STATUS_ON_THE_WAY_VN,
            BagStatusConstant::CHECK_GOOD_TQ   => PackageConstant::STATUS_ON_THE_WAY_VN,
            BagStatusConstant::CLEARED_TQ      => PackageConstant::STATUS_ON_THE_WAY_VN,
            BagStatusConstant::CHECK_GOOD_VN   => PackageConstant::STATUS_ON_THE_WAY_VN,
            BagStatusConstant::CLEARED_VN      => PackageConstant::STATUS_ON_THE_WAY_VN,
            BagStatusConstant::SHIPPING_VN     => PackageConstant::STATUS_ON_THE_WAY_VN,

            // BagStatusConstant::TO_WAREHOUSE_VN => PackageConstant::STATUS_WAREHOUSE_VN,
            BagStatusConstant::FINISH          => PackageConstant::STATUS_RECEIVED_GOODS,
            BagStatusConstant::CANCEL          => PackageConstant::STATUS_CANCEL,

            BagStatusConstant::PACKING          => PackageConstant::STATUS_IN_CHINA,
        ];

        if (array_key_exists($status_quote, $arr_convert_status)) {
            return $arr_convert_status[$status_quote];
        }
        return null;
    }

    public function syncOfficialServiceFee(Request $request)
    {
        $validationRules = [
            'data' => 'required|array',
            'data.*.bill_code' => 'required',
            'data.*.official_service_fee' => 'required',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        try {
            \DB::beginTransaction();
            $params = $request->all();
            $data = data_get($params, 'data');
            foreach($data as $item) {
                if(data_get($item, 'bill_code')) {
                    $orderPackage = OrderPackage::where('bill_code', data_get($item, 'bill_code'))->first();
                    if($orderPackage) {
                        $orderPackage->official_service_fee = data_get($item, 'official_service_fee');
                        // $orderPackage->special_consumption_tax = data_get($item, 'tax_ttdb');
                        // $orderPackage->tax_xnk = data_get($item, 'tax_xnk');
                        // $orderPackage->tax_vat = data_get($item, 'tax_vat');
                        // $orderPackage->environmental_tax = data_get($item, 'tax_nature');
                        // $orderPackage->safeguard_tax = data_get($item, 'tax_protect');
                        // $orderPackage->surcharge = data_get($item, 'surcharge');
                        $orderPackage->save();
                    }
                }
            }
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
           \DB::rollback();
            throw $e;
        }
    }

    public function rsOfficialServiceFee(Request $request)
    {
        $validationRules = [
            'bill_code' => 'required',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        try {
            \DB::beginTransaction();
            $params = $request->all();
            $billCode = data_get($params, 'bill_code');
            OrderPackage::where('bill_code', $billCode)->update(['official_service_fee' => 0]);
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
           \DB::rollback();
            throw $e;
        }
    }

    public function syncOfficialServiceFeeOrder(Request $request)
    {
        \Log::info($request->all());
        $validationRules = [
            'data' => 'required|array',
            // 'data.*.hangve_order_code' => 'required',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        try {
            \DB::beginTransaction();
            $params = $request->all();
            $data = data_get($params, 'data');
            foreach($data as $item) {
                $orderCode = data_get($item, 'hangve_order_code');
                $officialServiceFee = data_get($item, 'official_service_fee') ?? 0;
                $officialOrderCost = data_get($item, 'official_order_cost') ?? 0;
                if($orderCode) {
                    $order = Order::where('code', $orderCode)->first();
                    if($order) {
                        $order->official_service_fee = $officialServiceFee;
                        $order->official_order_cost = $officialOrderCost;
                        $order->save();
                    }
                }
            }
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
           \DB::rollback();
            throw $e;
        }
    }

    public function syncUnitPriceInternational(Request $request)
    {
        $validationRules = [
            'data' => 'required|array',
            'data.*.bill_code' => 'required',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        try {
            $accountingService = \App::make('App\Services\AccountingService');
            $packController    = \App::make('App\Http\Controllers\PackageController');
            \DB::beginTransaction();
            $params = $request->all();
            $data = data_get($params, 'data');
            foreach($data as $item) {
                if(data_get($item, 'bill_code')) {
                    $orderPackage = OrderPackage::where('bill_code', data_get($item, 'bill_code'))->where('status', '!=', PackageConstant::STATUS_RECEIVED_GOODS)->first();
                    if($orderPackage) {
                        $isWeight = false;
                        $orderPackage->unit_price_international_kg = data_get($item, 'unit_price_international_kg');
                        $orderPackage->unit_price_international_m3 = data_get($item, 'unit_price_international_m3');
                        $orderPackage->save();
                        $international_shipping_cost = $accountingService->getInternationShippingCost(
                            getProvinceX(optional($orderPackage->warehouse)->province_id),
                            $orderPackage->weight,
                            $orderPackage->volume,
                            $orderPackage->customer_id,
                            $isWeight,
                            $orderPackage->id
                        );
                        $orderPackage->type_quantitative = $isWeight ? 'weight' : 'volume';
                        $unitPrice = 0;
                        if ($orderPackage->type_quantitative == 'weight') {
                            if ($orderPackage->weight > 0) {
                                $unitPrice = $orderPackage->weight == 0 ? 0 : ($international_shipping_cost / $orderPackage->weight);
                            }
                        } else {
                            $unitPrice = $orderPackage->volume == 0 ? 0 : ($international_shipping_cost / $orderPackage->volume);
                        }
                        $orderPackage->save();
                        if($orderPackage->weight > 0 || $orderPackage->volume > 0) {
                            $packController->updateOrderAndPackage($orderPackage, ['unit_price' => $unitPrice], $international_shipping_cost);
                        }

                    }
                }
            }
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
           \DB::rollback();
            throw $e;
        }
    }

    // Bên Báo giá nhập kiện của công ty HV thì đẩy vào đây
    public function quoteSyncPackHV(Request $request)
    {
        $validationRules = [
            'hangve_pack_bill_code' => 'required',
        ];
        $validationMessages = [

        ];
        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();
        $this->writeLog($params);

        try {
            \DB::beginTransaction();
            \Log::info('quoteSyncPackHV go: ' . $params['hangve_pack_bill_code']);
            \DB::commit();
            return resSuccessWithinData([]);
        } catch (Exception $e) {
           \DB::rollback();
            throw $e;
        }
    }
}
