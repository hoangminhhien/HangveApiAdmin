<?php

namespace App\Http\Resources\Report;

use App\Constants\UserConstant;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffComplainResource extends JsonResource
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
            'id'                => $this->id,
            'code'              => $this->code,
            'name'              => $this->name,
            'totalPending'      => $this->totalPending ?? 0,
            'totalProcessing'   => $this->totalProcessing ?? 0,
            'totalProcessed'    => $this->totalProcessed ?? 0,
            'totalAll'          => $this->totalAll ?? 0,
            'setOrderStatus'    => $this->reset_staff_allow
        ];
    }
}
