<?php

namespace App\Http\Resources\Report;

use App\Constants\OrderConstant;
use App\Http\Resources\ReportStatusResource;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        $ordersCare = optional($this->reportCustomerOrders);
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone_number,
            'email' => $this->email,
            'source' => $this->source ?? '',
            'source_name' => Customer::LIST_SOURCE[$this->source] ?? '',
            'orders' => $ordersCare ? $this->getListOrders($ordersCare) : []
        ];
    }

    public function getListOrders($ordersCare): array
    {
        $listOrders = [];
        $orders = $ordersCare->all();
        foreach ($orders as $item) {
            $timeStatus = optional($item->orderStatusTime)->{$item->status};
            $timeAgo = $timeStatus ? $this->getTimeAgo($timeStatus) : '';
            $action = '';
            $listOrders[] = [
                'id' => $item->id,
                'code' => $item->code,
                'status' => $item->status,
                'statusLabel' => new ReportStatusResource(
                    $item->status,
                    OrderConstant::STATUSES,
                    OrderConstant::STATUSES_COLOR
                ),
                'timeAgo' => $timeAgo,
                'action' => $action,
                'created_at' => date('d/m/Y H:i', strtotime($item->created_at))
            ];
        }
        return $listOrders;
    }

    public function getTimeAgo($timeStatus): string
    {
        $now = Carbon::now();
        $days = $now->diffInDays($timeStatus);
        if ($days > 0) {
            return $days . ' ngày';
        }
        $hours = $now->diffInHours($timeStatus);
        return $hours . ' giờ';
    }
}
