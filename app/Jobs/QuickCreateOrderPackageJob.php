<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderPackage;
use App\Services\OrderPackageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QuickCreateOrderPackageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $data;
    public $purchaseOrderOuterId;

    public $platform;
    public function __construct($data, $purchaseOrderOuterId, $platform)
    {
        $this->data = $data;
        $this->purchaseOrderOuterId = $purchaseOrderOuterId;
        $this->platform = $platform;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(OrderPackageService $orderPackageService)
    {
       if (!empty($this->data)) {
           $arrayInsertOrderPackage = $this->data;
           foreach ($arrayInsertOrderPackage as $key => $item) {
               if (
                   OrderPackage::query()->where('order_id', $key)
                       ->where('buyder_no', $item['buyder_no'])->first()
               ) {
                   continue;
               }
               if ($this->platform == 'taobao') {
                   $item['code_po'] = $this->purchaseOrderOuterId;
               }
               $order = Order::query()->find($key);
               $package = $orderPackageService->quickCreatePackage($item, $order);
               // Log::info('QuickCreateOrderPackageJob' . json_encode($package));
               Log::info('QuickCreateOrderPackageJob');
           }
       }
    }
}
