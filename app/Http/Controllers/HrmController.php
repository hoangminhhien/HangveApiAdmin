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

use Carbon\Carbon;

class HrmController extends Controller
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
            'description' => 'HrmController@hrmSubscribe',
            'body'        => json_encode($params, JSON_UNESCAPED_UNICODE),
            'from_ip'     => request()->ip()
        ];
        $logWebhookModel->create($data_log);
    }

    public function hrmSubscribe(Request $request)
    {
        $params = $request->all();
        $this->writeLog($params);

        try {
            \DB::beginTransaction();

            $result_data = [];
            if ($params['event_name'] == 'get_sale_kpi') {
                $result_data = $this->getSaleKPI($params);
            }
            if ($params['event_name'] == 'get_order_consignment_kpi') {
                $result_data = $this->getOrderConsignmentKpi($params['data']);
            }
            if ($params['event_name'] == 'create_user') {
                $result_data = $this->hrmCreateUser($params['data']);
            }

            \DB::commit();
            return resSuccessWithinData($result_data);
        } catch (Exception $e) {
           \DB::rollback();
            throw $e;
        }
    }

    public function getSaleKPI($data)
    {
        $date_current = date('m/Y', time());
        $date_before = date('m/Y', strtotime('-1 month'));

        $arr_time = [
            $date_current,
            $date_before
        ];

        $userGroupModel = \App::make('App\Models\UserGroup');
        $userGroupDBs = $userGroupModel->whereIn('month', $arr_time)->with('user')->get();

        $data_result = [];
        if (count($userGroupDBs) > 0) {
            $users    = collect($userGroupDBs->toArray());
            $groupeds = $users->groupBy('month');
            foreach ($groupeds as $key_1 => $grouped) {
                $data_user = [];
                foreach ($grouped as $key_2 => $item) {
                    if (!is_null($item['user'])) {
                        $temp_2 = [
                            'email'      => $item['user']['email'],
                            'kpi_sales'  => $item['kpi_sales'],
                            'real_sales' => $item['real_sales'],
                        ];
                        array_push($data_user, $temp_2);
                    }
                }
                $temp_1 = [
                    'month'     => $key_1,
                    'data_user' => $data_user,
                ];
                array_push($data_result, $temp_1);
            }
        }

        return $data_result;
    }

    public function getOrderConsignmentKpi($data)
    {
        if (!isset($data['date_check'])) {
            return [];
        }
        $arr_result = [];

        $firstDay = Carbon::now()->startOfMonth()->toDateString();
        $lastDay  = Carbon::now()->endOfMonth()->toDateString();

        $params['date_done'] = $firstDay . ',' . $lastDay;
        $params['date_done'] = $data['date_check'] . ',' . $data['date_check'];
        \Auth::login(User::find(2));

        $request = new \Illuminate\Http\Request($params);
        app()->instance('request', $request);

        $reportService = \App::make('App\Services\ReportService');
        $data = $reportService->getOrderComplete($request, false, true);
        $arr_data = $data->toArray();
        $groupeds = collect($arr_data)->groupBy('staff_group_id')->toArray();

        foreach ($groupeds as $key_1 => $group) {
            $sum_order_fee            = array_sum(array_column($group, 'order_fee'));
            $sum_inspection_cost      = array_sum(array_column($group, 'inspection_cost'));
            $sum_official_service_fee = array_sum(array_column($group, 'official_service_fee'));
            $temp = [
                'sum_order_fee'            => $sum_order_fee,
                'sum_consignment_fee'      => 0,
                'sum_service_fee'          => 0,
                'sum_inspection_fee'       => $sum_inspection_cost,
                'sum_official_service_fee' => $sum_official_service_fee,

                'user_id'                  => $group[0]['staff_group_id'],
                'user_name'                => $group[0]['staff_group'] ? $group[0]['staff_group']['name'] : null,
                'user_email'               => $group[0]['staff_group'] ? $group[0]['staff_group']['email'] : null,
            ];
            array_push($arr_result, $temp);
        }

        // Phần phí kí gửi
        $params_2 = $params;
        $params_2['per_page']   = 100000;
        $params_2['order_type'] = 2;

        $request = new \Illuminate\Http\Request($params_2);
        app()->instance('request', $request);

        $data = $reportService->reportConsignment($request, $extra);
        $arr_data = $data->getCollection()->toArray();
        $groupeds = collect($arr_data)->groupBy('staff_group_id')->toArray();

        foreach ($groupeds as $key_2 => $group) {
            if ($key_2 == "") {
                continue;
            }
            $sum_consignment_fee      = array_sum(array_column($group, 'amount'));

            // Check xem nó tồn tại ở mảng kết quả chưa
            $index = array_search($key_2, array_column($arr_result, 'user_id'));
            if ($index !== false) {
                $arr_result[$index]['sum_consignment_fee']      = $sum_consignment_fee;
            } else {
                $temp = [
                    'sum_order_fee'            => 0,
                    'sum_consignment_fee'      => $sum_consignment_fee,
                    'sum_service_fee'          => 0,
                    'sum_inspection_fee'       => 0,
                    'sum_official_service_fee' => 0,

                    'user_id'                  => $group[0]['staff_group_id'],
                    'user_name'                => $group[0]['staff_group'] ? $group[0]['staff_group']['name'] : null,
                    'user_email'               => $group[0]['staff_group'] ? $group[0]['staff_group']['email'] : null,
                    'version'                  => 2,
                ];
                array_push($arr_result, $temp);
            }
        }

        // Phần dịch vụ gia tăng
        $params_3 = $params;
        $params_3['per_page']   = 100000;

        $request = new \Illuminate\Http\Request($params_3);
        app()->instance('request', $request);

        $data = $reportService->reportConsignment($request, $extra);
        $arr_data = $data->getCollection()->toArray();
        $groupeds = collect($arr_data)->groupBy('staff_group_id')->toArray();

        foreach ($groupeds as $key_3 => $group) {
            if ($key_3 == "") {
                continue;
            }

            $sum_insurance_cost   = array_sum(array_column($group, 'insurance_cost'));
            $sum_woodworking_cost = array_sum(array_column($group, 'woodworking_cost'));
            $sum_shock_proof_cost = array_sum(array_column($group, 'shock_proof_cost'));
            $sum_service_fee =  $sum_insurance_cost + $sum_woodworking_cost + $sum_shock_proof_cost;

            $sum_official_service_fee = array_sum(array_column($group, 'official_service_fee'));

            // Check xem nó tồn tại ở mảng kết quả chưa
            $index = array_search($key_3, array_column($arr_result, 'user_id'));
            if ($index !== false) {
                $arr_result[$index]['sum_service_fee']          = $sum_service_fee;
                $arr_result[$index]['sum_official_service_fee'] += $sum_official_service_fee;
            } else {
                $temp = [
                    'sum_order_fee'            => 0,
                    'sum_consignment_fee'      => 0,
                    'sum_service_fee'          => $sum_service_fee,
                    'sum_inspection_fee'       => 0,
                    'sum_official_service_fee' => $sum_official_service_fee,

                    'user_id'                  => $group[0]['staff_group_id'],
                    'user_name'                => $group[0]['staff_group'] ? $group[0]['staff_group']['name'] : null,
                    'user_email'               => $group[0]['staff_group'] ? $group[0]['staff_group']['email'] : null,
                    'version'                  => 3,
                ];
                array_push($arr_result, $temp);
            }
        }

        return [
            'arr_result' => $arr_result,
        ];
    }

    public function hrmCreateUser($data)
    {
        if (env('APP_URL') == 'https://api-admin.hangve.com') {
            return [];
        }

        $name     = isset($data['name']) ? $data['name'] : null;
        $email    = isset($data['email']) ? $data['email'] : null;
        $phone    = isset($data['phone']) ? $data['phone'] : null;
        $password = isset($data['password']) ? $data['password'] : null;

        if ($email == null) {
            return [
                'success' => 0,
                'message' => 'Không có email',
            ];
        }
        if ($password == null) {
            return [
                'success' => 0,
                'message' => 'Không có password',
            ];
        }

        $userModel = \App::make('App\Models\User');
        $staffService = \App::make('App\Services\StaffService');
        $userDB = $userModel->where('email', $email)->first();
        if (is_null($userDB)) {
            $temp = [
                'name'            => $name,
                'email'           => $email,
                'phone_number'    => $phone,
                'password'        => $password,
                'organization_id' => 1
            ];
            $staffService->store($temp);
            return [
                'success' => 1,
                'message' => 'Tạo tài khoản thành công',
            ];
        }
        return [
            'success' => 1,
            'message' => 'Tài khoản đã có trên hệ thống Hàng Về',
        ];
    }

    public function hrmStatisticFee(Request $request, $fee)
    {
        $validationRules = [
            'data.date_check' => 'required|date_format:Y-m-d',
        ];
        $validationMessages = [
        ];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();
        $params = $params['data'];

        $arr_result = [];

        if ($fee == 'order_fee') {
            $data = $params;
            $arr_result = $this->hrmStatisticFeeOrder($data, 'order_fee');
        }

        if ($fee == 'inspection_fee') {
            $data = $params;
            $arr_result = $this->hrmStatisticFeeOrder($data, 'inspection_fee');
        }

        if ($fee == 'consignment_fee') {
            $data = $params;
            $data['per_page']   = 100000;
            $data['order_type'] = 2;
            $arr_result = $this->hrmStatisticFeeConsignment($data, 'consignment_fee');
        }

        if ($fee == 'service_fee') {
            $data = $params;
            $data['per_page']   = 100000;
            $arr_result = $this->hrmStatisticFeeConsignment($data, 'service_fee');
        }
        // Thêm tách lẻ cho service_fee
        if ($fee == 'insurance_fee') {
            $data = $params;
            $data['per_page']   = 100000;
            $arr_result = $this->hrmStatisticFeeConsignment($data, 'insurance_fee');
        }
        if ($fee == 'woodworking_fee') {
            $data = $params;
            $data['per_page']   = 100000;
            $arr_result = $this->hrmStatisticFeeConsignment($data, 'woodworking_fee');
        }
        if ($fee == 'shock_proof_fee') {
            $data = $params;
            $data['per_page']   = 100000;
            $arr_result = $this->hrmStatisticFeeConsignment($data, 'shock_proof_fee');
        }
        // Thêm tách lẻ

        if ($fee == 'official_fee') {
            $data = $params;
            $arr_result_1 = $this->hrmStatisticFeeOrder($data, 'official_fee');

            $data['per_page']   = 100000;
            $data['official']   = 2;
            $data['order_type'] = 2;
            $arr_result_2 = $this->hrmStatisticFeeConsignment($data, 'official_fee');

            // Gộp nó lại
            $result = [];
            foreach (array_merge($arr_result_1, $arr_result_2) as $item) {
                $id = $item['user_id'];
                if (!isset($result[$id])) {
                    $result[$id] = $item;
                } else {
                    $result[$id]['sum_amount'] += $item['sum_amount'];
                }
            }
            $arr_result = array_values($result);
        }

        $result_data = [
            'arr_result' => $arr_result,
        ];
        return resSuccessWithinData($result_data);
    }

    public function hrmStatisticFeeOrder($data, $key)
    {
        $arr_result = [];
        $params['date_done'] = $data['date_check'] . ',' . $data['date_check'];
        \Auth::login(User::find(2));

        $request = new \Illuminate\Http\Request($params);
        app()->instance('request', $request);

        $reportService = \App::make('App\Services\ReportService');
        $data = $reportService->getOrderComplete($request, false, true);
        $arr_data = $data->toArray();
        $groupeds = collect($arr_data)->groupBy('staff_group_id')->toArray();

        foreach ($groupeds as $key_2 => $group) {
            if ($key_2 == "") {
                continue;
            }
            if ($key == 'order_fee') {
                $sum_amount = array_sum(array_column($group, 'order_fee'));
            }
            if ($key == 'inspection_fee') {
                $sum_amount = array_sum(array_column($group, 'inspection_cost'));
            }
            if ($key == 'official_fee') {
                $sum_amount = array_sum(array_column($group, 'official_service_fee'));
            }
            $temp = [
                'sum_amount' => $sum_amount,
                'user_id'    => $group[0]['staff_group_id'],
                'user_name'  => $group[0]['staff_group'] ? $group[0]['staff_group']['name'] : null,
                'user_email' => $group[0]['staff_group'] ? $group[0]['staff_group']['email'] : null,
            ];
            array_push($arr_result, $temp);
        }

        return $arr_result;
    }

    public function hrmStatisticFeeConsignment($params, $key)
    {
        $arr_result = [];
        $params['date_done'] = $params['date_check'] . ',' . $params['date_check'];
        \Auth::login(User::find(2));

        $request = new \Illuminate\Http\Request($params);
        app()->instance('request', $request);

        $reportService = \App::make('App\Services\ReportService');
        $data = $reportService->reportConsignment($request, $extra);
        $arr_data = $data->getCollection()->toArray();
        $groupeds = collect($arr_data)->groupBy('staff_group_id')->toArray();

        foreach ($groupeds as $key_2 => $group) {
            if ($key_2 == "") {
                continue;
            }
            if ($key == 'consignment_fee') {
                $sum_amount = array_sum(array_column($group, 'amount'));
            }
            if ($key == 'service_fee') {
                $sum_insurance_cost   = array_sum(array_column($group, 'insurance_cost'));
                $sum_woodworking_cost = array_sum(array_column($group, 'woodworking_cost'));
                $sum_shock_proof_cost = array_sum(array_column($group, 'shock_proof_cost'));
                $sum_amount =  $sum_insurance_cost + $sum_woodworking_cost + $sum_shock_proof_cost;
            }
            if ($key == 'insurance_fee') {
                $sum_amount   = array_sum(array_column($group, 'insurance_cost'));
            }
            if ($key == 'woodworking_fee') {
                $sum_amount = array_sum(array_column($group, 'woodworking_cost'));
            }
            if ($key == 'shock_proof_fee') {
                $sum_amount = array_sum(array_column($group, 'shock_proof_cost'));
            }
            if ($key == 'official_fee') {
                $sum_amount = array_sum(array_column($group, 'official_service_fee'));
            }
            $temp = [
                'sum_amount' => $sum_amount,
                'user_id'    => $group[0]['staff_group_id'],
                'user_name'  => $group[0]['staff_group'] ? $group[0]['staff_group']['name'] : null,
                'user_email' => $group[0]['staff_group'] ? $group[0]['staff_group']['email'] : null,
            ];
            array_push($arr_result, $temp);
        }
        return $arr_result;
    }
}
