<?php

namespace App\Http\Resources;

use App\Models\Ecommerce;
use Illuminate\Http\Resources\Json\JsonResource;

class EcommerceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id ?? 0,
            'name' => $this->name ?? '',
            'key' => $this->key ?? '',
            'time' => $this->time ?? 0,
            'time_type' => $this->time_type ?? '',
            'time_type_text' => Ecommerce::LIST_TIME_TYPE[$this->time_type] ?? ''
        ];
    }
}
