<?php

namespace App\Observers;

use App\Constants\ReportConstant;
use App\Constants\TransactionConstant;
use App\Models\Transaction;
use App\Services\ReportService;

class TransactionObserver
{
    public function created(Transaction $transaction)
    {
        // Phục vụ lúc đồng bộ dữ liệu
        $syncMaxPing = env('SYNC_MAX_PING', false);
        if ($syncMaxPing) {
            \Log::info('TransactionObserver created loại');
            return;
        }
        // \Log::info('TransactionObserver created');

        $service = new ReportService();
        if ($transaction->status == TransactionConstant::STATUS_PURCHASE) {
            $service->incrementByReportRevenue($transaction->amount, ReportConstant::REVENUE);
        }

        if ($transaction->status == TransactionConstant::STATUS_REFUND) {
            $service->decrementByReportRevenue($transaction->amount, ReportConstant::REVENUE);
        }
    }
}
