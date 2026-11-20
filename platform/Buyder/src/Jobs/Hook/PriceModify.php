<?php

namespace ThuongDo\Buyder\src\Jobs\Hook;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\OrderBuyder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PriceModify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Input data, là data nhận từ Buyder (Buyder chuyển hết từ Pandapon sang)
     *
     * @var array
     */
    private array $data;
    /**
     * Nguồn đặt hàng (Taobao, 1688)
     *
     * @var string
     */
    private string $source;

    /**
     * Create a new job instance
     *
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        $this->source = data_get($data, 'source');
    }

    /**
     * Cập nhật tiền thực tế đơn đặt hàng
     *   + Update order_buyders: platform_total_amount, platform_goods_amount, platform_domestic_post_fee
     *   + Update order_buyder_subs: platform_total_amount, platform_goods_amount, platform_domestic_post_fee, status
     *
     * @return void
     */
    public function handle()
    {
        try {
            DB::transaction(function (): void {
                $method = 'priceModify' . ucfirst($this->source);
                $this->{$method}();
            });
        } catch (\Exception $e) {
            Log::error('Lỗi PriceModify: ' . $e);
        }
    }

    /**
     * Cập nhật dữ liệu giá các đơn Taobao
     * Cập nhật cả đơn cha và đơn con
     *
     * @return void
     */
    private function priceModifyTaobao(): void
    {
        Log::info('PriceModify Taobao');
        $messageBody = json_decode(data_get($this->data, 'messageBody'), true);
        $orderBuyderSubsData = data_get($messageBody, 'subOrders');
        $purchaseOrderOuterId = data_get($orderBuyderSubsData, '0.purchase_order_outer_id');

        // update order_buyders
        $orderBuyderPrice = data_get($messageBody, 'purchaseOrderPriceDTO');
        $priceData = [
            'platform_total_amount' => data_get($orderBuyderPrice, 'purchaseTotalFee') / 100,
            'platform_goods_amount' => data_get($orderBuyderPrice, 'purchaseProductFee') / 100,
            'platform_domestic_post_fee' => data_get($orderBuyderPrice, 'purchaseShippingFee') / 100,
        ];
        $orderBuyderSubs = $this->updateOrderBuyders(
            'purchase_order_outer_id',
            $purchaseOrderOuterId,
            $priceData
        );

        // update order_buyder_subs
        $this->updateOrderBuyderSubs($orderBuyderSubsData, $orderBuyderSubs);
    }

    /**
     * Cập nhật dữ liệu giá các đơn 1688
     * Chỉ cập nhật đơn cha
     *
     * @return void
     */
    private function priceModify1688(): void
    {
        Log::info('PriceModify 1688');
        $messageBody = json_decode(data_get($this->data, 'messageBody'), true);
        $baseInfo = data_get($messageBody, 'baseInfo');

        // update order_buyders
        $orderBuyderPrice = [
            'purchaseTotalFee' => data_get($baseInfo, 'totalAmount'),
            'purchaseProductFee' => data_get($baseInfo, 'sumProductPayment'),
            'purchaseShippingFee' => data_get($baseInfo, 'shippingFee'),
        ];
        $this->updateOrderBuyders(
            'buyder_id',
            data_get($baseInfo, 'idOfStr'),
            $orderBuyderPrice
        );
    }

    /**
     * Cập nhật record order_buyders
     *
     * @param string $column
     * @param string $value
     * @param array{
     *     purchaseTotalFee: int,
     *     purchaseProductFee: int,
     *     purchaseShippingFee: int
     * } $priceData
     * @return Collection
     */
    private function updateOrderBuyders(string $column, string $value, array $priceData): Collection
    {
        $orderBuyder = OrderBuyder::query()
            ->whereSource($this->source)
            ->where($column, $value)
            ->firstOrFail();
        $orderBuyder->update([
            'platform_total_amount' => data_get($priceData, 'purchaseTotalFee'),
            'platform_goods_amount' => data_get($priceData, 'purchaseProductFee'),
            'platform_domestic_post_fee' => data_get($priceData, 'purchaseShippingFee'),
        ]);

        return $orderBuyder->subOrderBuyders;
    }

    /**
     * Cập nhật các records order_buyder_subs
     *
     * @param array $orderBuyderSubsData
     * @param Collection $orderBuyderSubs
     * @return void
     */
    private function updateOrderBuyderSubs(array $orderBuyderSubsData, Collection $orderBuyderSubs): void
    {
        foreach ($orderBuyderSubsData as $orderBuyderSubData) {
            $orderBuyderSub = $orderBuyderSubs->firstWhere('mp_sku_id', data_get($orderBuyderSubData, 'sku_id'));
            if (!$orderBuyderSub) {
                continue;
            }

            $totalAmount = data_get($orderBuyderSubData, 'purchase_amount');
            $domesticPostFee = data_get($orderBuyderSubData, 'domestic_post_fee');
            $orderBuyderSub->update([
                'platform_total_amount' => $totalAmount,
                'platform_goods_amount' => $totalAmount - $domesticPostFee,
                'platform_domestic_post_fee' => $domesticPostFee,
                'status' => data_get($orderBuyderSubData, 'status'),
            ]);
        }
    }
}
