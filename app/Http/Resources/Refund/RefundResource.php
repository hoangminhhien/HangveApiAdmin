<?php

namespace App\Http\Resources\Refund;

use App\Constants\RefundConstant;
use App\Constants\OrderConstant;
use App\Helpers\StatusHelper;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Carbon\Carbon;
use App\Services\OrderPackageService;
use App\Helpers\MediaHelper;

class RefundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $order = $this->order;
        $staff = $this->staff;
        $createdAt = Carbon::parse($this->created_at);
        $timeCreatedAt = $createdAt->format('H:i');
        $dayCreatedAt = $createdAt->format('d/m/Y');
        if($order) {
            $orderStatus = [
                'value' => $order->status,
                'name' => OrderConstant::STATUSES[$order->status],
                'color' => OrderConstant::STATUSES_COLOR[$order->status],
            ];
        }else {
            $orderStatus = null;
        }
        $files = $this->files && $this->thuongdo_id == 0 ? array_map(function($item) {
            return MediaHelper::getFullUrlByValue($item);
        }, json_decode($this->files, true)) : [];

        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'order_id' => $this->order_id,
            'note' => $this->note,
            'file' => MediaHelper::getFullUrlByValue($this->file),
            'file' => $this->thuongdo_id == 0 ? MediaHelper::getFullUrlByValue($this->file) : $this->file,
            'path' => $this->file,
            'amount_cn' => $this->amount_cn,
            'amount_vn' => $this->amount_vn,
            'exchange_rate' => $this->exchange_rate,
            'type' => [
                'value' => $this->type,
                'name' => RefundConstant::TYPES[$this->type],
            ],
            'feedback' => [
                'count' => count($this->refund_feedback),
            ],
            'payment' => $this->refund_payment,
            'order_status' => $orderStatus,
            'customer_name' => $order ? data_get($order->customer, 'name') : null,
            'customer_source' => $order ? data_get($order->customer, 'source') : null,
            'staff_name' => data_get($staff, 'name'),
            'status' => StatusHelper::getInfo($this->status, RefundConstant::class),
            'time_created_at' => $timeCreatedAt,
            'day_created_at' => $dayCreatedAt,
            'created_at' => $createdAt->format('d/m/Y H:i:s'),
            'organization_id' => $this->organization_id,
            'files' => $files,
            'total_refund_cny' => $this->total_refund_cny,
            'total_refund_vn' => $this->total_refund_vn,
            'thuongdo_id'    => $this->thuongdo_id,
            'total_amount_cny' => $this->total_amount_cny,
            'total_amount' => $this->total_amount,
            'order_cost_real_old' => $this->order_cost_real_old,
            'order_cost_real' => $this->order_cost_real,
            'is_multi' => $this->is_multi,
            'money_refund_from_xadal' => $this->money_refund_from_xadal ?? 0,
            'order_code_from_xadal' => $this->order_code_from_xadal ?? '',
            'order_cost_cny' => $order ? round(($order->order_cost / $order->exchange_rate), 2) : 0,
        ];
    }
}
