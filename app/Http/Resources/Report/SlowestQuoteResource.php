<?php

namespace App\Http\Resources\Report;

use App\Constants\CustomerConstant;
use App\Constants\OrderConstant;
use App\Constants\ViaConstant;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class SlowestQuoteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'staff_id' => optional($this->staffQuotation)->id,
            'staff_name' => optional($this->staffQuotation)->name,
            'total_order' => $this->total_order
        ];
    }
}
