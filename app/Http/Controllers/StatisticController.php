<?php

namespace App\Http\Controllers;

use App\Models\OrderPackage;
use App\Models\Warehouse;
use App\Scopes\SortByCreatedDescScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Carbon\Carbon;
use App\Constants\DepartmentConstant;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\ComplainConstant;
use App\Constants\DeliveryConstant;
use App\Constants\FineConstant;
use App\Services\FilePrintService;
use App\Constants\FilePrintConstant;
use App\Models\FilePrint;
use App\Services\OrderPackageService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use App\Models\Order;
use App\Models\Complain;
use App\Models\User;
use App\Models\Role;
use App\Models\ConfigKpi;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use App\Constants\UserConstant;
use App\Constants\ConfigKpiConstant;

class StatisticController extends Controller
{

    protected $filePrintService;
    protected $orderPackService;

    public function __construct(FilePrintService $filePrintService, OrderPackageService $orderPackService)
    {
        $this->filePrintService = $filePrintService;
        $this->orderPackService = $orderPackService;
    }
    public function checkPerOK($user_type)
    {
        $user = \Auth::user();
        $arr_user_type = [
            'dashboard.type-1',
            'dashboard.type-2',
            'dashboard.type-3',
            'dashboard.type-4',
            'dashboard.type-5',
            'dashboard.type-6',
            'dashboard.type-7',
            'dashboard.type-8',
            'dashboard.type-9',
            'dashboard.type-10',
            'dashboard.type-11',
            'dashboard.type-12',
            'dashboard.type-13',
        ];
        $per_kaka = $arr_user_type[$user_type - 1];
        $done = $user->hasAnyDirectPermission($per_kaka);
        // if ($done) {
        //     return true;
        // }
        return false;
    }

    public function fast(Request $request)
    {
        $time = $request->get('time', 'd');
        $user_type = $request->get('user_type', 0);
        $customer_search = $request->get('customer_search', null);
        $warehouse_id = $request->get('warehouse_id', null);

        if ($user_type == 0) {
            return resSuccess('Done');
        }

        // Check quyền
        // if (!$this->checkPerOK($user_type)) {
        //     return resSuccess('Done done ...');
        // }

        // CEO = 1
        // Trưởng Phòng đặt hàng = 2
        // NV Báo giá = 3
        // NV Đặt hàng = 4
        // TP Tư vấn = 5
        // NV Tư vấn = 6
        // TP Chăm sóc = 7
        // NV Chăm sóc = 8
        // Kế toán = 9
        // Marketting = 10
        // Kho Trung Quốc = 11
        // Kho Trung Chuyển = 12
        // Kho Chi nhánh = 13
        if ($user_type == 1) {
            return $this->getFastType1($time, $customer_search);
        }

        if ($user_type == 2) {
            return $this->getFastType2($time, $customer_search);
        }
        if ($user_type == 3) {
            return $this->getFastType3($time, $customer_search);
        }
        if ($user_type == 4) {
            return $this->getFastType4($time, $customer_search);
        }
        if ($user_type == 5) {
            return $this->getFastType5($time, $customer_search);
        }
        if ($user_type == 6) {
            return $this->getFastType6($time, $customer_search);
        }
        if ($user_type == 7) {
            return $this->getFastType7($time, $customer_search);
        }
        if ($user_type == 8) {
            return $this->getFastType8($time, $customer_search);
        }
        if ($user_type == 9) {
            return $this->getFastType9($time, $customer_search);
        }
        if ($user_type == 10) {
            return $this->getFastType10($time, $customer_search);
        }
        if ($user_type == 11) {
            return $this->getFastType11($time, $customer_search);
        }
        if ($user_type == 12) {
            return $this->getFastType12($time, $customer_search);
        }
        if ($user_type == 13) {
            $warehouseModel = \App::make('App\Models\Warehouse');
            $warehouse = $warehouseModel->where('id', $warehouse_id)->first();
            if (is_null($warehouse)) {
                $result = ['error' => 'Chưa chọn kho, vui lòng thử lại'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            return $this->getFastType13($time, $customer_search, $warehouse_id);
        }
        return resSuccess('OK');
    }

    public function getFastType1($time, $customer_search)
    {
        $orderModel = \App::make('App\Models\Order');

        $orderM1 = $orderModel;

        $dateRange = $this->perDateRange($time);

        // Tiền hàng còn lại phải thu
        $arr_order_customer = [
            'total_remainder_fee' => 0,
        ];
        $arr_status = [
            // OrderConstant::KEY_STATUS_DEPOSITED, // Đã đặt cọc
            // OrderConstant::KEY_STATUS_ORDERING, // Đang đặt hàng
            // OrderConstant::KEY_STATUS_WAIT_TO_PAY, // Chờ thanh toán
            OrderConstant::KEY_STATUS_ORDERED, // Đặt hàng
        ];

        $orderM1 = $orderM1->whereIn('status', $arr_status);

        $select = 'sum(order_cost + order_fee + inspection_cost - discount_cost  - deposit_cost) as total_remainder_fee';
        $result_2 = $orderM1->selectRaw($select)->first();
        dd($result_2->total_remainder_fee);
    }

    public function getFastType2($time, $customer_search)
    {
        $orderModel = \App::make('App\Models\Order');
        $complainModel = \App::make('App\Models\Complain');
        $orderPackageModel = \App::make('App\Models\OrderPackage');

        $orderM1  = $orderModel;
        $orderM2  = $orderModel;
        $orderM3  = $orderModel;
        $complainM  = $complainModel;

        if (!is_null($customer_search)) {
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $customer_search)
                                    ->orWhere('phone_number', $customer_search)
                                    ->orWhere('email', $customer_search)
                                    ->first();
            if ($customer) {
                $orderM1 = $orderM1->where('customer_id', $customer->id);
                $orderM2 = $orderM2->where('customer_id', $customer->id);
                $orderM3 = $orderM3->where('customer_id', $customer->id);
                $complainM = $complainM->where('customer_id', $customer->id);
            } else {
                $result = ['error' => 'Không tìm thấy khách hàng hợp lệ'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        $dateRange = $this->perDateRange($time);

        // Đơn hàng
        $arr_current = [
            'total_all_order' => 0, // Tổng đơn
            'total_waiting_quote' => 0, // Chờ báo giá
            'total_waiting_deposit' => 0, // Đã báo giá
            'total_ordered' => 0, // Đã đặt hàng
            'total_done' => 0, // Hoàn thành
        ];
        $arr_previous = [
            'total_all_order' => 0, // Tổng đơn
            'total_waiting_quote' => 0, // Chờ báo giá
            'total_waiting_deposit' => 0, // Đã báo giá
            'total_ordered' => 0, // Đã đặt hàng
            'total_done' => 0, // Hoàn thành
        ];
        $totalOrder = $orderM1->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
            ->selectRaw('count(*) as total_all_order')->first();

        $select = 'sum(if(orders.status = \''. OrderConstant::KEY_STATUS_WAITING_QUOTE .'\', 1, 0)) as total_waiting_quote,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_ORDERED .'\', 1, 0)) as total_ordered,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_DONE .'\', 1, 0)) as total_done,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_WAITING_DEPOSIT .'\', 1, 0)) as total_waiting_deposit';

        $result_1 = $orderM1->whereBetween('updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                        ->selectRaw($select)->first();

        if ($result_1) {
            $arr_current['total_all_order']       = $totalOrder->total_all_order == null ? 0 : (int) $totalOrder->total_all_order;
            $arr_current['total_waiting_quote']   = $result_1->total_waiting_quote == null ? 0 : (int)$result_1->total_waiting_quote;
            $arr_current['total_waiting_deposit'] = $result_1->total_waiting_deposit == null ? 0 : (int)$result_1->total_waiting_deposit;
            $arr_current['total_ordered']         = $result_1->total_ordered == null ? 0 : (int)$result_1->total_ordered;
            $arr_current['total_done']            = $result_1->total_done == null ? 0 : (int)$result_1->total_done;
        }

        $result_2 = $orderM2->whereBetween('updated_at', [$dateRange['startPrevious'], $dateRange['endPrevious']])
                            ->selectRaw($select)->first();

        if ($result_2) {
            $arr_previous['total_all_order']       = $result_2->total_all_order;
            $arr_previous['total_waiting_quote']   = $result_2->total_waiting_quote == null ? 0 : (int)$result_2->total_waiting_quote;
            $arr_previous['total_waiting_deposit'] = $result_2->total_waiting_deposit == null ? 0 : (int)$result_2->total_waiting_deposit;
            $arr_previous['total_ordered']         = $result_2->total_ordered == null ? 0 : (int)$result_2->total_ordered;
            $arr_previous['total_done']            = $result_2->total_done == null ? 0 : (int)$result_2->total_done;
        }

        // Khiếu nại

        $arr_complain = [
            'total_pending' => 0, // Tổng khiếu nại chờ xử lý
            'total_process' => 0, // Tổng khiếu nại đang xử lý
            'total_done' => 0, // Hoàn thành
        ];

        $select = 'count(*) as total_all_complain,
                   sum(if(complains.status = \''. ComplainConstant::KEY_STATUS_PENDING .'\', 1, 0)) as total_pending,
                   sum(if(complains.status = \''. ComplainConstant::KEY_STATUS_PROCESS .'\', 1, 0)) as total_process,
                   sum(if(complains.status = \''. ComplainConstant::KEY_STATUS_PROCESSED .'\', 1, 0)) as total_done';
        $result_3 = $complainM->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)->first();
        if ($result_3) {
            $arr_complain['total_pending'] = $result_3->total_pending == null ? 0 : (int)$result_3->total_pending;
            $arr_complain['total_process'] = $result_3->total_process == null ? 0 : (int)$result_3->total_process;
            $arr_complain['total_done']    = $result_3->total_done == null ? 0 : (int)$result_3->total_done;
        }

        // Đơn quá ngày:
        // - Tổng đơn chưa hoàn thành quá ngày: 3 ngày tính từ thời điểm kiện hàng cuối cùng trong đơn về VN
        // - Tổng kiện chưa lấy hàng quá ngày: 3 ngày các kiện hàng ở trạng thái đến kho VN
        // - Tổng đơn chờ thanh lý: 1 tháng ở trạng thái đến kho VN

        $arr_outdate = [
            'waiting_for_liquidation' => 0, // Chờ thanh lý
            'not_take_pack' => 0, // Chưa lấy hàng quá ngày
            'not_done' => 0, // Chưa hoàn thành quá ngày
        ];
        $startDateLiquidation = date('Y-m-d H:i:s',strtotime('-30 day', strtotime($dateRange['startDate'])));
        $endDateliquidation = date('Y-m-d H:i:s',strtotime('-30 day', strtotime($dateRange['endDate'])));

        $startDatNotDone = date('Y-m-d H:i:s', strtotime('-7 day', strtotime($dateRange['startDate'])));
        $endDateNotDone = date('Y-m-d H:i:s',strtotime('-7 day', strtotime($dateRange['endDate'])));

        $startDatNotTake = date('Y-m-d H:i:s', strtotime('-3 day', strtotime($dateRange['startDate'])));
        $endDateNotTake =  date('Y-m-d H:i:s',strtotime('-3 day', strtotime($dateRange['endDate'])));

        // Kiện chờ thanh lý
        $packageLiquidation = $orderPackageModel
            ->where('status', PackageConstant::STATUS_PENDING_LIQUIDATION)
            ->whereBetween('updated_at', [$startDateLiquidation, $endDateliquidation])
            ->selectRaw('count(*) as waiting_for_liquidation')->first();

        // Đơn chưa hoàn thành quá ngày
        $orderOutDate = $orderM3->whereNotIn('status', [OrderConstant::KEY_STATUS_DONE, OrderConstant::KEY_STATUS_CANCEL])
            ->whereBetween('created_at', [$startDatNotDone, $endDateNotDone])
            ->orderBy('created_at', 'desc')
            ->selectRaw('count(*) as not_done')->first();

        // Kiện chưa lấy hàng quá ngày
        $endDateNoteTake =  date('Y-m-d H:i:s',strtotime('-3 day', strtotime($dateRange['endDate'])));
        $packageNotTake = $orderPackageModel
            ->where('status', PackageConstant::STATUS_WAREHOUSE_VN)
            ->whereBetween('updated_at', [$startDatNotTake, $endDateNotTake])
            ->selectRaw('count(*) as not_take_pack')->first();

        $arr_outdate['waiting_for_liquidation'] = $packageLiquidation == null ? 0 : $packageLiquidation['waiting_for_liquidation'];
        $arr_outdate['not_done'] = $orderOutDate == null ? 0 : $orderOutDate['not_done'];
        $arr_outdate['not_take_pack'] = $packageNotTake == null ? 0 : $packageNotTake['not_take_pack'];

        $data_result = [
            'arr_current'  => $arr_current,
            'arr_previous' => $arr_previous,
            'arr_outdate'  => $arr_outdate,
            'arr_complain' => $arr_complain,
        ];
        return resSuccessWithinData($data_result);
    }

    public function getFastType3($time, $customer_search)
    {
        $perService = \App::make('App\Services\PerService');
        $orderModel = \App::make('App\Models\Order');

        $orderM1  = $perService->getConditionByUserLogin($orderModel);

        if (!is_null($customer_search)) {
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $customer_search)
                                    ->orWhere('phone_number', $customer_search)
                                    ->orWhere('email', $customer_search)
                                    ->first();
            if ($customer) {
                $orderM1 = $orderM1->where('customer_id', $customer->id);
            } else {
                $result = ['error' => 'Không tìm thấy khách hàng hợp lệ'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        $dateRange = $this->perDateRange($time);

        // Đơn hàng
        $arr_current = [
            'total_full' => 0, // Tổng doanh số
            'total_waiting_quote' => 0, // Chờ báo giá
            'total_waiting_deposit' => 0, // Đã báo giá
            'total_done' => 0, // Hoàn thành
            'total_order_fee' => 0, // Tổng doanh số dịch vụ (phí đặt hàng của đơn hoàn thành)
        ];

        // orders: order_cost, order_fee, inspection_cost, china_shipping_cost
        // order_package: insurance_cost, woodworking_cost, international_shipping_cost, shock_proof_cost, storage_cost, delivery_cost

        $select = 'sum(if(orders.status = \''. OrderConstant::KEY_STATUS_DONE .'\', order_cost + order_fee + inspection_cost + china_shipping_cost * exchange_rate, 0)) as total_full,
                    sum(if(orders.status = \''. OrderConstant::KEY_STATUS_DONE .'\', order_fee, 0)) as total_order_fee,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_WAITING_QUOTE .'\', 1, 0)) as total_waiting_quote,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_DONE .'\', 1, 0)) as total_done,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_WAITING_DEPOSIT .'\', 1, 0)) as total_waiting_deposit,
                   sum(orders.order_cost - IF(orders.order_cost_real > 0, orders.order_cost_real, orders.order_cost) + orders.china_shipping_cost - orders.china_shipping_cost_real) as total_order_shiping_cost';

        $result_1 = $orderM1->whereBetween('updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)->first();
        // $result_2 = $orderM1->whereBetween('updated_at', [$dateRange['startDate'], $dateRange['endDate']])
        //                     ->where('status', OrderConstant::KEY_STATUS_DONE)
        //                     ->get();
        // dd(\Arr::pluck($result_2, 'code'));
        // Phí đàm phán
        $arr_current_2 = [
            'total_order_shiping_cost' => 0, // Phí đám phán
        ];
        if ($result_1) {
            $arr_current['total_full']       = $result_1->total_full == null ? 0 : (int)$result_1->total_full;
            $arr_current['total_order_fee']       = $result_1->total_order_fee == null ? 0 : (int)$result_1->total_order_fee;
            $arr_current['total_waiting_quote']   = $result_1->total_waiting_quote == null ? 0 : (int)$result_1->total_waiting_quote;
            $arr_current['total_waiting_deposit'] = data_get($result_1, 'total_waiting_deposit', 0);
            $arr_current['total_done']            = $result_1->total_done == null ? 0 : (int)$result_1->total_done;
            $arr_current_2['total_order_shiping_cost'] = data_get($result_1, 'total_order_shiping_cost', 0);
        }

        $data_result = [
            'arr_current'  => $arr_current,
            'arr_current_2'  => $arr_current_2,
        ];
        return resSuccessWithinData($data_result);
    }

    public function getFastType4($time, $customer_search)
    {
        $perService = \App::make('App\Services\PerService');
        $orderModel = \App::make('App\Models\Order');
        $orderPackageModel = \App::make('App\Models\OrderPackage');

        $orderM1  = $perService->getConditionByUserLogin($orderModel);
        $orderM2  = $perService->getConditionByUserLogin($orderModel);
        $orderM3 = $perService->getConditionByUserLogin($orderModel);
        $orderPackageDB = $perService->getConditionByUserLogin($orderPackageModel);

        if (!is_null($customer_search)) {
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $customer_search)
                                    ->orWhere('phone_number', $customer_search)
                                    ->orWhere('email', $customer_search)
                                    ->first();
            if ($customer) {
                $orderM1 = $orderM1->where('customer_id', $customer->id);
                $orderM2 = $orderM2->where('customer_id', $customer->id);
                $orderPackageDB = $orderPackageDB->where('customer_id', $customer->id);
            } else {
                $result = ['error' => 'Không tìm thấy khách hàng hợp lệ'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        $dateRange = $this->perDateRange($time);

        // Đơn hàng
        $arr_current = [
            'total_deposited' => 0, // Chờ đặt hàng
            'total_ordered' => 0, // Đã đặt hàng
            'total_done' => 0, // Hoàn thành
            'total_not_done' => 0,  // Chưa hoàn thành (tất cả các đơn trừ tt đã hoàn thành + đã hủy ạ)
            'total_full' => 0, // Tổng doanh số,
            'total_pack_not_take' => 0, // Tồng kiện chưa lấy hàng
            'total_order_fee' => 0, // Tổng doanh số dịch vụ (phí đặt hàng của đơn hoàn thành)
        ];
        $arr_previous = [
            'total_deposited' => 0, // Chờ đặt hàng
            'total_ordered' => 0, // Đã đặt hàng
            'total_done' => 0, // Hoàn thành
            'total_not_done' => 0,  // Chưa hoàn thành (tất cả các đơn trừ tt đã hoàn thành + đã hủy ạ)
            'total_full' => 0, // Tổng doanh số
        ];

        $select = 'sum(if(orders.status = \''. OrderConstant::KEY_STATUS_DONE .'\', order_cost + order_fee + inspection_cost + china_shipping_cost * exchange_rate, 0)) as total_full,
                sum(if(orders.status = \''. OrderConstant::KEY_STATUS_DONE .'\', order_fee, 0)) as total_order_fee,
                   sum(if(orders.status not in ( \''. OrderConstant::KEY_STATUS_DONE .'\', \''. OrderConstant::KEY_STATUS_CANCEL .'\'), 1, 0)) as total_not_done,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_DEPOSITED .'\', 1, 0)) as total_deposited,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_ORDERED .'\', 1, 0)) as total_ordered,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_DONE .'\', 1, 0)) as total_done,
                   sum(orders.order_cost - IF(orders.order_cost_real > 0, orders.order_cost_real, orders.order_cost) + orders.china_shipping_cost - orders.china_shipping_cost_real) as total_order_shiping_cost';

        $result_1 = $orderM1->whereBetween('updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)->first();
         // Phí đàm phán
         $arr_current_2 = [
            'total_order_shiping_cost' => 0, // Phí đám phán
        ];
        if ($result_1) {
            $arr_current['total_deposited']   = $result_1->total_deposited == null ? 0 : (int)$result_1->total_deposited;
            $arr_current['total_ordered']   = $result_1->total_ordered == null ? 0 : (int)$result_1->total_ordered;
            $arr_current['total_done']   = $result_1->total_done == null ? 0 : (int)$result_1->total_done;
            $arr_current['total_not_done']   = $result_1->total_not_done == null ? 0 : (int)$result_1->total_not_done;
            $arr_current['total_full']   = $result_1->total_full == null ? 0 : (int)$result_1->total_full;
            $arr_current['total_order_fee']   = $result_1->total_order_fee == null ? 0 : (int)$result_1->total_order_fee;
            $arr_current_2['total_order_shiping_cost']   = data_get($result_1, 'total_order_shiping_cost', 0);
        }

        $result_2 = $orderM2->whereBetween('updated_at', [$dateRange['startPrevious'], $dateRange['endPrevious']])
                            ->selectRaw($select)->first();

        if ($result_2) {
            $arr_previous['total_deposited']   = $result_2->total_deposited == null ? 0 : (int)$result_2->total_deposited;
            $arr_previous['total_ordered']   = $result_2->total_ordered == null ? 0 : (int)$result_2->total_ordered;
            $arr_previous['total_done']   = $result_2->total_done == null ? 0 : (int)$result_2->total_done;
            $arr_previous['total_not_done']   = $result_2->total_not_done == null ? 0 : (int)$result_2->total_not_done;
            $arr_previous['total_full']   = $result_2->total_full == null ? 0 : (int)$result_2->total_full;
        }

        // Xử lý đếm bên kiện
        $order_packageDB = $orderM1->whereBetween('updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                                    ->where('status', OrderConstant::KEY_STATUS_DONE)
                                    ->get();

        if ($order_packageDB) {
            $order_ids = \Arr::pluck($order_packageDB, 'id');
            // dd($order_ids);
            $orderPackageModel = \App::make('App\Models\OrderPackage');

            $select_package = 'sum(insurance_cost + woodworking_cost + international_shipping_cost + shock_proof_cost + storage_cost + delivery_cost) as total_full_in_package';

            $result_2 = $orderPackageModel->whereIn('order_id', $order_ids)->selectRaw($select_package)->first();
            if ($result_2) {
                $arr_current['total_full'] += $result_2->total_full_in_package == null ? 0 : (int)$result_2->total_full_in_package;
            }
        }

        // Tổng kiện chưa lấy hàng
        // kiện ở tt Đến kho vn a
        $orderPackageDB = $orderPackageDB->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
                                            ->where('status', PackageConstant::STATUS_WAREHOUSE_VN);

        $select = 'count(*) as total_pack_not_take';

        $orderPackageV1 = $orderPackageDB->selectRaw($select)->first();

        if ($orderPackageV1) {
            $arr_current['total_pack_not_take']   = $orderPackageV1->total_pack_not_take == null ? 0 : (int)$orderPackageV1->total_pack_not_take;
        }


        $data_result = [
            'arr_current'  => $arr_current,
            'arr_previous' => $arr_previous,
            'arr_current_2'  => $arr_current_2,
        ];
        return resSuccessWithinData($data_result);
    }

    public function getFastType5($time, $customer_search)
    {
        $perService = \App::make('App\Services\PerService');
        $orderModel = \App::make('App\Models\Order');
        $complainModel = \App::make('App\Models\Complain');
        $orderPackageModel = \App::make('App\Models\OrderPackage');

        $orderM1  = $perService->getConditionByUserLogin($orderModel);
        $complainM  = $perService->getConditionByUserLogin($complainModel);
        $orderPackageDB = $perService->getConditionByUserLogin($orderPackageModel);

        if (!is_null($customer_search)) {
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $customer_search)
                                    ->orWhere('phone_number', $customer_search)
                                    ->orWhere('email', $customer_search)
                                    ->first();
            if ($customer) {
                $orderM1 = $orderM1->where('customer_id', $customer->id);
                $complainM = $complainM->where('customer_id', $customer->id);
                $orderPackageDB = $orderPackageDB->where('customer_id', $customer->id);
            } else {
                $result = ['error' => 'Không tìm thấy khách hàng hợp lệ'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        $dateRange = $this->perDateRange($time);

        // Đơn hàng
        $arr_order = [
            'total_not_done' => 0,  // Chưa hoàn thành (tất cả các đơn trừ tt đã hoàn thành + đã hủy ạ)
            'total_waiting_quote' => 0, // Chờ báo giá
            'total_waiting_deposit' => 0, // Chờ đặt cọc
            'total_ordered' => 0, // Đã đặt hàng
        ];

        $select = 'sum(if(orders.status not in ( \''. OrderConstant::KEY_STATUS_DONE .'\', \''. OrderConstant::KEY_STATUS_CANCEL .'\'), 1, 0)) as total_not_done,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_WAITING_QUOTE .'\', 1, 0)) as total_waiting_quote,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_WAITING_DEPOSIT .'\', 1, 0)) as total_waiting_deposit,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_ORDERED .'\', 1, 0)) as total_ordered';

        $result_1 = $orderM1->whereBetween('updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)->first();

        if ($result_1) {
            $arr_order['total_not_done']   = $result_1->total_not_done == null ? 0 : (int)$result_1->total_not_done;
            $arr_order['total_waiting_quote']   = $result_1->total_waiting_quote == null ? 0 : (int)$result_1->total_waiting_quote;
            $arr_order['total_waiting_deposit']   = $result_1->total_waiting_deposit == null ? 0 : (int)$result_1->total_waiting_deposit;
            $arr_order['total_ordered']   = $result_1->total_ordered == null ? 0 : (int)$result_1->total_ordered;
        }

        // Kiện
        $arr_pack = [
            'total_pack_not_take' => 0, // Tổng kiện hàng chưa lấy (kiện ở tt Đến kho vn a)
        ];

        $orderPackageDB = $orderPackageDB->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
                                            ->where('status', PackageConstant::STATUS_WAREHOUSE_VN);

        $select = 'count(*) as total_pack_not_take';

        $orderPackageV1 = $orderPackageDB->selectRaw($select)->first();

        if ($orderPackageV1) {
            $arr_pack['total_pack_not_take']   = $orderPackageV1->total_pack_not_take == null ? 0 : (int)$orderPackageV1->total_pack_not_take;
        }

        // Khiếu nại
        $arr_complain = [
            'total_pending' => 0, // Tổng khiếu nại chưa xử lý
            'total_done' => 0, // Hoàn thành
        ];

        $select = 'count(*) as total_all_complain,
                   sum(if(complains.status = \''. ComplainConstant::KEY_STATUS_PENDING .'\', 1, 0)) as total_pending,
                   sum(if(complains.status = \''. ComplainConstant::KEY_STATUS_PROCESSED .'\', 1, 0)) as total_done';
        $result_3 = $complainM->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)->first();
        if ($result_3) {
            $arr_complain['total_pending'] = $result_3->total_pending == null ? 0 : (int)$result_3->total_pending;
            $arr_complain['total_done']    = $result_3->total_done == null ? 0 : (int)$result_3->total_done;
        }

        // Khách hàng
        $arr_customer = [
            'total_waiting_for_advice' => 0, // Khách hàng chờ tư vấn
            'total_new' => 0, // Khách hàng mới
            'total_order' => 0, // Khách hàng order
            'total_consignment' => 0, // Khách hàng kí gửi
        ];
        $customerModel = \App::make('App\Models\Customer');

        $select = 'count(*) as total_new,
                   sum(if(staff_group_id is null , 1, 0)) as total_waiting_for_advice,
                   sum(if(customers.service = 0, 1, 0)) as total_order,
                   sum(if(customers.service = 1, 1, 0)) as total_consignment';

        $result_4 = $customerModel->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)->first();

        if ($result_4) {
            $arr_customer['total_waiting_for_advice'] = $result_4->total_waiting_for_advice == null ? 0 : (int)$result_4->total_waiting_for_advice;
            $arr_customer['total_new']    = $result_4->total_new == null ? 0 : (int)$result_4->total_new;
            $arr_customer['total_order']    = $result_4->total_order == null ? 0 : (int)$result_4->total_order;
            $arr_customer['total_consignment']    = $result_4->total_consignment == null ? 0 : (int)$result_4->total_consignment;
        }

        // Cập nhật logic mới
        $total_reset_care =  $customerModel->whereNull('staff_group_id')
                                            ->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
                                            ->count();

        $arr_customer['total_waiting_for_advice']  = $total_reset_care;

        $data_result = [
            'arr_order'    => $arr_order,
            'arr_pack'     => $arr_pack,
            'arr_complain' => $arr_complain,
            'arr_customer' => $arr_customer,
        ];
        return resSuccessWithinData($data_result);
    }
    public function getFastType6($time, $customer_search)
    {
        $perService = \App::make('App\Services\PerService');
        $orderModel = \App::make('App\Models\Order');
        $complainModel = \App::make('App\Models\Complain');
        $orderPackageModel = \App::make('App\Models\OrderPackage');

        $orderM1  = $perService->getConditionByUserLogin($orderModel);
        $complainM  = $perService->getConditionByUserLogin($complainModel);
        $orderPackageDB = $perService->getConditionByUserLogin($orderPackageModel);

        if (!is_null($customer_search)) {
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $customer_search)
                                    ->orWhere('phone_number', $customer_search)
                                    ->orWhere('email', $customer_search)
                                    ->first();
            if ($customer) {
                $orderM1 = $orderM1->where('customer_id', $customer->id);
                $complainM = $complainM->where('customer_id', $customer->id);
                $orderPackageDB = $orderPackageDB->where('customer_id', $customer->id);
            } else {
                $result = ['error' => 'Không tìm thấy khách hàng hợp lệ'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        $dateRange = $this->perDateRange($time);

        // Đơn hàng
        $arr_order = [
            'total_waiting_quote' => 0, // Chờ báo giá
            'total_waiting_deposit' => 0, // Chờ đặt cọc
            'total_ordered' => 0, // Đã đặt hàng
            'total_done' => 0, // Hoàn thành
            'total_order_fee' => 0, // Tổng doanh số dịch vụ (phí đặt hàng của đơn hoàn thành)
        ];

        $select = 'sum(if(orders.status = \''. OrderConstant::KEY_STATUS_DONE .'\', order_fee, 0)) as total_order_fee,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_DONE .'\', 1, 0)) as total_done,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_WAITING_QUOTE .'\', 1, 0)) as total_waiting_quote,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_WAITING_DEPOSIT .'\', 1, 0)) as total_waiting_deposit,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_ORDERED .'\', 1, 0)) as total_ordered';

        $result_1 = $orderM1->whereBetween('updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)->where('staff_group_id', getCurrentUserId())->first();

        if ($result_1) {
            $arr_order['total_done']   = $result_1->total_done == null ? 0 : (int)$result_1->total_done;
            $arr_order['total_waiting_quote']   = $result_1->total_waiting_quote == null ? 0 : (int)$result_1->total_waiting_quote;
            $arr_order['total_waiting_deposit']   = $result_1->total_waiting_deposit == null ? 0 : (int)$result_1->total_waiting_deposit;
            $arr_order['total_ordered']   = $result_1->total_ordered == null ? 0 : (int)$result_1->total_ordered;
            $arr_order['total_order_fee']   = $result_1->total_order_fee == null ? 0 : (int)$result_1->total_order_fee;
        }

        // Kiện
        $arr_pack = [
            'total_pack_not_take' => 0, // Tổng kiện hàng chưa lấy (kiện ở tt Đến kho vn a)
        ];

        $orderPackageDB = $orderPackageDB->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
                                            ->where('status', PackageConstant::STATUS_WAREHOUSE_VN)
                                            ->where('staff_group_id', getCurrentUserId());

        $select = 'count(*) as total_pack_not_take';

        $orderPackageV1 = $orderPackageDB->selectRaw($select)->first();

        if ($orderPackageV1) {
            $arr_pack['total_pack_not_take']   = $orderPackageV1->total_pack_not_take == null ? 0 : (int)$orderPackageV1->total_pack_not_take;
        }

        // Khiếu nại
        $arr_complain = [
            'total_pending' => 0, // Tổng khiếu nại chờ xử lý
            'total_process' => 0, // Tổng khiếu nại đang xử lý
            'total_done' => 0, // Hoàn thành
        ];

        $select = 'count(*) as total_all_complain,
                   sum(if(complains.status = \''. ComplainConstant::KEY_STATUS_PENDING .'\', 1, 0)) as total_pending,
                   sum(if(complains.status = \''. ComplainConstant::KEY_STATUS_PROCESS .'\', 1, 0)) as total_process,
                   sum(if(complains.status = \''. ComplainConstant::KEY_STATUS_DONE .'\', 1, 0)) as total_done';
        $result_3 = $complainM->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)
                            ->where('staff_group_id', getCurrentUserId())->first();
        if ($result_3) {
            $arr_complain['total_pending'] = $result_3->total_pending == null ? 0 : (int)$result_3->total_pending;
            $arr_complain['total_process'] = $result_3->total_process == null ? 0 : (int)$result_3->total_process;
            $arr_complain['total_done']    = $result_3->total_done == null ? 0 : (int)$result_3->total_done;
        }

        // Khách hàng
        $arr_customer = [
            'total_counselor' => 0, // Tổng khách hàng đang tư vấn
        ];
        $customerModel = \App::make('App\Models\Customer');
        //
        $staffGroupId = \Auth::user()->id;
        $customer_counselor = $customerModel
                                    ->where('staff_group_id', $staffGroupId)
                                    ->count();
        $arr_customer['total_counselor'] = $customer_counselor;

        $data_result = [
            'arr_order'    => $arr_order,
            'arr_pack'     => $arr_pack,
            'arr_complain' => $arr_complain,
            'arr_customer' => $arr_customer,
        ];
        return resSuccessWithinData($data_result);
    }

    public function getFastType7($time, $customer_search)
    {
        $orderModel = \App::make('App\Models\Order');
        $complainModel = \App::make('App\Models\Complain');
        $orderPackageModel = \App::make('App\Models\OrderPackage');

        $orderM1  = $orderModel;
        $complainM  = $complainModel;
        $orderPackageDB = $orderPackageModel;

        if (!is_null($customer_search)) {
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $customer_search)
                                    ->orWhere('phone_number', $customer_search)
                                    ->orWhere('email', $customer_search)
                                    ->first();
            if ($customer) {
                $orderM1 = $orderM1->where('customer_id', $customer->id);
                $complainM = $complainM->where('customer_id', $customer->id);
                $orderPackageDB = $orderPackageDB->where('customer_id', $customer->id);
            } else {
                $result = ['error' => 'Không tìm thấy khách hàng hợp lệ'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        $dateRange = $this->perDateRange($time);

        // Đơn hàng
        $arr_order = [
            'total_not_done' => 0,  // Chưa hoàn thành (các đơn ở trạng thái : Đang dặt hàng, Chờ thanh toán, Đặt hàng )
            'total_waiting_quote' => 0, // Chờ báo giá
            'total_waiting_deposit' => 0, // Chờ đặt cọc
            'total_ordered' => 0, // Đã đặt hàng
        ];

        $select = 'sum(if(orders.status in ( \''. OrderConstant::KEY_STATUS_ORDERING .'\', \''. OrderConstant::KEY_STATUS_WAIT_TO_PAY .'\', \''. OrderConstant::KEY_STATUS_ORDERED .'\' ), 1, 0)) as total_not_done,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_WAITING_QUOTE .'\', 1, 0)) as total_waiting_quote,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_WAITING_DEPOSIT .'\', 1, 0)) as total_waiting_deposit,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_ORDERED .'\', 1, 0)) as total_ordered';

        $result_1 = $orderM1->whereBetween('updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)->first();

        if ($result_1) {
            $arr_order['total_not_done']   = $result_1->total_not_done == null ? 0 : (int)$result_1->total_not_done;
            $arr_order['total_waiting_quote']   = $result_1->total_waiting_quote == null ? 0 : (int)$result_1->total_waiting_quote;
            $arr_order['total_waiting_deposit']   = $result_1->total_waiting_deposit == null ? 0 : (int)$result_1->total_waiting_deposit;
            $arr_order['total_ordered']   = $result_1->total_ordered == null ? 0 : (int)$result_1->total_ordered;
        }

        // Tính lại cái chưa hoàn thành

        $result_1a = $orderM1->leftJoin('order_status_times', 'orders.id', '=', 'order_status_times.order_id')
                        ->whereBetween('order_status_times.updated_at', [$dateRange['startDate'], $dateRange['endDate']]);

        $select = 'sum(if(orders.status in ( \''. OrderConstant::KEY_STATUS_ORDERING .'\', \''. OrderConstant::KEY_STATUS_WAIT_TO_PAY .'\', \''. OrderConstant::KEY_STATUS_ORDERED .'\' ), 1, 0)) as total_not_done';

        $result_1a = $result_1a->selectRaw($select)->first();

        if ($result_1a) {
            $arr_order['total_not_done']   = $result_1a->total_not_done == null ? 0 : (int)$result_1a->total_not_done;
        }

        // Kiện
        $arr_pack = [
            'total_pack_not_take' => 0, // Tổng kiện hàng chưa lấy (kiện ở tt Đến kho vn a)
        ];

        $orderPackageDB = $orderPackageDB->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
                                            ->where('status', PackageConstant::STATUS_WAREHOUSE_VN);

        $select = 'count(*) as total_pack_not_take';

        $orderPackageV1 = $orderPackageDB->selectRaw($select)->first();

        if ($orderPackageV1) {
            $arr_pack['total_pack_not_take']   = $orderPackageV1->total_pack_not_take == null ? 0 : (int)$orderPackageV1->total_pack_not_take;
        }

        // Khiếu nại
        $arr_complain = [
            'total_pending' => 0, // Tổng khiếu nại chưa xử lý
            'total_done' => 0, // Hoàn thành
        ];

        $select = 'count(*) as total_all_complain,
                   sum(if(complains.status = \''. ComplainConstant::KEY_STATUS_PENDING .'\', 1, 0)) as total_pending,
                   sum(if(complains.status = \''. ComplainConstant::KEY_STATUS_PROCESSED .'\', 1, 0)) as total_done';
        $result_3 = $complainM->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)->first();
        if ($result_3) {
            $arr_complain['total_pending'] = $result_3->total_pending == null ? 0 : (int)$result_3->total_pending;
            $arr_complain['total_done']    = $result_3->total_done == null ? 0 : (int)$result_3->total_done;
        }

        // Khách hàng
        $arr_customer = [
            'total_care' => 0, // Khách hàng chăm sóc
            'total_transfer_from_tu_van' => 0, // Khách từ tư vấn chuyển sang
            'total_order' => 0, // Khách hàng order
            'total_consignment' => 0, // Khách hàng kí gửi
        ];
        $customerModel = \App::make('App\Models\Customer');

        $select = 'count(*) as total_new,
                   sum(if(customers.staff_care_id, 1, 0)) as total_care,
                   sum(if(customers.service = 0, 1, 0)) as total_order,
                   sum(if(customers.service = 1, 1, 0)) as total_consignment';

        $result_4 = $customerModel->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)->first();

        if ($result_4) {
            $arr_customer['total_care']    = $result_4->total_care == null ? 0 : (int)$result_4->total_care;
            $arr_customer['total_order']    = $result_4->total_order == null ? 0 : (int)$result_4->total_order;
            $arr_customer['total_consignment']    = $result_4->total_consignment == null ? 0 : (int)$result_4->total_consignment;
        }

        $customerResetStaffTimeModel = \App::make('App\Models\CustomerResetStaffTime');
        $select = 'count(*) as total_reset';
        $total_reset_care =  $customerResetStaffTimeModel->selectRaw($select)
                                            ->where('reset_staff', 'counselor')
                                            ->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
                                            ->groupBy('customer_id')
                                            ->get();

        $arr_customer['total_transfer_from_tu_van']  = count($total_reset_care);

        // Cập nhật logic mới
        $customer_care = $customerModel->where('status', 1)
                                    ->whereNotNull('staff_care_id')
                                    ->count();
        $arr_customer['total_care'] = $customer_care;


        $data_result = [
            'arr_order'    => $arr_order,
            'arr_pack'     => $arr_pack,
            'arr_complain' => $arr_complain,
            'arr_customer' => $arr_customer,
        ];
        return resSuccessWithinData($data_result);
    }

    public function getFastType8($time, $customer_search)
    {
        $orderModel = \App::make('App\Models\Order');
        $complainModel = \App::make('App\Models\Complain');
        $orderPackageModel = \App::make('App\Models\OrderPackage');

        $orderM1  = $orderModel;
        $complainM  = $complainModel;
        $orderPackageDB = $orderPackageModel;

        if (!is_null($customer_search)) {
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $customer_search)
                                    ->orWhere('phone_number', $customer_search)
                                    ->orWhere('email', $customer_search)
                                    ->first();
            if ($customer) {
                $orderM1 = $orderM1->where('customer_id', $customer->id);
                $complainM = $complainM->where('customer_id', $customer->id);
                $orderPackageDB = $orderPackageDB->where('customer_id', $customer->id);
            } else {
                $result = ['error' => 'Không tìm thấy khách hàng hợp lệ'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        $dateRange = $this->perDateRange($time);

        // Đơn hàng
        $arr_order = [
            'total_waiting_quote' => 0, // Chờ báo giá
            'total_waiting_deposit' => 0, // Chờ đặt cọc
            'total_ordered' => 0, // Đã đặt hàng
            'total_done' => 0, // Hoàn thành
            'total_order_fee' => 0, // Tổng doanh số dịch vụ (phí đặt hàng của đơn hoàn thành)
        ];

        $select = 'sum(if(orders.status = \''. OrderConstant::KEY_STATUS_DONE .'\', order_fee, 0)) as total_order_fee,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_DONE .'\', 1, 0)) as total_done,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_WAITING_QUOTE .'\', 1, 0)) as total_waiting_quote,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_WAITING_DEPOSIT .'\', 1, 0)) as total_waiting_deposit,
                   sum(if(orders.status = \''. OrderConstant::KEY_STATUS_ORDERED .'\', 1, 0)) as total_ordered';

        $result_1 = $orderM1->whereBetween('updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)->first();

        if ($result_1) {
            $arr_order['total_done']   = $result_1->total_done == null ? 0 : (int)$result_1->total_done;
            $arr_order['total_waiting_quote']   = $result_1->total_waiting_quote == null ? 0 : (int)$result_1->total_waiting_quote;
            $arr_order['total_waiting_deposit']   = $result_1->total_waiting_deposit == null ? 0 : (int)$result_1->total_waiting_deposit;
            $arr_order['total_ordered']   = $result_1->total_ordered == null ? 0 : (int)$result_1->total_ordered;
            $arr_order['total_order_fee']   = $result_1->total_order_fee == null ? 0 : (int)$result_1->total_order_fee;
        }

        // Kiện
        $arr_pack = [
            'total_pack_not_take' => 0, // Tổng kiện hàng chưa lấy (kiện ở tt Đến kho vn a)
        ];

        $orderPackageDB = $orderPackageDB->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
                                            ->where('status', PackageConstant::STATUS_WAREHOUSE_VN);

        $select = 'count(*) as total_pack_not_take';

        $orderPackageV1 = $orderPackageDB->selectRaw($select)->first();

        if ($orderPackageV1) {
            $arr_pack['total_pack_not_take']   = $orderPackageV1->total_pack_not_take == null ? 0 : (int)$orderPackageV1->total_pack_not_take;
        }

        // Khiếu nại
        $arr_complain = [
            'total_pending' => 0, // Tổng khiếu nại chờ xử lý
            'total_process' => 0, // Tổng khiếu nại đang xử lý
            'total_done' => 0, // Hoàn thành
        ];

        $select = 'count(*) as total_all_complain,
                   sum(if(complains.status = \''. ComplainConstant::KEY_STATUS_PENDING .'\', 1, 0)) as total_pending,
                   sum(if(complains.status = \''. ComplainConstant::KEY_STATUS_PROCESS .'\', 1, 0)) as total_process,
                   sum(if(complains.status = \''. ComplainConstant::KEY_STATUS_PROCESSED .'\', 1, 0)) as total_done';
        $result_3 = $complainM->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)->first();
        if ($result_3) {
            $arr_complain['total_pending'] = $result_3->total_pending == null ? 0 : (int)$result_3->total_pending;
            $arr_complain['total_process'] = $result_3->total_process == null ? 0 : (int)$result_3->total_process;
            $arr_complain['total_done']    = $result_3->total_done == null ? 0 : (int)$result_3->total_done;
        }

        // Khách hàng
        $arr_customer = [
            'total_care' => 0, // Tổng khách hàng đang chăm sóc
        ];
        $customerModel = \App::make('App\Models\Customer');
        $customer_care = $customerModel->where('status', 1)
                                    ->whereNotNull('staff_care_id')
                                    ->count();
        $arr_customer['total_care'] = $customer_care;


        $data_result = [
            'arr_order'    => $arr_order,
            'arr_pack'     => $arr_pack,
            'arr_complain' => $arr_complain,
            'arr_customer' => $arr_customer,
        ];
        return resSuccessWithinData($data_result);
    }

    public function getFastType9($time, $customer_search)
    {
        $reportCustomerModel = \App::make('App\Models\ReportCustomer');
        $orderModel = \App::make('App\Models\Order');
        $orderPackageModel = \App::make('App\Models\OrderPackage');
        $deliveryModel = \App::make('App\Models\Delivery');

        $reportCustomerM1 = $reportCustomerModel;
        $orderM1 = $orderModel;
        $orderPackageM1 = $orderPackageModel;
        $orderPackageM2 = $orderPackageModel;
        $deliveryM1 = $deliveryModel;

        if (!is_null($customer_search)) {
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $customer_search)
                                    ->orWhere('phone_number', $customer_search)
                                    ->orWhere('email', $customer_search)
                                    ->first();
            if ($customer) {
                $reportCustomerM1 = $reportCustomerM1->where('customer_id', $customer->id);
                $orderM1 = $orderM1->where('customer_id', $customer->id);
                $orderPackageM1 = $orderPackageM1->where('customer_id', $customer->id);
                $orderPackageM2 = $orderPackageM2->where('customer_id', $customer->id);
                $deliveryM1 = $deliveryM1->where('customer_id', $customer->id);
            } else {
                $result = ['error' => 'Không tìm thấy khách hàng hợp lệ'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        $dateRange = $this->perDateRange($time);

        // Số dư tài khoản
        $arr_report_customer = [
            'total_balance_amount' => 0,
        ];
        $select = 'sum(balance_amount) as total_balance_amount';
        $result_1 = $reportCustomerM1->selectRaw($select)->first();
        if ($result_1) {
            $arr_report_customer['total_balance_amount'] = $result_1->total_balance_amount == null ? 0 : (int)$result_1->total_balance_amount;
        }

        // Tiền hàng còn lại phải thu
        $arr_order_customer = [
            'total_remainder_fee' => 0,
        ];
        $arr_status = [
            // OrderConstant::KEY_STATUS_DEPOSITED, // Đã đặt cọc
            // OrderConstant::KEY_STATUS_ORDERING, // Đang đặt hàng
            // OrderConstant::KEY_STATUS_WAIT_TO_PAY, // Chờ thanh toán
            OrderConstant::KEY_STATUS_ORDERED, // Đặt hàng
        ];

        $orderM1 = $orderM1->whereIn('status', $arr_status);

        $select = 'sum(order_cost + order_fee + inspection_cost + china_shipping_cost * exchange_rate - discount_cost  - deposit_cost) as total_remainder_fee';
        $result_2 = $orderM1->selectRaw($select)->first();

        if ($result_2) {
            $arr_order_customer['total_remainder_fee'] = $result_2->total_remainder_fee == null ? 0 : (int)$result_2->total_remainder_fee;
        }

        // Tổng tiền vận chuyển phải thu
        $arr_order_package = [
            'total_transport_need_to_collect' => 0,
        ];
        $select = 'sum(if(status = \''.  PackageConstant::STATUS_WAREHOUSE_VN .'\', international_shipping_cost + woodworking_cost + insurance_cost- discount_cost, 0)) as total_transport_need_to_collect';

        $result_3 = $orderPackageM1->selectRaw($select)->first();
        if ($result_3) {
            $arr_order_package['total_transport_need_to_collect'] = $result_3->total_transport_need_to_collect == null ? 0 : (int)$result_3->total_transport_need_to_collect;
        }

        // Tổng tiền COD phải thu
        $arr_delivery = [
            'total_remainder_code' => 0,
        ];

        // số này mk chỉ tính đơn có kiện tạo giao hàng thôi a

        // sum tay thì a lấy từ giao hàng -> kiện -> đơn nha a

        // Đơn Order: Tiền hàng còn thiếu +  phí vận chuyển TQ+ Phí vận chuyển tạm tính + đóng gỗ + chống sốc+ Lưu kho + vc nc ngoài

        // Chi tiết:
        // phí vận chuyển TQ: china_shipping_cost

        // storage_cost: Phí lưu kho

        // Phí chống sốc: shock_proof_cost

        // Phí Đóng gỗ: woodworking_cost

        // international_shipping_cost: Phí vận chuyển quốc tế

        // mấy cái trên lấy ở order_package

        // Phí vận chuyển tạm tính: delivery_cost => bảng: deliveries

        // Tiền hàng còn thiếu  order_cost + order_fee + inspection_cost - discount_cost  - deposit_cost


        $arr_status = [
            DeliveryConstant::KEY_STATUS_PENDING, // Chờ xử lý
            DeliveryConstant::KEY_STATUS_PROCESS, // Đang xử lý
        ];

        $select = 'sum(delivery_cost + international_shipping_cost + debt_cost + shock_proof_cost + woodworking_cost) as total_remainder_code';
        $result_4 = $deliveryM1->where('payment', 'cod')
                                    ->where('order_type', 'App\Models\Order')
                                    ->whereIn('status', $arr_status)
                                    ->selectRaw($select)
                                    ->first();

        if ($result_4) {
            $arr_delivery['total_remainder_code'] = $result_4->total_remainder_code == null ? 0 : (int)$result_4->total_remainder_code;
        }

        // Tổng tiền phạt
        $arr_fine = [
            'total_fine_amount' => 0,
        ];
        $fineModel = \App::make('App\Models\Fine');
        $select = 'sum(amount) as total_fine_amount';
        $result_5 = $fineModel->where('status', FineConstant::KEY_STATUS_DONE)
                            ->selectRaw($select)
                            ->first();
        if ($result_5) {
            $arr_fine['total_fine_amount'] = $result_5->total_fine_amount == null ? 0 : (int)$result_5->total_fine_amount;
        }

        // Tổng tiền thu chi
        $arr_fund = [
            'total_nap' => 0,
            'total_chi' => 0,
        ];
        $fundTransactionModel = \App::make('App\Models\FundTransaction');
        $select = 'sum(if(fund_type_pay_code = 1, money_update, 0)) as total_nap,
                   sum(if(type_pay = 2 and fund_unit_currency = 1, money_update, 0)) as total_chi';

        $result_6 = $fundTransactionModel->selectRaw($select)
                            ->first();
        if ($result_6) {
            $arr_fund['total_nap'] = $result_6->total_nap == null ? 0 : (int)$result_6->total_nap;
            $arr_fund['total_chi'] = $result_6->total_chi == null ? 0 : (int)$result_6->total_chi;
        }

        // Tổng doanh thu vận chuyển
        $arr_order_package_2 = [
            'total_transport_done' => 0,
        ];
        $select = 'sum(if(status = \''.  PackageConstant::STATUS_RECEIVED_GOODS .'\', international_shipping_cost, 0)) as total_transport_done';

        $result_7 = $orderPackageM2->selectRaw($select)->first();

        if ($result_7) {
            $arr_order_package_2['total_transport_done'] = $result_7->total_transport_done == null ? 0 : (int)$result_7->total_transport_done;
        }


        // Tổng tiền bồi thường của khách hàng
        $arr_compensation = [
            'total_compensation' => 0,
        ];
        $complainModel = \App::make('App\Models\Complain');
        $select = 'sum(compensation) as total_compensation';
        $result_8 = $complainModel->where('status', ComplainConstant::KEY_STATUS_DONE)
                            ->selectRaw($select)
                            ->first();
        if ($result_8) {
            $arr_compensation['total_compensation'] = $result_8->total_compensation == null ? 0 : (int)$result_8->total_compensation;
        }

        // Tổng tiền doanh thu dịch vụ
        $arr_service = [
            'total_service' => 0,
        ];
        $complainModel = \App::make('App\Models\Complain');
        $select = 'sum(order_fee) as total_service';
        $result_9 = $orderModel->where('status', OrderConstant::KEY_STATUS_DONE)
                            ->selectRaw($select)
                            ->first();
        if ($result_9) {
            $arr_service['total_service'] = $result_9->total_service == null ? 0 : (int)$result_9->total_service;
        }
        // Tổng tiền doanh thu đàm phán
        $arr_negotiate = [
            'total_negotiate' => 0,
        ];
        $complainModel = \App::make('App\Models\Complain');
        //order_cost_old + china_shipping_cost_old  * tỉ giá - order_cost_real - china_shipping_cost_real * tỉ giá
        $select = 'SUM((order_cost - order_cost_real) +
                ((china_shipping_cost - china_shipping_cost_real) * exchange_rate) +
                (bonus * exchange_rate)) AS total_negotiate';
        $result_10 = $orderModel->where('status', OrderConstant::KEY_STATUS_DONE)
                            ->selectRaw($select)
                            ->first();
        if ($result_10) {
            $arr_negotiate['total_negotiate'] = $result_10->total_negotiate == null ? 0 : round($result_10->total_negotiate, 0);
        }
        $data_result = [
            'arr_report_customer' => $arr_report_customer,
            'arr_order_customer'  => $arr_order_customer,
            'arr_order_package'   => $arr_order_package,
            'arr_delivery'        => $arr_delivery,
            'arr_fine'            => $arr_fine,
            'arr_fund'            => $arr_fund,
            'arr_order_package_2' => $arr_order_package_2,
            'arr_compensation'    => $arr_compensation,
            'arr_service'         => $arr_service,
            'arr_negotiate'       => $arr_negotiate,
        ];
        return resSuccessWithinData($data_result);
    }

    public function getFastType10($time, $customer_search)
    {
        // Khách hàng
        $arr_customer = [
            'total_new' => 0, // Khách hàng mới
            'total_order' => 0, // Khách hàng order
            'total_consignment' => 0, // Khách hàng kí gửi
        ];
        $customerModel = \App::make('App\Models\Customer');

        $dateRange = $this->perDateRange($time);

        $select = 'count(*) as total_new,
                   sum(if(customers.service = 0, 1, 0)) as total_order,
                   sum(if(customers.service = 1, 1, 0)) as total_consignment';

        $result_1 = $customerModel->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)->first();

        if ($result_1) {
            $arr_customer['total_new']    = $result_1->total_new == null ? 0 : (int)$result_1->total_new;
            $arr_customer['total_order']    = $result_1->total_order == null ? 0 : (int)$result_1->total_order;
            $arr_customer['total_consignment']    = $result_1->total_consignment == null ? 0 : (int)$result_1->total_consignment;
        }

        // Đơn hàng
        $arr_customer_order = [
            'total_place_order' => 0, // Khách hàng lên đơn
            'total_place_ordered' => 0, // Khách hàng đặt hàng
        ];
        $orderModel = \App::make('App\Models\Order');

        $orderM1 = $orderModel;
        $orderM1 = $orderM1->leftJoin('order_status_times', 'orders.id', '=', 'order_status_times.order_id');

        $select = 'sum(if(orders.status not in ( \''. OrderConstant::KEY_STATUS_CANCEL .'\'), 1, 0)) as total_place_order,
                   sum(if(order_status_times.status_3, 1, 0)) as total_place_ordered';

        $result_2 = $orderM1->whereBetween('orders.created_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)
                            ->groupBy('orders.customer_id')
                            ->get();

        if ($result_2) {
            foreach ($result_2 as $key => $item) {
                if ($item['total_place_order'] > 0) {
                    $arr_customer_order['total_place_order'] += 1;
                }
                if ($item['total_place_ordered'] > 0) {
                    $arr_customer_order['total_place_ordered'] += 1;
                }
            }
        }

        $data_result = [
            'arr_customer' => $arr_customer,
            'arr_customer_order' => $arr_customer_order,
        ];
        return resSuccessWithinData($data_result);
    }

    public function getFastType11($time, $customer_search)
    {
        $orderPackageModel = \App::make('App\Models\OrderPackage');

        $orderPackageA1 = $orderPackageModel;

        if (!is_null($customer_search)) {
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $customer_search)
                                    ->orWhere('phone_number', $customer_search)
                                    ->orWhere('email', $customer_search)
                                    ->first();
            if ($customer) {
                $orderPackageA1 = $orderPackageA1->where('customer_id', $customer->id);
            } else {
                $result = ['error' => 'Không tìm thấy khách hàng hợp lệ'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        $dateRange = $this->perDateRange($time);
        $dateRangeDay = $this->perDateRange('d');

        // đã đến kho TQ : status_5
        // đang về VN : status_6
        // Chờ xử lý: status_0

        $arr_warehouse = [
            'total_pack_in_china'        => 0, // Tổng kiện hàng về kho TQ trong ngày (đã đến kho TQ)
            'total_pack_in_warehouse'    => 0, // Tổng kiện hàng trong kho (đã đến kho TQ) - Theo ngày hiện tại, không theo bộ lọc
            'total_pack_transfered'      => 0, // Tổng kiện hàng đã chuyển ( Trạng thái đang về VN)
            'total_pack_waiting_confirm' => 0, // Tổng kiện hàng chờ xác nhận (Đến kho TQ và loại hàng là Chờ xác định)
            'total_kg_in_warehouse'      => 0, // Tổng trọng lượng trong kho TQ (Đã đến kho TQ)
            'total_m3_in_warehouse'      => 0, // Tổng thể tích trong kho TQ (Đã đến kho TQ)
            'total_kg_pack_delivery'     => 0, // Tổng khối lượng kiện hàng giao ( Trạng thái đang về VN)
            'total_m3_pack_delivery'     => 0, // Tổng thể tích kiện hàng giao ( Trạng thái đang về VN)
        ];

        // Tổng kiện hàng trong kho
        $select = 'count(*) as total_pack_in_warehouse';
        $result_1 = $orderPackageA1->where('status', 'status_5')
                            ->selectRaw($select)
                            ->first();

        if ($result_1) {
            $arr_warehouse['total_pack_in_warehouse'] = $result_1->total_pack_in_warehouse == null ? 0 : (int)$result_1->total_pack_in_warehouse;
        }

        // Các ô còn lại
        $select = "sum(if(status = 'status_5', 1, 0)) as total_pack_in_china,
                   sum(if(status = 'status_6', 1, 0)) as total_pack_transfered,
                   sum(if(status = 'status_5' && order_type is null, 1, 0)) as total_pack_waiting_confirm,
                   sum(if(status = 'status_5', round(weight, 2), 0)) as total_kg_in_warehouse,
                   sum(if(status = 'status_5', round((height * length * width), 2), 0)) as total_m3_in_warehouse,
                   sum(if(status = 'status_6', round(weight, 2), 0)) as total_kg_pack_delivery,
                   sum(if(status = 'status_6', round((height * length * width), 2), 0)) as total_m3_pack_delivery
                   ";

        $result_2 = $orderPackageA1->whereBetween('updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)
                            ->first();

        if ($result_2) {
            $arr_warehouse['total_pack_in_china'] = $result_2->total_pack_in_china == null ? 0 : (int)$result_2->total_pack_in_china;
            $arr_warehouse['total_pack_transfered'] = $result_2->total_pack_transfered == null ? 0 : (int)$result_2->total_pack_transfered;
            $arr_warehouse['total_pack_waiting_confirm'] = $result_2->total_pack_waiting_confirm == null ? 0 : (int)$result_2->total_pack_waiting_confirm;
            $arr_warehouse['total_kg_in_warehouse'] = $result_2->total_kg_in_warehouse == null ? 0 : round($result_2->total_kg_in_warehouse,2);
            $arr_warehouse['total_m3_in_warehouse'] = $result_2->total_m3_in_warehouse == null ? 0 : round($result_2->total_m3_in_warehouse/1000000, 2);
            $arr_warehouse['total_kg_pack_delivery'] = $result_2->total_kg_pack_delivery == null ? 0 : round($result_2->total_kg_pack_delivery,2);
            $arr_warehouse['total_m3_pack_delivery'] = $result_2->total_m3_pack_delivery == null ? 0 : round($result_2->total_m3_pack_delivery/1000000, 2);
        }

        $data_result = [
            'arr_warehouse' => $arr_warehouse,
        ];
        return resSuccessWithinData($data_result);
    }

    public function getFastType12($time, $customer_search)
    {
        $orderPackageModel = \App::make('App\Models\OrderPackage');

        $orderPackageA1 = $orderPackageModel;

        if (!is_null($customer_search)) {
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $customer_search)
                                    ->orWhere('phone_number', $customer_search)
                                    ->orWhere('email', $customer_search)
                                    ->first();
            if ($customer) {
                $orderPackageA1 = $orderPackageA1->where('customer_id', $customer->id);
            } else {
                $result = ['error' => 'Không tìm thấy khách hàng hợp lệ'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        $dateRange = $this->perDateRange($time);
        $dateRangeDay = $this->perDateRange('d');

        // Đang kiểm hàng : status_8
        // Đang chyển hàng : status_9
        // đang về VN : status_6

        $arr_warehouse = [
            'total_pack_receive'                => 0, // Tổng kiện hàng nhận hàng (Đang kiểm hàng + Đang chyển hàng)
            'total_pack_delivered'              => 0, // Tổng kiện hàng đã giao (đang về VN)
            'total_pack_waiting_delivery'       => 0, // Tổng kiện hàng chờ giao (đang kiểm hàng)
            'total_pack_confirmation_warehouse' => 0, // Tổng kiện hàng chờ xác nhận từ kho (Tổng kiện hàng nhận hàng +  loại là chưa xác định)
        ];

        $select = "sum(if(status in ('status_8', 'status_9'), 1, 0)) as total_pack_receive,
                   sum(if(status = 'status_6', 1, 0)) as total_pack_delivered,
                   sum(if(status = 'status_8', 1, 0)) as total_pack_waiting_delivery,
                   sum(if(status in ('status_8', 'status_9') && order_type is null, 1, 0)) as total_pack_confirmation_warehouse
                   ";

        $result_2 = $orderPackageA1->whereBetween('updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)
                            ->first();

        if ($result_2) {
            $arr_warehouse['total_pack_receive'] = $result_2->total_pack_receive == null ? 0 : (int)$result_2->total_pack_receive;
            $arr_warehouse['total_pack_delivered'] = $result_2->total_pack_delivered == null ? 0 : (int)$result_2->total_pack_delivered;
            $arr_warehouse['total_pack_waiting_delivery'] = $result_2->total_pack_waiting_delivery == null ? 0 : (int)$result_2->total_pack_waiting_delivery;
            $arr_warehouse['total_pack_confirmation_warehouse'] = $result_2->total_pack_confirmation_warehouse == null ? 0 : (int)$result_2->total_pack_confirmation_warehouse;
        }

        $data_result = [
            'arr_warehouse' => $arr_warehouse,
        ];
        return resSuccessWithinData($data_result);
    }

    public function getFastType13($time, $customer_search, $warehouse_id)
    {
        $orderPackageModel = \App::make('App\Models\OrderPackage');

        $orderPackageA1 = $orderPackageModel->where('order_package.warehouse_id', $warehouse_id);
        $orderPackageA2 = $orderPackageModel->where('order_package.warehouse_id', $warehouse_id);
        $orderPackageA3 = $orderPackageModel->where('order_package.warehouse_id', $warehouse_id);

        if (!is_null($customer_search)) {
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $customer_search)
                                    ->orWhere('phone_number', $customer_search)
                                    ->orWhere('email', $customer_search)
                                    ->first();
            if ($customer) {
                $orderPackageA1 = $orderPackageA1->where('customer_id', $customer->id);
                $orderPackageA2 = $orderPackageA2->where('customer_id', $customer->id);
                $orderPackageA3 = $orderPackageA3->where('customer_id', $customer->id);
            } else {
                $result = ['error' => 'Không tìm thấy khách hàng hợp lệ'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        $dateRange = $this->perDateRange($time);

        // Đến kho VN : status_10
        // Đã nhận hàng : status_16
        // Chờ COD : status_14

        $arr_warehouse = [
            'total_pack_in_warehouse'    => 0, // Tổng kiện hàng trong kho: tất cả các kiện ở trạng thái đến kho VN ( tính đến ngày hiện tại)
            'total_pack_received'        => 0, // Tổng kiện hàng đã nhận (Đã nhận hàng)
            'total_pack_cod'             => 0, // Tổng kiện hàng COD (Chờ COD - vòng qua stt)
            'total_pack_waiting_confirm' => 0, // Tổng kiện hàng chờ xác nhận (Đến kho VN + loại là chưa xác định - vòng sttt)
            'total_kg_in_warehouse'      => 0, // Tổng trọng lượng hàng trong kho (Đã đến kho VN) (tính đến ngày hiện tại)
            'total_m3_in_warehouse'      => 0, // Tổng thể tích hàng trong kho (Đã đến kho VN) (tính đến ngày hiện tại)
        ];

        $result = $orderPackageA1->selectRaw("sum(if(status = 'status_10', 1, 0)) as total_pack_in_warehouse")->first();
        $select = "sum(if(status = 'status_10', weight, 0)) as total_kg_in_warehouse,
                   sum(if(status = 'status_10', (height * length * width), 0)) as total_m3_in_warehouse
                   ";
        $result_1 = $orderPackageA1
            ->whereBetween('updated_at', [$dateRange['startDate'], $dateRange['endDate']])
            ->selectRaw($select)->first();

        if ($result_1) {
            $arr_warehouse['total_pack_in_warehouse'] = $result->total_pack_in_warehouse == null ? 0 : (int)$result->total_pack_in_warehouse;
            $arr_warehouse['total_kg_in_warehouse'] = $result_1->total_kg_in_warehouse == null ? 0 : round($result_1->total_kg_in_warehouse, 2);
            $arr_warehouse['total_m3_in_warehouse'] = $result_1->total_m3_in_warehouse == null ? 0 : round($result_1->total_m3_in_warehouse/1000000, 2);
        }

        // ------
        $select = "sum(if(status = 'status_16', 1, 0)) as total_pack_received
                   ";

        $result_1 = $orderPackageA1->whereBetween('updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->selectRaw($select)->first();

        if ($result_1) {
            $arr_warehouse['total_pack_received'] = $result_1->total_pack_received == null ? 0 : (int)$result_1->total_pack_received;
        }

        // --------
        $select = "sum(if(status = 'status_14', 1, 0)) as total_pack_cod
                   ";

        $result_1 = $orderPackageA2->leftJoin('order_package_status_times', 'order_package.id', '=', 'order_package_status_times.order_package_id')
                            ->whereBetween('order_package_status_times.updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->where('order_package_status_times.key', 'status_14')
                            ->groupBy('order_package_status_times.order_package_id')
                            ->withoutGlobalScope(SortByCreatedDescScope::class)
                            ->get();

        if ($result_1) {
            $arr_warehouse['total_pack_cod'] = count($result_1);
        }

        // --------
        $select = "sum(if(status = 'status_10', 1, 0)) as total_pack_waiting_confirm
                   ";

        $orderPackageStatusTimeModel = \App::make('App\Models\OrderPackageStatusTime');

        $a1 = $orderPackageStatusTimeModel->where('key', 'status_10')
                                        ->whereBetween('order_package_status_times.updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                                        ->groupBy('order_package_status_times.order_package_id')
                                        ->get();

        $pack_ids = \Arr::pluck($a1, 'order_package_id');

        $orderPackageModel = \App::make('App\Models\OrderPackage');

        $b1 = $orderPackageModel->whereIn('id', $pack_ids)
                                ->where('order_type', NULL)
                                ->where('order_package.warehouse_id', $warehouse_id)
                                ->count();
        // $btest = $orderPackageModel->where('order_type', NULL)
        //                         ->where('order_package.warehouse_id', $warehouse_id)
        //                         ->get();

        // $pack_ids_test = \Arr::pluck($btest, 'id');
        // dd($pack_ids_test);

        if ($result_1) {
            $arr_warehouse['total_pack_waiting_confirm'] = $b1;
        }

        $data_result = [
            'arr_warehouse' => $arr_warehouse,
        ];
        return resSuccessWithinData($data_result);
    }

    public static function perDateRange($time, $options = []): array
    {
        $endDate = Carbon::now();
        $startDate = '';
        $startPrevious = '';
        $endPrevious = '';

        if ($time == 'd') {
            $startDate =  Carbon::today()->startOfDay();
        }
        if ($time == 'w'){
            $startDate = Carbon::now()->startOfWeek();
        }
        if ($time == 'm'){
            $startDate = Carbon::now()->firstOfMonth();
        }
        if ($time == 'q'){
            $startDate = Carbon::now()->startOfQuarter();
        }
        if ($time == 'y'){
            $startDate = Carbon::now()->startOfYear();
            if (!empty($options['year'])) {
                $startDate = $options['year'] . '-01-01 00:00:00';
                $endDate = $options['year'] . '-12-31 23:59:59';
            }
        }

        if ($time == 'd'){
            $startPrevious = Carbon::today()->subDay()->startOfDay();
            $endPrevious = Carbon::today()->subDay()->endOfDay();
        }
        if ($time == 'w'){
            $startPrevious = Carbon::today()->subWeek()->startOfWeek();
            $endPrevious = Carbon::today()->subWeek()->endOfWeek();
        }
        if ($time == 'm'){
            $startPrevious = Carbon::today()->subMonth()->startOfMonth();
            $endPrevious = Carbon::today()->subMonth()->endOfMonth();
        }
        if ($time == 'q'){
            $startPrevious = Carbon::today()->subMonth(3)->startOfQuarter();
            $endPrevious = Carbon::today()->subMonth(3)->endOfQuarter();
        }
        if ($time == 'y'){
            $startPrevious = Carbon::today()->subMonth(12)->startOfYear();
            $endPrevious = Carbon::today()->subMonth(12)->endOfYear();
            if (!empty($options['year'])) {
                $year = $options['year'] - 1;
                $startPrevious = $year . '-01-01 00:00:00';
                $endPrevious = $year . '-12-31 23:59:59';
            }
        }
        if (!empty($options['year'])) {
            return [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'startPrevious' => $startPrevious,
                'endPrevious' => $endPrevious,
            ];
        }

        return array(
           'startDate' => $startDate->format('Y-m-d H:i:s'),
           'endDate' => $endDate->format('Y-m-d H:i:s'),
           'startPrevious' => $startPrevious->format('Y-m-d H:i:s'),
           'endPrevious' => $endPrevious->format('Y-m-d H:i:s'),
       );
    }


    public function slow(Request $request): JsonResponse
    {
        $user_type = $request->get('user_type', 0);
        $warehouse_id = $request->get('warehouse_id', null);
        if ($user_type == 0) {
            return resSuccess('Done');
        }
        if ($user_type == 1) {
            return $this->getSlowType1($request->all());
        }
        if ($user_type == 2) {
            return $this->getSlowType2();
        }
        if ($user_type == 3) {
            return $this->getSlowType3();
        }
        if ($user_type == 4) {
            return $this->getSlowType4();
        }
        if ($user_type == 5) {
            return $this->getSlowType5();
        }
        if ($user_type == 7) {
            return $this->getSlowType7();
        }
        if ($user_type == 9) {
            return $this->getSlowType9();
        }
        if ($user_type == 10) {
            return $this->getSlowType10();
        }
        if ($user_type == 11) {
            return $this->getSlowType11();
        }
        if ($user_type == 12) {
            return $this->getSlowType12();
        }
        if ($user_type == 13) {
            $warehouse = Warehouse::query()->where('id', $warehouse_id)->first();
            if (is_null($warehouse)) {
                $result = ['error' => 'Chưa chọn kho, vui lòng thử lại'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            return $this->getSlowType13($warehouse_id);
        }
        return resSuccess('OK');
    }

    public function getSlowType1(array $params = []): JsonResponse
    {
        $year = (int)date('Y');
        if (!empty($params['year'])) {
            $year = (int)$params['year'];
        }
        $key = 'getSlowType1_'.$year;
        $results = Cache::get($key, []);
        if (empty($results)) {
            $data_2 = $this->getQuote('y', ['year' => $year]);
            $data_warehouse_done_2 = $this->getCustomerWarehouse10('y', ['year' => $year]);
            $warehouseModel = App::make('App\Models\Warehouse');
            $warehouses = $warehouseModel->where('country', 'vi')->get();
            $arr_warehouse_done_2 = [];
            foreach ($warehouses as $warehouse) {
                $code99 = str_replace("-", "", $warehouse->code);
                $temp = [
                    'name' => $warehouse->code,
                    'data' => $data_warehouse_done_2[$code99]
                ];
                $arr_warehouse_done_2[] = $temp;
            }
            $results = [
                'chart_user_type_1'  => [
                    'fee' => [
                        'chart_month' => $data_2
                    ],
                    'order_by_warehouse' => [
                        'chart_month' => $arr_warehouse_done_2
                    ]
                ],
            ];
            Cache::put($key, $results, now()->addMinutes(10));
        }
        return resSuccessWithinData($results);
    }

    public function getSlowType2()
    {
        $orderModel = \App::make('App\Models\Order');
        $dateRangeM = $this->perDateRange('m');
        $dateRangeD = $this->perDateRange('d');
        $dateRangeY = $this->perDateRange('y');

        // Nhân viên báo giá
        // Nhân viên báo giá
        // Nhân viên báo giá

        // Theo tháng
        $orderM1 = $orderModel;
        $orderM1 = $orderM1->leftJoin('users', 'orders.staff_quotation_id', '=', 'users.id');

        $orderM1 = $orderM1->whereBetween('orders.created_at', [$dateRangeM['startDate'], $dateRangeM['endDate']])
                            ->whereNotNull('staff_quotation_id');

        $select = 'orders.staff_quotation_id, users.name as staff_name,
                   count(*) as total_order';
        $orderM1 = $orderM1->selectRaw($select)->groupBy('staff_quotation_id')
                                    ->orderBy('total_order', 'DESC')
                                    ->offset(0)
                                    ->limit(7)
                                    ->get();

        $quotation_chart_month = [];
        if ($orderM1) {
            foreach ($orderM1 as $key => $item) {
                $temp = [
                    $item->staff_name,
                    $item->total_order
                ];
                array_push($quotation_chart_month, $temp);
            }
        }

        // Theo ngày
        $orderD1 = $orderModel;
        $orderD1 = $orderD1->leftJoin('users', 'orders.staff_quotation_id', '=', 'users.id');

        $orderD1 = $orderD1->whereBetween('orders.created_at', [$dateRangeD['startDate'], $dateRangeD['endDate']])
                            ->whereNotNull('staff_quotation_id');

        $select = 'orders.staff_quotation_id, users.name as staff_name,
                   count(*) as total_order';
        $orderD1 = $orderD1->selectRaw($select)->groupBy('staff_quotation_id')
                                    ->orderBy('total_order', 'DESC')
                                    ->offset(0)
                                    ->limit(7)
                                    ->get();

        $quotation_chart_day = [];
        if ($orderD1) {
            foreach ($orderD1 as $key => $item) {
                $temp = [
                    $item->staff_name,
                    $item->total_order
                ];
                array_push($quotation_chart_day, $temp);
            }
        }


        // Nhân viên đặt hàng
        // Nhân viên đặt hàng
        // Nhân viên đặt hàng

        $orderM1 = $orderModel;
        $orderM1 = $orderM1->leftJoin('users', 'orders.staff_order_id', '=', 'users.id');

        $orderM1 = $orderM1->whereBetween('orders.created_at', [$dateRangeM['startDate'], $dateRangeM['endDate']])
                            ->whereNotNull('staff_order_id');

        $select = 'orders.staff_order_id, users.name as staff_name,
                   count(*) as total_order';
        $orderM1 = $orderM1->selectRaw($select)->groupBy('staff_order_id')
                                    ->orderBy('total_order', 'DESC')
                                    ->offset(0)
                                    ->limit(7)
                                    ->get();

        $order_chart_month = [];
        if ($orderM1) {
            foreach ($orderM1 as $key => $item) {
                $temp = [
                    $item->staff_name,
                    $item->total_order
                ];
                array_push($order_chart_month, $temp);
            }
        }

        // Theo ngày
        $orderD1 = $orderModel;
        $orderD1 = $orderD1->leftJoin('users', 'orders.staff_order_id', '=', 'users.id');

        $orderD1 = $orderD1->whereBetween('orders.created_at', [$dateRangeD['startDate'], $dateRangeD['endDate']])
                            ->whereNotNull('staff_order_id');

        $select = 'orders.staff_order_id, users.name as staff_name,
                   count(*) as total_order';
        $orderD1 = $orderD1->selectRaw($select)->groupBy('staff_order_id')
                                    ->orderBy('total_order', 'DESC')
                                    ->offset(0)
                                    ->limit(7)
                                    ->get();

        $order_chart_day = [];
        if ($orderD1) {
            foreach ($orderD1 as $key => $item) {
                $temp = [
                    $item->staff_name,
                    $item->total_order
                ];
                array_push($order_chart_day, $temp);
            }
        }

        // Nhân viên khiếu nại
        // Nhân viên khiếu nại
        // Nhân viên khiếu nại

        $complainModel = \App::make('App\Models\Complain');
        $complainM = $complainModel;
        // $complainM = $complainModel->leftJoin('users', 'complains.staff_complain_id', '=', 'users.id');

        $complainM = $complainM->whereBetween('complains.created_at', [$dateRangeY['startDate'], $dateRangeY['endDate']]);

        $dataFormat = 'DATE_FORMAT(complains.created_at, "%m-%Y")';
        $dataFormatShow = 'DATE_FORMAT(complains.created_at, "%m")';

        $select = $dataFormat . 'as create_time,' . $dataFormatShow . 'as create_time_show,
                count(*) as total_complain,
                complains.staff_complain_id';

        $complainM = $complainM->selectRaw($select)->groupBy('create_time')
                                    ->orderBy('create_time_show', 'DESC')
                                    ->withoutGlobalScope(SortByCreatedDescScope::class)
                                    ->get();

        $complain_chart_month = [];
        for ($i=1; $i < 13; $i++) {
            array_push($complain_chart_month, 0);
        }

        if ($complainM) {
            foreach ($complainM as $key => $complain) {
                $timeM = (int)$complain->create_time_show;
                $complain_chart_month[$timeM - 1] = $complain['total_complain'];
            }
        }

        $data_result = [
            'chart_user_type_2'  => [
                'staff_quotation' => [
                    'chart_month' => $quotation_chart_month,
                    'chart_day' => $quotation_chart_day
                ],
                'staff_order' => [
                    'chart_month' => $order_chart_month,
                    'chart_day' => $order_chart_day
                ],
                'staff_complain' => [
                    'chart_month' => $complain_chart_month,
                ]
            ],
        ];
        return resSuccessWithinData($data_result);
    }

    public function getSlowType3()
    {
        $data_1 = $this->getQuote('m');
        $data_2 = $this->getQuote('y');

        $data_result = [
            'chart_user_type_3'  => [
                'chart_day' => $data_1,
                'chart_month' => $data_2
            ],
        ];
        return resSuccessWithinData($data_result);
    }

    public function getQuote($time, $options = []): array
    {
        $orderModel = App::make('App\Models\Order');
        $dateRangeY = $this->perDateRange('y', $options);
        if ($time == 'm') {
            $dateRangeY = $this->perDateRange('m', $options);
        }
        $chart_order_fee = [];
        $chart_inspection_cost = [];
        $chart_woodworking_cost = [];
        $chart_order_shipping_cost = [];

        $endFor = 13;
        if ($time == 'm') {
            $endFor = 32;
        }

        for ($i = 1; $i < $endFor; $i++) {
            $chart_order_fee[] = 0;
            $chart_inspection_cost[] = 0;
            $chart_woodworking_cost[] = 0;
            $chart_order_shipping_cost[] = 0;
        }

        $orderDB = $orderModel->where('status', OrderConstant::KEY_STATUS_DONE);
        $orderV1 = $orderDB->whereBetween('date_done', [$dateRangeY['startDate'], $dateRangeY['endDate']]);

        $dataFormat = 'DATE_FORMAT(date_done, "%m-%Y")';
        $dataFormatShow = 'DATE_FORMAT(date_done, "%m")';

        if ($time == 'm') {
            $dataFormat = 'DATE_FORMAT(date_done, "%d-%m-%Y")';
            $dataFormatShow = 'DATE_FORMAT(date_done, "%d")';
        }

        $select = $dataFormat . 'as create_time,' . $dataFormatShow . 'as create_time_show,
                sum(order_fee) as total_order_fee,
                sum(inspection_cost) as total_inspection_cost,
                sum(
                    (order_cost - order_cost_real)
                    + ((china_shipping_cost - china_shipping_cost_real) * exchange_rate)
                    + (bonus * exchange_rate)
                ) as total_order_shipping_cost,
                id';

        $orderV1 = $orderV1->selectRaw($select)->groupBy('create_time')
                                    ->orderBy('create_time_show', 'DESC')
                                    ->get();

        if ($orderV1) {
            foreach ($orderV1 as $order) {
                $timeM = (int)$order->create_time_show;
                $chart_order_fee[$timeM - 1] = round($order['total_order_fee']);
                $chart_inspection_cost[$timeM - 1] = round($order['total_inspection_cost']);
                $chart_order_shipping_cost[$timeM - 1] = round($order['total_order_shipping_cost']);
            }
        }

        // Đếm phí đóng gỗ
        $queryOrderPackage = OrderPackage::query();
        $queryOrderPackage = $queryOrderPackage->where('order_package.status', PackageConstant::STATUS_RECEIVED_GOODS);
        $queryOrderPackage = $queryOrderPackage->join('order_package_status_times', 'order_package_status_times.order_package_id', '=', 'order_package.id');
        $queryOrderPackage = $queryOrderPackage->where('order_package_status_times.key', PackageConstant::STATUS_RECEIVED_GOODS)
            ->whereBetween('order_package_status_times.created_at', [
                $dateRangeY['startDate'], $dateRangeY['endDate']
            ]);
        $dataFormat_go = 'DATE_FORMAT(order_package_status_times.created_at, "%m-%Y")';
        $dataFormatShow_go = 'DATE_FORMAT(order_package_status_times.created_at, "%m")';

        if ($time == 'm') {
            $dataFormat_go = 'DATE_FORMAT(order_package_status_times.created_at, "%d-%m-%Y")';
            $dataFormatShow_go = 'DATE_FORMAT(order_package_status_times.created_at, "%d")';
        }

        $select = $dataFormat_go . 'as create_time,' . $dataFormatShow_go . 'as create_time_show,
                sum(order_package.woodworking_cost) as total_woodworking_cost';

        $orderPackageV1 = $queryOrderPackage->selectRaw($select)->groupBy('create_time')
                                    ->orderBy('create_time_show', 'DESC')
                                    ->withoutGlobalScope(SortByCreatedDescScope::class);
        foreach ($orderPackageV1->cursor() as $order) {
            $timeM = (int)$order->create_time_show;
            $chart_woodworking_cost[$timeM - 1] = round($order->total_woodworking_cost);
        }

        return [
            [
                "name" => "Phí đặt hàng",
                "data" => $chart_order_fee
            ],
            [
                "name" => "Phí đàm phán",
                "data" => $chart_order_shipping_cost
            ],
            [
                "name" => "Phí kiểm đếm",
                "data" => $chart_inspection_cost
            ],
            [
                "name" => "Phí đóng gỗ",
                "data" => $chart_woodworking_cost
            ],
        ];
    }

    public function getSlowType4()
    {
        $data_1 = $this->getOrder('m');
        $data_2 = $this->getOrder('y');

        $data_result = [
            'chart_user_type_4'  => [
                'chart_day' => $data_1,
                'chart_month' => $data_2
            ],
        ];
        return resSuccessWithinData($data_result);
    }

    public function getOrder($time)
    {
        $orderModel = \App::make('App\Models\Order');
        $orderPackageModel = \App::make('App\Models\OrderPackage');
        $dateRangeY = $this->perDateRange('y');
        if ($time == 'm') {
            $dateRangeY = $this->perDateRange('m');
        }

        $chart_new = []; // Đơn hàng mới
        $chart_ordered = []; // Đã đặt hàng
        $chart_done = []; // Đã hoàn thành
        $chart_not_done = []; // Chưa hoàn thành, tất cả các đơn trừ tt đã hoàn thành + đã hủy

        $endFor = 13;
        if ($time == 'm') {
            $endFor = 32;
        }

        for ($i = 1; $i < $endFor; $i++) {
            array_push($chart_new, 0);
            array_push($chart_ordered, 0);
            array_push($chart_done, 0);
            array_push($chart_not_done, 0);
        }

        $orderDB = $orderModel;
        $orderV1 = $orderDB->whereBetween('created_at', [$dateRangeY['startDate'], $dateRangeY['endDate']]);

        $dataFormat = 'DATE_FORMAT(created_at, "%m-%Y")';
        $dataFormatShow = 'DATE_FORMAT(created_at, "%m")';

        if ($time == 'm') {
            $dataFormat = 'DATE_FORMAT(created_at, "%d-%m-%Y")';
            $dataFormatShow = 'DATE_FORMAT(created_at, "%d")';
        }

        $select = $dataFormat . 'as create_time,' . $dataFormatShow . 'as create_time_show,
                sum(if(orders.status = \''. OrderConstant::KEY_STATUS_WAITING_QUOTE .'\', 1, 0)) as total_new,
                sum(if(orders.status = \''. OrderConstant::KEY_STATUS_ORDERED .'\', 1, 0)) as total_ordered,
                sum(if(orders.status = \''. OrderConstant::KEY_STATUS_DONE .'\', 1, 0)) as total_done,
                sum(if(orders.status not in ( \''. OrderConstant::KEY_STATUS_DONE .'\', \''. OrderConstant::KEY_STATUS_CANCEL .'\'), 1, 0)) as total_not_done,
                id';

        $orderV1 = $orderV1->selectRaw($select)->groupBy('create_time')
                                    ->orderBy('create_time_show', 'DESC')
                                    ->get();
        if ($orderV1) {
            foreach ($orderV1 as $key => $order) {
                $timeM = (int)$order->create_time_show;
                $chart_new[$timeM - 1] = (int)$order['total_new'];
                $chart_ordered[$timeM - 1] = (int)$order['total_ordered'];
                $chart_done[$timeM - 1] = (int)$order['total_done'];
                $chart_not_done[$timeM - 1] = (int)$order['total_not_done'];
            }
        }

        return [
            [
                "name" => "Đơn hàng mới",
                "data" => $chart_new
            ],
            [
                "name" => "Đã đặt hàng",
                "data" => $chart_ordered
            ],
            [
                "name" => "Đã hoàn thành",
                "data" => $chart_done
            ],
            [
                "name" => "Chưa hoàn thành",
                "data" => $chart_not_done
            ],
        ];
    }

    public function getSlowType5()
    {
        $data_1 = $this->getOrderGroup('m');
        $data_2 = $this->getOrderGroup('y');

        $data_result = [
            'chart_user_type_5'  => [
                'chart_day' => $data_1,
                'chart_month' => $data_2
            ],
        ];
        return resSuccessWithinData($data_result);
    }

    public function getOrderGroup($time)
    {
        $orderModel = \App::make('App\Models\Order');
        $dateRange = $this->perDateRange('y');
        if ($time == 'm') {
            $dateRange = $this->perDateRange('m');
        }
        $orderDB = $orderModel;
        // $orderDB = $orderModel->leftJoin('customers', 'orders.customer_id', '=', 'customers.id');
        $orderDB = $orderDB->join('order_package', 'orders.id', '=', 'order_package.order_id');
        $orderDB = $orderDB->where('orders.status', OrderConstant::KEY_STATUS_DONE)
                            ->whereNotNull('orders.staff_counselor_id')
                            ->whereBetween('orders.created_at', [$dateRange['startDate'], $dateRange['endDate']]);

        $select = 'sum(orders.order_cost + orders.order_fee + orders.inspection_cost + orders.china_shipping_cost * orders.exchange_rate) + sum(order_package.insurance_cost + order_package.woodworking_cost + order_package.international_shipping_cost + order_package.shock_proof_cost + order_package.storage_cost + order_package.delivery_cost) as total_full,
            orders.staff_counselor_id';


        $orderDB = $orderDB->selectRaw($select)->groupBy('orders.staff_counselor_id')
                                    ->orderBy('total_full', 'DESC')
                                    ->offset(0)
                                    ->limit(7)
                                    ->get();

        $arr_result = [];
        if ($orderDB) {
            $userModel = \App::make('App\Models\User');
            foreach ($orderDB as $key => $item) {
                $user = $userModel->where('id', $item->staff_counselor_id)->first();
                // $temp = [
                //     'user_name' => $user->name,
                //     'total_full' => (int) $item->total_full,
                // ];
                $temp = [
                    $user->name,
                    (int)$item->total_full,
                ];
                array_push($arr_result, $temp);
            }
        }

        return $arr_result;
    }

    public function getSlowType7()
    {
        $data_1 = $this->getOrderGroupV2('m');
        $data_2 = $this->getOrderGroupV2('y');

        $data_result = [
            'chart_user_type_7'  => [
                'chart_day' => $data_1,
                'chart_month' => $data_2
            ],
        ];
        return resSuccessWithinData($data_result);
    }

    public function getOrderGroupV2($time)
    {
        $orderModel = \App::make('App\Models\Order');
        $dateRange = $this->perDateRange('y');
        if ($time == 'm') {
            $dateRange = $this->perDateRange('m');
        }
        $orderDB = $orderModel;
        // $orderDB = $orderModel->leftJoin('customers', 'orders.customer_id', '=', 'customers.id');
        // $orderDB = $orderDB->leftJoin('order_package', 'orders.id', '=', 'order_package.order_id');
        $orderDB = $orderDB->where('orders.status', OrderConstant::KEY_STATUS_DONE)
                            ->whereNotNull('orders.staff_care_id')
                            ->whereBetween('orders.created_at', [$dateRange['startDate'], $dateRange['endDate']]);

        $select = 'sum(orders.order_cost + orders.order_fee + orders.inspection_cost + orders.china_shipping_cost * orders.exchange_rate) as total_full,
            orders.staff_care_id';


        $orderDB = $orderDB->selectRaw($select)->groupBy('orders.staff_care_id')
                                    ->orderBy('total_full', 'DESC')
                                    ->offset(0)
                                    ->limit(7)
                                    ->get();
        // $a = \Arr::pluck($orderDB, 'staff_care_id');
        // dd(array_unique($a));

        $arr_result = [];
        if ($orderDB) {
            $userModel = \App::make('App\Models\User');
            foreach ($orderDB as $key => $item) {
                $user = $userModel->where('id', $item->staff_care_id)->first();
                // $temp = [
                //     'user_name' => $user->name,
                //     'total_full' => (int) $item->total_full,
                // ];
                $temp = [
                    $user->name,
                    (int)$item->total_full,
                ];
                array_push($arr_result, $temp);
            }
        }

        return $arr_result;
    }

    public function getSlowType9()
    {
        $fundTransactionModel = \App::make('App\Models\FundTransaction');
        $fundTransactionM = $fundTransactionModel;

        $dateRangeY = $this->perDateRange('y');

        $fundTransactionM = $fundTransactionM->whereBetween('created_at', [$dateRangeY['startDate'], $dateRangeY['endDate']]);

        $dataFormat = 'DATE_FORMAT(created_at, "%m-%Y")';
        $dataFormatShow = 'DATE_FORMAT(created_at, "%m")';

        $select = $dataFormat . 'as create_time,' . $dataFormatShow . 'as create_time_show,
                sum(if(type_pay = 1 and fund_unit_currency = 1, money_update, 0)) as total_thu,
                sum(if(type_pay = 2 and fund_unit_currency = 1, money_update, 0)) as total_chi,
                id';

        $fundTransactionM = $fundTransactionM->selectRaw($select)->groupBy('create_time')
                                    ->orderBy('create_time_show', 'DESC')
                                    ->get();

        $thu_chart_month = [];
        $chi_chart_month = [];
        for ($i=1; $i < 13; $i++) {
            array_push($thu_chart_month, 0);
            array_push($chi_chart_month, 0);
        }

        if ($fundTransactionM) {
            foreach ($fundTransactionM as $key => $fundTransaction) {
                $timeM = (int)$fundTransaction->create_time_show;
                $thu_chart_month[$timeM - 1] = $fundTransaction['total_thu'];
                $chi_chart_month[$timeM - 1] = $fundTransaction['total_chi'];
            }
        }

        $data_result = [
            'chart_user_type_9'  => [
                'chart_month' => [
                    [
                        'name' => 'Khoản thu',
                        'data' => $thu_chart_month,
                        'color' => '#F09920'
                    ],
                    [
                        'name' => 'Khoản chi',
                        'data' => $chi_chart_month,
                        'color' => '#D60B4A'
                    ],
                ]
            ]
        ];
        return resSuccessWithinData($data_result);
    }

    public function getSlowType10()
    {
        // Khách hàng mới
        $data_customer_new_1 = $this->getCustomerNew10('m');
        $data_customer_new_2 = $this->getCustomerNew10('y');

        // Biểu đồ khách đặt hàng
        $data_customer_place_ordered_1 = $this->getCustomerPlaceOrdered10('m');
        $data_customer_place_ordered_2 = $this->getCustomerPlaceOrdered10('y');

        // Biểu đồ khách đặt hàng theo kênh
        $data_customer_ecommerce_1 = $this->getCustomerEcommerce10('m');
        $data_customer_ecommerce_2 = $this->getCustomerEcommerce10('y');

        // Biểu đồ khách đặt hàng theo chi nhánh
        $data_customer_warehouse_1 = $this->getCustomerWarehouse10('m');
        $data_customer_warehouse_2 = $this->getCustomerWarehouse10('y');

        // Lấy kho
        $warehouseModel = \App::make('App\Models\Warehouse');
        $warehouses = $warehouseModel->where('country', 'vi')->get();
        $arr_warehouses = [];
        foreach ($warehouses as $key => $warehouse) {
            $code99 = str_replace("-", "", $warehouse->code);
            $temp = [
                'code' => $code99,
                'code_full' => $warehouse->code,
                'name' => $warehouse->name
            ];
            array_push($arr_warehouses, $temp);
        }

        $data_result = [
            'chart_user_type_10'  => [
                'customer_new' => [
                    'chart_day' => $data_customer_new_1,
                    'chart_month' => $data_customer_new_2
                ],
                'customer_place_ordered' => [
                    'chart_day' => $data_customer_place_ordered_1,
                    'chart_month' => $data_customer_place_ordered_2
                ],
                'customer_ecommerce' => [
                    'chart_day' => $data_customer_ecommerce_1,
                    'chart_month' => $data_customer_ecommerce_2
                ],
                'customer_warehouse' => [
                    'arr_warehouses'  => $arr_warehouses,
                    'chart_day'   => $data_customer_warehouse_1,
                    'chart_month' => $data_customer_warehouse_2
                ]
            ],
        ];
        return resSuccessWithinData($data_result);
    }

    public function getCustomerNew10($time)
    {
        $customerModel = \App::make('App\Models\Customer');

        $dateRange = $this->perDateRange('y');
        if ($time == 'm') {
            $dateRange = $this->perDateRange('m');
        }

        $chart_customer_new = []; // Khách hàng mới

        $endFor = 13;
        if ($time == 'm') {
            $endFor = 32;
        }

        for ($i = 1; $i < $endFor; $i++) {
            array_push($chart_customer_new, 0);
        }

        $customerA1 = $customerModel->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']]);

        $dataFormat = 'DATE_FORMAT(created_at, "%m-%Y")';
        $dataFormatShow = 'DATE_FORMAT(created_at, "%m")';

        if ($time == 'm') {
            $dataFormat = 'DATE_FORMAT(created_at, "%d-%m-%Y")';
            $dataFormatShow = 'DATE_FORMAT(created_at, "%d")';
        }

        $select = $dataFormat . 'as create_time,' . $dataFormatShow . 'as create_time_show,
                count(*) as total_new,
                id';

        $customerA1 = $customerA1->selectRaw($select)->groupBy('create_time')
                                    ->orderBy('create_time_show', 'DESC')
                                    ->get();


        if ($customerA1) {
            foreach ($customerA1 as $key => $order) {
                $timeM = (int)$order->create_time_show;
                $chart_customer_new[$timeM - 1] = (int)$order['total_new'];
            }
        }

        return $chart_customer_new;
    }

    public function getCustomerPlaceOrdered10($time)
    {
        $orderModel = \App::make('App\Models\Order');

        $dateRange = $this->perDateRange('y');
        if ($time == 'm') {
            $dateRange = $this->perDateRange('m');
        }

        $chart_data = []; // Khách hàng đặt hàng

        $endFor = 13;
        if ($time == 'm') {
            $endFor = 32;
        }

        for ($i = 1; $i < $endFor; $i++) {
            array_push($chart_data, 0);
        }

        $orderA1 = $orderModel;
        $orderA1 = $orderA1->leftJoin('order_status_times', 'orders.id', '=', 'order_status_times.order_id');

        $orderA1 = $orderA1->whereBetween('orders.updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->where('order_status_times.status_3', '<>', "");

        $dataFormat = 'DATE_FORMAT(orders.created_at, "%m-%Y")';
        $dataFormatShow = 'DATE_FORMAT(orders.created_at, "%m")';

        if ($time == 'm') {
            $dataFormat = 'DATE_FORMAT(orders.created_at, "%d-%m-%Y")';
            $dataFormatShow = 'DATE_FORMAT(orders.created_at, "%d")';
        }

        $select = $dataFormat . 'as create_time,' . $dataFormatShow . 'as create_time_show,
                count(*) as total_place_ordered,
                orders.customer_id';

        $orderA1 = $orderA1->selectRaw($select)->groupBy('create_time')
                                    ->groupBy('orders.customer_id')
                                    ->get();

        if ($orderA1) {
            $collection = collect($orderA1->toArray());
            $grouped = $collection->groupBy('create_time_show');
            $grouped->all();

            foreach ($grouped as $key => $order) {
                $timeM = (int)$key;
                $chart_data[$timeM - 1] = (int)count($order);
            }
        }

        return $chart_data;
    }

    public function getCustomerEcommerce10($time)
    {
        $orderModel = \App::make('App\Models\Order');

        $dateRange = $this->perDateRange('y');
        if ($time == 'm') {
            $dateRange = $this->perDateRange('m');
        }

        $chart_data_taobao = [];
        $chart_data_tmall = [];
        $chart_data_1688 = [];

        $endFor = 13;
        if ($time == 'm') {
            $endFor = 32;
        }

        for ($i = 1; $i < $endFor; $i++) {
            array_push($chart_data_taobao, 0);
            array_push($chart_data_tmall, 0);
            array_push($chart_data_1688, 0);
        }

        $arr_ecommerces = [
            'taobao.com',
            'tmall.com',
            '1688.com'
        ];

        $orderA1 = $orderModel;

        $orderA1 = $orderA1->whereBetween('orders.updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                            ->where('orders.status', '<>', OrderConstant::KEY_STATUS_CANCEL)
                            ->whereIn('orders.ecommerce', $arr_ecommerces);

        $dataFormat = 'DATE_FORMAT(orders.created_at, "%m-%Y")';
        $dataFormatShow = 'DATE_FORMAT(orders.created_at, "%m")';

        if ($time == 'm') {
            $dataFormat = 'DATE_FORMAT(orders.created_at, "%d-%m-%Y")';
            $dataFormatShow = 'DATE_FORMAT(orders.created_at, "%d")';
        }

        $select = $dataFormat . 'as create_time,' . $dataFormatShow . 'as create_time_show,
                count(*) as total_ecommerce,
                orders.ecommerce';

        $orderA1 = $orderA1->selectRaw($select)->groupBy('create_time')
                                    ->groupBy(['orders.ecommerce'])
                                    ->get();
        foreach ($orderA1 as $key => $order) {
            $timeM = (int)$key;

            if (data_get($order, 'ecommerce') == 'taobao.com') {
                $chart_data_taobao[$timeM - 1] = (int)data_get($order, 'total_ecommerce', 0);
            }
            if (data_get($order, 'ecommerce') == 'tmall.com') {
                $chart_data_tmall[$timeM - 1] = (int)data_get($order, 'total_ecommerce', 0);
            }
            if (data_get($order, 'ecommerce') == '1688.com') {
                $chart_data_1688[$timeM - 1] = (int)data_get($order, 'total_ecommerce', 0);
            }

        }

        return [
            [
                'name' => 'Taobao.com',
                'data' => $chart_data_taobao
            ],
            [
                'name' => 'Tmall.com',
                'data' => $chart_data_tmall
            ],
            [
                'name' => '1688.com',
                'data' => $chart_data_1688
            ]
        ];
    }

    public function getCustomerWarehouse10($time, $options = []): array
    {
        // Lấy kho
        $warehouseModel = App::make('App\Models\Warehouse');
        $warehouses = $warehouseModel->where('country', 'vi')->get();
        $warehouseIds = array_map(function($ware){
            return $ware['id'];
        }, $warehouses->toArray());


        $orderModel = App::make('App\Models\Order');

        $dateRange = $this->perDateRange('y', $options);
        $start = !empty($dateRange['startDate']) ? $dateRange['startDate'] : Carbon::now()->startOfYear();
        $end = !empty($dateRange['endDate']) ? $dateRange['endDate'] : Carbon::now()->endOfYear();
        if ($time == 'm') {
            $dateRange = $this->perDateRange('m');
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();
        }

        $data_chart = [];
        $orderA1 = $orderModel;
        $orderA1 = $orderA1->where('orders.status', OrderConstant::KEY_STATUS_DONE)
                            ->whereBetween('orders.date_done', [$start, $end])
                            ->whereIn('warehouse_id', $warehouseIds);

        $select = 'count(*) as total_place_warehouse, month(orders.date_done) date, orders.warehouse_id as warehouse_id';
        if ($time == 'm') {
            $select = 'count(*) as total_place_warehouse, orders.date_done as date, orders.warehouse_id as warehouse_id';
        }
        $orderA1 = $orderA1->selectRaw($select)
                                    ->groupBy(['date', 'orders.warehouse_id'])
                                    ->orderBy('warehouse_id')
                                    ->get();
        foreach ($warehouses as $warehouse) {
            $code99 = str_replace("-", "", $warehouse->code);
            $data_chart[$code99] = [];

            $endFor = 13;
            if ($time == 'm') {
                $endFor = 32;
            }

            for ($i = 1; $i < $endFor; $i++) {
                $result = $orderA1->filter(function ($q) use ($i, $warehouse) {
                    return $q->date == $i && $q->warehouse_id == $warehouse->id;
                })->first();
                $data_chart[$code99][] = round(data_get($result, 'total_place_warehouse', 0));
            }

        }

        return $data_chart;
    }

    public function getSlowType11()
    {
        // Kiện hàng về kho status_5
        $data_pack_in_china_1 = $this->getOrderPack11('m', 'status_5');
        $data_pack_in_china_2 = $this->getOrderPack11('y', 'status_5');

        // Kiện hàng giao hàng status_6 (Đang về VN)
        $data_pack_in_progress_1 = $this->getOrderPack11('m', 'status_6');
        $data_pack_in_progress_2 = $this->getOrderPack11('y', 'status_6');

        $data_result = [
            'chart_user_type_11'  => [
                'pack_in_china' => [
                    'chart_day' => $data_pack_in_china_1,
                    'chart_month' => $data_pack_in_china_2
                ],
                'pack_in_progress' => [
                    'chart_day' => $data_pack_in_progress_1,
                    'chart_month' => $data_pack_in_progress_2
                ],
            ],
        ];
        return resSuccessWithinData($data_result);
    }

    public function getOrderPack11($time, $status)
    {
        $orderPackageModel = \App::make('App\Models\OrderPackageStatusTime');

        $dateRange = $this->perDateRange('y');
        if ($time == 'm') {
            $dateRange = $this->perDateRange('m');
        }


        $data_chart = [];
        $endFor = 13;
        if ($time == 'm') {
            $endFor = 32;
        }
        for ($i = 1; $i < $endFor; $i++) {
            array_push($data_chart, 0);
        }

        $orderPackageA1 = $orderPackageModel;
        $orderPackageA1 = $orderPackageA1
            ->where('order_package_status_times.key', $status)
            ->whereBetween('order_package_status_times.created_at', [$dateRange['startDate'], $dateRange['endDate']]);


        $dataFormat = 'DATE_FORMAT(order_package_status_times.created_at, "%m-%Y")';
        $dataFormatShow = 'DATE_FORMAT(order_package_status_times.created_at, "%m")';

        if ($time == 'm') {
            $dataFormat = 'DATE_FORMAT(order_package_status_times.created_at, "%d-%m-%Y")';
            $dataFormatShow = 'DATE_FORMAT(order_package_status_times.created_at, "%d")';
        }
        // Trùng lặp; DISTINCT

        $select = $dataFormat . 'as create_time,' . $dataFormatShow . 'as create_time_show,
                order_package_id,
                order_package_status_times.key
                ';


        $orderPackageA1 = $orderPackageA1
                                    ->selectRaw($select)
                                    ->groupBy('create_time')
                                    ->groupBy('order_package_id')
                                    // ->withoutGlobalScope(\App\Scopes\SortByCreatedDescScope::class)
                                    ->get();
        if ($orderPackageA1) {
            $collection = collect($orderPackageA1->toArray());
            $grouped = $collection->groupBy('create_time_show');
            $grouped->all();
            foreach ($grouped as $key => $order) {
                $timeM = (int)$key;
                $data_chart[$timeM - 1] = (int)count($order);
            }
        }

        return $data_chart;
    }

    public function getSlowType12()
    {
        // Tổng trọng lượng kiện hàng nhận
        $data_pack_receive_kg_1 = $this->getOrderPack12('m', 'kg');
        $data_pack_receive_kg_2 = $this->getOrderPack12('y', 'kg');

        // Tổng thể tích kiện hàng nhận
        $data_pack_receive_m3_1 = $this->getOrderPack12('m', 'm3');
        $data_pack_receive_m3_2 = $this->getOrderPack12('y', 'm3');

        $data_result = [
            'chart_user_type_12'  => [
                'pack_pack_receive_kg' => [
                    'chart_day' => $data_pack_receive_kg_1,
                    'chart_month' => $data_pack_receive_kg_2
                ],
                'pack_pack_receive_m3' => [
                    'chart_day' => $data_pack_receive_m3_1,
                    'chart_month' => $data_pack_receive_m3_2
                ],
            ],
        ];
        return resSuccessWithinData($data_result);
    }

    public function getOrderPack12($time, $type)
    {
        $orderPackageModel = \App::make('App\Models\OrderPackage');

        $dateRange = $this->perDateRange('y');
        if ($time == 'm') {
            $dateRange = $this->perDateRange('m');
        }

        $data_chart = [];
        $endFor = 13;
        if ($time == 'm') {
            $endFor = 32;
        }
        for ($i = 1; $i < $endFor; $i++) {
            array_push($data_chart, 0);
        }

        $orderPackageA1 = $orderPackageModel;
        $orderPackageA1 = $orderPackageA1->leftJoin('order_package_status_times', 'order_package.id', '=', 'order_package_status_times.order_package_id')
            ->whereIn('order_package_status_times.key', ['status_8', 'status_9'])
            ->whereBetween('order_package_status_times.created_at', [$dateRange['startDate'], $dateRange['endDate']]);

        // dd($orderPackageA1->get());
        // $sum = 0;
        // foreach ($orderPackageA1->get() as $key => $value) {
        //     $sum += round(($value->height * $value->length * $value->width), 2);
        // }

        // dd($sum);

        $dataFormat = 'DATE_FORMAT(order_package_status_times.created_at, "%m-%Y")';
        $dataFormatShow = 'DATE_FORMAT(order_package_status_times.created_at, "%m")';

        if ($time == 'm') {
            $dataFormat = 'DATE_FORMAT(order_package_status_times.created_at, "%d-%m-%Y")';
            $dataFormatShow = 'DATE_FORMAT(order_package_status_times.created_at, "%d")';
        }

        $select = $dataFormat . 'as create_time,' . $dataFormatShow . 'as create_time_show,
                sum(round(weight, 2)) as total_kg,
                sum(round((height * length * width), 2)) as total_m3,
                order_package.id';


        $orderPackageA1 = $orderPackageA1->selectRaw($select)->groupBy('create_time')
                                    ->groupBy('order_package.id')
                                    ->withoutGlobalScope(SortByCreatedDescScope::class)
                                    ->get();

        if ($orderPackageA1) {
            $collection = collect($orderPackageA1->toArray());
            $grouped = $collection->groupBy('create_time_show');
            $grouped->all();

            foreach ($grouped as $key => $order) {
                $timeM = (int)$key;
                $kaka = \Arr::pluck($order, 'total_kg');
                if ($type == 'm3') {
                    $kaka = \Arr::pluck($order, 'total_m3');
                }

                $total_kaka = array_sum($kaka);

                $data_chart[$timeM - 1] = (int)$total_kaka;
            }
        }

        return $data_chart;
    }


    public function getSlowType13($warehouse_id)
    {
        $data_warehouse_vn_1 = $this->getOrderPack13('m', $warehouse_id);
        $data_warehouse_vn_2 = $this->getOrderPack13('y', $warehouse_id);

        $data_result = [
            'chart_user_type_13'  => [
                'chart_day'   => $data_warehouse_vn_1,
                'chart_month' => $data_warehouse_vn_2
            ],
        ];
        return resSuccessWithinData($data_result);
    }

    public function getOrderPack13($time , $warehouse_id)
    {
        $orderPackageModel = \App::make('App\Models\OrderPackage');

        $dateRange = $this->perDateRange('y');
        if ($time == 'm') {
            $dateRange = $this->perDateRange('m');
        }

        $data_chart = [];
        $endFor = 13;
        if ($time == 'm') {
            $endFor = 32;
        }
        for ($i = 1; $i < $endFor; $i++) {
            array_push($data_chart, 0);
        }

        $orderPackageA1 = $orderPackageModel->where('order_package.warehouse_id', $warehouse_id);
        $orderPackageA1 = $orderPackageA1->leftJoin('order_package_status_times', 'order_package.id', '=', 'order_package_status_times.order_package_id')
            ->whereIn('order_package_status_times.key', ['status_10'])
            ->whereBetween('order_package_status_times.created_at', [$dateRange['startDate'], $dateRange['endDate']]);

        // $a = $orderPackageA1->get();

        // foreach ($a as $key => $value) {
        //     dump($value->created_at->format('Y-m-d H:i:s'));
        // }
        // dd($orderPackageA1->get());

        $dataFormat = 'DATE_FORMAT(order_package_status_times.created_at, "%m-%Y")';
        $dataFormatShow = 'DATE_FORMAT(order_package_status_times.created_at, "%m")';

        if ($time == 'm') {
            $dataFormat = 'DATE_FORMAT(order_package_status_times.created_at, "%d-%m-%Y")';
            $dataFormatShow = 'DATE_FORMAT(order_package_status_times.created_at, "%d")';
        }

        $select = $dataFormat . 'as create_time,' . $dataFormatShow . 'as create_time_show,
                count(*) as total_pack,
                order_package.id';


        $orderPackageA1 = $orderPackageA1->selectRaw($select)->groupBy('create_time')
                                    ->groupBy('order_package.id')
                                    ->withoutGlobalScope(SortByCreatedDescScope::class)
                                    ->get();
        // dd($orderPackageA1);

        // foreach ($orderPackageA1 as $key => $value) {
        //     dump($value->create_time);
        // }
        // dd($orderPackageA1->get());

        if ($orderPackageA1) {
            $collection = collect($orderPackageA1->toArray());
            $grouped = $collection->groupBy('create_time_show');
            $grouped->all();

            foreach ($grouped as $key => $order) {
                $timeM = (int)$key;
                $data_chart[$timeM - 1] = (int)count($order);
            }
        }


        // Combat
        // $orderPackageStatusTimeModel = \App::make('App\Models\OrderPackageStatusTime');

        // $abs = $orderPackageStatusTimeModel->where('key', "status_10")
        //                                 ->whereBetween('order_package_status_times.created_at', ['2023-03-10 00:00:00', '2023-03-10 23:59:59'])
        //                                 ->get();


        // $pack_ids = \Arr::pluck($abs, 'order_package_id');


        // $orderPackageModel = \App::make('App\Models\OrderPackage');

        // $b1 = $orderPackageModel->whereIn('id', $pack_ids)
        //                         ->where('order_package.warehouse_id', "1eda5f97-9239-66e8-a055-00163e0109a7")
        //                         ->get();
        // dd($b1);



        return $data_chart;
    }



    public function getListWareHouse()
    {
        // Lấy kho
        $warehouseModel = \App::make('App\Models\Warehouse');
        $warehouses = $warehouseModel->where('country', 'vi')->get();
        $arr_warehouses = [];
        foreach ($warehouses as $key => $warehouse) {
            $code99 = str_replace("-", "", $warehouse->code);
            $temp = [
                'id' => $warehouse->id,
                'code' => $code99,
                'code_full' => $warehouse->code,
                'name' => $warehouse->name
            ];
            array_push($arr_warehouses, $temp);
        }

        $data_result = [
            'arr_warehouses'  => $arr_warehouses
        ];
        return resSuccessWithinData($data_result);
    }

    public function printReceiveGoods(Request $request)
    {
        $params = $request->all();
        return $this->filePrintService->getData($params);
    }

    public function listUserReceiveGoods(Request $request)
    {
        $params = $request->all();
        $userModel = \App::make('App\Models\User');
        $users = $userModel->role(['nhan-vien-kho.1', 'truong-phong-kho.1'])->get();
        $data_result = [];
        if (count($users)) {
            foreach ($users as $key => $user) {
                $temp = [
                    'user_id'   => (int) $user->id,
                    'user_name' => $user->name,
                ];
                array_push($data_result, $temp);
            }

        }
        return resSuccessWithinData($data_result);
    }

    public function printFile($id)
    {
        $file = FilePrint::find($id);
        $dataJson = json_decode($file->data_json);
        $type = $file->type;
        $data = $this->orderPackService->getDataPrintPack($dataJson);
        $data['type'] = $type;
        $view_content = View::make('print.warehouse_pack', $data)->render();
        return resSuccessWithinData($view_content);
    }

    public function getPackInFilePrint(Request $request)
    {
        $validationRules = [
            'file_print_id'    => 'required|exists:file_prints,id',
            'order_package_id' => 'required'
        ];
        $validationMessages = [

        ];

        $params = $request->all();

        $orderPackModel = \App::make('App\Models\OrderPackage');
        $filePrintModel = \App::make('App\Models\FilePrint');
        $filePrintCheckModel = \App::make('App\Models\FilePrintCheck');

        $filePrintDB = $filePrintModel->where('id', $params['file_print_id'])->first();
        $dataFilePrint = json_decode($filePrintDB->data_json, true);

        // Đã check
        $filePrintCheckDBs = $filePrintCheckModel->where('file_print_id', $params['file_print_id'])->get();

        // Khách hàng
        $customerDB = $filePrintDB->customer;

        // Kiện
        $orderPackageDBs = $orderPackModel->whereIn('id', $dataFilePrint['orderPackIds'])->get();
        $data_packs = [];
        foreach ($orderPackageDBs as $key => $orderPackageDB) {

            $weight = $orderPackageDB->weight ?? 0;
            $volume = $orderPackageDB->volume ?? 0;

            $checked = false;
            $time_check = null;

            if (count($filePrintCheckDBs) > 0) {
                foreach ($filePrintCheckDBs as $key => $filePrintCheckDB) {
                    if ($filePrintCheckDB->order_package_id == $orderPackageDB->id) {
                        $checked = true;
                        $time_check = $orderPackageDB->created_at->format('Y-m-d H:i:s');
                    }
                }
            }

            $temp = [
                'id'             => (int) $orderPackageDB->id,
                'bill_code'      => $orderPackageDB->bill_code,
                'kg_m3'          => $orderPackageDB->type_quantitative == 'weight' ? "$weight kg" : "$volume m³",
                'package_number' => $orderPackageDB->package_number,
                'note_internal'  => $orderPackageDB->note_internal,
                'checked'        => $checked,
                'time_check'     => $time_check,
                'fee'            => $orderPackageDB->amount
            ];

            array_push($data_packs, $temp);
        }

        $data_return = [
            'id'             => (int) $filePrintDB->id,
            'customer_id'    => (int) $customerDB->id,
            'customer_name'  => $customerDB->name,
            'customer_code'  => $customerDB->code,
            'customer_phone' => $customerDB->phone_number,
            'data_packs'     => $data_packs,
        ];

        return resSuccessWithinData($data_return);
    }

    public function checkPackInFilePrint(Request $request)
    {
        $validationRules = [
            'file_print_id'    => 'required|exists:file_prints,id',
            'order_package_id' => 'required'
        ];
        $validationMessages = [

        ];

        $params = $request->all();

        $orderPackModel = \App::make('App\Models\OrderPackage');
        $filePrintModel = \App::make('App\Models\FilePrint');
        $filePrintCheckModel = \App::make('App\Models\FilePrintCheck');

        $filePrintDB = $filePrintModel->where('id', $params['file_print_id'])->first();
        $dataFilePrint = json_decode($filePrintDB->data_json, true);

        // Nếu không có kiện trong File thì cho té
        if (!in_array($params['order_package_id'], $dataFilePrint['orderPackIds'])) {
            $result = [
                'unit' => 'Kiện không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check xem nó đã dc được check chưa
        $filePrintCheckDB = $filePrintCheckModel->where('file_print_id', $params['file_print_id'])
                                                ->where('order_package_id', $params['order_package_id'])
                                                ->first();
        if (!is_null($filePrintCheckDB)) {
            $result = [
                'unit' => 'Kiện đã được quét'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Cập nhật
        try {
            $userOnline = \Auth::user();
            \DB::beginTransaction();
            $data_check = [
                'file_print_id'    => $params['file_print_id'],
                'order_package_id' => $params['order_package_id'],
                'user_id'          => (int) $userOnline->id
            ];
            $filePrintCheckModel->create($data_check);
            \DB::commit();
            return resSuccessWithinData([]);
        } catch (Exception $e) {
            \DB::rollback();
            return resError($e->getMessage());
        }
    }

    public function reportQuote(Request $request)
    {
        $now = Carbon::now();
        $startDate = $request->input('start_date') ? Carbon::createFromFormat('Y-m-d', $request->input('start_date'))->startOfDay() : $now->copy()->startOfDay();
        $endDate = $request->input('end_date') ? Carbon::createFromFormat('Y-m-d', $request->input('end_date'))->endOfDay() : $now->copy()->endOfDay();
        $statuses = [
            OrderConstant::KEY_STATUS_WAITING_QUOTE,
            OrderConstant::KEY_STATUS_WAITING_DEPOSIT,
            OrderConstant::KEY_STATUS_DEPOSITED,
            OrderConstant::KEY_STATUS_ORDERED,
            OrderConstant::KEY_STATUS_RECONFIRM
        ];
        $query = Order::query()->selectRaw("count(id) as count, status")
                                ->whereIn('status', $statuses);
        if($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        $auth = getCurrentUser();
        if($auth->hasDepartmentName(DepartmentConstant::TYPE_QUOTE)) {
            $query->where('staff_quotation_id', $auth->id);
        }
        $data = $query->groupBy('status')->get();
        $listStatus = [];
        foreach($statuses as $status) {
            $listStatus[] = [
                'quantity' => 0,
                'value' => $status,
                'name' => OrderConstant::STATUSES[$status]
            ];
        }
        $listStatus = array_map(function($item) use ($data) {
            $filtereStatus = $data->filter(function ($value) use ($item) {
                return $value->status == $item['value'];
            })->first();
            $item['quantity'] = $filtereStatus ? $filtereStatus->count : 0;
            return $item;
        }, $listStatus);
        $totalSlowQuote = Order::query()
                                ->select('id')
                                ->where('is_sent_notification', 2)
                                // ->where('status', OrderConstant::KEY_STATUS_WAITING_QUOTE)
                                ->where('created_at', '>', $startDate)
                                // ->where('created_at', '>', '2025-05-20: 00:00:00')
                                ->whereNull('code_parent')
                                ->whereNotNull('orders.staff_quotation_id')
                                ->count();
        array_push($listStatus, [
            'quantity' => $totalSlowQuote,
            'value' => 'slow_quote',
            'name' => 'Báo giá chậm'
        ]);
        return resSuccessWithinData($listStatus);
    }

    public function reportOrder(Request $request)
    {
        $now = Carbon::now();
        $startDate = $request->input('start_date') ? Carbon::createFromFormat('Y-m-d', $request->input('start_date'))->startOfDay() : $now->copy()->startOfDay();
        $endDate = $request->input('end_date') ? Carbon::createFromFormat('Y-m-d', $request->input('end_date'))->endOfDay() : $now->copy()->endOfDay();
        $statuses = [
            OrderConstant::KEY_STATUS_DEPOSITED,
            OrderConstant::KEY_STATUS_WAIT_TO_PAY,
            OrderConstant::KEY_STATUS_ORDERED,
            OrderConstant::KEY_STATUS_DONE
        ];
        $query = Order::query()->selectRaw("count(id) as count, status")
                                ->whereIn('status', $statuses);
        if($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        $auth = getCurrentUser();
        if($auth->hasDepartmentName(DepartmentConstant::TYPE_ORDER)) {
            $query->where('staff_order_id', $auth->id);
        }
        $data = $query->groupBy('status')->get();
        $listStatus = [];
        foreach($statuses as $status) {
            $listStatus[] = [
                'quantity' => 0,
                'value' => $status,
                'name' => OrderConstant::STATUSES[$status]
            ];
        }
        $listStatus = array_map(function($item) use ($data) {
            $filtereStatus = $data->filter(function ($value) use ($item) {
                return $value->status == $item['value'];
            })->first();
            $item['quantity'] = $filtereStatus ? $filtereStatus->count : 0;
            return $item;
        }, $listStatus);
        $auth = getCurrentUser();
        return resSuccessWithinData($listStatus);
    }

    public function reportComplain(Request $request)
    {
        $now = Carbon::now();
        $startDate = $request->input('start_date') ? Carbon::createFromFormat('Y-m-d', $request->input('start_date'))->startOfDay() : $now->copy()->startOfDay();
        $endDate = $request->input('end_date') ? Carbon::createFromFormat('Y-m-d', $request->input('end_date'))->endOfDay() : $now->copy()->endOfDay();
        $statuses = [
            ComplainConstant::KEY_STATUS_PENDING,
            ComplainConstant::KEY_STATUS_PROCESS,
            ComplainConstant::KEY_STATUS_PROCESSED,
            ComplainConstant::KEY_STATUS_DONE,
            ComplainConstant::KEY_STATUS_CANCEL
        ];
        $query = Complain::query()->selectRaw("count(id) as count, status")
                                ->whereIn('status', $statuses);
        if($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        $auth = getCurrentUser();
        if($auth->hasDepartmentName(DepartmentConstant::TYPE_COMPLAIN)) {
            $query->where('staff_complain_id', $auth->id);
        }
        $data = $query->groupBy('status')->get();
        $listStatus = [];
        foreach($statuses as $status) {
            $listStatus[] = [
                'quantity' => 0,
                'value' => $status,
                'name' => ComplainConstant::STATUSES[$status]
            ];
        }
        $listStatus = array_map(function($item) use ($data) {
            $filtereStatus = $data->filter(function ($value) use ($item) {
                return $value->status == $item['value'];
            })->first();
            $item['quantity'] = $filtereStatus ? $filtereStatus->count : 0;
            return $item;
        }, $listStatus);
        return resSuccessWithinData($listStatus);
    }

    public function reportTotalSale(Request $request)
    {
        $year = $request->input('year') ?? now()->year;
        $month = $request->input('month');
        $time = Carbon::createFromFormat('Y', $year);
        if($month) {
            $time = Carbon::createFromFormat('Y-m', $year.'-'.$month);
            $startDate = $time->copy()->startOfMonth();
            $endDate = $time->copy()->endOfMonth();
        }else {
            $time = Carbon::createFromFormat('Y', $year);
            $startDate = $time->copy()->startOfYear();
            $endDate = $time->copy()->endOfYear();
        }
        $auth = getCurrentUser();
        $isDepartmentStaffQuote = $auth->hasDepartmentName(DepartmentConstant::TYPE_QUOTE);
        $isDepartmentStaffOrder = $auth->hasDepartmentName(DepartmentConstant::TYPE_ORDER);
        $isDepartmentStaffComplain = $auth->hasDepartmentName(DepartmentConstant::TYPE_COMPLAIN);
        $staffQuoteId = $isDepartmentStaffQuote ? $auth->id : null;
        $staffOrderId = $isDepartmentStaffOrder ? $auth->id : null;
        $staffComplainId = $isDepartmentStaffComplain ? $auth->id : null;
        $totalOrderFee = 0;
        $totalNegotiate = 0;
        $totalSale = 0;
        $totalKpi = 0;
        $totalComplain = 0;
        if($staffQuoteId == null && $staffOrderId == null) {
            $baseQueyComplain = Complain::query()->join('complain_status_times', 'complain_status_times.complain_id', '=', 'complains.id')
                                        ->where('complains.status', ComplainConstant::KEY_STATUS_DONE)
                                        ->whereBetween('complain_status_times.created_at', [$startDate, $endDate]);
            if($staffComplainId) {
                $baseQueyComplain->where('complains.staff_complain_id', $staffComplainId);
            }
            if($month) {
                $totalComplain = $baseQueyComplain->distinct('complains.id')->count();
                if($totalComplain > 0) {
                    $totalKpi = $this->getConfigKpiByType(ConfigKpiConstant::TYPE_COMPLAIN, $time->startOfMonth(), $totalComplain);
                }
            }else {
                $totalFullYear = $baseQueyComplain->select([
                    DB::raw('COUNT(DISTINCT complains.id) AS total_complain'),
                    DB::raw("DATE_FORMAT(complain_status_times.created_at, '%Y-%m') as month_year"),
                ])->groupBy('month_year')->get();
                foreach($totalFullYear as $item) {
                    if($item->total_complain > 0) {
                        $countComplain = $item->total_complain ?? 0;
                        $dateCurrent = Carbon::createFromFormat('Y-m', $item->month_year)->startOfMonth();
                        $totalComplain += $countComplain;
                        if($staffComplainId) {
                            $totalKpiRecord = $this->getConfigKpiByType(ConfigKpiConstant::TYPE_COMPLAIN, $dateCurrent, $countComplain);
                            $totalKpi += $totalKpiRecord;
                        }
                    }
                }
            }
        }
        if($staffComplainId == null) {
            $select = [
                DB::raw('SUM(order_fee) AS total_order_fee'),
                DB::raw('SUM((order_cost - order_cost_real) +
                    ((china_shipping_cost - china_shipping_cost_real) * exchange_rate) +
                    (bonus * exchange_rate)) AS total_negotiate'
                ),
            ];
            $selectFullYear = array_merge($select, [
                DB::raw("DATE_FORMAT(date_done, '%Y-%m') as month_year")
            ]);
            $baseQueyOrder = Order::where('orders.status', OrderConstant::KEY_STATUS_DONE)
                            ->whereBetween('orders.date_done', [$startDate, $endDate]);
            if($staffQuoteId) {
                $baseQueyOrder->where('staff_quotation_id', $staffQuoteId);
            }
            if($staffOrderId) {
                $baseQueyOrder->where('staff_order_id', $staffOrderId);
            }
            if($month) {
                $baseQueyOrder->select($select);
                $result = $baseQueyOrder->first();
            }else {
                $baseQueyOrder->select($selectFullYear);
                $result = $baseQueyOrder->groupBy('month_year')->get();
            }
            if($result instanceof \Illuminate\Support\Collection) {
                foreach($result as $item) {
                    $orderFee = $item->total_order_fee ?? 0;
                    $negotiate = $item->total_negotiate ?? 0;
                    $kpi = $orderFee + $negotiate;
                    $totalOrderFee += $orderFee;
                    $totalNegotiate += $negotiate;
                    $dateCurrent = Carbon::createFromFormat('Y-m', $item->month_year)->startOfMonth();
                    if($staffQuoteId || $staffOrderId) {
                        if($staffQuoteId) {
                            $totalKpiRecord = $this->getConfigKpiByType(ConfigKpiConstant::TYPE_QUOTE, $dateCurrent, $kpi);
                            $totalKpi += $totalKpiRecord;
                        }
                        if($staffOrderId) {
                            $totalKpiRecord = $this->getConfigKpiByType(ConfigKpiConstant::TYPE_ORDER, $dateCurrent, $kpi);
                            $totalKpi += $totalKpiRecord;
                        }
                    }
                }
            }else {
                $totalOrderFee = $result->total_order_fee ?? 0;
                $totalNegotiate = $result->total_negotiate ?? 0;
                $kpi = $totalOrderFee + $totalNegotiate;
                if($staffQuoteId || $staffOrderId) {
                    if($staffQuoteId) {
                        $totalKpiRecord = $this->getConfigKpiByType(ConfigKpiConstant::TYPE_QUOTE, $time->startOfMonth(), $kpi);
                        $totalKpi += $totalKpiRecord;
                    }
                    if($staffOrderId) {
                        $totalKpiRecord = $this->getConfigKpiByType(ConfigKpiConstant::TYPE_ORDER, $time->startOfMonth(), $kpi);
                        $totalKpi += $totalKpiRecord;
                    }
                }
            }
        }
        $totalSale = $totalOrderFee + $totalNegotiate;
        return resSuccessWithinData([
            'total_sale' => $totalSale,
            'total_order_fee' => $totalOrderFee,
            'total_negotiate' => $totalNegotiate,
            'total_kpi' => $totalKpi,
            'total_complain' => $totalComplain
        ]);
    }

    private function getConfigKpiByType($type, $time, $amount = 0)
    {
        $configKpi = ConfigKpi::where('type', $type)
                                ->where('date_start', '<=', $time)
                                ->orderBy('date_start', 'desc')
                                ->first();
        $valueKpi = 0;
        $config = data_get($configKpi, 'config', []);
        if(!empty($config)) {
            $convertConfig = json_decode($config, true);
            foreach ($convertConfig as $key => $item) {
                if ($amount >= $item['min']) {
                    $maxbefore = $key == 0 ? 0 : $convertConfig[$key - 1]['max'];
                    $amountInLevel = min($amount, $item['max']) - $maxbefore;
                    if(in_array($type, [ConfigKpiConstant::TYPE_QUOTE, ConfigKpiConstant::TYPE_ORDER])) {
                        $valueKpi += $amountInLevel * ($item['value'] / 100);
                    }else {
                        $valueKpi += $amountInLevel * $item['value'];
                    }
                }
            }
        }
        return $valueKpi;
    }

    public function reportUser(Request $request)
    {
        $userName = $request->input('user_name');
        $departmentId = $request->input('department_id');
        $year = $request->input('year') ?? now()->year;
        $month = $request->input('month');
        $time = Carbon::createFromFormat('Y', $year);
        if($month) {
            $time = Carbon::createFromFormat('Y-m', $year.'-'.$month);
            $startDate = $time->copy()->startOfMonth();
            $endDate = $time->copy()->endOfMonth();
        }else {
            $time = Carbon::createFromFormat('Y', $year);
            $startDate = $time->copy()->startOfYear();
            $endDate = $time->copy()->endOfYear();
        }
        $departmentIds = Department::query()->whereIn('type', [
            DepartmentConstant::TYPE_QUOTE,
            DepartmentConstant::TYPE_ORDER,
            DepartmentConstant::TYPE_COMPLAIN
        ])->pluck('id')->toArray();
        $queryUser = User::query()->whereIn('department_id', $departmentIds)
                                ->where('status', UserConstant::STATUS_ACTIVE);
        if($userName) {
            $queryUser->where('users.name', 'like', '%'.$userName.'%');
        }
        if($departmentId) {
            $queryUser->where('users.department_id', $departmentId);
        }
        $listUser = (clone $queryUser)->join('departments', 'departments.id', 'users.department_id')
                                    ->select([
                                        'users.id as user_id',
                                        'users.code as user_code',
                                        'users.name as user_name',
                                        'departments.name as department_name'
                                    ])->get()->toArray();
        $userIds = (clone $queryUser)->pluck('id')->toArray();
        $quotationQuery = DB::table('orders')
        ->selectRaw("
            staff_quotation_id as user_id,
            SUM(order_fee) as total_order_fee,
            SUM(
                (order_cost - order_cost_real) +
                ((china_shipping_cost - china_shipping_cost_real) * exchange_rate) +
                (bonus * exchange_rate)
            ) as total_negotiate,
            COUNT(id) as total_order
        ")
        ->where('status', OrderConstant::KEY_STATUS_DONE)
        ->whereBetween('date_done', [$startDate, $endDate])
        ->whereNotNull('staff_quotation_id')
        ->groupBy('staff_quotation_id');
            
        $orderQuery = DB::table('orders')
            ->selectRaw("
                staff_order_id as user_id,
                SUM(order_fee) as total_order_fee,
                SUM(
                    (order_cost - order_cost_real) +
                    ((china_shipping_cost - china_shipping_cost_real) * exchange_rate) +
                    (bonus * exchange_rate)
                ) as total_negotiate,
                COUNT(id) as total_order
            ")
            ->where('status', OrderConstant::KEY_STATUS_DONE)
            ->whereBetween('date_done', [$startDate, $endDate])
            ->whereNotNull('staff_order_id')
            ->groupBy('staff_order_id');
        $unionQuery = $quotationQuery->unionAll($orderQuery);
        $query = DB::query()
            ->fromSub($unionQuery, 'tmp')
            ->join('users', 'users.id', '=', 'tmp.user_id')
            ->join('departments', 'departments.id', '=', 'users.department_id')
            ->whereIn('users.id', $userIds);
        if($userName) {
            $query->where('users.name', 'like', '%'.$userName.'%');
        }
        if($departmentId) {
            $query->where('users.department_id', $departmentId);
        }
        $users = $query->selectRaw("
            users.id as user_id,
            users.code as user_code,
            users.name as user_name,
            departments.name as department_name,
            SUM(tmp.total_order_fee) as total_order_fee,
            SUM(tmp.total_negotiate) as total_negotiate,
            SUM(tmp.total_order) as total_order
        ")->groupBy([
            'users.id',
            'users.code',
            'users.name',
            'departments.name'
        ])->get()->toArray();
        $selectComplain = [
            'users.id as user_id',
            'users.code as user_code',
            'users.name as user_name',
            'departments.name as department_name',
            DB::raw('COUNT(complains.id) AS total_complain'),
        ];
        $userComplains = User::query()->select($selectComplain)
                            ->join('complains', function ($join) {
                                $join->on('complains.staff_complain_id', '=', 'users.id');
                            })
                            ->join('departments', 'departments.id', 'users.department_id')
                            ->whereIn('users.id', $userIds)
                            ->whereBetween('complains.created_at', [$startDate, $endDate])
                            ->where('complains.status', ComplainConstant::KEY_STATUS_DONE)
                            ->when($departmentId, function($query) use ($departmentId) {
                                $query->where('users.department_id', $departmentId);
                            })
                            ->groupBy('users.id')
                            ->get()
                            ->toArray();
        $dataComplainByUser = array_map(function($item) use ($startDate) {
            $totalComplain = $item['total_complain'] ?? 0;
            $totalKpi = $this->getConfigKpiByType(ConfigKpiConstant::TYPE_COMPLAIN, $startDate, $totalComplain);
            return [
                'user_id' => $item['user_id'],
                'user_code' => $item['user_code'],
                'user_name' => $item['user_name'],
                'department_name' => $item['department_name'],
                'total_order_fee' => 0,
                'total_negotiate' => 0,
                'total_sale' => 0,
                'total_order' => $totalComplain,
                'total_kpi' => $totalKpi
            ];
        }, $userComplains);
        $dataByUser = array_map(function($item) use ($startDate) {
            $totalKpi = 0;
            $totalSale = ($item->total_order_fee ?? 0) + ($item->total_negotiate ?? 0);
            if($item->department_name == 'Báo giá') {
                $totalKpi = $this->getConfigKpiByType(ConfigKpiConstant::TYPE_QUOTE, $startDate, $totalSale);
            }
            if($item->department_name == 'Đặt hàng') {
                $totalKpi = $this->getConfigKpiByType(ConfigKpiConstant::TYPE_ORDER, $startDate, $totalSale);
            }
            return [
                'user_id' => $item->user_id,
                'user_code' => $item->user_code,
                'user_name' => $item->user_name,
                'department_name' => $item->department_name,
                'total_order_fee' => $item->total_order_fee ?? 0,
                'total_negotiate' => $item->total_negotiate ?? 0,
                'total_sale' => $totalSale,
                'total_order' => $item->total_order ?? 0,
                'total_kpi' => $totalKpi
            ];
        }, $users);
        $data = array_merge($dataComplainByUser, $dataByUser);
        $userMap = array_column($data, null, 'user_id');
        // Merge dữ liệu
        $result = array_map(function ($itemUser) use ($userMap) {
            $user = $userMap[$itemUser['user_id']] ?? [];
            return array_merge($itemUser, [
                'total_order_fee' => $user['total_order_fee'] ?? 0,
                'total_negotiate' => $user['total_negotiate'] ?? 0,
                'total_sale' => $user['total_sale'] ?? 0,
                'total_order' => $user['total_order'] ?? 0,
                'total_kpi' => $user['total_kpi'] ?? 0,
            ]);
        }, $listUser);
        return resSuccessWithinData($result);
    }
}
