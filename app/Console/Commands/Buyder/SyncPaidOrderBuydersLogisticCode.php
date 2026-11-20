<?php

namespace App\Console\Commands\Buyder;

use App\Constants\TimeConstant;
use App\Jobs\Buyder\SyncOrderBuyderSubsLogisticCode;
use App\Models\OrderBuyder;
use App\Traits\PlaceOrder\GetBuyderRequestResource;
use Carbon\Carbon;
use Illuminate\Console\Command;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;
use App\Constants\Buyder\BuyderStatusConstant;

class SyncPaidOrderBuydersLogisticCode extends Command
{
    use GetBuyderRequestResource;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:paid-order-buyder-subs-logistic-code';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cập nhật mã vận đơn (logistic_number) của các đơn đặt hàng đã thanh toán';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Cập nhật logistic_number cho order_buyder_subs
     *
     * @return int
     */
    public function handle()
    {
        $this->updateTaobao();
        $this->update1688();
    }

    /**
     * Cập nhật logistic_number cho Taobao order_buyder_subs
     *
     * @return void
     */
    private function updateTaobao(): void
    {
        // lấy mọi order_buyders có order_buyder_subs chưa có mã vận đơn logistic_number
        // kèm theo mọi order_buyder_subs chưa có mã vận đơn logistic_number đó
        // tạm thời không check theo trạng thái đơn to (order_buyders) do có thể sai lệch do 2 cron chạy gần nhau
        $orderBuyders = OrderBuyder::query()
            ->sourceTaobao()
            ->whereNotIn('supplier_status', [BuyderStatusConstant::BUYDER_ORDER_WAITING_PAY, BuyderStatusConstant::BUYDER_ORDER_CONFIRM_FINANCE_FAIL, BuyderStatusConstant::BUYDER_ORDER_CANCELED])
            ->withWhereHas('subOrderBuyders', fn ($query) => $query->whereNull('logistic_number'))
            ->where('created_at', '<=', Carbon::now()->subHours(
                TimeConstant::UPDATE_LOGISTIC_CODE_PERIOD
            ))->get();

        // nếu có đơn cần update thì dispatch job cập nhật order_buyder_subs.logistic_number
        if ($orderBuyders->count()) {
            dispatch(new SyncOrderBuyderSubsLogisticCode(
                BuyderServiceConstant::SOURCE_TAOBAO,
                config('partner.buyder.domain') . config('partner.buyder_taobao.order-detail'),
                $orderBuyders,
            ));
        }
    }

    /**
     * Cập nhật logistic_number cho 1688 order_buyder_subs
     *
     * @return void
     */
    private function update1688(): void
    {
        // lấy mọi order_buyders có order_buyder_subs chưa có mã vận đơn logistic_number
        // kèm theo mọi order_buyder_subs chưa có mã vận đơn logistic_number đó
        $orderBuyders = OrderBuyder::query()
            ->where1688Paid()
            ->whereNotIn('supplier_status', [BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_BUYER_PAY, BuyderStatusConstant::ORDER_BUYDER_1688_CANCELED])
            ->whereHas('subOrderBuyders', fn ($query) => $query->whereNull('logistic_number'))
            ->with([
                'subOrderBuyders' => fn ($query) => $query->whereNull('logistic_number')
            ])->get();

        // dispatch job cập nhật order_buyder_subs.logistic_number
        dispatch(new SyncOrderBuyderSubsLogisticCode(
            BuyderServiceConstant::SOURCE_1688,
            config('partner.buyder.domain') . config('partner.buyder_1688.order_detail'),
            $orderBuyders,
        ));
    }
}
