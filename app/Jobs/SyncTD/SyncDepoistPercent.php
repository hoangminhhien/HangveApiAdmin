<?php

namespace App\Jobs\SyncTD;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as Guzzle;

use App\Models\Order;

class SyncDepoistPercent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $orderDB;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($orderDB)
    {
        $this->orderDB = $orderDB;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $orderDB = $this->orderDB;

        \Log::info('SyncDepoistPercent: v1:' . $orderDB->id);

        if ($orderDB->deposit_percent > 0) {
            \Log::info('SyncDepoistPercent: v1: Không dc phép chạy');
            return;
        }

        // Cập nhật
        $configService = \App::make('App\Services\ConfigService');
        $accounttingService = \App::make('App\Services\AccountingService');
        $orderDetailModel = \App::make('App\Models\OrderDetail');
        $customerDB = $orderDB->customer;
        if ($customerDB) {
            // % đặc cọc : deposit_percent
            $deposit_percent = data_get($customerDB->offer, 'deposit_rate') ? data_get($customerDB->offer, 'deposit_rate') : $configService->getResultFromValueByLevel($customerDB->level)->deposit; // Lấy % đặt cọc theo level
            if ($deposit_percent > 0) {
                $orderDB->deposit_percent = $deposit_percent;
            }

            // Phí đặt hàng, % đặt hàng
            if ($orderDB->order_fee == 0) {
                $order_fee = $accounttingService->getOrderFee($orderDB->order_cost, $orderDB->customer_id, $orderPercent);
                $order_percent = $orderPercent;

                // Lưu
                if ($order_fee) {
                    $orderDB->order_fee = $order_fee;
                    $orderDB->order_percent = $order_percent;
                }

                // Chiết khấu
                if ($orderDB->discount_cost == 0) {
                    $discount_cost = $accounttingService->getDiscountCost($order_fee, $orderDB->customer_id);
                    if ($discount_cost) {
                        $orderDB->discount_cost = $discount_cost;
                    }
                }
            }

            // Kiểm đếm
            if ($orderDB->is_inspection == 1) {
                $orderDetails = $orderDetailModel->where('order_id', $orderDB->id)->get();
                if (count($orderDetails)) {
                    $total_quantity = $orderDetails->sum('quantity');
                    $inspection_cost = $accounttingService->getInspectionCost($total_quantity);
                    if ($inspection_cost > 0) {
                        $orderDB->inspection_cost = $inspection_cost;
                    }
                }
            }

            $orderDB = Order::withoutEvents(function () use ($orderDB) {
                $orderDB->save();
                return $orderDB;
            });
        }
    }
}
