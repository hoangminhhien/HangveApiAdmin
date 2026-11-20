<?php

namespace App\Jobs\Customer;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Customer;

class SyncLastOrderAtPackJob implements ShouldQueue
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
        // Chỉ chạy KH có đơn từ đầ u năm 2025 trở đi
        $time =  '2025-01-01 00:00:59';
        $builder = Customer::query()
                ->where(function($q) use ($time) {
                    $q->whereNull('last_order_at')
                        ->orWhere('last_order_at', '<', $time);
                })
                ->whereHas('packages', function ($q) use ($time){
                    $q->where('created_at', '>', $time);
                })
                ->with(['packages' => function($q) {
                    $q->orderBy('created_at', 'desc');
                }])
                ->chunk(1000, function($customers)
                {
                    \Log::info($customers->count());
                    foreach($customers as $key => $customer) {
                        $lastOrderAt = $customer->last_order_at;
                        $lastPackage = $customer->packages->first();
                        $customer->update([
                            'last_order_at' => $lastPackage->created_at
                        ]);
                    }
                });

    }
}
