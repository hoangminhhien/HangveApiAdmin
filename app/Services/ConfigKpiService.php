<?php


namespace App\Services;

use Illuminate\Http\Request;
use App\Models\ConfigKpi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use App\Constants\ConfigKpiConstant;
use App\Http\Resources\ListResource;
use App\Http\Resources\Config\ConfigKpiResource;
use App\Http\Resources\Resource;

class ConfigKpiService extends BaseService
{
    protected string $_listResource = ListResource::class;
    protected string $_resource = ConfigKpiResource::class;
    protected string $_resourceBase = Resource::class;

    public function store(array $data)
    {
        $this->throwModel();
        $config = array_map(function($item) {
            return [
                'name' => $item['name'],
                'min' => $item['min'],
                'max' => $item['max'],
                'value' => $item['value'],
            ];
        }, $data['config']);

        return $this->_model->create([
            'type' => $data['type'],
            'month_start' => $data['date_start'],
            'date_start' => Carbon::createFromFormat('Y-m', $data['date_start'])->startOfMonth(),
            'config' => json_encode($config)
        ]);
    }

    public function update(string $id, array $data)
    {
        $this->throwModel();
        $record = $this->_model->newQuery()->findOrFail($id);
        $config = array_map(function($item) {
            return [
                'name' => $item['name'],
                'min' => $item['min'],
                'max' => $item['max'],
                'value' => $item['value'],
            ];
        }, $data['config']);
        $record->update([
            'month_start' => $data['date_start'],
            'date_start' => Carbon::createFromFormat('Y-m', $data['date_start'])->startOfMonth(),
            'config' => json_encode($config)
        ]);
        return $record;
    }

    public function list()
    {
        // $type = in_array(request()->input('type'), [1, 2, 3]) ? request()->input('type') : 1;
        $configs = $this->_model->orderBy('id', 'desc')->get();
        return resSuccessWithinData(new $this->_listResource($configs, $this->_resource));
    }

    public function latestByType()
    {
        $type = in_array(request()->input('type'), [ConfigKpiConstant::TYPE_QUOTE, ConfigKpiConstant::TYPE_ORDER, ConfigKpiConstant::TYPE_COMPLAIN]) ? request()->input('type') : 1;
        $config = $this->_model->where('type', $type)->orderBy('id', 'desc')->first();
        if($config) {
            return resSuccessWithinData(new $this->_resource($config));
        }
        return resSuccessWithinData(new $this->_resourceBase($config));
    }
}
