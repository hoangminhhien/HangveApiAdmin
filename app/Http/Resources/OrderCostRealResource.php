<?php

namespace App\Http\Resources;

use App\Constants\OrderCostRealConstant;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\MediaHelper;

class OrderCostRealResource extends JsonResource
{
    public function toArray($request)
    {
        if($this->files && str_contains($this->files, 'hangve')) {
            $files = array_map(function($item) {
                return MediaHelper::getFullUrlByValue($item);
            }, json_decode($this->files, true));
        }elseif($this->file) {
            $files = [$this->file];
        }else {
            $files = [];
        }
        return [
            'id'               => $this->id,
            'order_id'         => $this->order_id,
            'order_code'       => $this->order_code,
            'order_code_po'    => $this->order_code_po,
            'staff_order_id'   => $this->staff_order_id,
            'staff_order_name' => $this->staff_order_name,
            'file'             => $this->file,
            'cost_old'         => $this->cost_old,
            'cost_new'         => $this->cost_new,
            'note'             => $this->note,
            'status' => [
                'value' => $this->status,
                'name' => OrderCostRealConstant::STATUSES[$this->status],
                'color' => OrderCostRealConstant::STATUSES_COLOR[$this->status],
            ],
            'created_at'             => $this->created_at ? date("Y-m-d H:i", strtotime($this->created_at)) : null,
            'files' => $files
        ];
    }
}
