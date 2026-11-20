<?php

namespace App\Jobs\Buyder;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\OrderBuyderCategory;
use App\Models\OrderBuyder;
use App\Constants\OrderBuyderConstant;
use Carbon\Carbon;
use App\Constants\TimeConstant;
use App\Traits\PlaceOrder\GetBuyderRequestResource;
use ThuongDo\Buyder\src\Services\ServiceRequest;
use App\Services\OrderPackageService;

class SyncOrderPackageByBuyder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use GetBuyderRequestResource;

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
        $this->syncOrderPackge();
    }

    // Tạo kiện khi có logistic_number
    private function syncOrderPackge()
    {
        $orderPackageService = app()->make(OrderPackageService::class);
        // Tìm tất cả order_buyder đã có logistic_number nhưng chưa cập nhật thời gian update package_time
        $orderBuyders = OrderBuyder::query()
            ->whereNull('package_time')
            ->withWhereHas('subOrderBuyders', fn ($query) => $query->whereNotNull('logistic_number'))
            ->with(['subOrderBuyders', 'orderBuyderCategories'])
            ->get();
        foreach($orderBuyders as $orderBuyder){
            $subOrderBuyders = $orderBuyder->subOrderBuyders;
            $orderBuyderCategories = $orderBuyder->orderBuyderCategories;
            foreach($subOrderBuyders as $key => $subOrderBuyder){
                $order = $subOrderBuyder->order;
                $orderBuyderCategory = data_get($orderBuyderCategories, $key);
                $products = [
                    'id' => data_get($subOrderBuyder, 'sourceable_id'),
                    'quantity' => data_get($subOrderBuyder, 'quantity')
                ];
                $dataCreate = [
                    'products' => [$products],
                    'bill_code' => data_get($subOrderBuyder, 'logistic_number'),
                    'category_id' => data_get($orderBuyderCategory, 'category_id'),
                ];
                $orderPackageService->storeByOrderId($dataCreate, $order);
            }
            $orderBuyder->update(['package_time' => Carbon::now()]);
        }

    }
}
