<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderCompleteResource extends JsonResource
{
    public function toArray($request): array
    {
        $number = 0;
        if(!empty($this->staffGroup)) {
            if(!empty($this->orderParent)) {
                $number = optional($this->orderParent)->reset_position;
            } else {
                $number = $this->reset_position ?? 0;
            }
        }
        return [
            'id' => $this->id ?? '',
            'code' => $this->code ?? '',
            'customer_id' => $this->customer_id ?? '',
            'customer_code' => optional($this->customer)->code,
            'staff_name' => !empty($this->staffGroup) ? optional($this->staffGroup)->name : '',
            'total_amount' => roundMoney($this->getTotalAmount()),
            'order_fee'  => $this->order_fee ?? 0,
            'inspection_cost'  => $this->inspection_cost ?? 0,
            'number' => $number,
            'date_done' => $this->dateDone ? date('d/m/Y', strtotime($this->dateDone)) : '',
            'official_service_fee' => $this->official_service_fee
        ];
    }
}
