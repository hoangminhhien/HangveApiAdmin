<?php

namespace App\Http\Resources\Order;

use App\Helpers\TimeHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderLogResource extends JsonResource
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
            'time' => TimeHelper::format($this->created_at),
            'label' => json_decode($this->properties) ?? ['name' => $this->subject->getTableFriendly(), 'color' => $this->subject->getColorLog()],
            // 'content' => sprintf($this->content, data_get($this->causer, 'name')),
            'content' => $this->getContent()
        ];
    }

    public function getContent()
    {
        if (str_contains($this->content, '%s')) {
            return str_replace('%s', data_get($this->causer, 'name'), $this->content);
        }

        return $this->content;
    }
}
