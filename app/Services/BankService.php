<?php


namespace App\Services;


use App\Http\Resources\BankResource;
use App\Models\Bank;
use Illuminate\Http\JsonResponse;

class BankService extends BaseService
{
    protected string $_resource = BankResource::class;

    /**
     * @param  string  $country
     * @return JsonResponse
     */
    public function getBankByCountry(string $country): JsonResponse
    {
        $query = Bank::query();

        $query->join('organization_banks', 'banks.id', '=', 'organization_banks.bank_id')
            ->where('country', $country);
        $query->select('banks.name', 'banks.id', 'banks.short_name', 'organization_banks.branch',
            'banks.charge_code', 'organization_banks.account_number', 'organization_banks.id as organization_bank_id', 'organization_banks.name as org_bank_name');

        $query->orderBy('banks.priority', 'DESC');
        $query->orderBy('banks.short_name');

        $data = $query->get();
        return resSuccessWithinData(new $this->_listResource($data, $this->_resource));
    }
}
