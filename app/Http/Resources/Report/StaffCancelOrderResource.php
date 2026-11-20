<?php

namespace App\Http\Resources\Report;

use App\Constants\UserConstant;
use App\Services\OrderService;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffCancelOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'total_canceled_orders' => $this->total_canceled_orders,
        ];
    }
}
