<?php

namespace App\Http\Resources\Complain;

use App\Http\Resources\Traits\HasPaginate;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use JsonSerializable;

class AutoMessagePaginationResource extends JsonResource
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
            'items' => $this->collection->transform(
                function ($item) {
                    return new CustomerResource($item);
                }
            ),
            'pagination' => $this->getPaginateInfo()
        ];
    }
}
