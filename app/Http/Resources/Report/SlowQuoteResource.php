<?php

namespace App\Http\Resources\Report;

use App\Constants\CustomerConstant;
use App\Constants\OrderConstant;
use App\Constants\ViaConstant;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class SlowQuoteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'staff_id' => optional($this->staffQuotation)->id,
            'staff_name' => optional($this->staffQuotation)->name,
            'order_code' => $this->code,
            'created_at' => Carbon::parse($this->created_at)->format('d/m/Y H:i'),
            'time_quotation' => !empty($this->orderStatusTime->status_1) ? Carbon::parse($this->orderStatusTime->status_1)->format('d/m/Y H:i') : '',
        ];
    }
}
