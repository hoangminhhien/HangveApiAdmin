<?php


namespace App\Services;

use App\Http\Resources\Complain\ComplainAutoMessageResource;
use App\Models\ComplainAutoMessage;
use Illuminate\Http\JsonResponse;

class ComplainAutoMessageService extends BaseService
{
    protected string $_resource = ComplainAutoMessageResource::class;

    public function pagination(int $perPage): JsonResponse
    {
        $staffId = getCurrentUserId();
        $keyword = request()->get('keyword');

        $data = ComplainAutoMessage::query()->when($keyword, function($q) use ($keyword) {
            $q->where('title', 'like', '%' . $keyword . '%');
        })->where(['staff_id' => $staffId])->orderBy('created_at', 'DESC')->paginate($perPage);

        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }
}