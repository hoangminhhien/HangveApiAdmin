<?php

namespace App\Http\Resources\Order;

use App\Constants\OrderConstant;
use App\Http\Resources\ReportStatusResource;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class OrderAdvanceMoneyResource extends JsonResource
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
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer_code' => optional($this->customer)->code,
            'customer_name' => optional($this->customer)->name,
            'customer_phone' => optional($this->customer)->phone_number,
            'customer_source' => optional($this->customer)->source,
            'order_id' => $this->order_id,
            'order_code' => $this->order_code,
            'amount' => $this->amount,
            'staff_id' => $this->staff_id,
            'staff_name' => optional($this->staff)->name,
            'manager_id' => $this->manager_id,
            'manager_name' => optional($this->manager)->name,
            'status' => new ReportStatusResource(
                $this->status,
                OrderConstant::ORDER_ADVANCE_STATUSES,
                OrderConstant::ORDER_ADVANCE_STATUSES_COLOR
            ),
            'created_at' => date('Y/m/d H:i', strtotime($this->created_at)),
            'updated_at' => date('Y/m/d H:i', strtotime($this->updated_at)),
            'organization_id' => $this->organization_id,
//            'staff' => [
//                'order' => new OnlyIdNameResource($this->staffOrder),
//                'sale' => new OnlyIdNameResource($this->staffSale),
//                'care' => new OnlyIdNameResource($this->staffCare),
//                'counselor' => new OnlyIdNameResource($this->staffCounselor),
//                'quotation' => new OnlyIdNameResource($this->staffQuotation),
//            ],
        ];
    }
}
