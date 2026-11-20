<?php

namespace App\Http\Resources\Complain;

use App\Helpers\MediaHelper;
use App\Constants\ComplainConstant;
use App\Constants\TimeConstant;
use App\Helpers\TimeHelper;
use App\Http\Resources\ListResource;
use App\Http\Resources\ReportStatusResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplainDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $orderDetails = $this->orderDetails;
        $complainDetails = $this->complainDetails;
        return [
            'id' => $complain = $this->id,
            'order' => [
                'id' => optional($this->order)->id,
                'code' => optional($this->order)->code,
            ],
            'image' => optional($orderDetails->first())->image,
            'image_v2' => str_contains(optional($orderDetails->first())->image, 'http') ? optional($orderDetails->first())->image : MediaHelper::getURL(optional($orderDetails->first())->image),
            'type' => optional($this->complainType)->name,
            'time' => TimeHelper::format($this->created_at, TimeConstant::DATETIME_BY_DAY_HI),
            'solution' =>[
                'id' => $this->solution_id,
                'code' => optional($this->solution)->code,
                'name' => optional($this->solution)->name
            ],
            'compensation' => $this->compensation,
            'status' => new ReportStatusResource(
                $this->status,
                ComplainConstant::STATUSES,
                ComplainConstant::STATUSES_COLOR
            ),
            'images' => optional($this->images)->where('is_bill', 0)->pluck('image'),
            'images_bill' => optional($this->images)->where('is_bill', 1)->pluck('image'),
            'products' => $complainDetails->transform(function($item) use($complain) {
                return new ComplainProductDetailResource($item, $complain);
            }),
            'customer' => [
                'id' => data_get($this->customer, 'id'),
                'code' => data_get($this->customer, 'code'),
            ],
        ];
    }
}
