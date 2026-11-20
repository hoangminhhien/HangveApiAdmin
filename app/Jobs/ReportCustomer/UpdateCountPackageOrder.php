<?php

namespace App\Jobs\ReportCustomer;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class UpdateCountPackageOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $step;
    protected $i;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($step, $i)
    {
        $this->step = $step;
        $this->i = $i;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $step = $this->step;
        $i = $this->i;

        \Log::info('UpdateCountPackageOrder: ' . $i);

        $orders = Order::query()
            ->select(DB::raw('COUNT(order_package.id) as totalPackage, orders.id'))
            ->join('order_package','orders.id','=','order_package.order_id')
            ->groupBy('orders.id')
            ->limit($step)
            ->offset($i)
            ->get();

        foreach ($orders->chunk(1000) as $chunk) {
            foreach ($chunk as $order) {
                $user = Order::withoutEvents(function () use ($order) {
                    $order->packages_number = $order->totalPackage;
                    $order->save();
                    return 1;
                });
            }
        }
    }
}
