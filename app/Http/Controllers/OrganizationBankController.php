<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Fund;
use App\Models\OrganizationBank;
use App\Models\Website;
use App\Services\OrganizationBankService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrganizationBankController extends Controller
{
    public function __construct(OrganizationBankService $service)
    {
        $this->_service = $service;
    }


    public function store(): JsonResponse
    {
        try {
            DB::beginTransaction();
            $data = request()->all();
            $qrCode = $data['qr_code'] ?? null;
            $bank = Bank::query()->where('bank_id', $qrCode)->where('country', 'vi')->first();
            if (!$bank) {
                return resError('Ngân hàng không tồn tại');
            }
            $dataStore = [
                'bank_id' => $bank->id ?? 0,
                'type' => $data['bank_type'],
                'type_payment' => isset($data['type_payment']) ? $data['type_payment'] : 1,
                'organization_id' => getOrganization(),
                'name' => $data['owner'] ?? null,
                'account_number' => $data['account_number'] ?? null,
            ];
            $bank = $this->_service->store($dataStore);

            if (!empty($data['website_ids'])) {
                $bank->websites()->sync($data['website_ids']);
            }
            DB::commit();
            return resSuccess('Tạo ngân hàng thành công');
        } catch (\Exception $e) {
            DB::rollBack();
            return resError($e->getMessage());
        }

    }

    public function update(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $data = request()->all();
            $status = $data['status'] ?? null;
            if(request()->has('status') && $status === false) {
                $fundModel = \App::make('App\Models\Fund');
                $fundDB = $fundModel->where('organization_bank_id', $id)->first();
                if (!is_null($fundDB)) {
                    $fundDB->organization_bank_id = 0;
                    $fundDB->bank_number = null;
                    $fundDB->bank_id = null;
                    $fundDB->save();
                }
            }
            $data = parent::update($id);
            DB::commit();

            return $data;
        } catch(\Exception $e) {
            DB::rollBack();
            return resError($e->getMessage());
        }

    }

    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $organizationBank = OrganizationBank::query()->findOrFail($id);

            $fundModel = \App::make('App\Models\Fund');
            $fundDB = $fundModel->where('organization_bank_id', $id)->first();
            if (!is_null($fundDB)) {
                $fundDB->organization_bank_id = 0;
                $fundDB->bank_number = null;
                $fundDB->bank_id = null;
                $fundDB->save();
            }
            $data = parent::destroy($id);
            DB::commit();

            return $data;
        } catch(\Exception $e) {
            DB::rollBack();
            return resError($e->getMessage());
        }

    }

    public function updateBank($id): JsonResponse
    {
        $data = request()->all();

        $qrCode = $data['qr_code'] ?? null;
        $bank = Bank::query()->where('bank_id', $qrCode)->where('country', 'vi')->first();

        if (empty($bank)) {
            return resError('Ngân hàng không tồn tại');
        }

        // Nếu có link vào quỹ thì không cho update
        $fundModel = \App::make('App\Models\Fund');
        $fundDB = $fundModel->where('organization_bank_id', $id)->first();
        if (!is_null($fundDB)) {
            // return resError('Không thể cập nhật do đang có link ngân hàng với quỹ');
            if ($fundDB->bank_number != $data['account_number']) {
                $fundDB->bank_number = $data['account_number'];
                $fundDB->save();
            }
            if ($fundDB->bank_id != $bank->id) {
                $fundDB->bank_id = $bank->id;
                $fundDB->save();
            }
        }

        $dataStore = [
            'bank_id' => $bank->id,
            'organization_id' => getOrganization(),
            'type' => $data['bank_type'],
            'type_payment' => isset($data['type_payment']) ? $data['type_payment'] : 1,
            'name' => $data['owner'] ?? null,
            'account_number' => $data['account_number'] ?? null,
        ];
        $bank = $this->_service->update($id, $dataStore);
        $bank->websites()->sync($data['website_ids']);
        return resSuccess('Chỉnh sửa ngân hàng thành công');
    }

    public function website()
    {
        return resSuccessWithinData(Website::query()->get());
    }
}
