<?php

namespace App\Http\Resources\Report;

use App\Constants\CustomerConstant;
use App\Constants\OrderConstant;
use App\Constants\ViaConstant;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class FeeServiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'staff_id' => optional($this->staffGroup)->id,
            'staff_name' => optional($this->staffGroup)->name,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_code' => $this->customer->code,
            'insurance_cost' => $this->insurance_cost,
            'woodworking_cost' => $this->woodworking_cost,
            'shock_proof_cost' => $this->shock_proof_cost,
            'inspection_cost' => $this->total_inspection_cost,
            'amount' => $this->amount,
        ];
    }
}
