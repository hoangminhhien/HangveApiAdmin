<?php

namespace ThuongDo\Buyder\src\Jobs\Hook;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\OrderBuyder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuyerStatus implements ShouldQueue
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
     * Cập nhật trạng thái đơn đặt hàng
     *   + order_buyders.supplier_status
     *   + order_buyder_subs.buyder_status
     *
     * @return void
     */
    public function handle()
    {
        try {
            DB::transaction(function (): void {
                $method = 'buyerStatus' . ucfirst($this->source);
                $this->{$method}();
            });
        } catch (\Exception $e) {
            Log::error('Lỗi BuyerStatus: ' . $e);
        }
    }

    private function buyerStatusTaobao(): void
    {
        Log::info('BuyerStatus Taobao');
        //$messageBody = data_get($this->data, 'messageBody');
        //foreach ($messageBody as $data) {
        //    $orderBuyder = OrderBuyder::query()
        //        ->firstWhere('purchase_order_outer_id', data_get($data, 'outer_purchase_id'));
        //    if (!$orderBuyder) {
        //        continue;
        //    }
        //
        //    $orderBuyder->update([
        //        'supplier_status' => data_get($data, 'status')
        //    ]);
        //}
    }

    private function buyerStatus1688(): void
    {
        Log::info('BuyerStatus 1688');
    }
}
