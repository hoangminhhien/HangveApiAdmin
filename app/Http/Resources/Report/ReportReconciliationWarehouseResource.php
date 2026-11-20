<?php

namespace App\Http\Resources\Report;

use App\Constants\PackageConstant;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportReconciliationWarehouseResource extends JsonResource
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
            'customer_id' => $this->customer_id,
            'customer_code' => $this->customer_code,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'user_id' => $this->user_id,
            'created_at_date' => $this->created_at ? Carbon::parse($this->created_at)->format('d/m/Y') : null,
            'created_at_time' => $this->created_at ? Carbon::parse($this->created_at)->format('H:i:s') : null,
            'user_name' => $this->user_name,
            'user_code' => $this->user_code,
            'order_package_id' => $this->order_package_id,
            'order_package_type' => $this->order_package_type,
            'order_package_type_text' => $this->order_package_type == 1 ? 'Order' : 'Ký gửi',
            'order_code' => $this->order_code,
            'order_id' => $this->order_id,
            'bill_code' => $this->bill_code,
            'status' => $this->status,
            'status_text' => PackageConstant::STATUSES[$this->status] ?? '',
            'status_color' => PackageConstant::STATUSES_COLOR[$this->status] ?? '',
            'type' => $this->type,
            'type_text' => $this->type == 1 ? 'Tại kho' : 'Giao hàng',
            'warehouse_code' => $this->warehouse_code,
            'print_receipt_code' => $this->print_receipt_code,
            'transport_fee' => $this->transport_fee,
            'other_fee' => $this->other_fee,
            'total_amount' => $this->total_amount,
        ];
    }
}
