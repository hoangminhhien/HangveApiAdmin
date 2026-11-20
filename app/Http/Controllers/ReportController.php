<?php

namespace App\Http\Controllers;

use App\Constants\CustomerConstant;
use App\Constants\DepartmentConstant;
use App\Constants\OrderConstant;
use App\Exports\ExportExcelReportClosingRate;
use App\Exports\ExportExcelReportClosingRateStaff;
use App\Exports\ExportExcelReportControlMarketing;
use App\Exports\ExportExcelReportCustomerOrderSaleStaff;
use App\Exports\ExportOrderCustomer;
use App\Exports\ExportReportCustomerOrderSale;
use App\Exports\ExportReportOrderConsignment;
use App\Helpers\PaginateHelper;
use App\Http\Resources\PaginateJsonResource;
use App\Http\Resources\Report\CustomerActiveResource;
use App\Http\Resources\Report\CustomerConsignmentResource;
use App\Http\Resources\Report\CustomerOrderResource;
use App\Http\Resources\Report\CustomerPackageVNResource;
use App\Http\Resources\Report\CustomerPotentialResource;
use App\Http\Resources\Report\FeeServiceResource;
use App\Http\Resources\Report\OrderCompleteResource;
use App\Http\Resources\Report\ReportReconciliationWarehouseResource;
use App\Http\Resources\Report\SlowestQuoteResource;
use App\Http\Resources\Report\SlowQuoteResource;
use App\Http\Resources\Report\StaffComplainResource;
use App\Http\Resources\Report\StaffOrderResource;
use App\Http\Resources\Report\StaffQuotationResource;
use App\Http\Resources\WithdrawalResource;
use App\Jobs\Report\InsertDataOrderCustomerJob;
use App\Jobs\Report\InsertDataOrderCustomerSaleJob;
use App\Jobs\Report\InsertDataOrderCustomerSaleStaffJob;
use App\Jobs\Report\InsertDataOrderCustomerVipJob;
use App\Jobs\Report\InsertDataOrderReportConsignmentJob;
use App\Jobs\Report\InsertDataReportClosingRateJob;
use App\Jobs\Report\InsertDataReportClosingRateStaffJob;
use App\Jobs\Report\InsertDataReportCustomerReOrderJob;
use App\Models\Order;
use App\Models\ReportRunMarketing;
use App\Services\CalculatorWeekReport;
use App\Services\CustomerService;
use App\Services\OrderService;
use App\Services\FinancialReportService;
use App\Services\ReportCeoService;
use App\Services\ReportCustomerOrders;
use App\Services\ReportCustomerReOrders;
use App\Services\ReportOrderClientService;
use App\Services\ReportOrderCustomerVip;
use App\Services\ReportOrderPackageClientService;
use App\Services\ReportOrdersConsignment;
use App\Services\ReportProvinceService;
use App\Services\ReportRevenueExpenditureService;
use App\Services\Reports\ReportCustomerConsultService;
use App\Services\Reports\ReportOrderChannel;
use App\Services\ReportsClosingRateStaff;
use App\Services\ReportSaleStaffService;
use App\Services\ReportService;
use App\Services\ReportStaffService;
use App\Services\SmsZaLoService;
use App\Services\StaffService;
use App\Models\Customer;
use App\Services\ValidateReport\ValidateReportClosingRate;
use App\Services\ValidateReport\ValidateReportClosingRateStaff;
use App\Services\ValidateReport\ValidateReportConsignment;
use App\Services\ValidateReport\ValidateReportControllerMarketing;
use App\Services\ValidateReport\ValidateReportOrder;
use App\Services\ValidateReport\ValidateReportOrderSale;
use App\Services\ValidateReport\ValidateReportOrderSaleStaff;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use App\Exports\CustomerActiveExport;
use App\Jobs\ExportExcelJob;
use App\Exports\OrderCompleteExport;
use App\Exports\CustomerPackageVNExport;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\ReportCustomerOrderSale;
use App\Services\ReportsControlMarketing;
use App\Services\ReportCustomerOrderSaleStaff;
use App\Services\ReportsClosingRate;
use App\Models\OrderPerformanceHistory;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Artisan;

class ReportController extends Controller
{
    protected ReportOrderCustomerVip $reportOrderCustomerVip;
    protected ReportCustomerReOrders $reportCustomerReOrders;
    protected ReportCustomerOrders $reportCustomerOrders;

    protected ReportOrdersConsignment $reportOrdersConsignment;
    protected ReportCustomerOrderSale $reportCustomerOrderSale;
    protected ReportsControlMarketing $reportsControlMarketing;
    protected ReportCustomerOrderSaleStaff $reportCustomerOrderSaleStaff;
    protected ReportsClosingRate $reportsClosingRate;
    protected ReportsClosingRateStaff $reportsClosingRateStaff;
    protected ReportRevenueExpenditureService $reportRevenueExpenditure;
    protected ReportSaleStaffService $reportsSaleStaffService;
    protected ReportProvinceService $reportsProvinceService;

    public function __construct(
        ReportService $service,
        ReportOrderCustomerVip $reportOrderCustomerVip,
        ReportCustomerReOrders $reportCustomerReOrders,
        ReportCustomerOrders $reportCustomerOrders,
        ReportOrdersConsignment $reportOrdersConsignment,
        ReportCustomerOrderSale $reportCustomerOrderSale,
        ReportsControlMarketing $reportsControlMarketing,
        ReportCustomerOrderSaleStaff $reportCustomerOrderSaleStaff,
        ReportsClosingRate $reportsClosingRate,
        ReportsClosingRateStaff $reportsClosingRateStaff,
        ReportRevenueExpenditureService $reportRevenueExpenditure,
        ReportSaleStaffService $reportsSaleStaffService,
        ReportProvinceService $reportsProvinceService
    )
    {
        $this->_service = $service;
        $this->reportOrderCustomerVip = $reportOrderCustomerVip;
        $this->reportCustomerReOrders = $reportCustomerReOrders;
        $this->reportCustomerOrders = $reportCustomerOrders;
        $this->reportOrdersConsignment = $reportOrdersConsignment;
        $this->reportCustomerOrderSale = $reportCustomerOrderSale;
        $this->reportsControlMarketing = $reportsControlMarketing;
        $this->reportCustomerOrderSaleStaff = $reportCustomerOrderSaleStaff;
        $this->reportsClosingRate = $reportsClosingRate;
        $this->reportsClosingRateStaff = $reportsClosingRateStaff;
        $this->reportRevenueExpenditure = $reportRevenueExpenditure;
        $this->reportsSaleStaffService = $reportsSaleStaffService;
        $this->reportsProvinceService = $reportsProvinceService;
    }

    public function reportOrderChannelChart(
        Request $request,
        ReportOrderChannel $reportOrderChannel
    ): JsonResponse
    {
        $results = $reportOrderChannel->chart($request->all());
        return resSuccessWithinData($results);
    }

    public function reportOrderChannelTable(
        Request $request,
        ReportOrderChannel $reportOrderChannel
    ): JsonResponse
    {
        $results = $reportOrderChannel->table($request->all());
        return resSuccessWithinData($results);
    }

    public function totalRevenueCeo(ReportCeoService $reportCeoService, Request $request): JsonResponse
    {
        $year = (int) date('Y');
        if (!empty($request->year)) {
            $year = (int)$request->year;
        }
        $key = 'total_revenue_ceo_'.$year;
        $results = Cache::get($key, '');
        if (!$results) {
            // thêm 1 key time cập nhật data vào 1 api bất kỳ để hiển thị
            Cache::put('timeRequest', Carbon::now()->timestamp);
            $results = $reportCeoService->getTotalRevenue(['year' => $year]);
            Cache::put($key, $results, now()->addMinutes(10));
        }
        return resSuccessWithinData($results);
    }
    public function totalShippingReceivedGoodCeo(ReportCeoService $reportCeoService, Request $request): JsonResponse
    {
        $year = (int) date('Y');
        if (!empty($request->year)) {
            $year = (int)$request->year;
        }
        $key = 'totalShippingReceivedGoodCeo_'.$year;
        $results = Cache::get($key, '');
        if (!$results) {
            $results = $reportCeoService->getTotalShippingReceivedGood(['year' => $year]);
            Cache::put($key, $results, now()->addMinutes(10));
        }

        return resSuccessWithinData($results);
    }

    public function totalAccountBalanceCeo(ReportCeoService $reportCeoService): JsonResponse
    {
        $key = 'totalAccountBalanceCeo';
        $results = Cache::get($key, '');
        if (!$results) {
            Log::info('totalAccountBalanceCeo');
            $results = $reportCeoService->totalAccountBalance();
            Cache::put($key, $results, now()->addMinutes(10));
        }

        return resSuccessWithinData(round($results));
    }

    public function totalAmountPayableCeo(OrderService $orderService): JsonResponse
    {
        $key = 'total_amount_payable_ceo';
        $results = Cache::get($key, []);
        if (empty($results)) {
            Log::info('total_amount_payable_ceo');
            $results = $orderService->totalAmountPayable();
            Cache::put($key, $results, now()->addMinutes(10));
        }

        return resSuccessWithinData($results['total_order_cost_vn']);
    }

    public function totalFundCeo(ReportCeoService $reportCeoService): JsonResponse
    {
        $key = 'totalFundCeo';
        $results = Cache::get($key, '');
        if (!$results) {
            $results = $reportCeoService->getFund();
            Cache::put($key, $results, now()->addMinutes(10));
        }

        return resSuccessWithinData($results);
    }

    public function totalShippingWareHouseVnCeo(ReportCeoService $reportCeoService): JsonResponse
    {
        $key = 'totalShippingWareHouseVnCeo';
        $results = Cache::get($key, '');
        if (!$results) {
            Log::info('totalShippingWareHouseVnCeo');
            $results = $reportCeoService->totalShippingWareHouseVnCeo();
            Cache::put($key, $results, now()->addMinutes(10));
        }

        return resSuccessWithinData($results);
    }

    public function totalFundVnCeo(ReportCeoService $reportCeoService): JsonResponse
    {
        $key = 'totalFundVnCeo';
        $results = Cache::get($key, '');
        if (!$results) {
            Log::info('totalFundVnCeo');
            $results = $reportCeoService->totalFundVnCeo();
            Cache::put($key, $results, now()->addMinutes(10));
        }

        return resSuccessWithinData($results);
    }

    public function totalRemainingAmountCeo(ReportCeoService $reportCeoService): JsonResponse
    {
        $key = 'totalRemainingAmountCeo';
        $results = Cache::get($key, '');
        if (!$results) {
            Log::info('totalRemainingAmountCeo');
            $results = $reportCeoService->totalRemainingAmountCeo();
            Cache::put($key, $results, now()->addMinutes(10));
        }

        return resSuccessWithinData($results);
    }

    /**
     * @throws BindingResolutionException
     */
    public function totalRemainingAmountPaid(): JsonResponse
    {
        $reportCeoService = app()->make(ReportCeoService::class);
        $orderService = app()->make(OrderService::class);
        $totalFundVnCeo = $reportCeoService->totalFundVnCeo();
        $totalRemainingAmountCeo = $reportCeoService->totalRemainingAmountCeo();
        $totalShippingWareHouseVnCeo = $reportCeoService->totalShippingWareHouseVnCeo();
        $totalAccountBalanceCeo = $reportCeoService->totalAccountBalance();
        $totalAmountPayableCeo = $orderService->totalAmountPayable();
        $total_order_cost_vn = $totalAmountPayableCeo['total_order_cost_vn'] ?? 0;

        $total_remaining_amount_paid = $totalFundVnCeo
            + $totalRemainingAmountCeo
            + $totalShippingWareHouseVnCeo
            - $totalAccountBalanceCeo
            - $total_order_cost_vn;
        $total_remaining_amount_paid = round($total_remaining_amount_paid);

        return resSuccessWithinData($total_remaining_amount_paid);
    }

    public function getWarehouse(string $id): JsonResponse
    {
        return resSuccessWithinData((new ReportCeoService())->getChartWarehouses($id));
    }

    public function getCategories(string $type, Request $request): JsonResponse
    {
        $year = (int) date('Y');
        if (!empty($request->year)) {
            $year = (int)$request->year;
        }
        $key = 'getCategories_'.$type.'_'.$year;
        $params = [
            'type' => $type,
            'year' => $year
        ];
        $results = Cache::get($key, '');
        if (!$results) {
            $results = (new ReportCeoService())->getChartCategories($params);
            Cache::put($key, $results, now()->addMinutes(10));
        }
        return resSuccessWithinData($results);
    }
    public function forgetCache(Request $request): JsonResponse
    {
        $year = (int) date('Y');
        if (!empty($request->year)) {
            $year = (int)$request->year;
        }
        $arrayKey = [
            'total_revenue_ceo_'.$year,
            'totalShippingReceivedGoodCeo_'.$year,
            'totalAccountBalanceCeo',
            'total_amount_payable_ceo',
            'totalShippingWareHouseVnCeo',
            'totalFundCeo',
            'totalFundVnCeo',
            'totalRemainingAmountCeo'
        ];
        Cache::put('timeRequest', Carbon::now()->timestamp);
        foreach ($arrayKey as $key) {
            Cache::forget($key);
        }
        return resSuccess();
    }

    public function getYear(): JsonResponse
    {
        $start_year = 2020;
        $current_year = (int)date('Y');
        $count = $current_year - $start_year;
        $array_year = [];
        for ($i = 0; $i <= $count; $i++) {
            $array_year[] = $current_year - $i;

        }
        return resSuccessWithinData(['years' => $array_year]);
    }

    public function timeRequest(): JsonResponse
    {
        $timeRequest = Cache::get('timeRequest');
        if(!$timeRequest){
            return resSuccessWithinData('Chưa cập nhật dữ liệu mới');
        }
        $timeRequest = Carbon::parse((int)$timeRequest);
        $now = Carbon::now();
        $time = $now->diffInMinutes($timeRequest);
        $timeText = '';
        if($time < 1) {
            $time = 1;
            $timeText = $time . ' phút trước';
        }
        if($time > 1 && $time < 60){
            $timeText = $time . ' phút trước';
        }
        if($time >= 60){
            $time = $now->diffInHours($timeRequest);
            $timeText = $time . ' giờ trước';
        }

        return resSuccessWithinData($timeText);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getOrderComplete(Request $request): JsonResponse
    {
        try {
            $params = $request->only('code', 'customer_code', 'staff_group_id', 'date_done', 'per_page', 'official');
            $data = $this->_service->getOrderComplete($params);

            list($totalAmountAll, $totalOrderFee, $totalInspection, $totalOfficialServiceFee) = $this->_service->getOrderComplete($params, true);

            $_paginateResource = PaginateJsonResource::class;
            $_resource = OrderCompleteResource::class;

            $staffCounselorCare = (new StaffService())->getStaffByDepartmentTypes([DepartmentConstant::TYPE_BUSINESS]);

            $extra = [
                'staff' => $staffCounselorCare,
                'summary' => [
                    'amount' => $totalAmountAll,
                    'total_inspection' => $totalInspection,
                    'order_fee' => $totalOrderFee,
                    'totalOfficialServiceFee' => $totalOfficialServiceFee,
                ]
            ];

            return resSuccessWithinData(new $_paginateResource($data, $_resource, $extra));

        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getCustomerActive(Request $request): JsonResponse
    {
        try {
            $params = $request->only(
                'code', 'name', 'phone_number', 'source',
                'page', 'per_page', 'wait_deposit_level'
            );
            $data = $this->_service->getCustomerActive($params);
            $totalOrdersDeposit = $this->_service->getCustomerActive($params, true);
            $_paginateResource = PaginateJsonResource::class;
            $_resource = CustomerActiveResource::class;
            $extra = [
                'totalOrdersDeposit' => $totalOrdersDeposit,
                'levelWaitDeposit'   => [
                    [ 'value' => CustomerConstant::WAIT_DEPOSIT_LV1, 'name' => 'Dưới 5 phút' ],
                    [ 'value' => CustomerConstant::WAIT_DEPOSIT_LV2, 'name' => 'Dưới 60 phút' ],
                    [ 'value' => CustomerConstant::WAIT_DEPOSIT_LV3, 'name' => 'Trên 60 phút' ],
                ],
                'statusDeposit' => OrderConstant::KEY_STATUS_WAITING_DEPOSIT
            ];

            return resSuccessWithinData(new $_paginateResource($data, $_resource, $extra));

        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getCustomerOrder(Request $request): JsonResponse
    {
        try {
            $params = $request->only(
                'code', 'name', 'phone_number', 'page', 'source',
                'per_page', 'order_status', 'action', 'created_at'
            );
            $data = $this->_service->getCustomerOrder($params);

            $_paginateResource = PaginateJsonResource::class;
            $_resource = CustomerOrderResource::class;
            $extra = [
                'orderStatus' => OrderConstant::REPORT_CUSTOMER_ORDER_STATUSES
            ];

            return resSuccessWithinData(new $_paginateResource($data, $_resource, $extra));

        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getCustomerPackageVN(Request $request): JsonResponse
    {
        try {
            $params = $request->only(
                'code', 'name', 'phone_number',
                'page', 'per_page', 'created_at', 'priority',
                'bill_code', 'source'
            );
            $data = $this->_service->getCustomerPackageVN($params, $extra);

            $_paginateResource = PaginateJsonResource::class;
            $_resource = CustomerPackageVNResource::class;

            return resSuccessWithinData(new $_paginateResource($data, $_resource, $extra));
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    // báo cáo khách hàng tiềm năng
    public function getCustomerPotential(Request $request): JsonResponse
    {
        try {
            $params = $request->only(
                'code', 'name', 'phone_number',
                'page', 'per_page', 'wait_deposit_level',
                'point_way', 'chatbiz_total_call', 'staff_group_id',
                'start_date', 'end_date', 'potential', 'source'
            );
            $data = $this->_service->getCustomerPotential($params);
            $totalOrdersDeposit = $this->_service->getCustomerPotential($params, true);
            $_paginateResource = PaginateJsonResource::class;
            $_resource = CustomerPotentialResource::class;
            $extra = [
                'totalCustomerPotential' => $totalOrdersDeposit,
                'levelWaitDeposit' => [
                    [ 'value' => CustomerConstant::WAIT_DEPOSIT_LV1, 'name' => 'Level 1' ],
                    [ 'value' => CustomerConstant::WAIT_DEPOSIT_LV2, 'name' => 'Level 2' ],
                    [ 'value' => CustomerConstant::WAIT_DEPOSIT_LV3, 'name' => 'Level 3' ],
                ]
            ];

            return resSuccessWithinData(new $_paginateResource($data, $_resource, $extra));
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function changePointCare(Request $request, string $id): JsonResponse
    {
        Customer::query()->where(['id' => $id])->update(['point_care' => $request->get('point_care')]);
        return resSuccess();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getStaffQuotation(Request $request): JsonResponse
    {
        try {
            $params = $request->only('code', 'name', 'time', 'page', 'per_page');
            $data = $this->_service->getStaffQuotation($params);

            return resSuccessWithinData($data);

        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function getDepositCustomer(Request $request): JsonResponse
    {
        $order = Order::query();
        $order->select('id', 'code', 'exchange_rate', 'deposit_cost', 'deposit_percent', 'status', 'created_at');
        $auth = getCurrentUser();
        if($auth->hasPermissionName('report.view-only-quote-by-self')) {
            $order->where(function($q) use ($auth) {
                $q->where('staff_order_id', $auth->id)
                    ->orWhere('staff_quotation_id', $auth->id);
            });
        }
        $status = $request->status ?? null;
        if (!$status || !in_array($status, [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_ORDERED])) {
            return resError('Trạng thái không hợp lệ!');
        }
        if (!empty($request->status)) {
            $order->where('status', $request->status);
        }
        $order->with(['orderSuppliers']);

        $date_created = $request->date_created ?? null;
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        if ($date_created) {
            $arrayRangeDate = explode(',', $date_created);
            $start_date = $arrayRangeDate[0];
            $end_date = $arrayRangeDate[1];
        }
        $order->whereBetween('orders.created_at', [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $results = $order->get();
        $lists = [];
        foreach ($results as $result) {
            $lists[$result->deposit_percent] = [
                'deposit_percent' => $result->deposit_percent,
                'deposit_cost' => 0,
                'total_amount' => 0,
                'total_order' => 0,
            ];
        }
        $total_deposit_cost = 0;
        $total_total_amount = 0;
        $total_order = 0;
        foreach ($results as $result) {
            $orderSuppliers = $result->orderSuppliers ?? null;
            if (isset($lists[$result->deposit_percent])) {
                $lists[$result->deposit_percent]['deposit_cost'] += $result->deposit_cost;
                $lists[$result->deposit_percent]['total_order'] += 1;
                $total_deposit_cost += $result->deposit_cost;
                $total_order += 1;
                $total_amount = 0;
                if ($orderSuppliers && $orderSuppliers->count() > 0) {
                    foreach ($orderSuppliers as $orderSupplier) {
                        $total_amount += $orderSupplier->china_shipping_cost * $result->exchange_rate;
                        $total_amount += $orderSupplier->order_cost;
                        $total_amount += $orderSupplier->inspection_cost;
                        $total_amount += $orderSupplier->order_fee;
                        $total_amount -= $orderSupplier->discount_cost;
                    }
                }
                $lists[$result->deposit_percent]['total_amount'] += $total_amount;
                $total_total_amount += $total_amount;
            }
        }
        $lists = array_values($lists);
        $res = [
            'lists' => $lists,
            'total' => [
                'total_deposit_cost' => $total_deposit_cost,
                'total_total_amount' => $total_total_amount,
                'total_order' => $total_order
            ]
        ];
        return resSuccessWithinData($res);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getStaffOrder(Request $request): JsonResponse
    {
        try {
            $params = $request->only('code', 'name', 'time', 'page', 'per_page');
            $data = $this->_service->getStaffOrder($params);

            return resSuccessWithinData($data);

        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getStaffComplain(Request $request): JsonResponse
    {
        try {
            $params = $request->only('code', 'name', 'time', 'page', 'per_page');
            $data = $this->_service->getStaffComplain($params);

            return resSuccessWithinData($data);

        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }
    public function getStaffCancelOrder(Request $request): JsonResponse
    {
        try {
            $params = $request->only('user_name', 'date_from', 'date_to', 'page', 'per_page');

            $listStaffComplain = $this->_service->getStaffCancelOrder($params);
            $_paginateResource = PaginateJsonResource::class;
            $_resource         = \App\Http\Resources\Report\StaffCancelOrderResource::class;
            $total = $this->_service->getTotalOrderCancel($params);
            $extra             = ['total' => $total];

            return resSuccessWithinData(new $_paginateResource($listStaffComplain, $_resource, $extra));

        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function getStaffCancelOrderChart(Request $request): JsonResponse
    {
        try {
            $params = $request->only('user_name', 'date_from', 'date_to', 'page', 'per_page');

            $listStaffComplain = $this->_service->getStaffCancelOrderChart($params);

            return resSuccessWithinData($listStaffComplain);

        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getStaffCareCounselor(Request $request): JsonResponse
    {
        try {
            $params = $request->only('keyword', 'filter_by', 'page', 'per_page');
            $data   = $this->_service->getStaffCareAndCounselor($params);

            if(!$data) {
                return resError('Bạn không có quyền. Vui lòng liên hệ admin');
            }
            $_paginateResource = PaginateJsonResource::class;
            $_resource         = \App\Http\Resources\Report\StaffCareCounselorResource::class;
            $extra             = [];

            return resSuccessWithinData(new $_paginateResource($data, $_resource, $extra));

        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function reportPurchaseChannel(Request $request)
    {
        try {
            $params = $request->only('time', 'page', 'per_page');

            $time                      = $params['time'];
            list($startDate, $endDate) = getStartDateEndDateByType($time);
            $params['startDate']       = $startDate;
            $params['endDate']         = $endDate;

            $res = $this->_service->getPurchaseChannel($params);

            return resSuccessWithinData($res);
        } catch (Exception $e) {
            \Log::info($e);
            return resError();
        }
    }

    public function export(Request $request)
    {
        $params = $request->all();
        try {
            // $time = Carbon::now()->format('YmdHms');
            // $fileName = getPathFileExport('customer_active').'/customer_active_'.$time.'.xlsx';
            // (new CustomerActiveExport($params, Auth::user()->id))->queue($fileName)->chain([
            //     new ExportExcelJob(11, 11003, Auth::user()->id, $fileName),
            // ]);
            return resSuccess();
        } catch (Exception $e) {
            Log::info($e);
            return resError();
        }
    }
    public function transportFee(Request $request): JsonResponse
    {
        $data = $this->_service->getTransportFee();

        return resSuccessWithinData($data);
    }

    public function reconciliationWarehouse(Request $request): JsonResponse
    {
        $data = $this->_service->reconciliationWarehouse($request->all());

        return resSuccessWithinData($data);
    }

    public function orderCompleteExport(Request $request)
    {
        try {
            $params = $request->only('code', 'customer_code', 'staff_group_id', 'date_done', 'official');
            return Excel::download(new OrderCompleteExport($params), 'order_complete.xlsx');
        } catch (Exception $e) {
            Log::info($e);
            return resError();
        }
    }
    public function customerPackVNExport(Request $request)
    {
        try {
            $params = $request->only('code', 'name', 'phone_number', 'created_at', 'priority',
                'bill_code', 'source');
            $data = $this->_service->customerPackVNExport($params);
            return Excel::download(new CustomerPackageVNExport($data), 'customer_package_vn.xlsx');
        } catch (Exception $e) {
            Log::info($e);
            return resError();
        }
    }

    public function getGroup(): JsonResponse
    {
        $results = $this->reportOrderCustomerVip->getGroup();
        return resSuccessWithinData($results);
    }
    public function getWeekReport(): JsonResponse
    {
        $results = $this->reportOrderCustomerVip->getWeekReport();
        return resSuccessWithinData($results);
    }
    /* Báo Cáo Thống Kê Khách Hàng VIP */
    public function getReportOrderCustomerVip(Request $request): JsonResponse
    {
        try {
            $params = $request->all();
            $reports = $this->reportOrderCustomerVip->getReport($params);
            return resSuccessWithinData($reports);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function syncDataReportOrderCustomerVip(
        Request $request
    ): JsonResponse
    {
        try {
            $params = $request->all();
            $job = new InsertDataOrderCustomerVipJob($params);
            dispatch($job)->onQueue('sync-data-report');
            return resSuccess();
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }
    /* Khách hàng Order */
    public function getReportOrderCustomer(Request $request): JsonResponse
    {
        try {
            if(empty($request->time1) && empty($request->time2) && empty($request->time3)) {
                $params = new \stdClass();
                $validateReportCustomer = (new ValidateReportOrder($params))
                    ->validate()
                    ->getTimeData()
                    ->exec();
                if ($validateReportCustomer['code'] == 400){
                    return resError($validateReportCustomer['message'], 422);
                }
                $data = $validateReportCustomer['message'];
                $reports = $this->reportCustomerOrders->getReport($data, true);
            }else {
                if(empty($request->time1)) {
                    return resError('Chưa chọn thời gian tham chiếu 1', 422);
                }
                if(empty($request->time2)) {
                    return resError('Chưa chọn thời gian tham chiếu 2', 422);
                }
                if(empty($request->time3)) {
                    return resError('Chưa chọn thời gian xem', 422);
                }
                $data = [
                    'time1' => $request->time1,
                    'time2' => $request->time2,
                    'time3' => $request->time3,
                    'group_id' => data_get($request->all(), 'group_id'),
                    'source' => data_get($request->all(), 'source'),
                ];
                $reports = $this->reportCustomerOrders->getReport($data, false);
            }
            return resSuccessWithinData($reports);
        } catch (Exception $e) {
            \Log::info($e);
            return resError($e->getMessage());
        }
    }

    public function getAllWeekOfMonth(Request $request): JsonResponse
    {
        $results = CalculatorWeekReport::getAllWeekOfMonth($request);
        return resSuccessWithinData($results);
    }
    /* Báo cáo kênh lên đơn hàng */
    public function reportOrderChannel(Request $request)
    {

    }
    /* Báo Cáo Trong Tuần Chạy KM, KH cũ lên đơn CS */
    public function getReportReOrderCustomer(Request $request): JsonResponse
    {
        try {
            $params['date'] = date('Y-m-d');
            $params['group_id'] = $request->group_id ?? '';
            $params['last_month'] = $request->last_month ?? '';
            $reports = $this->reportCustomerReOrders->getReport($params);
            return resSuccessWithinData($reports);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function getReportCustomerOrderSale(Request $request): JsonResponse
    {
        try {
            $params = new \stdClass();
            $params->isNullWeek = $request->isNullWeekOrderSale ?? '';
            $params->numberWeekStart = $request->numberWeekStartOrderSale ?? '';
            $params->numberWeekEnd = $request->numberWeekEndOrderSale ?? '';
            $params->endMonthAndYear = $request->endMonthAndYearOrderSale ?? '';
            $params->startMonthAndYear = $request->startMonthAndYearOrderSale ?? '';
            $params->isClickReport = false;
            $params->filterByGroupId = $request->group_id ?? '';
            $validateReportCustomer = (new ValidateReportOrderSale($params))
                ->validate()
                ->getTimeData()
                ->exec();
            if ($validateReportCustomer['code'] == 400){
                return resError($validateReportCustomer['message'], 422);
            }
            $data = array_merge($validateReportCustomer['message'], $request->all());
            $reportCustomerConsultService = app()->make(ReportCustomerConsultService::class);
            $reports = $reportCustomerConsultService->index($data);
            if ($reports['code'] == 200) {
                return resSuccessWithinData($reports['data']);
            } else {
                return resError($reports['message']);
            }

        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }
    public function exportExcelReportCustomerOrderSale(
        Request $request
    ): BinaryFileResponse
    {
        $params = new \stdClass();
        $params->isNullWeek = $request->isNullWeekOrderSale ?? '';
        $params->numberWeekStart = $request->numberWeekStartOrderSale ?? '';
        $params->numberWeekEnd = $request->numberWeekEndOrderSale ?? '';
        $params->endMonthAndYear = $request->endMonthAndYearOrderSale ?? '';
        $params->startMonthAndYear = $request->startMonthAndYearOrderSale ?? '';
        $params->isClickReport = $request->isClickReportOrderSale ?? '';
        $params->filterByGroupId = $request->group_id ?? '';
        $validateReportCustomer = (new ValidateReportOrderSale($params))
            ->validate()
            ->getTimeData()
            ->exec();
        $data = $validateReportCustomer['message'];
        $reports = $this->reportCustomerOrderSale->getReport($data);
        return Excel::download(new ExportReportCustomerOrderSale($reports), 'bao_cao_doanh_so_dat_hang.xlsx');
    }

    public function exportExcelReportOrdersConsignment(
        Request $request
    ): BinaryFileResponse
    {
        if(empty($request->time1) && empty($request->time2) && empty($request->time3)) {
            $params = new \stdClass();
            $validateReportCustomer = (new ValidateReportOrder($params))
                ->validate()
                ->getTimeData()
                ->exec();
            if ($validateReportCustomer['code'] == 400){
                return resError($validateReportCustomer['message'], 422);
            }
            $data = $validateReportCustomer['message'];
            $reports = $this->reportOrdersConsignment->getReport($data, true);
        }else {
            if(empty($request->time1)) {
                return resError('Chưa chọn thời gian tham chiếu 1', 422);
            }
            if(empty($request->time2)) {
                return resError('Chưa chọn thời gian tham chiếu 2', 422);
            }
            if(empty($request->time3)) {
                return resError('Chưa chọn thời gian xem', 422);
            }
            $data = [
                'time1' => $request->time1,
                'time2' => $request->time2,
                'time3' => $request->time3,
                'group_id' => data_get($request->all(), 'group_id')
            ];
            $reports = $this->reportOrdersConsignment->getReport($data, false);
        }
        return Excel::download(new ExportReportOrderConsignment($reports), 'bao_cao_khach_hang_ky_gui.xlsx');
    }

    public function exportExcelOrderCustomer(
        Request $request
    ): BinaryFileResponse
    {
        if(empty($request->time1) && empty($request->time2) && empty($request->time3)) {
            $params = new \stdClass();
            $validateReportCustomer = (new ValidateReportOrder($params))
                ->validate()
                ->getTimeData()
                ->exec();
            if ($validateReportCustomer['code'] == 400){
                return resError($validateReportCustomer['message'], 422);
            }
            $data = $validateReportCustomer['message'];
            $reports = $this->reportCustomerOrders->getReport($data, true);
        }else {
            if(empty($request->time1)) {
                return resError('Chưa chọn thời gian tham chiếu 1', 422);
            }
            if(empty($request->time2)) {
                return resError('Chưa chọn thời gian tham chiếu 2', 422);
            }
            if(empty($request->time3)) {
                return resError('Chưa chọn thời gian xem', 422);
            }
            $data = [
                'time1' => $request->time1,
                'time2' => $request->time2,
                'time3' => $request->time3,
                'group_id' => data_get($request->all(), 'group_id')
            ];
            $reports = $this->reportCustomerOrders->getReport($data, false);
        }
        return Excel::download(new ExportOrderCustomer($reports), 'bao_cao_khach_hang_order.xlsx');
    }

    public function exportExcelReportClosingRateStaff(
        Request $request
    ): BinaryFileResponse
    {
        $params = new \stdClass();
        $params->numberWeekEnd = $request->numberWeekEndClosingRateStaff ?? '';
        $params->endMonthAndYear = $request->endMonthAndYearClosingRateStaff ?? '';
        $params->isClickReport = $request->isClickReportClosingRateStaff ?? '';
        $validateReportClosingRateStaff = (new ValidateReportClosingRateStaff($params))
            ->validate()
            ->getTimeData()
            ->exec();
        $data = $validateReportClosingRateStaff['message'];
        $reports = $this->reportsClosingRateStaff->getReport($data);
        return Excel::download(new ExportExcelReportClosingRateStaff($reports), 'bao_cao_data_phong_va_ty_le_chot_nhan_vien.xlsx');
    }

    public function exportExcelReportCustomerOrderSaleStaff(
        Request $request
    ): BinaryFileResponse
    {
        $params = new \stdClass();
        $params->numberWeekEnd = $request->numberWeekEndStaff ?? '';
        $params->endMonthAndYear = $request->endMonthAndYearStaff ?? '';
        $params->isClickReport = $request->isClickReportStaff ?? '';
        $validateReportCustomer = (new ValidateReportOrderSaleStaff($params))
            ->validate()
            ->getTimeData()
            ->exec();
        $data = $validateReportCustomer['message'];
        $reports = $this->reportCustomerOrderSaleStaff->getReport($data);
        return Excel::download(new ExportExcelReportCustomerOrderSaleStaff($reports), 'bao_cao_doanh_so_theo_nhan_vien_tu_van.xlsx');
    }

    public function exportExcelReportClosingRate(
        Request $request
    ): BinaryFileResponse
    {
        $params = new \stdClass();
        $params->numberWeekEnd = $request->numberWeekEndClosingRate ?? '';
        $params->endMonthAndYear = $request->endMonthAndYearClosingRate ?? '';
        $params->isClickReport = $request->isClickReportClosingRate ?? '';
        $validateReportClosingRate = (new ValidateReportClosingRate($params))
            ->validate()
            ->getTimeData()
            ->exec();
        $data = $validateReportClosingRate['message'];
        $reports = $this->reportsClosingRate->getReport($data);
        return Excel::download(new ExportExcelReportClosingRate($reports), 'bao_cao_doanh_so_theo_phòng.xlsx');
    }

    public function exportExcelReportControlMarketing(
        Request $request
    ): BinaryFileResponse
    {
        $params = new \stdClass();
        $params->isNullWeek = $request->isNullWeekControl;
        $params->numberWeekStart = $request->numberWeekStartControl;
        $params->numberWeekEnd = $request->numberWeekEndControl;
        $params->endMonthAndYear = $request->endMonthAndYearControl;
        $params->startMonthAndYear = $request->startMonthAndYearControl;
        $params->isClickReport = $request->isClickReportControl;
        $validateReportControl = (new ValidateReportControllerMarketing($params))
            ->validate()
            ->getTimeData()
            ->exec();
        $data = $validateReportControl['message'];
        $reports = $this->reportsControlMarketing->getReport($data);
        return Excel::download(new ExportExcelReportControlMarketing($reports), 'bao_cao_doi_soat_marketing.xlsx');
    }

    public function getReportCustomerOrderSaleStaff(Request $request): JsonResponse
    {
        try {
            $params = new \stdClass();
            $params->numberWeekEnd = $request->numberWeekEndStaff ?? '';
            $params->endMonthAndYear = $request->endMonthAndYearStaff ?? '';
            $params->isClickReport = $request->isClickReportStaff ?? '';
            $validateReportCustomer = (new ValidateReportOrderSaleStaff($params))
                ->validate()
                ->getTimeData()
                ->exec();
            if ($validateReportCustomer['code'] == 400){
                return resError($validateReportCustomer['message'], 422);
            }
            $data = $validateReportCustomer['message'];
            $reports = $this->reportCustomerOrderSaleStaff->getReport($data);
            return resSuccessWithinData($reports);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }
    /* Bao cao TỔNG DATA PHÒNG VÀ TỶ LỆ CHỐT ĐƠN */
    public function getReportClosingRate(Request $request): JsonResponse
    {
        try {
            $params = new \stdClass();
            $params->numberWeekEnd = $request->numberWeekEndClosingRate ?? '';
            $params->endMonthAndYear = $request->endMonthAndYearClosingRate ?? '';
            $params->isClickReport = $request->isClickReportClosingRate ?? '';
            $validateReportClosingRate = (new ValidateReportClosingRate($params))
                ->validate()
                ->getTimeData()
                ->exec();
            if ($validateReportClosingRate['code'] == 400){
                return resError($validateReportClosingRate['message'], 422);
            }
            $data = $validateReportClosingRate['message'];
            $reports = $this->reportsClosingRate->getReport($data);
            if (empty($reports)) {
                return resError('Không có dữ liệu');
            }
            return resSuccessWithinData($reports);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function getReportClosingRateStaff(Request $request): JsonResponse
    {
        try {
            $params = new \stdClass();
            $params->numberWeekEnd = $request->numberWeekEndClosingRateStaff ?? '';
            $params->endMonthAndYear = $request->endMonthAndYearClosingRateStaff ?? '';
            $params->isClickReport = $request->isClickReportClosingRateStaff ?? '';
            $validateReportClosingRateStaff = (new ValidateReportClosingRateStaff($params))
                ->validate()
                ->getTimeData()
                ->exec();
            if ($validateReportClosingRateStaff['code'] == 400){
                return resError($validateReportClosingRateStaff['message'], 422);
            }
            $data = $validateReportClosingRateStaff['message'];
            $reports = $this->reportsClosingRateStaff->getReport($data);
            if (empty($reports)) {
                return resError('Không có dữ liệu', 422);
            }
            return resSuccessWithinData($reports);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }
    /* Khách hàng ký gửi */
    public function getReportOrdersConsignment(Request $request): JsonResponse
    {
        try {
            if(empty($request->time1) && empty($request->time2) && empty($request->time3)) {
                $params = new \stdClass();
                $validateReportCustomer = (new ValidateReportOrder($params))
                    ->validate()
                    ->getTimeData()
                    ->exec();
                if ($validateReportCustomer['code'] == 400){
                    return resError($validateReportCustomer['message'], 422);
                }
                $data = $validateReportCustomer['message'];
                $reports = $this->reportOrdersConsignment->getReport($data, true);
            } else {
                if(empty($request->time1)) {
                    return resError('Chưa chọn thời gian tham chiếu 1', 422);
                }
                if(empty($request->time2)) {
                    return resError('Chưa chọn thời gian tham chiếu 2', 422);
                }
                if(empty($request->time3)) {
                    return resError('Chưa chọn thời gian xem', 422);
                }
                $data = [
                    'time1' => $request->time1,
                    'time2' => $request->time2,
                    'time3' => $request->time3,
                    'group_id' => data_get($request->all(), 'group_id')
                ];
                $reports = $this->reportOrdersConsignment->getReport($data, false);
            }
            return resSuccessWithinData($reports);
        } catch (Exception $e) {
            \Log::info($e);
            return resError($e->getMessage());
        }
    }
    public function getReportControlMarketing(Request $request): JsonResponse
    {
        try {
            $params = new \stdClass();
            $params->isNullWeek = $request->isNullWeekControl;
            $params->numberWeekStart = $request->numberWeekStartControl;
            $params->numberWeekEnd = $request->numberWeekEndControl;
            $params->endMonthAndYear = $request->endMonthAndYearControl;
            $params->startMonthAndYear = $request->startMonthAndYearControl;
            $params->isClickReport = $request->isClickReportControl;
            $validateReportControl = (new ValidateReportControllerMarketing($params))
                ->validate()
                ->getTimeData()
                ->exec();
            if ($validateReportControl['code'] == 400){
                return resError($validateReportControl['message'], 422);
            }
            $data = $validateReportControl['message'];
            $reports = $this->reportsControlMarketing->getReport($data);
            return resSuccessWithinData($reports);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function getReportRevenueExpenditure(Request $request): JsonResponse
    {
        try {
            $reports = $this->reportRevenueExpenditure->getReport($request);
            return resSuccessWithinData($reports);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    /**
     * @throws BindingResolutionException
     */
    public function fetchDataReport(Request $request){
        $isReport = $request->isReport ?? '';
        $options['isReport'] = $isReport;
        $options['date'] = !empty($request->date) ? $request->date : date('Y-m-d');
        $options['not_group_id'] = !empty($request->not_group_id) ? $request->not_group_id : '';
        switch ($isReport) {
            case 'order_customer_vip':
                $reportOrderCustomerVip = new ReportOrderCustomerVip();
                $reportOrderCustomerVip->cronjobInsertData($options);
                echo "Success order_customer_vip";
                break;
            case 'order_customer_vip_all':
                $reportOrderCustomerVip = new ReportOrderCustomerVip();
                $reportOrderCustomerVip->cronjobInsertDataMany($options);
                echo "Success order_customer_vip_all";
                break;
            case 'order_customer':
               /*Khách hàng Order*/
                $data = $request->all();
                $job = new InsertDataOrderCustomerJob($data);
                dispatch($job)->onQueue('sync-data-report');
                echo "Success order_customer";
                break;
            case 're_order_customer':
                /* Báo Cáo Trong Tuần Chạy KM, KH cũ lên đơn CS */
                $params = $request->all();
                $job = new InsertDataReportCustomerReOrderJob($params);
                dispatch($job)->onQueue('sync-data-report');
                echo "Success re_order_customer";
                break;
            case 'order_consignment':
                /* Khách hàng ký gửi */
                $params = $request->all();
                $job = new InsertDataOrderReportConsignmentJob($params);
                dispatch($job)->onQueue('sync-data-report');
                echo "Success order_consignment";
                break;
            case 'order_customer_sale':
                /*$data = $request->all();
                $reportCustomerConsultService = app()->make(ReportCustomerConsultService::class);
                $report = $reportCustomerConsultService->cronjobInsertData($data);*/
                $job = new InsertDataOrderCustomerSaleJob($options);
                dispatch($job)->onQueue('sync-data-report');
                echo "Success order_customer_sale";
                break;
            case 'report_control_marketing':
                $data = $this->reportsControlMarketing->cronjobInsertData($options);
                $this->reportsControlMarketing->delete($options);
                $this->reportsControlMarketing->insert($data);

                $dataMonth = $this->reportsControlMarketing->cronjobInsertDataMonth($options);
                $this->reportsControlMarketing->deleteMonth($options);
                $this->reportsControlMarketing->insert($dataMonth);
                echo "Success report_control_marketing";
                break;
            case 'order_sale_staff':
                /* Doanh số theo nhân viên tư vấn */
                $job = new InsertDataOrderCustomerSaleStaffJob($options);
                dispatch($job)->onQueue('sync-data-report');
                echo "Success order_sale_staff";
                break;
            case 'report_closing_rate':
                /* Bao cao TỔNG DATA PHÒNG VÀ TỶ LỆ CHỐT ĐƠN */
                $data = $request->all();
                $job = new InsertDataReportClosingRateJob($data);
                dispatch($job)->onQueue('sync-data-report');

                echo "Success report_closing_rate";
                break;
            case 'report_closing_rate_staff':
                /* TỔNG DATA PHÒNG VÀ TỶ LỆ CHỐT ĐƠN NHÂN VIÊN */
                $data = $request->all();
                $job = new InsertDataReportClosingRateStaffJob($data);
                dispatch($job)->onQueue('sync-data-report');
                echo "Success report_closing_rate_staff";
                break;
            case 'report_revenue_expenditure':
                /* Báo cáo thu chi */
                $this->reportRevenueExpenditure->syncReportRevenueExpenditureByTime($options);
                echo "Success report_revenue_expenditure";
                break;
            case 'financial_report':
                /* Báo cáo tài chính */
                $options['customer_id'] = $request->customer_id ?? 0;
                $financialReportService = app()->make(FinancialReportService::class);
                $financialReportService->syncReport($options);
                echo "Success financial_report";
                break;
            case 'report_order_client':
                /* Báo cáo đơn hàng client */
                $options['customer_id'] = $request->customer_id ?? 0;
                $reportOrderClientService = app()->make(ReportOrderClientService::class);
                $reportOrderClientService->syncReport($options);
                echo "Success report_order_client";
                break;
            case 'report_order_package_client':
                /* Báo cáo đơn hàng client */
                $options['customer_id'] = $request->customer_id ?? 0;
                $reportOrderPackageClientService = app()->make(ReportOrderPackageClientService::class);
                $reportOrderPackageClientService->syncReport($options);
                echo "Success report_order_package_client";
                break;
            default:
                echo "Success default";
                break;
        }
    }

    /**
     * @throws BindingResolutionException
     */
    public function fetchDataReportCustomerVip(Request $request): JsonResponse
    {
        $reportOrderCustomerVip = new ReportOrderCustomerVip();
        $reportOrderCustomerVip->cronjobInsertData([]);
        return resSuccess();
    }

    public function fetchDataReportCustomerOrder(Request $request)
    {
        $reportCustomerOrder = new ReportCustomerOrders();
        $reportCustomerOrder->cronjobInsertData([]);
        return resSuccess();
    }

    public function getSaleStaff(Request $request): JsonResponse
    {
        return $this->reportsSaleStaffService->getSaleStaff($request->all());
    }

    /**
     * @throws Exception
     */
    public function getReportByProvince(Request $request): JsonResponse
    {
        return $this->reportsProvinceService->getReportByProvince($request->all());
    }

    /**
     * @throws Exception
     */
    public function syncReportProvince(Request $request): JsonResponse
    {
        return $this->reportsProvinceService->syncReportProvince();
    }

    public function syncReportSaleStaffByDate(Request $request): JsonResponse
    {
        $date = $request->date ?? date('Y-m-d H:i:s');
        $params['date'] = $date;
        return $this->reportsSaleStaffService->syncReportSaleStaffByTime($params);
    }

    public function initReportPerformanceStaffSale(Request $request): JsonResponse
    {
        try {
            $this->reportsSaleStaffService->initReportPerformanceStaffSale($request->get('type'));

            return resSuccess();
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function performanceSaleList(Request $request): JsonResponse
    {
        try {
            $data = $this->reportsSaleStaffService->performanceSaleList($request->all());

            return resSuccessWithinData($data);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function performanceSaleTop(Request $request): JsonResponse
    {
        try {
            $data = $this->reportsSaleStaffService->performanceSaleTop($request->all());

            return resSuccessWithinData($data);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function performanceSaleInWeek(): JsonResponse
    {
        try {
            $data = $this->reportsSaleStaffService->performanceSaleInWeek();

            return resSuccessWithinData($data);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function performanceSaleByTime(Request $request): JsonResponse
    {
        try {
            $data = $this->reportsSaleStaffService->performanceSaleByTime($request->all());

            return resSuccessWithinData($data);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function updateOrganizationOrder(Request $request): JsonResponse
    {
        try {
            $this->_service->updateOrganizationOrder($request->all());

            return resSuccess('OK');
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function updateOrganizationDelivery(Request $request): JsonResponse
    {
        try {
            $this->_service->updateOrganizationDelivery($request->all());

            return resSuccess('OK');
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function cronReportCheckMarketing(Request $request)
    {
        $reportCheckMarketingService = App::make('App\Services\Reports\ReportCheckMarketing');
        $reportCheckMarketingService->cronData();
    }

    public function getReportCheckMarketing(Request $request): JsonResponse
    {
        $validationRules = [
            'date_start' => 'required|date_format:Y-m-d',
            'date_end' => 'required|date_format:Y-m-d',
        ];
        $validationMessages = [
        ];

        $request->validate($validationRules, $validationMessages);

        // Tìm
        $params = $request->all();
        $reportCheckMarketingModel = App::make('App\Models\ReportCheckMarketing');
        $reportCheckMarketingDB = $reportCheckMarketingModel->where('date_start', $params['date_start'])
                                                            ->where('date_end', $params['date_end'])
                                                            ->first();
        if (is_null($reportCheckMarketingDB)) {
            $result = [
                'unit' => 'Không có dữ liệu, vui lòng chọn khoản thời gian tuần khác (T2 đến CN)'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $reportCheckMarketingService = App::make('App\Services\Reports\ReportCheckMarketing');
        $result = $reportCheckMarketingService->getData($reportCheckMarketingDB);

        return resSuccessWithinData($result);
    }

    public function updateReportCheckMarketing(Request $request, $id): JsonResponse
    {
        $arr_type = [
            'no_interaction_', // Không tương tác được
            'no_need_', // Không có nhu cầu
            'need_but_no_enter_good_', // Có nhu cầu nhưng không nhập được
            'reference_but_no_need_now_', // Có nhu cầu: Tham khảo chưa có nhu cầu ngay
            'reference_', // Tham khảo
            'follow_', // Follow
            'ordered_personal_', // Đã đặt hàng - Khách tiêu dùng
            'ordered_business_', // Đã đặt hàng - Khách kinh doanh
            'consignment_personal_', // Kí gửi - Khách tiêu dùng
            'consignment_business_', // Kí gửi - Khách kinh doanh
            'pay_help_personal_', // Thanh toán hộ - Khách tiêu dùng
            'pay_help_business_', // Thanh toán hộ - Khách kinh doanh
        ];
        $arr_chanel = [
            'gg_form',
            'fb_form',
        ];

        $onlyKey = [];
        foreach ($arr_type as $type) {
            foreach ($arr_chanel as $chanel) {
                $key_temp = $type . $chanel;
                $onlyKey[] = $key_temp;
            }
        }
        $params = $request->only($onlyKey);

        // Check
        $reportCheckMarketingModel = App::make('App\Models\ReportCheckMarketing');
        $reportCheckMarketingDB = $reportCheckMarketingModel->where('id', $id)->first();
        if (is_null($reportCheckMarketingDB)) {
            $result = [
                'unit' => 'Không tồn tại bản ghi để cập nhật'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Cập nhật
        try {
            DB::beginTransaction();
            $reportCheckMarketingDB->fill($params)->save();
            DB::commit();
            $reportCheckMarketingService = App::make('App\Services\Reports\ReportCheckMarketing');
            $result = $reportCheckMarketingService->getData($reportCheckMarketingDB);
            return resSuccessWithinData($result);
        } catch (Exception $e) {
            DB::rollback();
            return resError($e->getMessage());
        }
    }








    // Xử lý đối soát maketting mới: 08/01/2025
    public function cronReportRunMarketing(Request $request)
    {
        $reportRunMarketingService = App::make('App\Services\Reports\ReportRunMarketing');
        $reportRunMarketingService->cronData();
        $reportRunMarketingService->cronDataByTimeSignLabel(); // Lọc theo thời gian gán nhãn
        // dd("Đã đếm lại xong: " . date('Y-m-d H:i:s', time()));
        return resSuccessWithinData([]);
    }

    public function getReportRunMarketing(Request $request): JsonResponse
    {
        $validationRules = [
            'date_start' => 'required|date_format:Y-m-d',
            'date_end' => 'required|date_format:Y-m-d',
            'group_id' => 'nullable|exists:groups,id',
            'user_id' => 'nullable|exists:users,id',
            'type' => 'required|in:1,2',
        ];
        $request->validate($validationRules, []);
        $userOnline = Auth::user();
        $params = $request->all();
        $reportRunMarketingModel = ReportRunMarketing::query();
        $reportRunMarketingDBs = $reportRunMarketingModel->where('date_start', $params['date_start'])
                                                         ->where('date_end', $params['date_end'])
                                                         ->where('type', $params['type']);

        if (isset($params['group_id'])) {
            $reportRunMarketingDBs = $reportRunMarketingDBs->where('group_id', $params['group_id']);
        }

        if (isset($params['user_id'])) {
            $reportRunMarketingDBs = $reportRunMarketingDBs->where('user_id', $params['user_id']);
        }

        // Đứa nào không có quyền xem all thì lọc theo user_id
        $viewAll = $userOnline->checkUserHasPer('report.view-all-run-marketing');
        if (!$viewAll) {
            $reportRunMarketingDBs = $reportRunMarketingDBs->where('user_id', $userOnline->id);
        }

        $reportRunMarketingDBs = $reportRunMarketingDBs->get();

        if (count($reportRunMarketingDBs) == 0) {
            // Bổ sung nếu nó chọn thời gian tuần hiện tại, nếu chưa có data trước thì tạo cho nó
            $reportRunMarketingDBs = $this->createReportRunMarketingDefaultForAgent($viewAll, $params);

            if (is_null($reportRunMarketingDBs)) {
                $result = [
                    'unit' => 'Không có dữ liệu, vui lòng chọn khoản thời gian tuần khác (T2 đến CN)'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }


        $reportRunMarketingService = App::make('App\Services\Reports\ReportRunMarketing');
        $result = $reportRunMarketingService->getData($reportRunMarketingDBs);

        if (count($reportRunMarketingDBs) == 1) {
            $result['report_id'] = $reportRunMarketingDBs[0]->id;
            // Check xem nó được edit không để ẩn hiện trên FE
            $dateStartInt = strtotime($params['date_start'] . ' 00:00:00');
            $dateEndInt = strtotime($params['date_end'] . ' 23:59:59');
            $hasEdit = false;
            $nowTime = time();
            if (
                $nowTime >= $dateStartInt &&
                $nowTime <= $dateEndInt
            ) {
                $hasEdit = true;
            }

            // Tồn tại nghĩa là người full quyền đang lọc
            if (isset($params['user_id']) || isset($params['group_id'])) {
                $hasEdit = false;
            }

            $result['has_edit'] = $hasEdit;
        }

        return resSuccessWithinData($result);
    }

    public function createReportRunMarketingDefaultForAgent($viewAll, $params)
    {
        $dateStartInt = strtotime($params['date_start'] . ' 00:00:00');
        $dateEndInt = strtotime($params['date_end'] . ' 23:59:59');
        $nowTime = time();
        $canCreate = false;
        if (
            $nowTime >= $dateStartInt &&
            $nowTime <= $dateEndInt
        ) {
            $canCreate = true;
        }

        if ($canCreate && $viewAll == false) {
            $reportRunMarketingModel = \App::make('App\Models\ReportRunMarketing');
            $userGroupModel = \App::make('App\Models\UserGroup');
            $groupModel = \App::make('App\Models\Group');

            $userDB = \Auth::user();

            $data_report = [];
            $data_report['date_start'] = $params['date_start'];
            $data_report['date_end']   = $params['date_end'];
            $data_report['type']       = $params['type'];
            // Lưu thông tin user
            $data_report['user_id']         = (int) $userDB->id;
            $data_report['user_name']       = $userDB->name;
            $data_report['user_email']      = $userDB->email;
            $data_report['organization_id'] = $userDB->organization_id;

            // Lưu thông tin phòng
            $userGroup = $userGroupModel->where('user_id', (int)$userDB->id)
                                        ->orderBy('id', 'desc')
                                        ->first();
            if (!is_null($userGroup)) {
                $groupDB = $groupModel->where('id', $userGroup->group_id)->first();
                if (!is_null($groupDB)) {
                    $data_report['group_id']   = (int) $groupDB->id;
                    $data_report['group_name'] = $groupDB->name;
                }
            }

            // Lưu báo cáo
            $reportResult =  $reportRunMarketingModel->create($data_report);

            return [$reportResult];
        }
        return null;
    }

    public function getReportRunMarketingRealtime(Request $request): JsonResponse
    {
        $validationRules = [
            'date_start' => 'required|date_format:Y-m-d|before:today|before_or_equal:date_end',
            'date_end' => 'required|date_format:Y-m-d|before:today',
            'group_id' => 'nullable|exists:groups,id',
            'user_id' => 'nullable|exists:users,id',
            'type' => 'required|in:1',
        ];
        $validationMessages = [
        ];

        $request->validate($validationRules, $validationMessages);

        $userOnline = \Auth::user();

        $params = $request->all();

        // Check đủ 7 ngày
        $diff = strtotime($params['date_end']) - strtotime($params['date_start']);
        $diff_int = $diff / 86400;
        if ($diff_int != 6) {
            $result = [
                'unit' => 'Thời gian không hợp lệ, vui lòng chọn khoản thời gian tuần khác (T2 đến CN)'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check ngày date_end phải là chủ nhật
        $day = date('l', strtotime($params['date_end']));

        if ($day != 'Sunday') {
            $result = [
                'unit' => 'Thời gian không hợp lệ, vui lòng chọn khoản thời gian tuần khác (T2 đến CN)'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $reportRunMarketingService = App::make('App\Services\Reports\ReportRunMarketing');
        $reportRunMarketingDBs = $reportRunMarketingService->cronDataRealtime($params);


        if (isset($params['group_id'])) {
            // Kiểm tra có ai chưa được gán nhóm thì báo lỗi
            $string_err = "";
            foreach ($reportRunMarketingDBs as $key => $reportRunMarketingDB) {
                if (!isset($reportRunMarketingDB['group_id'])) {
                    $string_err .= $reportRunMarketingDB['user_email'] . ',';
                }
            }

            if ($string_err !== "") {
                $result = [
                    'unit' => 'Vui lòng cập nhật nhóm cho các nhân viên: ' . $string_err
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }

            $reportRunMarketingDBs = array_filter($reportRunMarketingDBs,function ($value) use($params) {
                return $value['group_id'] == $params['group_id'];
            });
        }

        if (isset($params['user_id'])) {
            $reportRunMarketingDBs = array_filter($reportRunMarketingDBs,function ($value) use($params) {
                return $value['user_id'] == $params['user_id'];
            });
        }

        // Đứa nào không có quyền xem all thì lọc theo user_id
        $viewAll = $userOnline->checkUserHasPer('report.view-all-run-marketing');
        if (!$viewAll) {
            $reportRunMarketingDBs = array_filter($reportRunMarketingDBs,function ($value) use($userOnline) {
                return $value['user_id'] == $userOnline->id;
            });
        }

        $reportRunMarketingService = App::make('App\Services\Reports\ReportRunMarketing');
        $result = $reportRunMarketingService->getData($reportRunMarketingDBs);

        $result['has_edit'] = false;

        return resSuccessWithinData($result);
    }

    public function updateReportRunMarketing(Request $request, $id): JsonResponse
    {
        $arr_type = [
            'no_interaction_',       // Không tương tác được
            'no_need_',              // Không có nhu cầu
            'no_enter_good_',        // Không nhập được
        ];
        $arr_chanel = [
            'gg_form',
            'fb_form',
        ];

        $onlyKey = [];
        foreach ($arr_type as $type) {
            foreach ($arr_chanel as $chanel) {
                $key_temp = $type . $chanel;
                $onlyKey[] = $key_temp;
            }
        }

        $params = $request->only($onlyKey);

        $userOnline = \Auth::user();

        $now = date('Y-m-d H:i:s', time());

        // Check
        $reportRunMarketingModel = App::make('App\Models\ReportRunMarketing');
        $reportRunMarketingDB = $reportRunMarketingModel->where('id', $id)
                                ->where('user_id', $userOnline->id)
                                ->where('date_start', '<=', $now)
                                ->where('date_end', '>=', $now)
                                ->first();

        if (is_null($reportRunMarketingDB)) {
            $result = [
                'unit' => 'Không tồn tại bản ghi để cập nhật'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Cập nhật
        try {
            DB::beginTransaction();
            $reportRunMarketingDB->fill($params)->save();
            DB::commit();
            $reportRunMarketingService = App::make('App\Services\Reports\ReportRunMarketing');
            $result = $reportRunMarketingService->getData([$reportRunMarketingDB]);
            $result['report_id'] = (int) $reportRunMarketingDB->id;
            // Check xem nó được edit không để ẩn hiện trên FE
            $dateStartInt = strtotime($reportRunMarketingDB->date_start);
            $dateEndInt = strtotime($reportRunMarketingDB->date_end);
            $hasEdit = false;
            $nowTime = time();
            if (
                $nowTime >= $dateStartInt &&
                $nowTime <= $dateEndInt
            ) {
                $hasEdit = true;
            }

            $result['has_edit'] = $hasEdit;
            return resSuccessWithinData($result);
        } catch (Exception $e) {
            DB::rollback();
            return resError($e->getMessage());
        }
    }

    public function getListUserByGroup(Request $request): JsonResponse
    {
        $validationRules = [
            'group_id' => 'required|exists:groups,id',
        ];
        $validationMessages = [
        ];

        $request->validate($validationRules, $validationMessages);

        $userOnline = \Auth::user();

        // Tìm
        $params = $request->all();

        $userGroupModel = \App::make('App\Models\UserGroup');
        $userModel = \App::make('App\Models\User');

        $monthYear = date('m/Y', time());
        $userGroupDBs = $userGroupModel->where('month', $monthYear)
                                        ->where('group_id', $params['group_id'])
                                        ->get();
        if (count($userGroupDBs) == 0) {
            $monthYear = date('m/Y', time() - 31 * 86400);

            $userGroupDBs = $userGroupModel->where('month', $monthYear)
                                            ->where('group_id', $params['group_id'])
                                            ->get();
        }

        $data_result = [];
        if (count($userGroupDBs) > 0) {
            $user_ids = \Arr::pluck($userGroupDBs, 'user_id');
            $userDBs = $userModel->whereIn('id', $user_ids)->where('status', 1)->get();
            if (count($userDBs) > 0) {
                foreach ($userDBs as $key => $userDB) {
                    $temp = [
                        'id'   => (int) $userDB->id,
                        'name' => $userDB->name,
                    ];
                    array_push($data_result, $temp);
                }
            }
        }

        return resSuccessWithinData($data_result);
    }



    public function updateKpi(): JsonResponse
    {
        try {
            DB::beginTransaction();
            $reportStaffService = App::make(ReportStaffService::class);
            $results = $reportStaffService->updateKpi();
            DB::commit();
            return resSuccessWithinData($results);
        } catch (Exception $e) {
            DB::rollback();
            return resError($e->getMessage());
        }
    }
    public function totalOrder(): JsonResponse
    {
        try {
            $reportStaffService = App::make(ReportStaffService::class);
            $results = $reportStaffService->totalOrder();

            return resSuccessWithinData($results);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function totalConsignment(): JsonResponse
    {
        try {
            $reportStaffService = App::make(ReportStaffService::class);
            $results = $reportStaffService->totalConsignment();

            return resSuccessWithinData($results);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function totalKpi(): JsonResponse
    {
        try {
            $reportStaffService = App::make(ReportStaffService::class);
            $results = $reportStaffService->totalKpi();

            return resSuccessWithinData($results);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }
    public function orderPackageVn(): JsonResponse
    {
        try {
            $reportStaffService = App::make(ReportStaffService::class);
            $results = $reportStaffService->orderPackageVn();

            return resSuccessWithinData($results);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }
    public function packageVn(): JsonResponse
    {
        try {
            $reportStaffService = App::make(ReportStaffService::class);
            $results = $reportStaffService->packageVn();

            return resSuccessWithinData($results);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function customerPotential(): JsonResponse
    {
        try {
            $reportStaffService = App::make(ReportStaffService::class);
            $results = $reportStaffService->customerPotential();

            return resSuccessWithinData($results);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function conversationTotal()
    {
        try {
            $reportStaffService = App::make(ReportStaffService::class);
            $results = $reportStaffService->conversationTotal();

            return resSuccessWithinData($results);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function chartKpi()
    {
        try {
            $reportStaffService = App::make(ReportStaffService::class);
            $results = $reportStaffService->chartKpi();

            return resSuccessWithinData($results);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function reportConsignment(Request $request)
    {
        try {
            $params = $request->only(
                'bill_code', 'code_warehouse', 'staff_group_id', 'type_quantitative', 'per_page',
                'service', 'date_done', 'range_time', 'order_type', 'official'
            );
            $data = $this->_service->reportConsignment($params, $extra);

            $_paginateResource = PaginateJsonResource::class;
            $_resource = CustomerConsignmentResource::class;
            return resSuccessWithinData(new $_paginateResource($data, $_resource, $extra));

        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function getLastTimePerformance()
    {
        $last = OrderPerformanceHistory::query()->orderBy('updated_at', 'desc')->pluck('updated_at')->first();
        $value = getTimeDiff($last);
        return resSuccessWithinData($value);
    }

    public function syncDataPerformanceSale()
    {
        try {
            $last = OrderPerformanceHistory::query()->orderBy('updated_at', 'desc')->first();
            if($last) {
                $last->updated_at = date('Y-m-d H:i:s');
                $last->save();
            }
            Artisan::call('insert:report-order-performance');
            return resSuccess();
        } catch (\Exception $e) {
            \Log::error($e);
            return resError();
        }
    }

    public function reportFeeService(Request $request)
    {
        try {
            $params = $request->only(
                'staff_group_id', 'per_page', 'date_done'
            );
            $data = $this->_service->reportFeeService($params, $extra);

            $_paginateResource = PaginateJsonResource::class;
            $_resource = FeeServiceResource::class;
            return resSuccessWithinData(new $_paginateResource($data, $_resource, $extra));
        } catch (\Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function reportSlowQuote(Request $request)
    {
        try {
            $params = $request->only(
                'staff_quotation_id', 'per_page', 'time', 'date_created'
            );
            $data = $this->_service->reportSlowQuote($params);

            $_paginateResource = PaginateJsonResource::class;
            $_resource = SlowQuoteResource::class;
            return resSuccessWithinData(new $_paginateResource($data, $_resource));
        } catch (\Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function reportSlowestQuote(Request $request)
    {
        try {
            $params = $request->only(
                'staff_quotation_id', 'per_page', 'time', 'date_created'
            );
            $data = $this->_service->reportSlowestQuote($params);

            $_paginateResource = PaginateJsonResource::class;
            $_resource = SlowestQuoteResource::class;
            return resSuccessWithinData(new $_paginateResource($data, $_resource));
        } catch (\Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function totalCustomer()
    {
        try {
            $reportStaffService = App::make(ReportStaffService::class);
            $results = $reportStaffService->totalCustomer();
            return resSuccessWithinData($results);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function totalSale()
    {
        try {
            $reportStaffService = App::make(ReportStaffService::class);
            $results = $reportStaffService->totalSale();
            return resSuccessWithinData($results);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function totalOrderFee()
    {
        try {
            $reportStaffService = App::make(ReportStaffService::class);
            $results = $reportStaffService->totalOrderFee();
            return resSuccessWithinData($results);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function totalOrderFeeStaff()
    {
        try {
            $reportStaffService = App::make(ReportStaffService::class);
            $results = $reportStaffService->totalOrderFeeStaff();
            return resSuccessWithinData($results);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function followWeek()
    {
        try {
            $reportStaffService = App::make(ReportStaffService::class);
            $results = $reportStaffService->followWeek();
            return resSuccessWithinData($results);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function totalChatbiz()
    {
        try {
            $reportStaffService = App::make(ReportStaffService::class);
            $results = $reportStaffService->totalChatbiz();
            return resSuccessWithinData($results);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function listDatePeriod()
    {
        $reportStaffService = App::make(ReportStaffService::class);
        $results = $reportStaffService->getListDateByPeriod('d/m/Y');
        return resSuccessWithinData($results);
    }

    public function listDateMonth()
    {
        $reportStaffService = App::make(ReportStaffService::class);
        $results = $reportStaffService->listDateMonth();
        return resSuccessWithinData($results);
    }
}
