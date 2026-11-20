<?php

namespace App\Services;

use App\Http\Resources\OrganizationBankResource;
use App\Models\Bank;
use Illuminate\Http\JsonResponse;

/**
 * Class OrganizationService
 * @package App\Services
 */
class OrganizationBankService extends BaseService
{

    protected string $_resource = OrganizationBankResource::class;

    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();

        $query = $this->_model->newQuery();
        $query->with(['bank', 'websites']);
        $query->orderBy('id', 'desc');
        $data = $query->paginate($perPage);

        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }

    public function destroy(string $id): JsonResponse
    {
        $this->_model->newQuery()->findOrFail($id)->delete();

        // Check có link vào quỹ thì xóa ở quỹ đi
        $fundModel = \App::make('App\Models\Fund');
        $fundDB = $fundModel->where('organization_bank_id', $id)->first();
        if (!is_null($fundDB)) {
            $fundDB->bank_id              = null;
            $fundDB->bank_number          = null;
            $fundDB->organization_bank_id = 0;
            $fundDB->save();
        }

        return resSuccess(trans('system.deleted'));
    }
}
