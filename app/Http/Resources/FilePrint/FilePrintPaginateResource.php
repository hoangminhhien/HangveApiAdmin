<?php

namespace App\Http\Resources\FilePrint;

use App\Http\Resources\Traits\HasPaginate;
use Illuminate\Http\Resources\Json\ResourceCollection;
use JsonSerializable;
use App\Http\Resources\FilePrint\FilePrintResource;

class FilePrintPaginateResource extends ResourceCollection
{
    use HasPaginate;
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
                    return new FilePrintResource($item);
                }
            ),
            'pagination' => $this->getPaginateInfo()
        ];
    }
}
