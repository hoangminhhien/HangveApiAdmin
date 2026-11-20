<?php

namespace App\Http\Resources\Report;

use App\Constants\OrderConstant;
use App\Http\Resources\ReportStatusResource;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Order;

class CustomerConsignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $service = '';
        if($this->is_insurance && !$this->is_woodworking && !$this->is_shock_proof) {
            $service = 'Bảo hiểm';
        }
        if($this->is_insurance && $this->is_woodworking && !$this->is_shock_proof) {
            $service = 'Bảo hiểm, đóng gỗ';
        }
        if($this->is_insurance && !$this->is_woodworking && $this->is_shock_proof) {
            $service = 'Bảo hiểm, chống sốc';
        }
        if($this->is_insurance && $this->is_woodworking && $this->is_shock_proof) {
            $service = 'Bảo hiểm, đóng gỗ, chống sốc';
        }
        if(!$this->is_insurance && $this->is_woodworking && !$this->is_shock_proof) {
            $service = 'Đóng gỗ';
        }
        if(!$this->is_insurance && $this->is_woodworking && $this->is_shock_proof) {
            $service = 'Đóng gỗ, chống sốc';
        }
        if(!$this->is_insurance && !$this->is_woodworking && $this->is_shock_proof) {
            $service = 'Chống sốc';
        }
        return [
            'id' => $this->id,
            'bill_code' => $this->bill_code,
            'code_warehouse' => $this->code_warehouse,
            'staff_id' => optional($this->staffGroup)->id,
            'staff_name' => optional($this->staffGroup)->name,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_code' => $this->customer->code,
            'type_quantitative' => $this->type_quantitative,
            'weight' => $this->weight,
            'm3' => $this->m3,
            'discount_percent' => $this->discount_percent,
            'discount_cost' => $this->discount_cost,
            'service' => $service,
            'amount' => $this->amount,
            'package_status_time' => $this->date_done ? Carbon::parse($this->date_done)->format('d-m-Y') : '',
            'official_service_fee' => $this->official == 1 ? 0 : $this->official_service_fee 
        ];
    }
}
