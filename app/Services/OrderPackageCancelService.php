<?php


namespace App\Services;


use App\Http\Resources\Package\OrderPackageCancelResource;
use Illuminate\Http\JsonResponse;

class OrderPackageCancelService extends BaseService
{
    protected string $_resource = OrderPackageCancelResource::class;


    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();
        $query = $this->_model->newQuery();
        $query = $query->billCode();
        $query = $query->orderCode();
        $query = $query->with(['customer', 'orderPackage']);
        $query = $query->orderBy('status', 'DESC');
        $query = $query->orderBy('created_at', 'DESC');
        $data = $query->paginate($perPage);

        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }


}
