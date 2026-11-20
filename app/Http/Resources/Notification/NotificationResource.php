<?php

namespace App\Http\Resources\Notification;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        $content = $this->getContent();
        /*if ($this->group == 2) {
            $content = $data->content ?? '';
        }*/
        return [
            'id' => $this->id,
            'group' => $this->group,
            'name' => $this->name,
            'group_txt' => $this->getGroupText(),
            'content' => $content,
            'time_create' => $this->getTimeCreate(),
            'time_seen' => $this->time_seen,
            'data' => json_decode($this->data),
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null
        ];
    }
}
