<?php


namespace App\Services;

use App\Constants\FundConstant;
use App\Constants\FundTransactionConstant;
use App\Models\FundLog;
use Illuminate\Http\Request;
use App\Models\Fund;
use App\Http\Resources\Fund\FundResource;
use App\Http\Resources\Fund\FundListResource;
use App\Http\Resources\Fund\FundPaginateResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\OrganizationBank;

class FundService extends BaseService
{
    protected string $_paginateResource = FundPaginateResource::class;
    protected string $_listResource = FundListResource::class;
    protected string $_resource = FundResource::class;

    public function updateTotalMoney($id)
    {
        $fundModel = \App::make('App\Models\Fund');
        $fund = $fundModel->where('id', $id)->withoutGlobalScopes()->first();
        if ($fund) {
            $fundTransactionModel = \App::make('App\Models\FundTransaction');

            $fundTransactionInDB = $fundTransactionModel
                                ->where('status', 1)
                                ->where('fund_id', $id)
                                ->where('type_pay', 1)
                                ->get();

            if (is_null($fundTransactionInDB)) {
                $totalIn = 0;
            } else {
                $totalIn = array_sum(\Arr::pluck($fundTransactionInDB, 'money_update'));
            }

            $fundTransactionOutDB = $fundTransactionModel
                                ->where('status', 1)
                                ->where('fund_id', $id)
                                ->where('type_pay', 2)
                                ->get();

            if (is_null($fundTransactionOutDB)) {
                $totalOut = 0;
            } else {
                $totalOut = array_sum(\Arr::pluck($fundTransactionOutDB, 'money_update'));
            }


            $fund->total_money_in = $totalIn;
            $fund->total_money_out = $totalOut;
            $fund->current_balance = $totalIn - $totalOut;
            $fund->save();

            // Thêm trả ra Balance hiện tại
            return $fund->current_balance;
        }
        return -1;
    }

    public function getBalanceFundChina(): float
    {
        return Fund::query()->where(['type_currency' => 2, 'unit_currency' => 2])->sum('current_balance');
    }

    public function store(array $data)
    {
        $this->throwModel();
        $userOnline = \Auth::user();
        if ($data['organization_bank_id'] != null || $data['organization_bank_id'] != '') {
            $organizationBankModel = \App::make('App\Models\OrganizationBank');
            $organizationBankDB = $organizationBankModel->where('id', $data['organization_bank_id'])->first();
            $data['bank_id'] = $organizationBankDB->bank_id;
            $data['bank_number'] = $organizationBankDB->account_number;
        }

        if ($data['organization_bank_id'] == null || $data['organization_bank_id'] == '') {
            $data['organization_bank_id'] = 0;
        }

        if ($data['type_currency'] != Fund::BANK_TYPE_CURRENCY ||
            ($data['type_currency'] == Fund::BANK_TYPE_CURRENCY && $data['unit_currency'] != Fund::VND_UNIT_CURRENCY)
        ) {
            $data['bank_id'] = null;
            $data['bank_number'] = null;
        }

        $data['organization_id'] = $userOnline->organization_id;
        $data['total_money_in']  = !empty($data['initial_balance']) ? $data['initial_balance'] : 0;
        $data['current_balance'] = !empty($data['initial_balance']) ? $data['initial_balance'] : 0;

        $fundDB = $this->_model->create($data);

        // Tạo 1 giao dịch khởi tạo ban đầu
        $fundTransactionModel = \App::make('App\Models\FundTransaction');
        $fundTransactionService = \App::make('App\Services\FundTransactionService');
        $data_fund_transaction = [
            'code'               => $fundTransactionService->generateRandomCode(),
            'organization_id'    => $userOnline->organization_id,
            'user_create_id'     => (int)$userOnline->id,
            'type_object'        => 3,
            'fund_id'            => (int) $fundDB->id,
            'fund_name'          => $fundDB->name,
            'fund_type_currency' => (int) $fundDB->type_currency,
            'fund_unit_currency' => (int) $fundDB->unit_currency,
            'money'              => $data['initial_balance'],
            'money_update'       => $data['initial_balance'],
            'balance'            => $data['initial_balance'],
            'ratio'              => isset($data['ratio']) ? $data['ratio'] : 0,
            'init'               => FundTransactionConstant::INIT_START,
            'note'               => 'Khởi tạo số dư ban đầu cho quỹ',
            'status'             => 1
        ];
        $fundTransactionDB = $fundTransactionModel->create($data_fund_transaction);

        return $fundDB;
    }

    public function getAllFundForExcel()
    {
        return $this->_model->newQuery()
            ->where(['status' => 1])
            ->orderBy('name')
            ->pluck('name');
    }

    /**
     * Cập nhật bản ghi theo ID
     * @param  int  $id
     * @param  array  $data
     * @return Builder|Builder[]|\Illuminate\Database\Eloquent\Collection|Model|null
     * @throws Exception
     */
    public function edit(int $id, array $data)
    {
        DB::beginTransaction();

        try {
            $this->throwModel();
            // update fund
            $record = $this->_model->newQuery()->findOrFail($id);
            if (isset($data['organization_bank_id'])) {
                $orgBankModel = \App::make('App\Models\OrganizationBank');
                $orgDB = $orgBankModel->where('id', $data['organization_bank_id'])->first();
                if (!is_null($orgDB)) {
                    $data['bank_id'] = $orgDB->bank_id;
                    $data['bank_number'] = $orgDB->account_number;
                }
            }
            $record->update($data);
            // update log
            $newRecord = $this->_model->newQuery()->find($id);
            $this->updateLog(FundConstant::LOG_TYPE_UPDATE, $newRecord);

            DB::commit();

            return $newRecord;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @param int $type
     * @param FundLog $record
     */
    public function updateLog($type, $record)
    {
        $user      = Auth::user();
        $dataLog   = [
            'type'            => $type,
            'fund_id'         => $record->id,
            'fund_name'       => $record->name,
            'data'            => $record,
            'user_id'         => $user->id,
            'user_name'       => $user->name,
            'organization_id' => $record->organization_id
        ];
        FundLog::create($dataLog);
    }

    /**
     * @param int $id
     * @return mixed
     */
    public function delete(int $id)
    {
        DB::beginTransaction();

        try {
            // delete fund
            $record = $this->_model->newQuery()->findOrFail($id);
            if ($record->current_balance != 0) {
                throw new \Exception('Quỹ không cho phép xoá!');
            }
            $record->delete();
            // update log
            $this->updateLog(FundConstant::LOG_TYPE_DELETE, $record);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    public function log()
    {
        $this->throwModel();

        $query = FundLog::query();

        if (!empty(request()->type)) {
            $query->type();
        }
        if (!empty(request()->user_name)) {
            $query->userName();
        }
        if (!empty(request()->fund_name)) {
            $query->fundName();
        }
        if (!empty(request()->date)) {
            $query->date();
        }

        $perPage = request()->per_page ?? 15;

        return $query->paginate($perPage);
    }

    public function getBankNumberInContent($content)
    {
        $bank_number = null;

        $bank = $this->getBank($content);
        if (!is_null($bank)) {
            $pattern = $this->smsMaskToLike($bank);
            $orgBank = OrganizationBank::where('account_number', 'like', $pattern)->first();
            if (!is_null($orgBank)) {
                $bank_number = $orgBank->account_number;
            }
        }

        return $bank_number;
    }

    public function smsMaskToLike($masked) {
        $pattern = preg_replace('/x+/', '%', $masked);
        return $pattern;
    }

    public function getBank($sms)
    {
        if (preg_match('/TK\s+(\d+x+\d+|\d{8,})/i', $sms, $matches)) {
            return $matches[1];
        }
        if (preg_match('/TK\s+([xX\d]{6,})/i', $sms, $matches)) {
            return $matches[1];
        }
        // VietinBank:03/02/2026 15:36|TK:62655868|GD:+690,786VND|SDC:54,418,117VND|ND:VTP LKE03 030226 23428184
        if (preg_match('/TK:(\d+)/', $sms, $m)) {
            return $m[1]; // 62655868
        }

        // + VND 1,000,000
        // Tai khoan Thanh toan: 8968333666
        // So du: VND 16,095,290
        // HV 315773
        if (preg_match('/Tai khoan.*?:\s*(\d+)/i', $sms, $m)) {
            return $m[1]; // 8968333666
        }

        return null;
    }
}
