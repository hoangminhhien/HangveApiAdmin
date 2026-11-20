<?php

namespace App\Http\Resources;

use App\Constants\OrderCostRealConstant;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderCostRealLogResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                 => $this->id,
            'user_id'            => $this->user_id,
            'user_name'          => $this->user_name,
            'order_cost_real_id' => $this->order_id,
            'order_code'         => $this->order_code,
            'cost_old'           => $this->cost_old,
            'cost_new'           => $this->cost_new,
            'note'               => $this->note,
            'status'             => [
                'value' => $this->status,
                'name' => OrderCostRealConstant::STATUSES[$this->status],
                'color' => OrderCostRealConstant::STATUSES_COLOR[$this->status],
            ],
            'created_at' => date('Y/m/d H:i', strtotime($this->created_at))
        ];
    }
}
