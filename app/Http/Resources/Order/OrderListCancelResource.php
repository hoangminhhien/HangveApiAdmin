<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Constants\TimeConstant;
use Carbon\Carbon;

class OrderListCancelResource extends JsonResource
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
            'order_id' => $this->order_id,
            'order_code' => $this->order_code,
            'reason_cancel' => $this->reason_cancel,
            'user_name' => $this->user_name,
            'time' => Carbon::parse($this->time)->format(TimeConstant::DATETIME_VI),
        ];
    }
}
