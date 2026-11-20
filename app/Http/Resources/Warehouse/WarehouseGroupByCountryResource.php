<?php

namespace App\Http\Resources\Warehouse;

use App\Http\Resources\ListResource;
use App\Models\Province;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseGroupByCountryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return $this->resource->map(
            function ($item, $key) {
                $data_province = Province::query()->find($key)->only('id', 'name');
                $data_province['id'] = (int) $data_province['id'];
                return [
                    'province' => $data_province,
                    'warehouses' => new ListResource($item, WarehouseResource::class)
                ];
            }
        )->values();
    }
}
