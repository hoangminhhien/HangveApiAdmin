<?php

namespace App\Jobs\Buyder;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\OrderBuyder;
use Illuminate\Support\Facades\Log;
use ThuongDo\Buyder\src\Services\ServiceRequest;

class SyncTaobaoGeneralData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Options của request bắn sang Buyder lấy chi tiết đơn hàng
     *
     * @var array[]
     */
    private array $buyderTaobaoOptions;

    /**
     * Create a new job instance
     *
     * @return void
     */
    public function __construct()
    {
        $this->buyderTaobaoOptions = [
            'headers' => [
                'X-Token-Auth' => config('partner.buyder.tokenAuth'),
            ],
        ];
    }

    /**
     * Job này trigger trong command Console\Commands\SyncTaobaoPlaceOrderGeneralData.php
     * Mục đích cập nhật dữ liệu đặt hàng đối với từng ID đặt hàng platform_id
     *   - order_buyders: supplier_status
     *   - order_buyder_subs: buyder_status
     *
     * @return void
     */
    public function handle()
    {
        try {
            // service request, dùng để thực hiện các HTTP requests
            $serviceRequest = app()->make(ServiceRequest::class);

            // lấy các đơn Taobao có tt khác 'chưa thanh toán', 'đã bị hủy' và 'đã giao hàng'
            // do số lượng đơn đặt hàng của mỗi ID đặt hàng ngày càng nhiều (có thể lên tới vài vạn đơn)
            // nên không lấy danh sách rồi update, mà sẽ update từng đơn một với các đơn còn đang hoạt động
            // dù bắn nhiều request nhưng sẽ tối ưu hơn về dài hạn khi số lượng đơn tăng lên
            $orderBuyders = OrderBuyder::query()
                ->with(['subOrderBuyders'])
                ->whereTaobaoPaidOrAfter()
                ->notCanceled()->notDelivered()
                ->sourceTaobao()
                ->get();

            $url = config('partner.buyder.domain') . config('partner.buyder_taobao.list-order');

            $orderBuyders->each(function ($orderBuyder) use ($serviceRequest, $url) {
                $options = array_merge($this->buyderTaobaoOptions, [
                    'query' => [
                        'orderNo' => data_get($orderBuyder, 'buyder_no'),
                        'userId' => data_get($orderBuyder, 'taobao_user_id')
                    ]
                ]);
                $result = $serviceRequest->handleGet($url, $options);

                if (data_get($result, 'code') != 200) {
                    return true;
                }

                $result = data_get($result, 'data.data.0');
                // cập nhật trạng thái đơn to (order_buyders)
                $newStatus = data_get($result, 'status');
                if (data_get($orderBuyder, 'supplier_status') != $newStatus) {
                    $orderBuyder->update(['supplier_status' => $newStatus]);
                }

                // cập nhật trạng thái các đơn con (order_buyder_subs)
                $orderBuyderSubs = $orderBuyder->subOrderBuyders;
                $listVos = data_get($result, 'subOrderVOS');
                foreach ($listVos as $vos) {
                    $corSub = $orderBuyderSubs->firstWhere('buyder_sub_id', data_get($vos, 'subOrderId'));
                    $newSubStatus = data_get($vos, 'status');
                    if (data_get($corSub, 'buyder_status') != $newSubStatus) {
                        $corSub->update(['buyder_status' => $newSubStatus]);
                    }
                }
            });
        } catch (\Exception $e) {
            $class = __CLASS__;
            $method = __FUNCTION__;
            Log::error(
                "Lỗi khi cập nhật dữ liệu đơn hàng Taobao:\n"
                . "Class {$class}, method {$method}\n"
                . $e->getMessage()
            );
        }
    }
}
