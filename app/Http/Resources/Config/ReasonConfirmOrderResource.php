<?php

namespace App\Http\Resources\Config;

use Illuminate\Http\Resources\Json\JsonResource;

class ReasonConfirmOrderResource extends JsonResource
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
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'count' => $this->count,
            'time' => json_decode($this->time),
            'time_quote' => $this->time_quote,
        ];
    }
}
