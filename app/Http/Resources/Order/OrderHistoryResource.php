<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderHistoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id ?? '',
            'order_id' => $this->order_id ?? '',
            'content' => $this->content ?? '',
            'date_created' => date('d-m-Y H:i:s', strtotime($this->date)),
            'user' => $this->user ?? []
        ];
    }
}
