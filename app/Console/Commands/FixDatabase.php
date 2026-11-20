<?php

namespace App\Console\Commands;

use App\Jobs\Buyder\SyncOrderBuyderSubsLogisticCode;
use App\Models\OrderBuyder;
use Illuminate\Console\Command;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class FixDatabase extends Command
{
    protected $signature = 'fix:db';
    protected $description = 'Chỉnh sửa dữ liệu trong database';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        //$this->info('Command này hiện chưa thực hiện hành động nào.');

        $orderBuyders = OrderBuyder::query()
            ->sourceTaobao()
            ->whereIn('id', ['1ee9d624-0acc-61c0-8b2e-00163e0109a7'])
            ->get();

        dispatch(new SyncOrderBuyderSubsLogisticCode(
            BuyderServiceConstant::SOURCE_TAOBAO,
            config('partner.buyder.domain') . config('partner.buyder_taobao.order-detail'),
            $orderBuyders,
        ));
    }
}
