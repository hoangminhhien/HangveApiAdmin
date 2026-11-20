<?php

namespace App\Jobs\ReportCustomer;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;
use App\Models\Consignment;
use App\Models\ReportCustomer;

class UpdateCountOrder implements ShouldQueue
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
        \Log::info('-----Start queue count order-----');
        Order::query()->select(\DB::raw('count(*) as order_count, customer_id'))
                ->groupBy('customer_id')
                ->chunk(1000, function($orders)
                {
                    foreach($orders as $order){
                        ReportCustomer::where('customer_id', $order->customer_id)
                        ->update([
                            'orders_number' => $order->order_count
                        ]);

                    }
                });
        Consignment::query()->select(\DB::raw('count(*) as order_count, customer_id'))
                ->groupBy('customer_id')
                ->chunk(1000, function($orders)
                {
                    foreach($orders as $order){
                        ReportCustomer::where('customer_id', $order->customer_id)
                        ->update([
                            'consignment_number' => $order->order_count
                        ]);

                    }
                });
        ReportCustomer::query()
                ->chunk(1000, function($reportCustomers)
                {
                    foreach($reportCustomers as $reportCustomer){
                        $reportCustomer->orders_number_increment = $reportCustomer->orders_number + $reportCustomer->consignment_number;
                        $reportCustomer->save();

                    }
                });             
        \Log::info('-----End queue count order-----');
    }
}
