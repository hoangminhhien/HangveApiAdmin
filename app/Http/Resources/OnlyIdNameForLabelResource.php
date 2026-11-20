<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OnlyIdNameForLabelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $data = [];
        if(!empty($this->resource)) {
            foreach($this->resource as $item) {
                $data[] = [
                    'id' => $item->id ?? 0,
                    'name' => $item->name ?? '',
                ];
            }
        }
        return $data;
    }
}
