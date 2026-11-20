<?php

namespace App\Http\Resources\Notification;

use Illuminate\Http\Resources\Json\ResourceCollection;

class NotificationListResource extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        return $this->collection->transform(
            function ($item) {
                return new NotificationResource($item);
            }
        );
    }
}
