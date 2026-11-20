<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Constants\TimeConstant;

class CustomerWithTrashedResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'deleted_by' => data_get($this->deletedBy, 'name'),
            'staff_group' => data_get($this->staffGroup, 'name'),
            'deleted_at' =>  $this->deleted_at->format(TimeConstant::DATETIME_VI),
        ];
    }
}
