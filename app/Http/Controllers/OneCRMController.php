<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\BagStatusConstant;
use App\Models\OrderPackage;
use App\Models\Order;

use App\Models\LogWebhook;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class OneCRMController extends Controller
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
            'description' => 'OneCRMController@cnSubscribe',
            'body'        => json_encode($params, JSON_UNESCAPED_UNICODE),
            'from_ip'     => request()->ip()
        ];
        if ($data_log['log_name'] == 'cn_get_hangve_bag') {
            // \Log::info('Bỏ qua ghi log cn_get_hangve_bag');
            return;
        }
        if ($data_log['log_name'] == 'cn_get_hangve_note') {
            // \Log::info('Bỏ qua ghi log cn_get_hangve_note');
            return;
        }
        if ($data_log['log_name'] == 'cn_get_hangve_service') {
            // \Log::info('Bỏ qua ghi log cn_get_hangve_service');
            return;
        }
        $logWebhookModel->create($data_log);
    }

    public function cnSubscribe(Request $request)
    {
        $params = $request->all();
        $this->writeLog($params);

        try {
            \DB::beginTransaction();

            $result_data = [];
            if ($params['event_name'] == 'cn_update_bag_real') {
                $result_data = $this->oneCrmUpdateBagReal($params['data']);
            }

            if ($params['event_name'] == 'cn_get_hangve_bag') {
                $result_data = $this->oneCrmGetHangVeBag($params['data']);
            }

            if ($params['event_name'] == 'cn_get_hangve_note') {
                $result_data = $this->oneCrmGetHangNote($params['data']);
            }

            if ($params['event_name'] == 'cn_get_hangve_service') {
                $result_data = $this->oneCrmGetHangService($params['data']);
            }

            \DB::commit();
            return resSuccessWithinData($result_data);
        } catch (Exception $e) {
           \DB::rollback();
            throw $e;
        }
    }

    public function oneCrmUpdateBagReal($data)
    {
        $packModel = \App::make('App\Models\OrderPackage');
        foreach ($data as $key => $item) {
            $packDB = $packModel->where('bill_code', $item['origin_code'])->first();
            if (!is_null($packDB)) {
                $packDB->cn_bag_real = $item['bag_real'];
                if ($item['bag_real'] != $packDB->package_number) {
                    $packDB->diff_count_pack = 1;
                }
                if ($item['bag_real'] == $packDB->package_number) {
                    $packDB->diff_count_pack = 0;
                }
                $packDB->save();

                // Đẩy noti nếu có
                if ($packDB->diff_count_pack == 1) {
                    if ($packDB->order_type == 'App\Models\Order') {
                        $this->pushNotiUpdateBagReal($packDB);
                    }
                    if ($packDB->order_type == 'App\Models\Consignment') {
                        $this->pushNotiUpdateBagRealConsignment($packDB);
                    }
                }
            }
        }
    }

    public function pushNotiUpdateBagReal($packDB)
    {
        $orderModel = \App::make('App\Models\Order');
        $orderDB = $orderModel->where('id', $packDB->order_id)->first();
        if (!is_null($orderDB)) {
            if ($orderDB->staff_order_id > 0) {
                $notificationService = \App::make('App\Services\NotificationService');
                $data_order = [
                    'bill_code'  => $packDB->bill_code,
                    'pack_id'    => $packDB->id,
                ];
                $data = [
                    'group'           => Notification::PACKAGE,
                    'name'            => 3005,
                    'data'            => json_encode($data_order),
                    'time_create'     => time(),
                    'status'          => 1
                ];
                $notificationService->storeNoti($orderDB->staff_order_id, $data);
            }
        }
    }

    public function pushNotiUpdateBagRealConsignment($packDB)
    {
        $notificationService = \App::make('App\Services\NotificationService');
        $data_order = [
            'bill_code' => $packDB->bill_code,
            'pack_id'   => $packDB->id,
        ];
        $data = [
            'group'           => Notification::PACKAGE,
            'name'            => 3005,
            'data'            => json_encode($data_order),
            'time_create'     => time(),
            'status'          => 1
        ];

        // Đẩy cho kinh doanh
        if ($packDB->staff_group_id > 0) {
            $notificationService->storeNoti($packDB->staff_group_id, $data);
        }
        // Đẩy cho khách hàng
        if ($packDB->customer_id > 0) {
            $notificationService->storeNotificationCustomer($packDB->customer_id, $data, true);
        }
    }

    public function oneCrmGetHangVeBag($data)
    {
        $packModel = \App::make('App\Models\OrderPackage');
        $packDBs = $packModel->whereIn('bill_code', $data['arr_code'])->where('status', '<>', PackageConstant::STATUS_CANCEL)->get();
        $arr_result = [];
        foreach ($packDBs as $key => $packDB) {
            $temp = [
                'code'           => $packDB->bill_code,
                'package_number' => $packDB->package_number
            ];
            array_push($arr_result, $temp);
        }
        return $arr_result;
    }

    public function oneCrmGetHangNote($data)
    {
        $packModel = \App::make('App\Models\OrderPackage');
        $packDB = $packModel->where('bill_code', $data['bill_code'])->first();

        $note = null;
        if (!is_null($packDB)) {
            return $packDB->note_internal;
        }

        return $note;
    }

    public function oneCrmGetHangService($data)
    {
        $packModel = \App::make('App\Models\OrderPackage');
        $packDB = $packModel->where('bill_code', $data['bill_code'])->first();

        $note = [
            'hangve_is_inspection'  => false,
            'hangve_is_insurance'   => false,
            'hangve_is_woodworking' => false,
            'hangve_is_shock_proof' => false,
        ];
        if (!is_null($packDB)) {
            $note = [
                'hangve_is_inspection'  => $packDB->is_inspection,
                'hangve_is_insurance'   => $packDB->is_insurance,
                'hangve_is_woodworking' => $packDB->is_woodworking,
                'hangve_is_shock_proof' => $packDB->is_shock_proof,
            ];
        }

        return $note;
    }

}
