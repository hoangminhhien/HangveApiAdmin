<?php

namespace App\Http\Resources\ConfigInternational;

use Illuminate\Http\Resources\Json\JsonResource;

class ConfigInternationalShipResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'type'          => $this->type,
            'date_start'    => $this->date_start->format('Y-m-d'),
            'matrix_price'  => json_decode($this->matrix_price, true),
            'list_province' => json_decode($this->list_province, true),
        ];
    }
}
