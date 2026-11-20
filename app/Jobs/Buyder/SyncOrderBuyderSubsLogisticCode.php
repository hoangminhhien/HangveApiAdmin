<?php

namespace App\Jobs\Buyder;

use App\Constants\TimeConstant;
use App\Models\OrderBuyder;
use App\Services\OrderPackageService;
use App\Traits\PlaceOrder\CreateLocalDataTrait;
use App\Traits\PlaceOrder\GetBuyderRequestResource;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ThuongDo\Buyder\src\Services\ServiceRequest;

class SyncOrderBuyderSubsLogisticCode implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use GetBuyderRequestResource;
    use CreateLocalDataTrait;

    /**
     * Nguồn đặt hàng, sử dụng để lấy options chi tiết đơn hàng tương ứng
     *
     * @var string
     */
    private string $source;

    /**
     * Link lấy chi tiết đơn hàng
     *
     * @var string
     */
    private string $url;

    /**
     * Eloquent Collection chứa các order_buyders cần làm việc
     *
     * @var Collection
     */
    private Collection $orderBuyders;

    /**
     * Create a new job instance.
     *
     * @param string $source
     * @param string $url
     * @param Collection $orderBuyders
     * @return void
     */
    public function __construct(string $source, string $url, Collection $orderBuyders)
    {
        $this->source = $source;
        $this->url = $url;
        $this->orderBuyders = $orderBuyders;
    }

    /**
     * Job này cập nhật 'order_buyder_subs.logistic_number'
     *
     * @return void
     */
    public function handle(): void
    {
        $source = $this->source;
        $url = $this->url;
        $orderBuyders = $this->orderBuyders;

        $method = 'syncLogisticCode' . ucfirst($source);
        $this->{$method}($orderBuyders, $source, $url);
    }

    private function syncLogisticCodeTaobao(Collection $orderBuyders, string $source, string $url): void
    {
        // service request, dùng để thực hiện HTTP request
        $serviceRequest = app()->make(ServiceRequest::class);

        // package service, dùng để tạo kiện
        $packageService = app()->make(OrderPackageService::class);

        // các đơn con (order_buyder_subs) chưa cập nhật được mã vận đơn
        // tổng hợp lại để bắn job reupdate
        $notYetHaveLogisticCodeOrderBuyderSubs = [];

        $orderBuyders->loadMissing('subOrderBuyders');
        $orderBuyders->each(function ($orderBuyder) use (
            $orderBuyders, $url, $source, $serviceRequest, $packageService, &$notYetHaveLogisticCodeOrderBuyderSubs
        ) {
            // với mỗi order_buyder sẽ lấy lại options tương ứng cho request
            $resources = $this->getOrderDetailRequestResource(
                $source,
                data_get($orderBuyder, 'taobao_user_id'),
                data_get($orderBuyder, 'buyder_id')
            );
            $options = data_get($resources, 'options');
            $result = $serviceRequest->handleGet($url, $options);

            if (data_get($result, 'code') != 200) {
                return true;
            }

            // lấy mọi order_buyder_subs chưa có logistic_number của order_buyders ở iteration hiện tại
            $orderBuyderSubs = $orderBuyder->subOrderBuyders;

            // cập nhật logistic_number, tạo kiện với từng order_buyder_subs
            $subOrderVO = data_get($result, 'data.0.subOrderVO');
            foreach ($subOrderVO as $vos) {
                $logisticCode = data_get($vos, 'logisticOrders.0.logistic_number');
                $orderBuyderSub = $orderBuyderSubs->firstWhere('mp_sku_id', data_get($vos, 'skuId'));
                if ($orderBuyderSub) {
                    $orderBuyderSub->update(['logistic_number' => $logisticCode]);
                }

            }
        });
    }

    private function syncLogisticCode1688(Collection $orderBuyders, string $source, string $url): void
    {
        // dd($orderBuyders, $source, $url);
        // service request, dùng để thực hiện HTTP request
        $serviceRequest = app()->make(ServiceRequest::class);
        $orderBuyders->loadMissing('subOrderBuyders');
        $orderBuyders->each(function ($orderBuyder) use (
            $orderBuyders, $url, $source, $serviceRequest) {
            // với mỗi order_buyder sẽ lấy lại options tương ứng cho request
            $resources = $this->getOrderDetailRequestResource(
                $source,
                config('partner.buyder_1688.user_id'),
                data_get($orderBuyder, 'buyder_id')
            );
            $options = data_get($resources, 'options');
            $result = $serviceRequest->handlePost($url, $options);

            if (data_get($result, 'code') != 200) {
                return true;
            }

            // lấy mọi order_buyder_subs chưa có logistic_number của order_buyders ở iteration hiện tại
            $orderBuyderSubs = $orderBuyder->subOrderBuyders;

            // cập nhật logistic_number, tạo kiện với từng order_buyder_subs
            $logisticsItems = data_get($result, 'data.nativeLogistics.logisticsItems');
            if($logisticsItems){
                foreach($logisticsItems as $key => $logisticsItem){
                    $orderBuyderSub = data_get($orderBuyderSubs, $key);
                    if($orderBuyderSub){
                        $orderBuyderSub->update(['logistic_number' => data_get($logisticsItem, 'logisticsCode')]);
                    }
                }
            }
        });
    }
}
