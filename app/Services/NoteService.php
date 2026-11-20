<?php


namespace App\Services;


use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderDetailNote;
use App\Models\OrderNote;
use App\Models\NoteHistory;
use App\Models\Staff;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class NoteService implements Service
{
    public function store(string $model, array $data)
    {
        return (new $model)::query()->create(
            [
                'order_id' => $data['order_id'] ?? null,
                $data['column'] => $data['id'],
                'subject_id' => getCurrentUser()->id,
                'subject_type' => Staff::class,
                'is_lastest' => $data['is_lastest'] ?? 0,
                'content' => $data['content'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'is_public' => $data['is_public'] ?? null,
                'order_provisional_date' => $data['order_provisional_date'] ?? null
            ]
        );
    }

    public function storeNoteDetail(string $model, array $data)
    {
        return (new $model)::query()->create(
            [
                'order_id' => $data['order_id'] ?? null,
                $data['column'] => $data['id'],
                'subject_id' => getCurrentUser()->id,
                'subject_type' => Staff::class,
                'is_lastest' => $data['is_lastest'] ?? 0,
                'content' => $data['content'],
                'priority' => $data['priority'] ?? 0,
                'supplier_id' => $data['supplier_id'] ?? null,
                'is_public' => $data['is_public'] ?? null,
                'order_provisional_date' => $data['order_provisional_date'] ?? null
            ]
        );
    }

    public function updateIsLastest($data, $supplierId)
    {
        $orderId = $data['order_id'];
        $orderDetailId = $data['id'];
        return OrderDetailNote::query()
            ->where('order_id', $orderId)
            ->where('order_detail_id', $orderDetailId)
            ->where('supplier_id', $supplierId)
            ->update(['is_lastest' => 0]);
    }


    public function update(string $model, array $data, $order_id){
        return (new $model)::query()->where('order_id', $order_id)->where('is_public', $data['is_public'])->update(
            [
                'order_id' => $data['order_id'] ?? null,
                $data['column'] => $data['id'],
                'subject_id' => getCurrentUser()->id,
                'subject_type' => Staff::class,
                'content' => $data['content'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'is_public' => $data['is_public'] ?? null
            ]
        );
    }

    /**
     * @param  string  $orderId
     * @param  string  $supplierId
     * @param  bool  $isPublic
     * @return mixed
     */
    public function getOrderNote(string $orderId, string $supplierId, bool $isPublic)
    {
        return optional(
            OrderNote::query()->where(
                [
                    'subject_type' => $isPublic ? Customer::class : Staff::class,
                    'supplier_id' => $supplierId,
                    'order_id' => $orderId,
                    'is_public' => $isPublic
                ]
            )->orderByDesc('id')->first()
        )->content;
    }

    public function editNoteCustomer($id, $content, $priority = 0)
    {
        // update
        $orderDetailNote =  OrderDetailNote::query()->findOrFail($id);
        $order_detail_id = $orderDetailNote->order_detail_id ?? 0;
        $orderDetailNoteLatest = OrderDetailNote::query()->where('order_detail_id', $order_detail_id)
            ->where('subject_type', Staff::class)
            ->orderBy('created_at', 'DESC')
            ->first();
        $orderDetailNote->update(['content' => $content, 'priority' => $priority]);
        if($priority == 1) {
            $order = Order::query()->find($orderDetailNote->orderDetail->order_id);
            $contentB = trans('note.order_note', ['order_code' => $order->code]);
            $dataOrder = [
                'order_code' => $order->code,
                'content' => $contentB,
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
            if ($order->staff_group_id != \Auth::user()->id) {
                $notificationService->storeNoti($order->staff_group_id, $data);
            }
        }
        if ($orderDetailNote->id == $orderDetailNoteLatest->id) {
            OrderDetail::query()->where('id', $order_detail_id)->update(['note_staff_latest' => $content]);
        }

        // Nâng cấp nếu tìm dc các các note tạo cùng thời điểm tạo thì update lại vào cái tổng
        $orderDetailNoteDBs = OrderDetailNote::query()->where('created_at', $orderDetailNoteLatest->created_at)
                                                      ->where('order_detail_id', $order_detail_id)
                                                      ->where('order_id', $orderDetailNote->order_id)
                                                      ->get();
        if (count($orderDetailNoteDBs) > 1 && $orderDetailNote->created_at == $orderDetailNoteLatest->created_at) {
            \Log::info('update note muity');
            $content = '';
            $newLine = "\r\n\r\n";
            foreach ($orderDetailNoteDBs as $key => $itemNote) {
                $content .= $itemNote->content . $newLine;
            }
            OrderDetail::query()->where('id', $order_detail_id)->update(['note_staff_latest' => $content]);
        }

        // save histories
        $this->saveNoteHistory(OrderDetailNote::class, $id, $content);
    }

    public function saveNoteHistory(string $model, int $id, string $content)
    {
        $user = Auth::user();

        $dataHistory = [
            'subject_type'    => $model,
            'subject_id'      => $id,
            'user_id'         => $user->id,
            'content'         => $content,
            'organization_id' => $user->organization_id,
        ];
        NoteHistory::query()->create($dataHistory);
    }

    public function deleteNoteOrderDetail(int $id)
    {
        // delete note
        OrderDetailNote::query()->findOrFail($id)->delete();
        // delete histories
        NoteHistory::query()->where(
            ['subject_type' => OrderDetailNote::class,  'subject_id' => $id]
        )->delete();
    }
}
