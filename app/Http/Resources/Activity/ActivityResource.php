<?php

namespace App\Http\Resources\Activity;

use App\Helpers\TimeHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $causer = $this->causer;

        return [
            'time'       => TimeHelper::format($this->created_at),
            'content'    => $this->content,
            'properties' => $this->properties ? json_decode($this->properties) : '',
            'staff'      => optional($causer)->name ? optional($causer)->name : 'Administrator'
        ];
    }
}
