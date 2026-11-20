<?php


namespace App\Services;

use Illuminate\Http\Request;
use App\Models\ConfigInternational;
use App\Http\Resources\ConfigInternational\ConfigInternationalShipResource;
use App\Http\Resources\ConfigInternational\ConfigInternationalListResource;
use App\Http\Resources\ConfigInternational\ConfigInternationalPaginateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use App\Models\ConfigInternationalShip;
use App\Constants\ActivityConstant;


class ConfigInternationalShipService extends BaseService
{
    // protected string $_paginateResource = ConfigInternationalPaginateResource::class;
    // protected string $_listResource = ConfigInternationalListResource::class;
    protected string $_resource = ConfigInternationalShipResource::class;

    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();
        $official = request()->input('official') ?? ConfigInternationalShip::OFFICIAL_1;
        $data = $this->_model->newQuery()->where('official', $official)->paginate($perPage);
        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }

    public function store(array $data)
    {
        $this->throwModel();
        $userOnline = \Auth::user();

        $data['matrix_price'] = json_encode($data['matrix_price'], JSON_UNESCAPED_UNICODE);
        $data['list_province'] = json_encode($data['list_province'], JSON_UNESCAPED_UNICODE);

        $data['organization_id'] = $userOnline->organization_id;


        // thêm log tạo mới bảng phí
        $logService = \App::make('App\Services\ActivityService');
        $content = 'Thêm phí vận chuyển mới áp dụng từ ngày ' . Carbon::parse($data['date_start'])->format('d-m-Y');
        $typeLog = data_get($data, 'official') == ConfigInternationalShip::OFFICIAL_2 ? ActivityConstant::CONFIG_LOG_OFFICIAL : ActivityConstant::CONFIG_LOG;
        $logService->setConfigLog($userOnline, $content, $typeLog);

        return $this->_model->create($data);
    }

    public function update(string $id, array $data)
    {
        $userOnline = \Auth::user();
        $configInterModel = ConfigInternationalShip::query();
        $configInterDB = $configInterModel->where('id', '=', $id)->first();
        $this->throwModel();
        $record = $this->_model->newQuery()->findOrFail($id);

        $data['matrix_price'] = json_encode($data['matrix_price'], JSON_UNESCAPED_UNICODE);
        $data['list_province'] = json_encode($data['list_province'], JSON_UNESCAPED_UNICODE);

        // thêm log cap nhat bảng phí
        $logService = \App::make('App\Services\ActivityService');
        $content = 'Cập nhật phí vận chuyển áp dụng từ ngày ' . Carbon::parse($data['date_start'])->format('d-m-Y');
        $typeLog = data_get($data, 'official') == ConfigInternationalShip::OFFICIAL_2 ? ActivityConstant::CONFIG_LOG_OFFICIAL : ActivityConstant::CONFIG_LOG;
        $logService->setConfigLog($userOnline, $content, $typeLog);

        $record->update($data);
        return $record;
    }
}
