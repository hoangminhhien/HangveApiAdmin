<?php

namespace App\Http\Resources\Package;

use App\Constants\PackageConstant;
use App\Http\Resources\ReportStatusResource;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageStoreByOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $packageStatusTime = $this->statusTimes()->orderBy('id', 'DESC')->first();
        return [
            'id' => $this->id,
            'bill_code' => $this->bill_code,
            'code_po' => $this->code_po,
            'package_status_time' => $packageStatusTime ? Carbon::parse($packageStatusTime->updated_at)->format('d-m-Y H:i:s') : '',
            'transporter' => optional($this->transporterRelation)->name ?? $this->transporter,
            'type' => [
                $this->order_kind_of,
                optional($this->categoryRelation)->name ?? $this->category,
                optional($this->order)->ecommerce,
            ],
            'status' => new ReportStatusResource(
                $this->status,
                PackageConstant::STATUSES,
                PackageConstant::STATUSES_COLOR
            ),
            'staff_id' => optional($this->staffOrder)->name
        ];
    }
}
