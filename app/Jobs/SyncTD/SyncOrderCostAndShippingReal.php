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

class SyncOrderCostAndShippingReal implements ShouldQueue
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

        \Log::info('saveLogEditOrderCostReal queue: ' . $orderDB->id);

        $this->updateCostAndShipRealAction($orderDB);
    }

    public function updateCostAndShipRealAction($orderDB)
    {
        // Bắn sang TD để lấy tiền hàng thực tế
        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/orders-fulfillment';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => 0,
                ],
                [
                    'name'     => 'order_code',
                    'contents' => $orderDB->code
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && count($result)) {
            $orders_supplierTD = $result[0]['orders_supplier'];
            $orders_supplierTD = reset($orders_supplierTD);

            \Log::info('saveLogEditOrderCostReal response: ' . $orderDB->id . " : " . $orders_supplierTD['tong_tien_dat_hang_thuc_te']);

            try {
                \DB::beginTransaction();
                $this->saveLogEditOrderCostReal($orderDB, $orders_supplierTD['tong_tien_dat_hang_thuc_te']);
                \DB::commit();
            } catch (Exception $e) {
                \DB::rollback();
                throw $e;
            }
        } else {
            \Log::info('Không có data: ' .  $orderDB->id );
        }
    }

    public function saveLogEditOrderCostReal($orderDB, $tong_tien_dat_hang_thuc_te)
    {
        $logEditModel = \App::make('App\Models\LogEdit');

        $tong_tien_dat_hang_thuc_te_new = $tong_tien_dat_hang_thuc_te;

        if ($tong_tien_dat_hang_thuc_te_new == null) {
            return;
        }
        if ($orderDB->china_shipping_cost_real > 0) {
            $tong_tien_dat_hang_thuc_te_new = $tong_tien_dat_hang_thuc_te_new - $orderDB->china_shipping_cost_real;
        }
        // Lưu log Order
        $data_log_order = [
            'old' => [
                'order_cost_real' => $orderDB->order_cost_real,
            ],
            'new' => [
                'order_cost_real' => round($tong_tien_dat_hang_thuc_te_new * $orderDB->exchange_rate, 2)
            ]
        ];

        $log_order = [
            'log_name'    => 'orders',
            'description' => 'update',
            'log_id'      => $orderDB->id,
            'data'        => json_encode($data_log_order, JSON_UNESCAPED_UNICODE)
        ];

        $data_log_order['old']['order_cost'] = $orderDB->order_cost;
        $data_log_order['old']['exchange_rate'] = $orderDB->exchange_rate;
        $data_log_order['old']['china_shipping_cost_real'] = $orderDB->china_shipping_cost_real;


        if ($data_log_order['old']['order_cost_real'] != $data_log_order['new']['order_cost_real']) {
            \Log::info('saveLogEditOrderCostReal: ' . $orderDB->id . " : " . json_encode($data_log_order, JSON_UNESCAPED_UNICODE));
            $logEditDB = $logEditModel->create($log_order);
        }

        // Lưu log OrderSup
        $orderSupplierFirst = optional($orderDB->orderSuppliers)->first();
        if ($orderSupplierFirst) {
            $data_log_order = [
                'old' => [
                    'order_cost_real' => $orderSupplierFirst->order_cost_real,
                ],
                'new' => [
                    'order_cost_real' => $tong_tien_dat_hang_thuc_te_new
                ]
            ];
            $log_order_sup = [
                'log_name'    => 'order_supplier',
                'description' => 'update',
                'log_id'      => $orderSupplierFirst->id,
                'data'        => json_encode($data_log_order, JSON_UNESCAPED_UNICODE)
            ];

            if ($data_log_order['old']['order_cost_real'] != $data_log_order['new']['order_cost_real']) {
                $logEditDB = $logEditModel->create($log_order_sup);
            }
        }

        // Lưu vào đơn
        $orderDB->order_cost_real = $tong_tien_dat_hang_thuc_te_new * $orderDB->exchange_rate;
        // $orderDB->save();
        $orderDB = Order::withoutEvents(function () use ($orderDB) {
            $orderDB->save();
            return $orderDB;
        });

        // Lưu vào đơn phụ
        if ($orderSupplierFirst) {
            $orderSupplierFirst->order_cost_real = $tong_tien_dat_hang_thuc_te_new;
            $orderSupplierFirst->save();
        }
    }
}
