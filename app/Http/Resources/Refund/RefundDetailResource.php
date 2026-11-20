<?php

namespace App\Http\Resources\Refund;

use App\Helpers\MediaHelper;
use App\Constants\RefundConstant;
use App\Helpers\TimeHelper;
use Illuminate\Http\Resources\Json\JsonResource;
class RefundDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $files = $this->files ? array_map(function($item) {
            return [
                "file" =>  $item,
                "file_temp" => MediaHelper::getFullUrlByValue($item)
            ];
        }, json_decode($this->files, true)) : [];
        return [
            'id' => $refund = $this->id,
            'order_code' => $this->order_code,
            'amount_cn' => $this->amount_cn,
            'exchange_rate' => $this->exchange_rate,
            'amount_vn' => $this->amount_vn,
            'type' => $this->type,
            'note' => $this->note,
            'file' => MediaHelper::getFullUrlByValue($this->file),
            'path' => $this->file,

            'list_order_detail' => json_decode($this->order_detail_change, true),
            'china_shipping_cost' => $this->china_shipping_cost,
            'order_id' => $this->order_id,
            'order_code' => $this->order_code,
            'order_status' => $this->order_status,
            'order_percent' => $this->order_percent,
            'total_money_item' => $this->total_money_item,
            'total_fee_service' => $this->total_fee_service,
            'total_fee_inspection' => $this->total_fee_inspection, // Tiền Việt
            'order_is_inspection' => data_get($this->order, 'is_inspection'), // Kiểm đếm
            'files' => $files,
            'total_refund_cny' => $this->total_refund_cny,
            'total_refund_vn' => $this->total_refund_vn,
            'order_cost_real_old' => $this->order_cost_real_old,
            'order_cost_real' => $this->order_cost_real
        ];
    }
}
