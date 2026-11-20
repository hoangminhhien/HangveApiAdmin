<?php

namespace App\Http\Resources\Complain;

use App\Http\Resources\ListResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplainAutoMessageResource extends JsonResource
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
            'title' => $this->title,
            'content' => $this->content,
            'staff_id' => $this->staff_id
        ];
    }
}
