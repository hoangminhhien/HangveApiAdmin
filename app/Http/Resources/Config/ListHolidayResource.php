<?php

namespace App\Http\Resources\Config;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class ListHolidayResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'date' => $this->date ? Carbon::parse($this->date)->format('d-m-Y') : null,
        ];
    }
}
