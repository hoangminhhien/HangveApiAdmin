<?php

namespace App\Jobs;

use App\Models\OrderDetail;
use App\Models\OrderDetailNote;
use App\Models\Staff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncOrderDetailNoteToOrderDetailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $step;

    public function __construct($step)
    {
        $this->step = $step;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $id_start = $this->step * 1000 + 1;
        $id_end = $this->step * 1000 + 1000;
        Log::info('Sync Order Detail start ' . $id_start . ' finish ' . $id_end);
        $this->syncWithCursor();
       /* $this->syncWithChunk();*/
        /*$this->syncWithCursorRaw();*/
    }

    private function syncWithCursor()
    {
        $id_start = $this->step * 1000 + 1;
        $id_end = $this->step * 1000 + 1000;
        foreach (
            OrderDetail::query()->select('id', 'note_staff_latest', 'order_id')
                ->whereNull('note_staff_latest')
                ->whereBetween('id', [$id_start, $id_end])->cursor() as $orderDetail
        ) {
            $orderDetailNote = OrderDetailNote::query()->select('id', 'content', 'order_detail_id')
                ->where('order_detail_id', $orderDetail->id)
                ->where('subject_type', Staff::class)
                ->orderBy('created_at', 'DESC')
                ->first();
            if (!$orderDetailNote) {
                continue;
            } else {
                $orderDetail->note_staff_latest = $orderDetailNote->content ?? '';
                $orderDetail->save();
            }
        }
    }

    private function syncWithCursorRaw()
    {
        foreach (
            OrderDetailNote::query()
                ->join('order_details', 'order_details.id', '=', 'order_detail_notes.order_detail_id')
                ->selectRaw('DISTINCT(order_details.id), order_detail_notes.content, order_detail_notes.id as order_detail_note_id')
                ->orderBy('order_detail_notes.created_at', 'DESC')->cursor() as $orderDetail
        ) {
            OrderDetail::query()
                ->where('id', $orderDetail->id)
                ->update(['note_staff_latest' => $orderDetail->content]);
        }
    }

    private function syncWithChunk()
    {
        OrderDetail::query()->select('id', 'note_staff_latest', 'order_id')
           ->whereNull('note_staff_latest')
           ->whereHas('orderDetailNote')
           ->chunkById(100, function ($orderDetails) {
               Log::info('Sync order detail notes ');
               foreach ($orderDetails as $detail) {
                   $orderDetailNote = OrderDetailNote::query()->select('id', 'content', 'order_detail_id')
                       ->where('order_detail_id', $detail->id)
                       ->where('subject_type', Staff::class)
                       ->orderBy('created_at', 'DESC')
                       ->first();
                   $detail->note_staff_latest = $orderDetailNote->content ?? '';
                   $detail->save();
               }
               unset($orderDetails);
           });
    }
}
