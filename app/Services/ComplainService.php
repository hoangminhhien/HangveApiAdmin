<?php


namespace App\Services;


use App\Constants\ComplainConstant;
use App\Http\Resources\ComplainResource;
use App\Models\Complain;
use App\Models\ComplainFeedback;

use App\Models\ComplainImage;
use Illuminate\Http\JsonResponse;

class ComplainService extends BaseService
{
    protected string $_resource = ComplainResource::class;

    /**
     * @param  string  $orderId
     * @return int
     */
    public function getStatusesDone(string $orderId): int
    {
        return Complain::query()->where(
            ['order_id' => $orderId, 'status' => ComplainConstant::KEY_STATUS_DONE]
        )->count();
    }

    public function getCommentsById(string $id)
    {
        return ComplainFeedback::query()->where('complain_id', $id)->get();
    }

    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();

        $perService = \App::make('App\Services\PerService');

        $query = $this->_model->newQuery();
        $query->with('comments', 'orderDetails', 'order', 'customer', 'complainType', 'solution', 'staffOrder',
            'staffCare', 'staffCounselor', 'staffComplain');

        $query = $perService->getConditionByUserLogin($query, 'complain');

        if(request()->input('status') == 'no_process') {
            $query = Complain::query()->withoutGlobalScopes()
                ->where('status', ComplainConstant::KEY_STATUS_PENDING);
        }
        if(request()->input('status') == 'complain_total_done') {
            $query = Complain::query()->withoutGlobalScopes()
                ->where('status', ComplainConstant::KEY_STATUS_DONE);
        }
        if (request()->query('time')) {
            $dateRange = $this->dateRange(request()->input('time'));
                $query->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']]);
        }
        $data = $query->paginate($perPage);
        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }

    public function export()
    {
        $this->throwModel();

        $perService = \App::make('App\Services\PerService');

        $query = $this->_model->newQuery();
        $query->with('comments', 'orderDetails', 'order', 'customer', 'complainType', 'solution', 'staffOrder',
            'staffCare', 'staffCounselor', 'staffComplain');

        $query = $perService->getConditionByUserLogin($query, 'complain');
        $data = $query->get();
        return $data;
    }

    public function insertImages(string $complainId, array $imagesBill, array $imageReceived): void
    {
        $images = [];
        $this->appendImages($images, $imagesBill, $complainId, 1);
        $this->appendImages($images, $imageReceived, $complainId, 0);
        ComplainImage::query()->insert($images);
    }

    private function appendImages(array &$images, array $data, string $complainId, bool $isBill)
    {
        foreach ($data as $image) {
            $images[] = [
                'complain_id' => $complainId,
                'image' => $image,
                'is_bill' => $isBill,
                'created_at' => now()
            ];
        }
    }
}
