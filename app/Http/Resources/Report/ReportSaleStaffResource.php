<?php

namespace App\Http\Resources\Report;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class ReportSaleStaffResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        return [
            "staff_code" => $this->user->code ?? null,
            "staff_name" => $this->user->name ?? null,
            "staff_id" => $this->user->id ?? null,
            "total_count_order" => (int)$this->total_count_order,
            "total_order_cost" => (float)$this->total_order_cost,
            "total_order_fee" => (float)$this->total_order_fee,
            "total_international_shipping_cost" => (float)$this->total_international_shipping_cost,
            "total_woodworking_cost" => (float)$this->total_woodworking_cost,
            "total_insurance_cost" => (float)$this->total_insurance_cost,
            "total_inspection_cost" => (float)$this->total_inspection_cost,
            "total_shock_proof_cost" => (float)$this->total_shock_proof_cost,
            "total_official_service_fee" => (float)$this->total_official_service_fee
        ];
    }
}
