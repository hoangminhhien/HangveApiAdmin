<?php

namespace App\Observers;

use App\Constants\CustomerConstant;
use App\Constants\OrderConstant;
use App\Constants\ReportConstant;
use App\Jobs\Order\UpdateOrderPercentJob;
use App\Models\Customer;
use App\Models\Order;
use App\Services\ActivityService;
use App\Services\ConfigService;
use App\Services\CustomerService;
use App\Services\GroupService;
use App\Services\OrderService;
use App\Services\ReportCustomerService;
use App\Services\ReportOrganizationService;
use App\Services\ReportSaleStaffService;
use App\Services\ReportService;
use App\Services\Service;
use App\Services\OrderPackageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderObserve
{
    private Service $_activityService;
    private CustomerService $_customerService;
    private ReportCustomerService $_reportCustomerService;
    private ReportOrganizationService $_reportOrganizationService;
    private OrderService $_orderService;
    private OrderPackageService $_orderPackageService;
    private GroupService $_groupService;

    public function __construct(
        ActivityService $active,
        CustomerService $customer,
        ReportCustomerService $reportCustomer,
        ReportOrganizationService $organizationService,
        OrderService $orderService,
        OrderPackageService $orderPackageService,
        GroupService $groupService
    ) {
        $this->_activityService = $active;
        $this->_customerService = $customer;
        $this->_reportCustomerService = $reportCustomer;
        $this->_reportOrganizationService = $organizationService;
        $this->_orderService = $orderService;
        $this->_orderPackageService = $orderPackageService;
        $this->_groupService = $groupService;
    }

    /**
     * Handle the Order "created" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function created(Order $order)
    {
        // Phục vụ lúc đồng bộ dữ liệu
        $syncMaxPing = env('SYNC_MAX_PING', false);
        if ($syncMaxPing) {
            \Log::info('OrderObserve created loại');
            return;
        }

        // \Log::info('OrderObserve created');

        $reportService = new ReportService();
        $reportService->incrementQuantity(ReportConstant::QUANTITY_ORDER);
        // (new ReportService())->updateQuantityOrganizationOrder($order, 'create');
        // update total order for customer
        $order->customer()->increment('total_orders');
    }

    /**
     * Handle the Order "updated" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function updated(Order $order)
    {
        // Phục vụ lúc đồng bộ dữ liệu
        $syncMaxPing = env('SYNC_MAX_PING', false);
        if ($syncMaxPing) {
            \Log::info('OrderObserve updated loại');
            return;
        }

        // \Log::info('OrderObserve update');

        // Ngắt không cần chạy Observers này
        $disable_observer_order = isset($_POST['disable_observer_order']) ? $_POST['disable_observer_order']: null;
        if (!is_null($disable_observer_order)) {
            \Log::info('Không chạy disable_observer_order');
            return;
        }
        $status = $order->status;
        if ($order->getOriginal('status') !== $status) {
            if($status == OrderConstant::KEY_STATUS_DONE) {
                $customer = Customer::query()->find($order->customer_id);
                $level = $customer->level;
                Customer::withoutEvents(function () use ($order) {
                    $this->_customerService->updateLevelCustomer($order->customer_id);
                });
            }
            $oldStatus = $order->getOriginal('status') ?? OrderConstant::KEY_STATUS_WAITING_QUOTE;
            $this->_reportCustomerService->changeStatus(
                $oldStatus,
                $status,
                $order->customer_id
            );
            if ($order->customer) {
                // $this->_reportOrganizationService->orderChangeStatus(
                //     $oldStatus,
                //     $status,
                //     $order->customer->organization_id
                // );
            }
            $reasonConfirm = "";
            if($status == OrderConstant::KEY_STATUS_RECONFIRM) {
                $reason = $order->reasonConfirmOrders()->orderBy('pivot_created_at', 'desc')->first();
                $reasonConfirm = data_get($reason, 'name');
            }
            if($order->status != OrderConstant::KEY_STATUS_CANCEL || $order->status == OrderConstant::KEY_STATUS_CANCEL && $order->reason_cancel) {
                $this->_activityService->setOrderLog(
                    $order,
                    trans(
                        "activity.order_$status",
                        ['user_name' => getCurrentUser()->name, 'deposit' => number_format($order->deposit_cost).'đ', 'reason_cancel' => $order->reason_cancel, 'reason_confirm' => $reasonConfirm]
                    ),
                    $order->id
                );

                Order::withoutEvents(function () use ($order) {
                    $this->_orderService->setStatusTime($order->id, $order->status);
                });
            }
            // Khi chuyển đơn sang trạng thái hoàn thành thì đồng thời chuyển kiện sang đã nhận hàng
            if($order->status == \App\Constants\OrderConstant::KEY_STATUS_DONE){
                // $this->_orderPackageService->reportStatusTime()
                // $packIds = $order->packages()->pluck('id')->toArray();
                // foreach($packIds as $id){
                //     $this->_orderPackageService->reportStatusTime($id, \App\Constants\PackageConstant::STATUS_RECEIVED_GOODS);
                // }
                // $packages = $order->packages;
                // foreach($packages as $package){
                //     $package->status = \App\Constants\PackageConstant::STATUS_RECEIVED_GOODS;
                //     $package->date_done = Carbon::now();
                //     $package->save();
                // }
            }
            // increment money for user group
            if ($order->status == \App\Constants\OrderConstant::KEY_STATUS_ORDERED) {
                $this->_groupService->updateKpiStaff($order);
            }
            // refund order cancel
            if ($order->status == \App\Constants\OrderConstant::KEY_STATUS_CANCEL) {
                // (new OrderService())->refundOrderCancel($order);

                // Chỉnh lại theo logic này
                $statusOld = $order->getOriginal('status');
                $arr_status = [
                    OrderConstant::KEY_STATUS_DEPOSITED,
                    OrderConstant::KEY_STATUS_WAIT_TO_PAY,
                    OrderConstant::KEY_STATUS_ORDERED,
                    OrderConstant::KEY_STATUS_DONE,
                ];
                if (in_array($statusOld, $arr_status)) {
                    Order::withoutEvents(function () use ($order) {
                        (new OrderService())->refundOrderCancel($order);
                    });
                }
            }
        }

        // update total orders for staffs group
        $staffGroupId    = $order->staff_group_id;
        $oldStaffGroupId = $order->getOriginal('staff_group_id');
        if ($staffGroupId && $oldStaffGroupId !== $staffGroupId) {
            // increment for new staff
            $this->_groupService->incrementTotalOrderByStaffId($staffGroupId);
            // decrement for old staff
            if ($oldStaffGroupId) {
                $this->_groupService->decrementTotalOrderByStaffId($oldStaffGroupId);
            }
        }

        // (new ActivityService())->activitiesOrder($order);
    }

    /**
     * Handle the Order "deleted" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function deleted(Order $order)
    {
        $this->_reportCustomerService->decrementByKey($order->status, $order->customer_id);
        $this->_reportCustomerService->decrementByKey(CustomerConstant::KEY_REPORT_ORDER, $order->customer_id);
        // $this->_reportOrganizationService->decrementByKey($order->status, $order->customer->organization_id);
        $this->_orderService->deleteByOrderId($order->id);
        // update total order for customer
        $order->customer()->decrement('total_orders');
    }

    /**
     * Handle the Order "restored" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function restored(Order $order)
    {
        $order->orderSuppliers()->withTrashed()->restore();
        $order->orderStatusTime()->withTrashed()->restore();
        $order->notePrivate()->withTrashed()->restore();
        $order->details()->withTrashed()->restore();
        //$order->deliveries()->withTrashed()->restore();
        $order->deliveriesOrder()->withTrashed()->restore();
        $order->reportVN()->withTrashed()->restore();
        $order->complains()->withTrashed()->restore();
    }

    /**
     * Handle the Order "force deleted" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function forceDeleted(Order $order)
    {
        $order->orderSuppliers()->withTrashed()->forceDelete();
        $order->orderStatusTime()->withTrashed()->forceDelete();
        $order->notePrivate()->withTrashed()->forceDelete();
        $order->details()->withTrashed()->forceDelete();
        //$order->deliveries()->withTrashed()->forceDelete();
        $order->deliveriesOrder()->forceDelete();
        $order->reportVN()->withTrashed()->forceDelete();
        $order->complains()->withTrashed()->forceDelete();
    }
}
