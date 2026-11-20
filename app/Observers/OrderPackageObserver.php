<?php

namespace App\Observers;

use App\Constants\ActivityConstant;
use App\Constants\CustomerConstant;
use App\Constants\PackageConstant;
use App\Models\Delivery;
use App\Models\OrderPackage;
use App\Models\ReportPackage;
use App\Services\ActivityService;
use App\Services\GroupService;
use App\Services\OrderPackageService;
use App\Services\OrderService;
use App\Services\ReportCustomerService;

use Carbon\Carbon;
use GuzzleHttp\Client as Guzzle;

use App\Models\Order;

class OrderPackageObserver
{
    private ReportCustomerService $_reportCustomerService;

    public function __construct(ReportCustomerService $reportCustomerService)
    {
        $this->_reportCustomerService = $reportCustomerService;
    }

    /**
     * Trigger khi event 'creating' của model OrderPackage được gọi
     *
     * @param OrderPackage $orderPackage
     * @return void
     */
    public function creating(OrderPackage $orderPackage)
    {

    }

    /**
     * Handle the OrderPackage "created" event.
     *
     * @param  \App\Models\OrderPackage  $orderPackage
     * @return void
     */
    public function created(OrderPackage $orderPackage)
    {
        // Phục vụ lúc đồng bộ dữ liệu
        $syncMaxPing = env('SYNC_MAX_PING', false);
        if ($syncMaxPing) {
            \Log::info('OrderPackageObserver created loại');
            return;
        }

        // \Log::info('OrderPackageObserver created');
        //dd('created', $orderPackage, $orderPackage->id);
        if($orderPackage->order_id) (new OrderService())->incrementByColumn($orderPackage->order_id, 'packages_number');
        OrderPackage::withoutEvents(function () use ($orderPackage) {
            (new OrderPackageService())->reportStatusTime(
                $orderPackage->id,
                PackageConstant::STATUS_PENDING
            );
        });
        ReportPackage::query()->firstOrCreate(['customer_id' => $orderPackage->customer_id, 'organization_id' => $orderPackage->organization_id])->increment(
            PackageConstant::STATUS_PENDING
        );
        $this->_reportCustomerService->incrementByKey(CustomerConstant::KEY_REPORT_PACKAGE, $orderPackage->customer_id);

        (new OrderPackageService())->saveConfigs($orderPackage);
        // Log ai là người tạo ra cái Pack này
        (new ActivityService())->setLog($orderPackage, PackageConstant::STATUS_PENDING, ActivityConstant::PACKAGE_STATUS);

        // Nếu tạo kiện Order thì đồng bộ sang Quote
        if ($orderPackage->order_type == 'App\Models\Order') {
            if ($orderPackage->bill_code) {
                $job = new \App\Jobs\Quote\SyncAddPackOrder($orderPackage);
                dispatch($job)->delay(now()->addSeconds(5))->onQueue('cron-data-2023');

                // Đồng bộ lại số bao dự kiến và trạng thái bên CN
                $job = new \App\Jobs\CN\SyncCountBagAndStatus($orderPackage->bill_code, $orderPackage->id);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
            }
        }
    }

    /**
     * Handle the OrderPackage "updated" event.
     *
     * @param  \App\Models\OrderPackage  $orderPackage
     * @return void
     */
    public function updated(OrderPackage $orderPackage)
    {
        // Phục vụ lúc đồng bộ dữ liệu
        $syncMaxPing = env('SYNC_MAX_PING', false);
        if ($syncMaxPing) {
            \Log::info('OrderPackageObserver updated loại');
            return;
        }

        if ($orderPackage->skipObserver === true) {
            // \Log::info('Observer bị skip');
            return;
        }

        // \Log::info('OrderPackageObserver updated');

        // Ngắt không cần chạy Observers này
        $disable_observer_package = isset($_POST['disable_observer_package']) ? $_POST['disable_observer_package']: null;
        if (!is_null($disable_observer_package)) {
            \Log::info('Không chạy disable_observer_package');
            return;
        }
        OrderPackage::withoutEvents(function () use ($orderPackage) {
            (new OrderPackageService())->storeActivity($orderPackage);
        });
        if (($old = $orderPackage->getOriginal('delivery_id')) != $orderPackage->delivery_id) {
            $delivery = $orderPackage->delivery ?? Delivery::query()->find($old);
            (new ActivityService())->setLog(
                $delivery,
                $orderPackage->delivery_id ? 'Thêm mới' : 'Hủy',
                ActivityConstant::DELIVERY_PACKAGE,
                null,
                json_encode(['package' => $orderPackage->bill_code])
            );
        }
        $status = $orderPackage->status;
        if ($orderPackage->getOriginal('status') !== $status) {
            $groupService = app()->make(GroupService::class);
            if ($orderPackage->order_type != 'App\Models\Order') {
                if ($status == PackageConstant::STATUS_RECEIVED_GOODS) {
                    // Tính KPI cho user
                    $groupService->updateKpiStaffForOrderPackage($orderPackage, $status);
                }
                if ($orderPackage->getOriginal('status') == PackageConstant::STATUS_RECEIVED_GOODS) {
                    // Trừ KPI nếu chuyển trạng thái khác
                    $groupService->updateKpiStaffForOrderPackage($orderPackage, $status);
                }
            }

            // Nếu đổi trạng thái kiện Order thì đồng bộ sang Quote
            // if ($orderPackage->order_type == 'App\Models\Order') {
                // $job = new \App\Jobs\Quote\SyncStatusPack($orderPackage);
                // dispatch($job)->delay(now()->addSeconds(5))->onQueue('cron-data-2023');
            // }
            if($status == PackageConstant::STATUS_CANCEL) {
                $jobCancelPack = new \App\Jobs\Quote\UpdateStatusCancelPack($orderPackage);
                dispatch($jobCancelPack)->onQueue('export-1800');
            }
        }
        // Kiểm tra có thay đổi international_shipping_cost hoặc woodworking_cost
        if (
            $orderPackage->getOriginal('international_shipping_cost') != $orderPackage->international_shipping_cost ||
            $orderPackage->getOriginal('woodworking_cost') != $orderPackage->woodworking_cost ||
            $orderPackage->getOriginal('shock_proof_cost') != $orderPackage->shock_proof_cost
        ) {
            // \Log::info('Cập nhật lại số tiền vận chuyển quốc tế vào đơn tổng');
            // Cập nhật lại số tiền vận chuyển quốc tế vào đơn tổng

            // Nếu khác kiện order thì không cần xử lý
            if ($orderPackage->order_type == 'App\Models\Order') {
                // \Log::info('Cập nhật lại số tiền vận chuyển quốc tế đơn Order');
                $orderDB = $orderPackage->orderPackageWithOrder;
                if (!is_null($orderDB)) {
                    // $orderPackagesDB = $orderDB->packagesOrder;
                    $orderPackagesDB = OrderPackage::query()->where('order_id', $orderDB->id)
                                                            ->where('order_type', Order::class)
                                                            ->where('status', '!=', PackageConstant::STATUS_CANCEL)
                                                            ->get();
                    if (!is_null($orderPackagesDB)) {

                        $total_international_shipping_cost = 0;
                        $total_woodworking_cost = 0;
                        $total_shock_proof_cost = 0;
                        foreach ($orderPackagesDB as $key => $orderPackage) {
                            $total_international_shipping_cost += $orderPackage->international_shipping_cost;
                            $total_woodworking_cost += $orderPackage->woodworking_cost;
                            $total_shock_proof_cost += $orderPackage->shock_proof_cost;
                        }

                        // Cập nhật vào đơn tổng
                        $orderDB->international_shipping_cost = $total_international_shipping_cost;
                        $orderDB->woodworking_cost = $total_woodworking_cost;
                        // $orderDB->save();
                        $orderDB = Order::withoutEvents(function () use ($orderDB) {
                            $orderDB->save();
                            return $orderDB;
                        });

                        // update internal shipping cost for order supplier first
                        $orderSupplierFirst = optional($orderDB->orderSuppliers)->first();
                        if ($orderSupplierFirst) {
                            $orderSupplierFirst->international_shipping_cost = $total_international_shipping_cost;
                            $orderSupplierFirst->woodworking_cost = $total_woodworking_cost;
                            $orderSupplierFirst->shock_proof_cost = $total_shock_proof_cost;
                            $orderSupplierFirst->save();
                        }
                    }
                }
            }
        }

        // Đồng bộ sang bên Dongdong
        // $this->syncPackToDongDong($orderPackage);

    }

    public function syncPackToDongDong($orderPackage)
    {
        $size = "";

        if ($orderPackage->length) {
            $size .= $orderPackage->length;
        }
        if ($orderPackage->width) {
            $size .= "x" . $orderPackage->width;
        }
        if ($orderPackage->height) {
            $size .= "x" . $orderPackage->height;
        }

        $volumnDB = round($orderPackage->length * $orderPackage->width * $orderPackage->height);

        $data_sync = [
            'code'             => $orderPackage->bill_code,
            'name'             => $orderPackage->product_name,
            'total_pack'       => $orderPackage->package_number,
            'carriers'         =>  optional($orderPackage->transporterRelation)->name ?? "",
            'category_pack_id' =>  $orderPackage->category_id,
            'weight'           =>  $orderPackage->weight,
            'volumn'           =>  $volumnDB,
            'size'             =>  $size,
            'size_long'        =>  $orderPackage->length,
            'size_width'       =>  $orderPackage->width,
            'size_height'      =>  $orderPackage->height,
            'transporter_id'   =>  $orderPackage->transporter_id
        ];

        $json = [
            'event_name' => 'update_pack',
            'data' => [
                'id'     => $orderPackage->id,
                'params' => $data_sync
            ]
        ];

        \Log::info('SyncOrderPack: ' . json_encode($json, JSON_UNESCAPED_UNICODE));

        if (env('APP_ENV') == 'production') {
            \Log::info('Zô đồng bộ');
            try {
                // Lấy dữ liệu
                $guzzle = new Guzzle;
                // $url = 'http://172.23.0.5/api/auth/demo-huuhu';

                $url = env('URL_DONGDONG') . 'webhooks/hangve-x329kxm2k2ixk39x92x1911947165';
                $options = [
                    'json' => $json,
                    'verify' => false,
                    'http_errors' => false,
                ];

                $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
                $result = json_decode($result, true);
                \Log::info($result);
            } catch (\Exception $e) {
                throw $e;
            }
        }
    }

    /**
     * Handle the OrderPackage "deleted" event.
     *
     * @param  \App\Models\OrderPackage  $orderPackage
     * @return void
     */
    public function deleted(OrderPackage $orderPackage)
    {
        // cập nhật lại số kiện của order tương ứng với order_package này
        $orderPackage->loadMissing('order');
        $order = $orderPackage->orderPackageWithOrder;
        if (!empty($order)) {
            $order->decrement('packages_number');
        }

        $this->_reportCustomerService->decrementByKey(CustomerConstant::KEY_REPORT_PACKAGE, $orderPackage->customer_id);
    }

    /**
     * Handle the OrderPackage "restored" event.
     *
     * @param  \App\Models\OrderPackage  $orderPackage
     * @return void
     */
    public function restored(OrderPackage $orderPackage)
    {
        $orderPackage->orderDetailsPackage()->withTrashed()->restore();
        $orderPackage->images()->withTrashed()->restore();
        $orderPackage->notes()->withTrashed()->restore();
        $orderPackage->statusTimes()->withTrashed()->restore();

        if ($orderPackage->order_type == 'App\Models\Consignment') {
            $orderPackage->order()->withTrashed()->restore();
        }
    }

    /**
     * Handle the OrderPackage "force deleted" event.
     *
     * @param  \App\Models\OrderPackage  $orderPackage
     * @return void
     */
    public function forceDeleted(OrderPackage $orderPackage)
    {
        \Log::info('forceDeleted');
        $orderPackage->orderDetailsPackage()->withTrashed()->forceDelete();
        $orderPackage->images()->withTrashed()->forceDelete();
        $orderPackage->notes()->withTrashed()->forceDelete();
        $orderPackage->statusTimes()->withTrashed()->forceDelete();

        if ($orderPackage->order_type == 'App\Models\Consignment') {
            $orderPackage->order()->withTrashed()->forceDelete();
        }
    }
}
