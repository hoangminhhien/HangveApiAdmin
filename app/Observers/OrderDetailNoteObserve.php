<?php

namespace App\Observers;

use App\Constants\ComplainConstant;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderDetailNote;
use Illuminate\Support\Facades\App;

class OrderDetailNoteObserve
{
    /**
     * Handle the Note "created" event.
     *
     * @param  \App\Models\Note  $note
     * @return void
     */
    public function created(OrderDetailNote $note)
    {
        $detail = $note->orderDetail;
        if (!$detail) return;

        Order::where('id', $detail->order_id)->increment('note_number');
        OrderDetail::where('id', $note->order_detail_id)->increment('note_number');

        // Bắn noti cho KH và nhân viên
        if($note->priority == 1) {
            $order = Order::query()->find($detail->order_id);
            $content = trans('note.order_note', ['order_code' => $order->code]);
            $dataOrder = [
                'order_code' => $order->code,
                'content' => $content,
                'order_id' => $order->id,
            ];
            $notificationService = App::make('App\Services\NotificationService');
            $data = [
                'group' => 13,
                'name' => 2015,
                'data' => json_encode($dataOrder),
                'time_create' => time(),
                'customer_id' => $order->customer_id,
                'status' => 1
            ];

            $notificationService->storeNotificationCustomer($order->customer_id, $data);
            /*if ($order->staff_group_id != \Auth::user()->id) {
                $notificationService->storeNoti($order->staff_group_id, $data);
            }*/
        }

        $orderId = $note->orderDetail->order_id;
        $supplierId = $note->supplier_id;
        $exists = OrderDetailNote::query()
            ->join('order_details', 'order_details.id', '=', 'order_detail_notes.order_detail_id')
            ->where('order_details.order_id', $orderId)
            ->where('order_detail_notes.supplier_id', $supplierId)
            ->count();
        if ($exists === 1) {
            Order::withoutEvents(function () use ($orderId) {
                Order::where('id', $orderId)
                    ->increment('supplier_note_count');
            });
        }
    }

    public function deleted(OrderDetailNote $note)
    {
        // \Log::info('OrderDetailNoteObserve deleted');
        Order::query()->find($note->orderDetail->order_id)->decrement('note_number');
        $detail = OrderDetail::query()->find($note->order_detail_id);
        if ($detail) {
            $detail->note_number -= 1;
            $detail->save();
        }

        $orderId = $note->orderDetail->order_id;
        $supplierId = $note->supplier_id;
        $remain = OrderDetailNote::query()
            ->join('order_details', 'order_details.id', '=', 'order_detail_notes.order_detail_id')
            ->where('order_details.order_id', $orderId)
            ->where('order_detail_notes.supplier_id', $supplierId)
            ->count();
        if ($remain === 0) {
            Order::withoutEvents(function () use ($orderId) {
                Order::where('id', $orderId)
                    ->decrement('supplier_note_count');
            });
        }
    }
}
