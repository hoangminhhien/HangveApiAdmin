<?php

namespace App\Http\Resources\Report;

use App\Constants\UserConstant;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffQuotationResource extends JsonResource
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
            'totalWaitingQuote' => $this->totalWaitingQuote,
            'totalQuoted'       => $this->totalQuoted,
            'totalAll'          => $this->totalAll,
            'setOrderStatus'    => $this->reset_staff_allow
        ];
    }
}
