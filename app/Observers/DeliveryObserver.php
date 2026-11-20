<?php

namespace App\Observers;

use App\Constants\ActivityConstant;
use App\Constants\DeliveryConstant;
use App\Http\Resources\ReportStatusResource;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\ReportDelivery;
use App\Services\ActivityService;
use App\Services\ReportService;

class DeliveryObserver
{
    public function created(Delivery $delivery)
    {
        // Phục vụ lúc đồng bộ dữ liệu
        $syncMaxPing = env('SYNC_MAX_PING', false);
        if ($syncMaxPing) {
            \Log::info('DeliveryObserver created loại');
            return;
        }
        // \Log::info('DeliveryObserver created');
        (new ReportService())->incrementByOrganization(ReportDelivery::class, DeliveryConstant::KEY_STATUS_PENDING);
    }

    public function updated(Delivery $delivery)
    {
        // Phục vụ lúc đồng bộ dữ liệu
        $syncMaxPing = env('SYNC_MAX_PING', false);
        if ($syncMaxPing) {
            \Log::info('DeliveryObserver updated loại');
            return;
        }
        // \Log::info('DeliveryObserver updated');
        if (($old = $delivery->getOriginal('status')) != $delivery->status) {
            (new ReportService())->inDecrementByOrganization(ReportDelivery::class, $delivery->status, $old);
            (new ActivityService())->setLog(
                $delivery,
                DeliveryConstant::STATUSES[$delivery->status],
                ActivityConstant::DELIVERY_STATUS,
                null,
                json_encode(['status' => new ReportStatusResource($delivery->status, DeliveryConstant::STATUSES, DeliveryConstant::STATUSES_COLOR)])
            );
            if($delivery->status == DeliveryConstant::KEY_STATUS_DONE && $delivery->order_type == Order::class) {
                if($delivery->order) {
                    $delivery->order->update(['debt_cost_pending' => 0]);
                }
            }
        }
    }

    public function deleted(Delivery $delivery)
    {
        (new ReportService())->decrementByOrganization(ReportDelivery::class, $delivery->status);
    }
}
