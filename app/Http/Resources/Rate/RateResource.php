<?php

namespace App\Http\Resources\Rate;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class RateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $customer = $this->customer;
        $order = $this->order;

        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'customer_code' => $this->customer_code,
            'sale_staff_rate' => $this->sale_staff_rate,
            'order_staff_rate' => $this->order_staff_rate,
            'transport_rate' => $this->transport_rate,
            'comment' => $this->comment,
            'customer_name' => $customer ? $customer->name : null,
            'customer_id' => $customer ? $customer->id : null,
            'order_id' => $order ? $order->id : null,
            'created_at' => date('d-m-Y H:i:s', strtotime($this->created_at))
        ];
    }
}
