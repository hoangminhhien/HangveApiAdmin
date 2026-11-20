<?php

namespace App\Observers;

use App\Helpers\AccountingHelper;
use App\Models\OrderDetail;
use App\Models\OrderDetailHistory;
use App\Models\OrderSupplier;
use App\Services\AccountingService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrderDetailObserve
{
    public function deleted(OrderDetail $detail)
    {
        $orderSupplier = OrderSupplier::query()->where(
            ['order_id' => $detail->order_id, 'supplier_id' => $detail->supplier_id]
        );
        $this->decrementByKey($orderSupplier, 'order_cost', $detail->order_cost ?? 0);
        (new OrderService())->orderCancelIfNotExistSupplier($detail->order_id);
    }

    public function updated(OrderDetail $detail)
    {
        // Phục vụ lúc đồng bộ dữ liệu
        $syncMaxPing = env('SYNC_MAX_PING', false);
        if ($syncMaxPing) {
            \Log::info('OrderDetailObserve updated loại');
            return;
        }

        // \Log::info('OrderDetailObserve update');

        $customerId = optional($detail->order)->customer_id;
        if($detail->getOriginal('amount_cny') != $detail->amount_cny)
        {
            $condition = ['order_id' => $detail->order_id, 'supplier_id' => $detail->supplier_id];
            $totalCNY = OrderDetail::query()->where($condition)->sum('amount_cny');
            $data['order_cost'] = $orderCost = AccountingHelper::getCosts($totalCNY * getExchangeRate($detail->order_id));
            $data['order_fee'] = (new AccountingService())->getOrderFee($orderCost, $customerId);
            OrderSupplier::query()->where($condition)->update($data);
        }
        // save histories change unit_price_cny, quantity
        $isChangePrice = $detail->getOriginal('unit_price_cny') != $detail->unit_price_cny;
        $isChangeQuantity = $detail->getOriginal('quantity') != $detail->quantity;
        if ($isChangePrice || $isChangeQuantity)
        {
            $dataHistories = [
                'order_detail_id' => $detail->id,
                'object' => $isChangePrice ? 'unit_price_cny' : 'quantity',
                'value'  => $isChangePrice ? $detail->unit_price_cny : $detail->quantity,
                'value_old'  => $isChangePrice ? $detail->getOriginal('unit_price_cny') : $detail->getOriginal('quantity'),
                'user_id' => Auth::user()->id
            ];
            OrderDetailHistory::query()->create($dataHistories);
        }
    }

    /**
     * @param $query
     * @param  string  $key
     * @param  float  $amount
     */
    private function decrementByKey($query, string $key, float $amount): void
    {
        $report = $query->first();
        if(!$report) return;
        if ($report->{$key} <= $amount) {
            $report->delete();
            return;
        }

        $customerId = optional($report->order)->customer_id;

        $report->decrement($key, $amount);
        $report->order_fee = (new AccountingService())->getOrderFee($report->order_cost, $customerId);
        $report->save();
    }
}
