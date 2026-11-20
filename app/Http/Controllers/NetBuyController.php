<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\BagStatusConstant;
use App\Models\OrderPackage;
use App\Models\Order;

use App\Models\LogWebhook;
use App\Models\User;
use App\Models\CustomerDelivery;

use Carbon\Carbon;

class NetBuyController extends Controller
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
            'description' => 'NetBuyController@netBuySubscribe',
            'body'        => json_encode($params, JSON_UNESCAPED_UNICODE),
            'from_ip'     => request()->ip()
        ];
        $logWebhookModel->create($data_log);
    }

    public function netBuySubscribe(Request $request)
    {
        $params = $request->all();
        $this->writeLog($params);

        try {
            $result_data = [];
            if ($params['event_name'] == 'check_order_code') {
                $result_data = $this->checkOrderCode($params);
            }
            if ($params['event_name'] == 'get_list_order_code') {
                $result_data = $this->getListOrderCode($params);
            }
            return resSuccessWithinData($result_data);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function checkOrderCode($params)
    {
        $has_error         = null;
        $hangve_order_code = null;
        $hangve_order_id   = 0;
        $total_amount      = 0;

        $buyer_name        = null;
        $buyer_tax_code    = null;
        $buyer_address     = null;
        $buyer_phone       = null;
        $buyer_code        = null;

        $orderDB = Order::query()->where('code', $params['order_code'])->first();
        if ($orderDB) {
            $arr_status = [
                OrderConstant::KEY_STATUS_ORDERED,
                OrderConstant::KEY_STATUS_DONE,
            ];
            if (in_array($orderDB->status, $arr_status)) {
                if ($orderDB->official == OrderConstant::OFFICIAL_SALE) {
                    $hangve_order_code = $orderDB->code;
                    $hangve_order_id = (int) $orderDB->id;

                    $customDelivery = CustomerDelivery::query()->where('id', $orderDB->customer_delivery_id)->first();
                    $buyer_address = $customDelivery ? $customDelivery->custom_name_Second : null;

                    $customerDB    = $orderDB->customer;
                    $buyer_name    = $customerDB->name;
                    $buyer_phone   = $customerDB->phone_number;
                    $buyer_code    = $customerDB->code;

                    $total_amount = $orderDB->getTotalAmount();
                } else {
                    $has_error = 'Đơn hàng không phải là đơn bán chính ngạch';
                }
            } else {
                $has_error = 'Đơn hàng không ở trạng thái đặt hàng hoặc hoàn thành';
            }
        } else {
            $has_error = 'Mã đơn hàng không tồn tại';
        }
        return [
            'has_error'         => $has_error,
            'hangve_order_code' => $hangve_order_code,
            'hangve_order_id'   => $hangve_order_id,
            'total_amount'   => $total_amount,

            'buyer_name'        => $buyer_name,
            'buyer_tax_code'    => $buyer_tax_code,
            'buyer_address'     => $buyer_address,
            'buyer_phone'       => $buyer_phone,
            'buyer_code'        => $buyer_code,
        ];
    }

    public function getListOrderCode($params)
    {
        $arr_status = [
            OrderConstant::KEY_STATUS_ORDERED,
            OrderConstant::KEY_STATUS_DONE,
        ];

        $orderDBs = Order::query()
                    ->select([
                        'id',
                        'code',
                        'customer_id',
                        'customer_delivery_id',
                    ])
                    ->with([
                        'customer:id,name,phone_number,code',
                        'customerDelivery',
                    ])
                    ->where('official', OrderConstant::OFFICIAL_SALE)
                    ->whereIn('status', $arr_status)
                    ->where('updated_at', '>=', now()->subHours(225))
                    ->where('created_at', '>=', '2026-05-11')
                    ->get();

        $data_result = [];
        foreach ($orderDBs as $key => $orderDB) {
            $temp = [
                'hangve_order_code' => $orderDB->code,
                'hangve_order_id'   => (int) $orderDB->id,

                'buyer_name'        => optional($orderDB->customer)->name,
                'buyer_tax_code'    => null,
                'buyer_address'     => optional($orderDB->customerDelivery)->custom_name_Second,
                'buyer_phone'       => optional($orderDB->customer)->phone_number,
                'buyer_code'        => optional($orderDB->customer)->code,

                'total_amount'      => $orderDB->getTotalAmount()
            ];
            array_push($data_result, $temp);
        }

        return $data_result;
    }
}
