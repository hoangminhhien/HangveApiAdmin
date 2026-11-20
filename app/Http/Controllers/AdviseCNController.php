<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Services\AdviseCNService;
use App\Constants\PackageConstant;
use App\Constants\ConversationConstant;
use App\Constants\DepartmentConstant;
use App\Constants\RepConstant;
use App\Constants\AdviseCNConstant;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Rep;
use App\Models\User;
use App\Models\NameTran;
use App\Models\Supplier;
use App\Models\AdviseCN;

use GuzzleHttp\Exception\RequestException;

class AdviseCNController extends Controller
{
    public function __construct(AdviseCNService $service)
    {
        $this->_service = $service;
    }

    public function storeV2(Request $request)
    {
        $validationRules = [
            'type'                 => 'required|in:1,2',
            'customer_name'        => 'required|present',
            'customer_phone' => [
                'required',
                'present',
                'regex:/^(03|05|07|08|09)[0-9]{8}$/'
            ],

            'warehouse_id'         => 'required|present|exists:warehouses,id',
            'warehouse_code'       => 'required|present',
            'item_name'            => 'required|present',

            'item_color'           => 'nullable|present',
            'item_size'            => 'nullable|present',
            'item_material'        => 'nullable|present',
            'item_info'            => 'nullable|present',
            'item_branch'          => 'nullable|present',

            'item_quantity'        => 'required|present|numeric|min:1',
            'item_kg'              => 'required|present|numeric:min:0',
            'item_m3'              => 'required|present|numeric|min:0',
            'item_unit_declare'    => 'required|present|numeric|min:1',

            'hs_code'              => 'nullable|present',
            'info_fee'             => 'nullable|present|array',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();

        $result_check = $this->handleBeforeStoreV2($params);
        if(!is_null($result_check)) {
            $result = ['error' => $result_check];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();
            $this->_service->handlerStoreV2($params);
            \DB::commit();
            return resSuccessWithinData([]);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function handleBeforeStoreV2($params)
    {
        // Check gì thêm nếu có
        return null;
    }

    public function changeAgent(Request $request)
    {
        $validationRules = [
            'advise_cn_id' => 'required|exists:advise_cns,id',
            'user_id'      => 'required|exists:users,id',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();

        $result_check = $this->handleBeforeChangeAgent($params);
        if(!is_null($result_check)) {
            $result = ['error' => $result_check];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();
            $this->_service->handlerChangeAgent($params);
            \DB::commit();
            return resSuccessWithinData([]);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function handleBeforeChangeAgent($params)
    {
        // Check xem có quyền gán không
        $userOnline = \Auth::user();
        $canAssign = $userOnline->checkUserHasPer('customer.advise-cn-assign-agent');
        if (!$canAssign) {
            return 'Bạn không có quyền gán nhân viên';
        }

        $adviseCN = AdviseCN::query()->where('id', $params['advise_cn_id'])->first();
        if ($adviseCN->status == AdviseCNConstant::STATUS_RECEIVED) {
            return 'Không thể gán lại nhân viên ở trạng thái này';
        }
    }

    public function changeAgentArr(Request $request)
    {
        $validationRules = [
            'arr_advise_cn_id'   => 'required|array',
            'arr_advise_cn_id.*' => 'required|exists:advise_cns,id',
            'user_id'            => 'required|exists:users,id',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();

        $result_check = $this->handleBeforeChangeAgentArr($params);
        if(!is_null($result_check)) {
            $result = ['error' => $result_check];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();
            $this->_service->handlerChangeAgentArr($params);
            \DB::commit();
            return resSuccessWithinData([]);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function handleBeforeChangeAgentArr($params)
    {
        // Check xem có quyền gán không
        $userOnline = \Auth::user();
        $canAssign = $userOnline->checkUserHasPer('customer.advise-cn-assign-agent');
        if (!$canAssign) {
            return 'Bạn không có quyền gán nhân viên';
        }


        $adviseCNs = AdviseCN::query()->whereIn('id', $params['arr_advise_cn_id'])
                                ->where('status', AdviseCNConstant::STATUS_RECEIVED)
                                ->get();
        $customer_names = \Arr::pluck($adviseCNs, 'customer_name');
        if (count($customer_names) > 0) {
            $string = implode(', ', $customer_names);
            return 'Không thể gán lại các yêu cầu của khách hàng ' . $string;
        }
    }


    public function changeStatus(Request $request)
    {
        $validationRules = [
            'advise_cn_id' => 'required|exists:advise_cns,id',
            'status'      => 'required|in:0,1',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();

        $result_check = $this->handleBeforeChangeStatus($params);
        if(!is_null($result_check)) {
            $result = ['error' => $result_check];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();
            $this->_service->handlerChangeStatus($params);
            \DB::commit();
            return resSuccessWithinData([]);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function handleBeforeChangeStatus($params)
    {
        // Check xem có quyền gán không
        $userOnline = \Auth::user();
        $canAssign = $userOnline->checkUserHasPer('customer.advise-cn-change-status');
        if (!$canAssign) {
            return 'Bạn không có quyền chuyển trạng thái';
        }

        $adviseCN = AdviseCN::query()->where('id', $params['advise_cn_id'])->first();
        if ($adviseCN->user_id == 0) {
            return 'Không được để trống tên nhân viên xử lý';
        }
    }


    public function updateHsCodeAndFee(Request $request)
    {
        $validationRules = [
            'advise_cn_id' => 'required|exists:advise_cns,id',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();

        try {
            $this->_service->handlerUpdateHsCodeAndFee($params);
            return resSuccessWithinData([]);
        } catch (Exception $e) {
            throw $e;
        }
    }



    public function getAdviseCNStatus(Request $request)
    {
        $countByStatus = AdviseCN::select('status', DB::raw('COUNT(*) as total'));

        // Check quyền
        $userOnline = \Auth::user();
        $viewAll = $userOnline->checkUserHasPer('customer.advise-cn-view-all');
        if (!$viewAll) {
            $countByStatus = $countByStatus->where('user_id', (int) $userOnline->id);
        }

        $countByStatus = $countByStatus
                            ->groupBy('status')
                            ->pluck('total', 'status')
                            ->toArray();

        $totalAll = array_sum($countByStatus);

        $listStatus = AdviseCNConstant::STATUSES_TEXT;
        $listColor = AdviseCNConstant::STATUSES_COLOR;

        $status_count = [
            [
                'value' => 'all',
                'quantity' => $totalAll,
                'name' => 'Tất cả',
                'color' => '#45b4ce',
            ],
            [
                'value' => AdviseCNConstant::STATUS_WAITING,
                'quantity' => $countByStatus[AdviseCNConstant::STATUS_WAITING] ?? 0,
                'name' => $listStatus[AdviseCNConstant::STATUS_WAITING],
                'color' => $listColor[AdviseCNConstant::STATUS_WAITING],
            ],
            [
                'value' => AdviseCNConstant::STATUS_RECEIVED,
                'quantity' => $countByStatus[AdviseCNConstant::STATUS_RECEIVED] ?? 0,
                'name' => $listStatus[AdviseCNConstant::STATUS_RECEIVED],
                'color' => $listColor[AdviseCNConstant::STATUS_RECEIVED],
            ]
        ];

        $list_status = [];
        foreach ($listStatus as $key => $value) {
            $list_status[] = [
                'key' => $key,
                'value' => $value
            ];
        }

        return resSuccessWithinData([
            'status_count' => $status_count,
            'list_status'  => $list_status,
        ]);
    }
}
