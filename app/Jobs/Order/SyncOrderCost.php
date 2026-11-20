<?php

namespace App\Jobs\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;
use Illuminate\Pagination\Paginator;

class SyncOrderCost implements ShouldQueue
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
        $calculatorPage = Order::where('thuongdo_id', '>', 0)->where('status', 'status_4')->where('created_at', '>=','2024-09-01')->paginate(1000);
        $totalPage = $calculatorPage->lastPage();
        for($i = 0; $i <= $totalPage; $i++){
            Paginator::currentPageResolver(function() use ($i) {
                return $i;
            });
            $orders = Order::where('thuongdo_id', '>', 0)->where('status', 'status_4')->where('created_at', '>=','2024-09-01')->with(['orderSuppliers'])->paginate(1000);
            foreach($orders as $order){
                $order->update(['order_cost' => $order->orderSuppliers->sum('order_cost')]);
            }
        }
        \Log::info("-------End queue sync date order--------");
    }
}
