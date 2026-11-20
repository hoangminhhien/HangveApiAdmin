<?php

namespace App\Http\Resources\Complain;

use App\Constants\ComplainConstant;
use App\Helpers\StatusHelper;
use App\Helpers\TimeHelper;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use App\Services\OrderPackageService;

class ComplainResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $orderDetails = $this->orderDetails;
        $complainDetails = $this->complainDetails;
        return [
            'id' => $this->id,
            'order_code' => optional($this->order)->code,
            'image' => optional($orderDetails->first())->image,
            'packages' => $this->getPackages($orderDetails),
            'packages_v2' => $this->getPackagesV2($complainDetails),
            'complain_type' => optional($this->complainType)->name,
            'solution' => optional($this->solution)->name,
            'status' => StatusHelper::getInfo($this->status, ComplainConstant::class),
            'created_at' => TimeHelper::format($this->created_at),
            'comment_number' => $this->comment_number
        ];
    }

    private function getPackages($orderDetails)
    {
        return optional(optional($orderDetails->first())->orderPackage)->only(['id', 'bill_code']);
    }

    private function getPackagesV2($complainDetails)
    {
        $orderPackageIds = $complainDetails->pluck('order_package_id')->toArray();
        return (new OrderPackageService())->getPackageIds($orderPackageIds);
    }
}
