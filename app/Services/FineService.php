<?php


namespace App\Services;


use App\Http\Resources\FineResource;
use App\Http\Resources\ListResource;
use App\Models\Consignment;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class FineService extends BaseService
{
    protected string $_resource = FineResource::class;

    /**
     * @param int $perPage
     * @return JsonResponse
     */
    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();

        //$perService = \App::make('App\Services\PerService');

        $query = $this->_model->newQuery();
        $query->with('staff', 'cause', 'orderPackage');

        //$query = $perService->getConditionByUserLogin($query);
        $data = $query->paginate($perPage);

        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }

    public function store(array $data)
    {
        if(isset($data['order_id'])) {
            $data['source_type'] = Order::class;
            $data['source_id'] = $data['order_id'];
        }
        $data['cause_id'] = getCurrentUser()->id;
        $fine =  parent::store($data);
        // Thêm noti
        $this->updateFineNotiLogic($fine);

        // Thêm ảnh
        $fineImageModel = \App::make('App\Models\FineImage');
        foreach ($data['images'] as $key => $image) {
            $temp = [
                'fine_id' => (int) $fine->id,
                'image'   => $image
            ];
            $fineImageModel->create($temp);
        }

        return $fine;
    }

    public function updateFineNotiLogic($fine)
    {
        $notificationService = \App::make('App\Services\NotificationService');

        $data_fine = [
            'fine_id'    => $fine->id,
        ];

        $data = [
            'group'           => 6,
            'name'            => 6001, // Thêm nộp phạt
            'data'            => json_encode($data_fine),
            'time_create'     => time(),
            'status'          => 1
        ];

        $notificationService->storeNoti($fine->user_id, $data);
    }

}
