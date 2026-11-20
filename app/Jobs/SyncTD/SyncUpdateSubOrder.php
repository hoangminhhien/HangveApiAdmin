<?php

namespace App\Jobs\SyncTD;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as Guzzle;

use App\Helpers\AccountingHelper;
use App\Http\Resources\ReportStatusResource;
use App\Constants\OrderConstant;

use App\Constants\DeliveryConstant;
use App\Models\OrderPackage;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Delivery;

// Đồng bộ hoàn tiền
use App\Constants\RefundConstant;

// Đồng bộ ảnh
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

// Xử lý lưu thêm tiền chênh lệch (-1000 đến 1000) vào phần tiền hàng còn thiếu
class SyncUpdateSubOrder implements ShouldQueue
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

        // \Log::info('SyncUpdateSubOrder: V2:' . $orderDB->id);

        $this->updateSubOrder($orderDB);
    }

    public function updateSubOrder($orderDB)
    {
        $totalAmountDisplay = $orderDB->getTotalAmount(false);
        $delivery_pay_debt_cost = $orderDB->delivery_pay_debt_cost;
        $delivery_pay_shipping_cost = $orderDB->delivery_pay_shipping_cost;
        $deposit_cost = $orderDB->deposit_cost;

        $diff =  round($totalAmountDisplay) - $delivery_pay_debt_cost - $delivery_pay_shipping_cost - $deposit_cost;

        try {
            \DB::beginTransaction();

            // Nếu lệch nhỏ
            if (
                $diff >= -500 &&
                $diff <= 500 &&
                $diff != 0
            ) {
                // // Lưu log
                // $this->saveLogEditOrderPayDebtCost($orderDB, $diff);

                // // Xử lý
                // if ($diff > 0) {
                //     $orderDB->delivery_pay_debt_cost += $diff;
                // } else {
                //     $orderDB->delivery_pay_debt_cost -= abs($diff);
                // }
                // $orderDB = Order::withoutEvents(function () use ($orderDB) {
                //     $orderDB->save();
                //     return $orderDB;
                // });
            }

            // Nếu lệch dưới 9000
            if ($diff > 500 && $diff < 9000) {
                if ($orderDB->order_fee == 9000) {
                    if ($orderDB->order_percent > 0) {
                        $orderFeeNew = $orderDB->order_cost * $orderDB->order_percent / 100;
                        $orderFeeNew = round($orderFeeNew);

                        // Lưu log
                        $this->saveLogEditOrderFee($orderDB, $orderFeeNew);

                        // Lưu vào order
                        $orderDB->order_fee = $orderFeeNew;
                        $orderDB = Order::withoutEvents(function () use ($orderDB) {
                            $orderDB->save();
                            return $orderDB;
                        });

                        // Lưu vào OrderSup
                        $orderSupplierFirst = optional($orderDB->orderSuppliers)->first();
                        if ($orderSupplierFirst) {
                            $orderSupplierFirst->order_fee = $orderFeeNew;
                            $orderSupplierFirst->discount_cost = $orderDB->discount_cost;
                            $orderSupplierFirst->save();
                        }
                    }
                }
            }


            // Nếu lệch trên 9000

            if ($diff > 9000) {
                if ($orderDB->delivery_pay_shipping_cost == 0) {
                    $packageDBs = $orderDB->packagesOrder;

                    $pay_shipping_cost = $packageDBs->sum('amount');
                    $diff_ship = $pay_shipping_cost - $diff;

                    if (abs($diff_ship) < 9000) {
                        // Lưu log
                        $this->saveLogEditOrderPayShipingCost($orderDB, $pay_shipping_cost);

                        // Lưu lại
                        $orderDB->delivery_pay_shipping_cost = $pay_shipping_cost;
                        $orderDB = Order::withoutEvents(function () use ($orderDB) {
                            $orderDB->save();
                            return $orderDB;
                        });
                    }
                }
            }

            \DB::commit();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function saveLogEditOrderPayDebtCost($orderDB, $diff)
    {
        $logEditModel = \App::make('App\Models\LogEdit');
        $delivery_pay_debt_cost_new = $orderDB->delivery_pay_debt_cost;
        if ($diff > 0) {
            $delivery_pay_debt_cost_new += $diff;
        }
        if ($diff < 0) {
            $delivery_pay_debt_cost_new -= abs($diff);
        }

        $data_log_order = [
            'old' => [
                'delivery_pay_debt_cost' => $orderDB->delivery_pay_debt_cost,
            ],
            'new' => [
                'delivery_pay_debt_cost' => $delivery_pay_debt_cost_new
            ]
        ];

        $log_order = [
            'log_name'    => 'orders',
            'description' => 'delivery_pay_debt_cost',
            'log_id'      => $orderDB->id,
            'data'        => json_encode($data_log_order, JSON_UNESCAPED_UNICODE)
        ];
        $logEditDB = $logEditModel->create($log_order);
    }

    public function saveLogEditOrderFee($orderDB, $orderFeeNew)
    {
        $logEditModel = \App::make('App\Models\LogEdit');
        // Lưu log Order
        $data_log_order = [
            'old' => [
                'order_fee' => $orderDB->order_fee,
            ],
            'new' => [
                'order_fee' => $orderFeeNew
            ]
        ];

        $log_order = [
            'log_name'    => 'orders',
            'description' => 'order_fee',
            'log_id'      => $orderDB->id,
            'data'        => json_encode($data_log_order, JSON_UNESCAPED_UNICODE)
        ];
        $logEditDB = $logEditModel->create($log_order);

        // Lưu log OrderSup
        $orderSupplierFirst = optional($orderDB->orderSuppliers)->first();
        if ($orderSupplierFirst) {
            $data_log_order = [
                'old' => [
                    'order_fee'     => $orderDB->order_fee,
                    'discount_cost' => $orderSupplierFirst->discount_cost,
                ],
                'new' => [
                    'order_fee'     => $orderFeeNew,
                    'discount_cost' => $orderDB->discount_cost
                ]
            ];
            $log_order_sup = [
                'log_name'    => 'order_supplier',
                'description' => 'order_fee, discount_cost',
                'log_id'      => $orderSupplierFirst->id,
                'data'        => json_encode($data_log_order, JSON_UNESCAPED_UNICODE)
            ];
            $logEditDB = $logEditModel->create($log_order_sup);
        }
    }

    public function saveLogEditOrderPayShipingCost($orderDB, $pay_shipping_cost)
    {
        $logEditModel = \App::make('App\Models\LogEdit');
        // Lưu log Order
        $data_log_order = [
            'old' => [
                'delivery_pay_shipping_cost' => $orderDB->delivery_pay_shipping_cost,
            ],
            'new' => [
                'delivery_pay_shipping_cost' => $pay_shipping_cost
            ]
        ];

        $log_order = [
            'log_name'    => 'orders',
            'description' => 'delivery_pay_shipping_cost',
            'log_id'      => $orderDB->id,
            'data'        => json_encode($data_log_order, JSON_UNESCAPED_UNICODE)
        ];

        $logEditDB = $logEditModel->create($log_order);
    }
}
