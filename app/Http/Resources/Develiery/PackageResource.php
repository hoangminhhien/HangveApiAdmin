<?php

namespace App\Http\Resources\Develiery;

use App\Constants\PackageConstant;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
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
            'order_id' => $this->order_id,
            'order_code' => optional($this->order)->code,
            'bill_code' => $this->bill_code,
            'weight' => $this->weight,
            'volume' => $this->volume,
            'order_kind_of' => $this->order_kind_of,
            'china_shipping_cost' => $this->amount,
            'amount' => $this->amount,
            'status' => PackageConstant::STATUSES[$this->status],

            'weight_real'         => $this->weight_real,
            // 'china_shipping_cost' => $this->china_shipping_cost,

            'shock_proof_cost'    => $this->shock_proof_cost,
            'storage_cost'        => $this->storage_cost,
            'inspection_cost'     => $this->inspection_cost,
            'insurance_cost'      => $this->insurance_cost,
            'woodworking_cost'    => $this->woodworking_cost,
        ];
    }
}
