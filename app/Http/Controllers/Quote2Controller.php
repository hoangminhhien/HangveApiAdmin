<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\BagStatusConstant;
use App\Models\OrderPackage;
use App\Models\Order;

use App\Models\LogWebhook;

class Quote2Controller extends Controller
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

        if (is_null($orderDB)) {
            $result = ['error' => 'Đơn hàng không tồn tại'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $data_order = [
            'hangve_order_id'   => (int) $orderDB->id,
            'hangve_order_code' => $orderDB->code,
            'arr_packs'         => [],

        ];

        // Check xem có kiện chưa
        $packModel = \App::make('App\Models\OrderPackage');
        $packDBs = $packModel->where('order_id', (int) $orderDB->id)
                            ->where('order_type', 'App\Models\Order')
                            ->where('status', '<>', PackageConstant::STATUS_CANCEL)
                            ->whereNull('deleted_at')
                            ->whereNotNull('bill_code')
                            ->whereRaw("TRIM(bill_code) <> ''")
                            ->get();

        if (count($packDBs) > 0) {
            $arr_packs = [];
            foreach ($packDBs as $key => $packDB) {
                $temp['hangve_pack_id']        = (int) $packDB->id;
                $temp['hangve_pack_bill_code'] = $packDB->bill_code;
                $temp['hangve_pack_status']    = $packDB->status;

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
            'hangve_order_id' => 'nullable'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();
        $packModel = \App::make('App\Models\OrderPackage');
        $packDB = $packModel->where('bill_code', $params['bill_code'])
                                ->where('status', '<>', PackageConstant::STATUS_RECEIVED_GOODS)
                                ->first();

        if (is_null($packDB)) {
            $result = ['error' => 'Kiện không tồn tại'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Gán
        $temp['hangve_pack_id']        = (int) $packDB->id;
        $temp['hangve_pack_bill_code'] = $packDB->bill_code;
        $temp['hangve_pack_status']    = $packDB->status;

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

        if ($packDB->order_type == 'App\Models\Order') {

            // Nếu có truyền sang hangve_order_id và hangve_order_id > 0 thì check xem kiện
            if (isset($params['hangve_order_id'])) {
                if ($params['hangve_order_id'] > 0) {
                    if ((int) $packDB->order_id !==  (int)$params['hangve_order_id']) {
                       $result = ['error' => 'Kiện với đơn hàng không hợp lệ'];
                       return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                    }
                }
            }

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
            dd('1231');
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
                \Log::info('Tìm thấy trạng thái bên Quote đẩy');
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
                            \Log::info('Không cập nhật quote status');
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
                    OrderPackage::where('bill_code', data_get($item, 'bill_code'))->update(
                        ['official_service_fee' => data_get($item, 'official_service_fee')]
                    );
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
                    Order::where('code', $orderCode)->update([
                        'official_service_fee' => $officialServiceFee,
                        'official_order_cost' => $officialOrderCost
                    ]);
                }
            }
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
           \DB::rollback();
            throw $e;
        }
    }
}
