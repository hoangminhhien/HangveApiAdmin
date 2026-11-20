<?php

namespace App\Services;

use App\Constants\DepartmentConstant;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\ReportConstant;
use App\Constants\UserConstant;
use App\Http\Resources\PaginateJsonResource;
use App\Http\Resources\Report\ReportSaleStaffResource;
use App\Jobs\Report\SyncReportOrderConsignmentDateJob;
use App\Jobs\Report\SyncReportOrderConsignmentMonthJob;
use App\Jobs\Report\SyncReportOrderConsignmentWeekJob;
use App\Jobs\Report\SyncReportOrderConsignmentYearJob;
use App\Models\Consignment;
use App\Models\OrderPackage;
use App\Models\ReportOrderConsignmentDate;
use App\Models\ReportPerformanceStaffSale;
use App\Models\OrderPerformanceHistory;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ReportSaleStaffService extends BaseService
{
    protected string $_paginateResource = PaginateJsonResource::class;

    protected string $_resource = ReportSaleStaffResource::class;

    public function __construct()
    {
        parent::__construct();
    }

    public function getSaleStaff(array $params): JsonResponse
    {
        try {
            $per_page = $params['per_page'] ?? 15;
            $group = $params["group"] ?? null;
            $startDate = $params["date_from"] ?? null;
            $endDate = $params["date_to"] ?? null;
            $time = $params['time'] ?? 'day';
            if ($startDate || $endDate) {
                $results = $this->getReportByDateRanger($params);
                return resSuccessWithinData($results);
            }
            $select = [
                "report_order_consignment_date.staff_group_id",
                DB::raw('SUM(count_order) AS total_count_order'), // Tổng order
                DB::raw('SUM(order_cost) AS total_order_cost'), // Tổng tiền hàng
                DB::raw('SUM(order_fee) AS total_order_fee'), // Phí dịch vụ
                DB::raw('SUM(international_shipping_cost) AS total_international_shipping_cost'), // Phí vận chuyển QT
                DB::raw('SUM(woodworking_cost) AS total_woodworking_cost'), // Chi phí đóng gỗ
                DB::raw('SUM(insurance_cost) AS total_insurance_cost'), // Phí bảo hiểm
                DB::raw('SUM(inspection_cost) AS total_inspection_cost'), // Phí kiểm đếm
                DB::raw('SUM(shock_proof_cost) AS total_shock_proof_cost'), // Phí chống sốc
                DB::raw('SUM(official_service_fee) AS total_official_service_fee'), // Phí dv chính ngạch
            ];
            $query = ReportOrderConsignmentDate::query()
                ->select($select)
                ->with(['user' => function ($user) {
                    $user->select('id', 'code', 'name');
                }]);
            $userGroups = UserGroup::query();
            if($startDate || $endDate) {
                if($endDate) {
                    $month = Carbon::createFromFormat('Y-m-d', $endDate)->format('m/Y');
                    $userGroups = $userGroups->where('month', $month);
                }else {
                    $month = Carbon::createFromFormat('Y-m-d', $startDate)->format('m/Y');
                    $userGroups = $userGroups->where('month', $month);
                }
            }else {
                $userGroups = $userGroups->where('month', date('m/Y'));
            }
            if($group) {
                $userGroups = $userGroups->where('group_id', $group);
            }
            $userGroups->join('users', 'users.id', '=', 'users_group.user_id');
            $userGroups = $userGroups->pluck('users_group.user_id')->toArray();
            $query->whereIn('report_order_consignment_date.staff_group_id', $userGroups);
            if($startDate || $endDate) {
                if($startDate) {
                    $query = $query->where("report_order_consignment_date.date", ">=", $startDate);
                }
                if ($endDate) {
                    $query = $query->where("report_order_consignment_date.date", "<=", $endDate);
                }
            }else {
                $currentDate = date('Y-m-d');
                $payload['date'] = $currentDate;
                $list = CalculatorWeekReport::getWeekMonthYear($payload);
                if ($time == 'day') {
                    $query = $query->where("report_order_consignment_date.date",  $currentDate)
                        ->where('report_order_consignment_date.type', $time);
                }
                if ($time == 'week') {
                    $query = $query->where('report_order_consignment_date.type', $time)
                        ->where('report_order_consignment_date.week', $list->week)
                        ->where('report_order_consignment_date.month', $list->month)
                        ->where('report_order_consignment_date.year', $list->year);
                }
                if ($time == 'month') {
                    $query = $query->where('report_order_consignment_date.type', $time)
                        ->where('report_order_consignment_date.month', $list->month)
                        ->where('report_order_consignment_date.year', $list->year);
                }
                if ($time == 'year') {
                    $query = $query->where('report_order_consignment_date.type', 'month')
                        ->where('report_order_consignment_date.year', $list->year);
                }
            }
            $resultData = $query->groupBy(["staff_group_id"])->paginate($per_page);
            return resSuccessWithinData(new $this->_paginateResource($resultData, $this->_resource));
        } catch (\Exception $exception) {
            return resError($exception->getMessage());
        }
    }

    private function getReportByDateRanger(array $params): array
    {
        $per_page = $params['per_page'] ?? 15;
        $group = $params["group"] ?? null;
        $startDate = $params["date_from"] ?? null;
        $endDate = $params["date_to"] ?? null;
        if ($startDate) {
            $startDate = $startDate . ' 00:00:00';
        }
        if ($endDate) {
            $endDate = $endDate . ' 23:59:59';
        }
        $userGroups = UserGroup::query();
        if($startDate || $endDate) {
            if($endDate) {
                $month = Carbon::createFromFormat('Y-m-d', $params["date_to"])->format('m/Y');
                $userGroups = $userGroups->where('month', $month);
            }else {
                $month = Carbon::createFromFormat('Y-m-d', $params["date_from"])->format('m/Y');
                $userGroups = $userGroups->where('month', $month);
            }
        }
        if ($group) {
            $userGroups->where('group_id', $group);
        }
        $userGroups->join('users', 'users.id', '=', 'users_group.user_id');
        $userIds = $userGroups->where('users.status', 1)
            ->pluck('users_group.user_id')->toArray();
        $userIds = array_unique($userIds);
        $data = [];
        $paginate = [
            'items' => [],
            'pagination' => [
                'total' => 0,
                'count' => 0,
                'per_page' => 0,
                'current_page' => 1,
                'total_pages' => 0
            ]
        ];
        if ($userIds) {
            foreach ($userIds as $user) {
                $data[$user] = [
                    "staff_id" => $user,
                    "staff_code" => 0,
                    "staff_name" => '',
                    "total_count_order" => 0,
                    "total_order_cost" => 0,
                    "total_order_fee" => 0,
                    "total_international_shipping_cost" => 0,
                    "total_woodworking_cost" => 0,
                    "total_inspection_cost" => 0,
                    "total_insurance_cost" => 0,
                    "total_shock_proof_cost" => 0,
                    "total_official_service_fee" => 0
                ];
            }
            $orders = $this->getOrderDoneByDate($startDate, $endDate, [
                'per_page' => $per_page,
                'user_ids' => $userIds
            ]);
            $paginate['pagination'] = [
                'total' => $orders->total(),
                'count' => $orders->count(),
                'per_page' => (int)$orders->perPage(),
                'current_page' => $orders->currentPage(),
                'total_pages' => $orders->lastPage()
            ];

            foreach ($orders as $item) {
                if (isset($data[$item->id])) {
                    $data[$item->id]["staff_code"] =
                        $item->code ?? '';
                    $data[$item->id]["staff_name"] =
                        $item->name ?? '';
                    $data[$item->id]["total_count_order"] =
                        $item->orderStaff->count() ?? 0;
                    $data[$item->id]["total_order_cost"] =
                        (float)$item->orderStaff->sum('order_cost') ?? 0;
                    $data[$item->id]["total_order_fee"] =
                        (float)$item->orderStaff->sum('order_fee') ?? 0;
                    $data[$item->id]["total_woodworking_cost"] =
                        (float)$item->orderStaff->sum('woodworking_cost') ?? 0;
                    $data[$item->id]["total_inspection_cost"] =
                        (float)$item->orderStaff->sum('inspection_cost') ?? 0;
                    $data[$item->id]["total_shock_proof_cost"] =
                        (float)$item->orderStaff->sum('shock_proof_cost') ?? 0;
                    $data[$item->id]["total_official_service_fee"] =
                        (float)$item->orderStaff->sum('official_service_fee') ?? 0;
                }
            }
            $consignments = $this->getConsignmentDoneByDate($startDate, $endDate, [
                'per_page' => $per_page,
                'user_ids' => $userIds
            ]);
            foreach ($consignments as $item) {
                if (isset($data[$item->id])) {
                    $data[$item->id]["staff_code"] =
                        $item->code ?? '';
                    $data[$item->id]["staff_name"] =
                        $item->name ?? '';
                    $data[$item->id]["total_woodworking_cost"] +=
                        (float)$item->orderPackageConsignment->sum('woodworking_cost') ?? 0;
                    $data[$item->id]["total_insurance_cost"] +=
                        (float)$item->orderPackageConsignment->sum('insurance_cost') ?? 0;
                    $data[$item->id]["total_international_shipping_cost"] +=
                        (float)$item->orderPackageConsignment->sum('international_shipping_cost') ?? 0;
                    $data[$item->id]["total_shock_proof_cost"] +=
                        (float)$item->orderPackageConsignment->sum('shock_proof_cost') ?? 0;
                    $data[$item->id]["total_official_service_fee"] +=
                        (float)$item->orderPackageConsignment->sum('official_service_fee') ?? 0;
                }
            }
            foreach ($data as $key => $value) {
                if ($value['staff_code'] == 0 || $value['staff_code'] == '') {
                    unset($data[$key]);
                }
            }
            $paginate['items'] = array_values($data);
        }
        return $paginate;
    }

    public function syncReportSaleStaffByTime($params): JsonResponse
    {
        $users = $this->getUserReport();
        /*$this->processDataReportMonth($params, $users);*/
        dispatch(new SyncReportOrderConsignmentDateJob($params, $users))
            ->onQueue('sync-data-report')->delay(1);
        dispatch(new SyncReportOrderConsignmentWeekJob($params, $users))
            ->onQueue('sync-data-report')->delay(1);
        dispatch(new SyncReportOrderConsignmentMonthJob($params, $users))
            ->onQueue('sync-data-report')->delay(1);
       /* dispatch(new SyncReportOrderConsignmentYearJob($params, $users))
            ->onQueue('sync-data-report')->delay(1);*/
        return resSuccess();
    }

    private function processData($startDate, $endDate, $users): array
    {
        $dataCreate = [];
        foreach ($users as $user) {
            $dataCreate[$user] = [
                "staff_group_id" => $user,
                "count_order" => 0,
                "order_cost" => 0,
                "order_fee" => 0,
                "international_shipping_cost" => 0,
                "woodworking_cost" => 0,
                "inspection_cost" => 0,
                "china_shipping_cost" => 0,
                "china_shipping_cost_old" => 0,
                "order_cost_old" => 0,
                "storage_cost" => 0,
                "shock_proof_cost" => 0,
                "insurance_cost" => 0,
                "official_service_fee" => 0
            ];
        }
        $dataOrders = $this->getOrderDoneByDate($startDate, $endDate);
        $dataConsignments = $this->getConsignmentDoneByDate($startDate, $endDate);
        foreach ($dataOrders as $item) {
            if (isset($dataCreate[$item->id])) {
                $dataCreate[$item->id]["count_order"] =
                    $item->orderStaff->count() ?? 0;
                $dataCreate[$item->id]["order_cost"] =
                    (float)$item->orderStaff->sum('order_cost') ?? 0;
                $dataCreate[$item->id]["order_fee"] =
                    (float)$item->orderStaff->sum('order_fee') ?? 0;
                $dataCreate[$item->id]["woodworking_cost"] =
                    (float)$item->orderStaff->sum('woodworking_cost') ?? 0;
                $dataCreate[$item->id]["inspection_cost"] =
                    (float)$item->orderStaff->sum('inspection_cost') ?? 0;
                $dataCreate[$item->id]["shock_proof_cost"] =
                    (float)$item->orderStaff->sum('shock_proof_cost') ?? 0;
                $dataCreate[$item->id]["official_service_fee"] =
                    (float)$item->orderStaff->sum('official_service_fee') ?? 0;
            }
        }
        foreach ($dataConsignments as $item) {
            if (isset($dataCreate[$item->id])) {
                $dataCreate[$item->id]["woodworking_cost"] +=
                    (float)$item->orderPackageConsignment->sum('woodworking_cost') ?? 0;
                $dataCreate[$item->id]["insurance_cost"] +=
                    (float)$item->orderPackageConsignment->sum('insurance_cost') ?? 0;
                $dataCreate[$item->id]["international_shipping_cost"] +=
                    (float)$item->orderPackageConsignment->sum('international_shipping_cost') ?? 0;
                $dataCreate[$item->id]["shock_proof_cost"] +=
                    (float)$item->orderPackageConsignment->sum('shock_proof_cost') ?? 0;
                $dataCreate[$item->id]["official_service_fee"] +=
                    (float)$item->orderPackageConsignment->sum('official_service_fee') ?? 0;
            }
        }

        return $dataCreate;
    }

    public function processDataReport($params, $users): bool
    {
        if (!empty($params['date'])) {
            $startDate = date('Y-m-d', strtotime($params['date']));
        } else {
            $startDate = date('Y-m-d');
        }
        $list = CalculatorWeekReport::getWeekMonthYear($params);
        $dataCreate = $this->processData(
         $startDate . ' 00:00:00',
         $startDate . ' 23:59:59',
                 $users
        );
        foreach ($dataCreate as $key => $itemList) {
            $itemList["date"] = $startDate;
            $itemList['type'] = 'day';
            $itemList["week"] = $list->week;
            $itemList["month"] = $list->month;
            $itemList["year"] = $list->year;
            ReportOrderConsignmentDate::query()->updateOrCreate([
                "staff_group_id" => $key,
                "date" => $startDate,
                'type' => 'day',
                "week" => $list->week,
                "month" => $list->month,
                "year" => $list->year,
            ], $itemList);
        }

        return true;
    }

    public function processDataReportWeek($params, $users): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($params);
        $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($list->year, $list->month, $list->week);
        $weekStartDate = $calculatorDatetime['week_start_date'];
        $weekEndDate = $calculatorDatetime['week_end_date'];
        $dataCreate = $this->processData($weekStartDate . ' 00:00:00', $weekEndDate . ' 23:59:59', $users);

        foreach ($dataCreate as $key => $itemList) {
            $itemList['type'] = 'week';
            $itemList["week"] = $list->week;
            $itemList["month"] = $list->month;
            $itemList["year"] = $list->year;
            ReportOrderConsignmentDate::query()->updateOrCreate([
                "staff_group_id" => $key,
                "type" => 'week',
                "week" => $list->week,
                "month" => $list->month,
                "year" => $list->year,
            ], $itemList);
        }
        return true;
    }

    public function processDataReportMonth($params, $users): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($params);
        $calculatorDatetime = CalculatorWeekReport::getStartAndEndDateOfMonth($params);
        $fistDayOfMonth = $calculatorDatetime->fistDayOfMonth . ' 00:00:00';
        $lastDayOfMonth = $calculatorDatetime->lastDayOfMonth . ' 23:59:59';
        $dataCreate = $this->processData($fistDayOfMonth, $lastDayOfMonth, $users);
        foreach ($dataCreate as $key => $itemList) {
            $itemList['type'] = 'month';
            $itemList["week"] = 0;
            $itemList["month"] = $list->month;
            $itemList["year"] = $list->year;
            ReportOrderConsignmentDate::query()->updateOrCreate([
                "staff_group_id" => $key,
                "type" => 'month',
                "month" => $list->month,
                "year" => $list->year,
            ], $itemList);
        }
        return true;
    }

    public function processDataReportYear($params, $users): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($params);
        $startDate = $list->year.'-01-01 00:00:00';
        $endDate = $list->year.'-12-31 23:59:59';
        $dataCreate = $this->processData($startDate, $endDate, $users);
        foreach ($dataCreate as $key => $itemList) {
            $itemList['type'] = 'year';
            $itemList["week"] = 0;
            $itemList["month"] = 0;
            $itemList["year"] = $list->year;
            ReportOrderConsignmentDate::query()->updateOrCreate([
                "staff_group_id" => $key,
                "type" => 'year',
                "year" => $list->year,
            ], $itemList);
        }
        return true;
    }

    private function getOrderDoneByDate($startDate, $endDate, $options = [])
    {
        $result = User::query();
        $result = $result->whereExists(function ($query) {
            $query->select("users_group.user_id")
                ->from('users_group')
                ->whereRaw('users_group.user_id = users.id');
        });
        if (!empty($options['user_ids'])) {
            $result = $result->whereIn('users.id', $options['user_ids']);
        }
        /*$result = $result->where('users.id', 366);*/
        $result->select('users.id', 'users.name', 'users.code')
            ->where('users.status', 1)
            ->with(['orderStaff' => function ($orderStaff) use ($startDate, $endDate) {
                $orderStaff->select(
                    'orders.id', 'orders.order_cost', 'orders.order_fee',
                    'orders.international_shipping_cost', 'orders.woodworking_cost', 'orders.staff_group_id',
                    'orders.inspection_cost', 'orders.china_shipping_cost', 'orders.china_shipping_cost_old',
                    'orders.order_cost_old', 'orders.date_done',
                    \DB::raw('IF(orders.official=2,orders.official_service_fee,0) as official_service_fee')
                )
                    ->where('orders.status', OrderConstant::KEY_STATUS_DONE)
                    ->whereNotNull("orders.staff_group_id")
                    ->whereNotNull('orders.date_done');
                    if ($startDate) {
                        $orderStaff->where("orders.date_done", ">=", $startDate);
                    }
                    if ($endDate) {
                        $orderStaff->where("orders.date_done", "<=", $endDate);
                    }
            }, 'orderStaff.orderSuppliers']);
        $result = $result->orderBy('users.id');
        if (!empty($options['per_page'])) {
            return $result->paginate($options['per_page']);
        } else {
            return $result->get();
        }
    }

    private function getConsignmentDoneByDate($startDate = null, $endDate = null, $options = [])
    {
        $user = User::query();
        $user = $user->select('users.id', 'users.name', 'users.code')
            ->where('users.status', 1);
        /*$user = $user->where('users.id', 366);*/
        $user = $user->whereExists(function ($query) {
            $query->select("users_group.user_id")
                ->from('users_group')
                ->whereRaw('users_group.user_id = users.id');
        });
        if (!empty($options['user_ids'])) {
            $user = $user->whereIn('users.id', $options['user_ids']);
        }
        $user = $user->with(['orderPackageConsignment' => function ($orderPackageConsignment) use ($startDate, $endDate) {
            $orderPackageConsignment->select(
                'order_package.id', 'order_package.order_id', 'order_package.order_type',
                'order_package.international_shipping_cost',
                'order_package.staff_group_id', 'order_package.woodworking_cost', 'order_package.shock_proof_cost',
                'order_package.insurance_cost', 'order_package.inspection_cost', 'order_package.storage_cost', 'order_package.official_service_fee'
            )->where('status', PackageConstant::STATUS_RECEIVED_GOODS);
            if ($startDate) {
                $orderPackageConsignment->where('date_done' , '>=', $startDate);
            }
            if ($endDate) {
                $orderPackageConsignment->where('date_done' , '<=', $endDate);
            }
        }]);
        $users = $user->orderBy('users.id');
        if (!empty($options['per_page'])) {
            $users = $users->paginate($options['per_page']);
        } else {
            $users = $users->get();
        }
        return $users;
    }

    private function getUserReport(): array
    {
        $result = User::query()
            ->select(
                'users.id'
            );
        /*$result = $result->where('users.id', 366);*/
        $result = $result->whereExists(function ($query) {
            $query->select("users_group.user_id")
                ->from('users_group')
                ->whereRaw('users_group.user_id = users.id');
        });
        return $result->pluck('id')->toArray();
    }

    public function initReportPerformanceStaffSale($type = null)
    {
        $dataInit = [];
        $users = User::query()
            ->select(['id', 'name', 'code', 'email', 'phone_number'])
            ->whereActive()
            ->whereHas('department', function ($role) {
                $role->where('type', DepartmentConstant::TYPE_BUSINESS);
            })
            ->get();
        if ($users->isEmpty()) {
            return $dataInit;
        }
        $dataOld = ReportPerformanceStaffSale::query()->pluck('user_id')->toArray();
        if ($type === 'force') {
            ReportPerformanceStaffSale::query()->truncate();
        }

        foreach ($users as $user) {
            if ( $type === 'force' || ($type !== 'force' && !in_array( $user->id, $dataOld)) ) {
                $dataInit[] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'time_sale_fastest' => 0,
                    'time_sale_slowest' => 0,
                    'time_sale_average' => 0,
                    'total_orders' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        }

        if (count($dataInit)) {
            DB::table('report_performance_staff_sale')->insert($dataInit);
        }
        return true;
    }

    public function performanceSaleList(array $params): LengthAwarePaginator
    {
        $query = DB::table('report_performance_staff_sale');
        $query->join('users', 'users.id', '=', 'report_performance_staff_sale.user_id')
            ->join('departments', 'departments.id', '=', 'users.department_id')
            ->where('departments.type', DepartmentConstant::TYPE_BUSINESS)
            ->where('users.status', UserConstant::RESET_STAFF_ALLOW)
            ->selectRaw('user_id, user_name, time_sale_fastest, time_sale_slowest, time_sale_average, total_orders,
            ROUND(time_sale_fastest/3600, 1) as time_fastest, ROUND(time_sale_slowest/3600, 1) as time_slowest,
            ROUND(time_sale_average/3600, 1) as time_average');
        if (!empty($params['name'])) {
            $query->where('user_name', 'LIKE', '%'.$params['name'].'%');
        }
        if (!empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }

        return $query->paginate();
    }

    public function performanceSaleTop(array $params): Collection
    {
        $params['filter_by'] = !empty($params['filter_by']) ? $params['filter_by'] :
            ReportConstant::REPORT_PERFORMANCE_SALE_TOP_DAY;

        $query = DB::table('order_performance_histories')
                    ->selectRaw('
                        order_performance_histories.staff_id,
                        users.name as staff_name,
                        SUM(total_order) as total_order'
                    );

        $query->join('users', 'users.id', '=', 'order_performance_histories.staff_id');

        $date = date('Y-m-d');
        $month = date('n');
        $year = date('Y');

        if (!empty($params['filter_by'])) {
            switch ($params['filter_by']) {
                case ReportConstant::REPORT_PERFORMANCE_SALE_TOP_DAY:
                    $query->where('date', $date);
                    $query->orderBy('total_order', 'DESC');
                    break;
                case ReportConstant::REPORT_PERFORMANCE_SALE_TOP_MONTH:
                    $query->where('year', $year);
                    $query->where('month', $month);
                    $query->orderBy('total_order', 'DESC');
                    break;
                case ReportConstant::REPORT_PERFORMANCE_SALE_TOP_YEAR:
                    $query->where('year', $year);
                    $query->orderBy('total_order', 'DESC');
                    break;
            }
        }

        $query->groupBy('order_performance_histories.staff_id');

        return $query->limit(5)->get();
    }

    public function performanceSaleInWeek(): array
    {
        $data = [];
        $daysOfWeek = $this->getDaysInWeek();
        foreach ($daysOfWeek as $day) {
            $data[$day] = [
                'label' => $day,
                'orders' => 0
            ];
        }

        $query = DB::table('order_performance_histories')
            ->selectRaw('date, SUM(total_order) as total_order')
            ->whereIn('date', $daysOfWeek)
            ->groupBy('date');

        $performanceDays = $query->get();
        foreach ($performanceDays as $item) {
            $data[$item->date]['orders'] = $item->total_order;
        }

        return array_values($data);
    }

    public function getDaysInWeek(): array
    {
        $now = Carbon::now();

        $monday = $now->startOfWeek()->format('Y-m-d');
        $tuesday = $now->startOfWeek()->addDay()->format('Y-m-d');
        $wedday = $now->startOfWeek()->addDay(2)->format('Y-m-d');
        $thursday = $now->startOfWeek()->addDay(3)->format('Y-m-d');
        $friday = $now->startOfWeek()->addDay(4)->format('Y-m-d');
        $satday = $now->startOfWeek()->addDay(5)->format('Y-m-d');
        $sunday = $now->endOfWeek()->format('Y-m-d');

        return [$monday, $tuesday, $wedday, $thursday, $friday, $satday, $sunday];
    }

    public function performanceSaleByTime(array $params): array
    {
        $data = [];

        $filterBy = !empty($params['filter_by']) ? $params['filter_by'] : ReportConstant::REPORT_PERFORMANCE_SALE_TIME_DAY;

        switch ($filterBy) {
            case ReportConstant::REPORT_PERFORMANCE_SALE_TIME_DAY:
                $data = $this->getDataPerformanceSaleInDay();
                break;
            case ReportConstant::REPORT_PERFORMANCE_SALE_TIME_WEEK:
                $data = $this->performanceSaleInWeek();
                break;
            case ReportConstant::REPORT_PERFORMANCE_SALE_TIME_MONTH:
                $data = $this->getDataPerformanceSaleInMonth();
                break;
        }

        return $data;
    }

    public function getWeeksInMonth($date): array
    {

        $date = $date->copy()->firstOfMonth()->startOfDay();
        $eom  = $date->copy()->endOfMonth()->startOfDay();

        $dates = [];
        for ($i = 1; $date->lte($eom); $i++) {
            $week = $date->week();
            $startDateWeek = clone $date->startOfWeek(Carbon::MONDAY);
            $endDateWeek = clone $date->endOfWeek(Carbon::SUNDAY);
            $dates[$week]['start'] = $i == 1 ? clone $date : $startDateWeek;
            $dates[$week]['end']   = $date->gte($eom) ? $eom : $endDateWeek;
            $date->addDays(1);
        }

        return $dates;
    }

    public function getDataPerformanceSaleInDay(): array
    {
        $data = [];
        for ($i = 0; $i < 24; $i++) {
            $data[] = [
                'label'  => $i . 'h',
                'orders' => 0
            ];
        }
        $query = DB::table('orders')
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(id) total_order')
            )
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->whereBetween('created_at', getStartDateEndDateByType());

        $performanceDays = $query->get();
        foreach ($performanceDays as $item) {
            if (isset($data[$item->hour])) {
                $data[$item->hour]['orders'] += $item->total_order;
            }
        }

        return $data;
    }

    public function getDataPerformanceSaleInMonth(): array
    {
        $data = [];
        $date = Carbon::now();
        $weeksInMonth = $this->getWeeksInMonth($date);
        $year = date('Y');
        $weeks = [];
        $i = 0;
        foreach($weeksInMonth as $week => $item) {
            $i ++;
            $data[$week] = [
                'label' => 'Tuần ' . $i,
                'orders' => 0
            ];
            $weeks[] = $week;
        }

        $query = DB::table('order_performance_histories')
            ->selectRaw('week, SUM(total_order) as total_order')
            ->where('year', $year)
            ->whereIn('week', $weeks)
            ->groupBy('week');

        $performanceDays = $query->get();
        foreach ($performanceDays as $item) {
            $data[$item->week]['orders'] = $item->total_order;
        }
        return array_values($data);
    }

    public function cronjobInsertDataOrderPerformance()
    {
        Order::query()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as total_order'),
                'staff_group_id'
                )
            ->whereBetween('created_at', getStartDateEndDateByType('week'))
            ->groupBy('date', 'staff_group_id')
            ->chunk(100, function ($orders) {
                    foreach($orders as $order) {
                        $this->insertDataOrderPerformanceDay([
                            'date' => $order->date,
                            'total_order' => $order->total_order,
                            'staff_id' => $order->staff_group_id,
                        ]);
                    }
                }
            );
        Order::query()
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') AS new_date"),
                DB::raw("YEAR(created_at) AS year"),
                DB::raw("MONTH(created_at) AS month"),
                DB::raw("WEEK(created_at) as week"),
                DB::raw("COUNT(*) as total_order"),
                "staff_group_id"
            )
            ->whereBetween('created_at', getStartDateEndDateByType('year'))
            ->groupBy('new_date', 'staff_group_id')
            ->groupByRaw('WEEK(created_at)')
            ->chunk(100, function ($orders) {
                foreach($orders as $order) {
                    $this->insertDataOrderPerformanceYear([
                        'year' => $order->year,
                        'month' => $order->month,
                        'week' => $order->week + 1,
                        'total_order' => $order->total_order,
                        'staff_id' => $order->staff_group_id,
                    ]);
                }
            }
        );
    }

    private function insertDataOrderPerformanceDay($data)
    {
        OrderPerformanceHistory::updateOrCreate([
            'date' => $data['date'],
            'staff_id' => $data['staff_id'],
        ],  $data);
    }

    private function insertDataOrderPerformanceYear($data)
    {
        OrderPerformanceHistory::updateOrCreate([
            'year' => $data['year'],
            'month' => $data['month'],
            'week' => $data['week'],
            'staff_id' => $data['staff_id'],
        ],  $data);
    }
}
