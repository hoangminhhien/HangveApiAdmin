<?php

namespace App\Jobs\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\OrderStatusTime;
use Illuminate\Pagination\Paginator;

class SyncDateByOrderStatusTime implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \Log::info("-------Start queue sync date order--------");
        $calculatorPage = OrderStatusTime::whereNotNull('status_2')->orWhereNotNull('status_3')->paginate(3000);
        $totalPage = $calculatorPage->lastPage();
        for($i = 0; $i <= $totalPage; $i++){
            Paginator::currentPageResolver(function() use ($i) {
                return $i;
            });
            $statusTimes = OrderStatusTime::whereNotNull('status_2')->orWhereNotNull('status_3')->with(['order'])->paginate(3000);
            foreach($statusTimes as $statusTime){
                $order = $statusTime->order;
                if($order && ($order->date_purchased == null || $order->date_ordered == null)) {
                    if($order->date_purchased == null) {
                        $order->date_purchased = $statusTime->status_2;
                    }
                    if($order->date_ordered == null) {
                        $order->date_ordered = $statusTime->status_3;
                    }
                    $order->save();
                }
            }
        }
        \Log::info("-------End queue sync date order--------");
    }
}
