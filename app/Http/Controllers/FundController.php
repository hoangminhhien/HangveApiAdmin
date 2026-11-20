<?php

namespace App\Http\Controllers;

use App\Http\Resources\Fund\FundLogResource;
use App\Http\Resources\PaginateJsonResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FundService;

use App\Models\Fund;

class FundController extends Controller
{
    public function __construct(FundService $service)
    {
        $this->_service = $service;
    }

    public function list()
    {
        // unset condition laravel scope
        // https://stackoverflow.com/questions/25312386/laravel-how-to-disable-a-global-scope-in-order-to-include-inactive-objects
        $fundModel = \App::make('App\Models\Fund');
        $funds = $fundModel->with('bank')->orderBy('id', 'asc')->get();

        // Thêm theo quyền
        $userOnline = \Auth::user();
        $hasPerrReserveAll = $userOnline->checkUserHasPer('fund.view-reserve-all');
        $hasPerrReserveWarehouse = $userOnline->checkUserHasPer('fund.view-reserve-warehouse');

        // Nếu không dc tích 1 trong 2 quyền xem thì funds = [];
        if ($hasPerrReserveAll == false && $hasPerrReserveWarehouse == false) {
            $funds = [];
        }
        // Nếu chỉ có quyền kho
        if ($hasPerrReserveWarehouse) {
            $funds = $fundModel->where('type_common', Fund::TYPE_COMMON_WAREHOUSE)->with('bank')->orderBy('id', 'asc')->get();
            // Logic 04-12-24: Logic lọc theo kho làm việc của User
            $departmentDB = $userOnline->department;
            if (!is_null($departmentDB) && $departmentDB->warehouse_id > 0) {
                $funds = $funds->where('warehouse_id', $departmentDB->warehouse_id);
            }
        }

        // Nếu chỉ có quyền xem tất cả
        if ($hasPerrReserveAll) {
            $funds = $fundModel->with('bank')->orderBy('id', 'asc')->get();
        }

        if ($funds) {
            $arr_VND = [];
            $total_VND = 0;
            $arr_China = [];
            $total_China = 0;
            $arr_virtual = [];

            foreach ($funds as $key => $item) {
                $temp = [
                    'id'   => $item->id,
                    'name' => $item->name,
                    'type_currency' => $item->type_currency,
                    'unit_currency' => $item->unit_currency,
                    'initial_balance' => $item->initial_balance,
                    'total_money_in' => $item->total_money_in,
                    'total_money_out' => $item->total_money_out,
                    'current_balance' => $item->current_balance,
                    'current_balance_format' => number_format($item->current_balance,2,",","."),
                    'organization_bank_id' => $item->organization_bank_id,
                    'note' => $item->note,
                    'status' => $item->status,
                    'bank_id' => $item->bank_id,
                    'bank_name' => optional($item->bank)->name,
                    'bank_number' => $item->bank_number
                ];
                // Nếu là tiền VND thì  push
                if ($item->unit_currency == 1 && $item->type_currency != 3) {
                    array_push($arr_VND, $temp);
                    $total_VND += $item->current_balance;
                }
                // Nếu là tiền Yên thì  push
                if ($item->unit_currency == 2 && $item->type_currency != 3) {
                    array_push($arr_China, $temp);
                    $total_China += $item->current_balance;
                }
                // Nếu là tiền ảo thì  push
                if ($item->type_currency == 3) {
                    array_push($arr_virtual, $temp);
                }
            }
            $data_result = [
                'arr_virtual' => $arr_virtual,
                'arr_VND' => $arr_VND,
                'total_VND' => $total_VND,
                'arr_China' => $arr_China,
                'total_China' => $total_China,
            ];
            return resSuccessWithinData($data_result);
        }
        $data_result = [
            'arr_virtual' => [],
            'arr_VND' => [],
            'total_VND' => [],
            'arr_China' => [],
            'total_China' => [],
        ];
        return resSuccessWithinData($data_result);
    }

    public function initDefault(Request $request)
    {
        $fundModel = \App::make('App\Models\Fund');

        $data = [
            ['name' => 'Vietcombank', 'type_currency' => 2, 'unit_currency' => 1],
            ['name' => 'Techcombank', 'type_currency' => 2, 'unit_currency' => 1],
            ['name' => 'BIDV', 'type_currency' => 2, 'unit_currency' => 1],
            ['name' => 'MB Bank', 'type_currency' => 2, 'unit_currency' => 1],
            ['name' => 'Tiền mặt', 'type_currency' => 1, 'unit_currency' => 1],

            ['name' => 'Công thương', 'type_currency' => 2, 'unit_currency' => 2],
            ['name' => 'Nhân dân', 'type_currency' => 2, 'unit_currency' => 2],

        ];

        $userOnline = \Auth::user();

        foreach ($data as $key => $item) {
            $fundDB = $fundModel->where('name', $item['name'])
                                ->where('organization_id', $userOnline->organization_id)
                                ->first();

            if (is_null($fundDB)) {
                $item['organization_id'] = $userOnline->organization_id;
                $fundModel->create($item);
            }
        }

        return resSuccess("OK");
    }

    public function storeMessage(): ?array
    {
       return [
           'organization_bank_id.exists' => 'Ngân hàng không tồn tại trong hệ thống',
           'organization_bank_id.unique' => 'Ngân hàng đã được gán quỹ'
       ];
    }

    public function storeRequest(): array
    {
        return [
            'name'            => 'required|string|max:255|unique:funds',
            'type_currency'   => 'required|in:1,2,3',
            'unit_currency'   => 'required|in:1,2',
            'initial_balance' => 'required|numeric|max:10000000000',
            'organization_bank_id' => 'nullable|exists:organization_banks,id|unique:funds',
            'ratio'           => 'required_if:unit_currency,==,2|min:0',
            'type_common'     => 'nullable|in:0,1'
        ];
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

    /**
     * @param int $id
     */
    public function edit(int $id)
    {
        try {
            $this->_service->edit($id, \request()->only('name', 'organization_bank_id'));

            return resSuccess('Cập nhật quỹ thành công!');
        } catch (\Exception $e) {
            //dd($e->getMessage());
            return resError('Cập nhật quỹ thất bại');
        }
    }

    /**
     * @param int $id
     */
    public function delete(int $id)
    {
        try {
            $this->_service->delete($id);

            return resSuccess('Xoá quỹ thành công!');
        } catch (\Exception $e) {
            //dd($e->getMessage());
            return resError('Xoá quỹ thất bại');
        }
    }

    /**
     * get fund log
     */
    public function log()
    {
        try {
            $data = $this->_service->log();

            return resSuccessWithinData(new PaginateJsonResource($data, FundLogResource::class));
        } catch (\Exception $e) {
            //dd($e->getMessage());
            return resErrorWithinData([], 'Lấy lịch sử quỹ thất bại');
        }
    }

    public function changeTypeCommon(Request $request)
    {
        $validationRules = [
            'fund_id' => 'required|exists:funds,id',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        try {
            \DB::beginTransaction();

            $fundModel = \App::make('App\Models\Fund');
            $fundDB = $fundModel->where('id', $params['fund_id'])->first();

            $fundDB->type_common = !$fundDB->type_common;
            $fundDB->save();

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function changeWarehouseId(Request $request)
    {
        $validationRules = [
            'fund_id'      => 'required|exists:funds,id',
            'warehouse_id' => 'nullable|numeric|min:0|exists:warehouses,id',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        try {
            \DB::beginTransaction();

            $fundModel = \App::make('App\Models\Fund');
            $fundDB = $fundModel->where('id', $params['fund_id'])->first();

            $fundDB->warehouse_id = isset($params['warehouse_id']) ? $params['warehouse_id'] : 0;
            $fundDB->save();

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function listBankForCreateFund(Request $request)
    {
        $fundModel = \App::make('App\Models\Fund');
        $orgBankModel = \App::make('App\Models\OrganizationBank');

        $fundDBs = $fundModel->where('organization_bank_id', '>', 0)->get();

        $organization_bank_ids = [];
        if (count($fundDBs) > 0) {
            $organization_bank_ids = \Arr::pluck($fundDBs, 'organization_bank_id');
        }

        $orgBanks = $orgBankModel->whereNotIn('id', $organization_bank_ids)->where('status', 1)->get();

        $arr_result = [];
        foreach ($orgBanks as $key => $orgBank) {
            $bankDB = $orgBank->bank;
            $shortName = $bankDB ? $bankDB->short_name : '';
            $temp = [
                'organization_bank_id' => $orgBank->id,
                'data_show' => $shortName . ' - ' . $orgBank->name . ' - ' . $orgBank->account_number
            ];
            array_push($arr_result, $temp);
        }
        return resSuccessWithinData($arr_result);
    }
}

