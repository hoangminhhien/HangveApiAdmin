<?php

namespace App\Http\Resources\Config;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ConfigKpiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $config = json_decode($this->config, true);
        return [
            'id' => $this->id,
            'type' => $this->type,
            'date_start' => Carbon::createFromFormat('Y-m-d', $this->date_start)->format('Y-m'),
            'config' => $config
        ];
    }
}
