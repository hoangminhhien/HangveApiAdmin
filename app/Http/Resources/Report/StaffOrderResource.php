<?php

namespace App\Http\Resources\Report;

use App\Constants\UserConstant;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffOrderResource extends JsonResource
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
            'totalWaitingOrder' => $this->totalWaitingOrder,
            'totalPayment'      => $this->totalPayment,
            'totalOrdered'      => $this->totalOrdered,
            'totalAll'          => $this->totalWaitingOrder + $this->totalPayment + $this->totalOrdered,
            'setOrderStatus'    => $this->reset_staff_allow
        ];
    }
}
