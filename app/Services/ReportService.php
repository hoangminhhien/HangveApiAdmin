<?php


namespace App\Services;

use App\Constants\ComplainConstant;
use App\Constants\CustomerConstant;
use App\Constants\DeliveryConstant;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\ReportConstant;
use App\Constants\RoleConstant;
use App\Constants\TimeConstant;
use App\Constants\UserConstant;
use App\Helpers\PaginateHelper;
use App\Http\Resources\PaginateJsonResource;
use App\Http\Resources\Report\ReportReconciliationWarehouseResource;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderPerformanceHistory;
use App\Models\OrderRefund;
use App\Models\ReconciliationWarehouse;
use App\Models\ReportDelivery;
use App\Models\ReportLevel;
use App\Models\ReportOrganizationOrder;
use App\Models\ReportPerformanceStaffSale;
use App\Models\ReportQuantity;
use App\Models\ReportRevenue;
use App\Models\User;
use DateTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use DatePeriod;
use DateInterval;
use App\Models\OrderPackage;
use Illuminate\Support\Facades\Log;
use App\Models\Consignment;
use Illuminate\Support\Str;

class ReportService implements Service
{
    /**
     * Đếm số report theo ngày, tuần, tháng
     *
     * @param string $model
     * @param string|null $column
     * @return array{today: int, week: int, month: int}
     */
    public function reports(string $model, ?string $column = 'created_at'): array
    {
        $startMonth = date('Y-m-01 00:00:00');
        $endMonth   = date('Y-m-d 23:59:59');

        $query = (new $model)->newQuery();
        $tableName = (new $model)->getTable();
        $perService = app()->make(PerService::class);
        $query = $perService->getConditionByUserLogin($query, $tableName);
        $result = $query
            ->selectRaw(
                "COUNT(CASE WHEN DATE($column) = ? THEN 1 END) as today",
                [date(TimeConstant::DATE)]
            )->selectRaw(
                "COUNT(CASE WHEN $column BETWEEN ? AND ? THEN 1 END) as week",
                [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]
            )->selectRaw(
                "COUNT(CASE WHEN MONTH($column) = ? AND YEAR($column) = ? THEN 1 END) as month",
                [date('m'), date('Y')]
            )
            ->whereBetween($column, [$startMonth, $endMonth])
            ->first();
        return [
            'today' => $result->today,
            'week' => $result->week,
            'month' => $result->month,
        ];
    }

    /**
     * @param  int  $level
     * @param  int|null  $oldLevel
     */
    public function reportLevel(int $level, ?int $oldLevel = null): void
    {
        $query = ReportLevel::query()->where(
            [
                'organization_id' => request()->input('organization_id') ?? getOrganization()
            ]
        );
        if (is_numeric($oldLevel)) {
            (clone $query)->where(['level' => $oldLevel])->where('quantity', '>', 0)->decrement('quantity');
        }
        (clone $query)->where(['level' => $level])->increment('quantity');
    }

    public function incrementByOrganization(string $model, string $column, ?int $number = 1, ?array $condition = [])
    {
        optional((new $model)::query()->where($condition)->first())->increment($column, $number);
    }

    public function decrementByOrganization(string $model, string $column, ?int $number = 1, ?array $condition = [])
    {
        optional((new $model)::query()->where($condition)->where($column, '>', 0)->first())->decrement(
            $column,
            $number
        );
    }

    public function inDecrementByOrganization(
        string $model,
        string $inColumn,
        string $deColumn,
        ?int $number = 1,
        ?array $condition = []
    ) {
        $this->incrementByOrganization($model, $inColumn, $number, $condition);
        $this->decrementByOrganization($model, $deColumn, $number, $condition);
    }

    /**
     * @param  float  $amount
     * @param  string  $key
     */
    public function incrementByReportRevenue(float $amount, string $key)
    {
        $this->storeReportRevenue($key);
        $this->incrementByOrganization(ReportRevenue::class, 'value', $amount, ['key' => $key]);
    }

    /**
     * @param  float  $amount
     * @param  string  $key
     */
    public function decrementByReportRevenue(float $amount, string $key)
    {
        $this->storeReportRevenue($key);
        $this->decrementByOrganization(ReportRevenue::class, 'value', $amount, ['key' => $key]);
    }

    /**
     * @param  string  $key
     */
    public function storeReportRevenue(string $key)
    {
        ReportRevenue::query()->firstOrCreate(
            ['key' => $key, 'organization_id' => getOrganization(), 'time' => date('Y')]
        );
    }

    public function incrementQuantity(string $key): void
    {
        ReportQuantity::query()->firstOrCreate(['key' => $key])->increment('quantity');
    }

    public function getQuantityByKey(string $key): int
    {
        return (optional(ReportQuantity::query()->where('key', $key)->first())->quantity ?? 0) + 1;
    }

    /**
     * @param array $params
     * @param bool $isTotal
     * @return mixed
     */
    public function getOrderComplete($params, $isTotal = false, $isExport = false)
    {
        $perPage = !empty($params['per_page']) ? $params['per_page'] : 25;

        $query = Order::query()
        ->code()
        ->customerCode()
        ->dateDone()
        ->where('orders.status', OrderConstant::KEY_STATUS_DONE);

        if (!empty($params['staff_group_id'])) {
            $staffGroupIds = is_array($params['staff_group_id']) ? $params['staff_group_id'] : [$params['staff_group_id']];
            $query->whereIn('orders.staff_group_id', $staffGroupIds)
                        ->when(in_array("0", $staffGroupIds), function($q) {
                            return $q->orWhereNull('staff_group_id');
                        });
        }
        if(!empty($params['official'])) {
             if($params['official'] == 3) {
                $query->where('official_is_partner', 1);
            }elseif($params['official'] == 2) {
                $query->where('official', OrderConstant::OFFICIAL)->where('official_is_partner', 0);
            }else {
                $query->where('official', $params['official'])->where('official_is_partner', 0);
            }
        }

        // Nếu không có quyền xem all thì mặc định lọc theo userLogin
        $userOnline = \Auth::user();
        $viewAll = $userOnline->checkUserHasPer('report.view-all-order-done');
        if (!$viewAll) {
            $staffGroupIds = [(int) $userOnline->id];
            $query->whereIn('orders.staff_group_id', $staffGroupIds);
        }
        $table = $query->clone()->selectRaw(
            'orders.id,
            orders.code,
            orders.customer_id,
            IF(orders.advanced_inspection_cost > 0,orders.advanced_inspection_cost,orders.inspection_cost) as inspection_cost,
            order_fee,
            staff_group_id,
            (order_cost + order_fee -
                discount_cost + inspection_cost + advanced_inspection_cost + (china_shipping_cost * exchange_rate)) as orderAmount,
            reset_position,
            orders.date_done as dateDone,
            IF(orders.official=2,orders.official_service_fee,0) as official_service_fee',
        )->with([
            'customer' => function($q) {
                $q->select('id', 'code');
            },
            'staffGroup' => function($q) {
                $q->select('id', 'name', 'email');
            }
        ])
        ->orderBy('dateDone', 'DESC');

        if ($isTotal) {
            $rowTotal = $query->clone()->select(
                DB::raw('SUM(order_cost + order_fee - discount_cost + inspection_cost + (china_shipping_cost * exchange_rate)) as order_amount'),
                DB::raw('SUM(order_fee) as total_order_fee'),
                DB::raw('SUM(inspection_cost + advanced_inspection_cost) as total_inspection_cost'),
                DB::raw('SUM(IF(orders.official=2,orders.official_service_fee,0)) as total_official_service_fee'),
            )->first();
            $totalAmountAll = $rowTotal->order_amount ?? 0;
            $totalOrderFee  = $rowTotal->total_order_fee ?? 0;
            $totalInspection  = $rowTotal->total_inspection_cost ?? 0;
            $totalOfficialServiceFee = $rowTotal->total_official_service_fee ?? 0;

            return [$totalAmountAll, $totalOrderFee, $totalInspection, $totalOfficialServiceFee];
        }
        if($isExport){
            return $table->get();
        }
        return $table->paginate($perPage);
    }

    /**
     * @param array $params
     * @param bool $isTotalDeposit
     * @return mixed
     */
    public function getCustomerActive($params, $isTotalDeposit = false)
    {
        $perPage = !empty($params['per_page']) ? $params['per_page'] : 25;

        $query = Customer::query();
        $query->select(
            'customers.id', 'customers.name', 'customers.source',
            'customers.phone_number', 'customers.code', 'customers.email'
        );
        $query->with('orders', 'orders.details.category', 'orders.orderStatusTime');

        if (!empty($params['code'])) {
            $query->code();
        }
        if (!empty($params['name'])) {
            $query->name();
        }
        if (data_get($params, 'source') !== null){
            $query->where('source', $params['source']);
        }
        if (!empty($params['phone_number'])) {
            $query->phoneNumber();
        }
        if (!empty($params['wait_deposit_level'])) {
            $query->waitDepositLevel();
        }

        $query->where('customers.status', CustomerConstant::STATUS_ACTIVE);

        if ($isTotalDeposit) {
            if (empty($params['wait_deposit_level'])) {
                $query->join('orders', 'customers.id', '=', 'orders.customer_id');
            }

            $query->where('orders.status', OrderConstant::KEY_STATUS_WAITING_DEPOSIT);
            $query->whereNull('orders.deleted_at');

            return $query->count();
        }

        $orderCompletes = $query->paginate($perPage);

        return $orderCompletes;
    }

    /**
     * @param array $params
     * @param bool $isTotalDeposit
     * @return mixed
     */
    public function getCustomerOrder($params)
    {
        $perPage = !empty($params['per_page']) ? $params['per_page'] : 25;

        $query = Customer::query();
        $query->select(
            'customers.id', 'customers.name', 'customers.phone_number',
            'customers.code', 'customers.email',
            'customers.source'
        );
        $query->with('reportCustomerOrders.orderStatusTime');
        $query->join('orders', 'customers.id', '=', 'orders.customer_id');

        if (!empty($params['code'])) {
            $query->code();
        }
        if (data_get($params, 'source') !== null) {
            $query->where('customers.source', $params['source']);
        }
        if (!empty($params['name'])) {
            $query->name();
        }
        if (!empty($params['phone_number'])) {
            $query->phoneNumber();
        }

        $query->where('customers.status', CustomerConstant::STATUS_ACTIVE);

        $orderStatus = OrderConstant::REPORT_CUSTOMER_ORDER_CARE;
        if (!empty($params['order_status'])) {
            $orderStatus = [$params['order_status']];
        }
        $query->whereIn('orders.status', $orderStatus);

        if (!empty($params['created_at'])) {
            $dates = explode(',', $params['created_at']);

            if (!empty($dates[0])) {
                $query->whereDate('orders.created_at', '>=', $dates[0]);
            }
            if (!empty($dates[1])) {
                $query->whereDate('orders.created_at', '<=', $dates[1]);
            }
        }

        $query->groupBy('customers.id');
        //dd($query->toSql());

        $customerOrders = $query->paginate($perPage);

        return $customerOrders;
    }

    public function getCustomerPackageVN($params, &$extra, $isExport = false)
    {
        $perPage = !empty($params['per_page']) ? $params['per_page'] : 25;

        $query = Customer::query();
        $query->select(
            'customers.id', 'customers.name', 'customers.source',
            'customers.phone_number', 'customers.code', 'customers.email'
        );
        $query->with('packagesVN', 'packagesVN.notes', 'packagesVN.statusTimes', 'packagesVN.order');
        $query->join('order_package', 'customers.id', '=', 'order_package.customer_id');

        if (!empty($params['created_at']) || !empty($params['priority'])) {
            $query->join('order_package_status_times', 'order_package.id', '=',
                'order_package_status_times.order_package_id');
        }

        if (!empty($params['code'])) {
            $query->code();
        }
        if (data_get($params, 'source') !== null) {
            $query->where('customers.source', $params['source']);
        }
        if (!empty($params['name'])) {
            $query->name();
        }
        if (!empty($params['phone_number'])) {
            $query->phoneNumber();
        }

        if (!empty($params['bill_code'])) {
            $query->where('order_package.bill_code', 'LIKE', '%' . request()->get('bill_code') . '%');
        }

        $query->where('order_package.status', PackageConstant::STATUS_WAREHOUSE_VN);

        if (!empty($params['created_at'])) {
            $dates = explode(',', $params['created_at']);

            if (count($dates)) {
                $query->where('order_package_status_times.key', PackageConstant::STATUS_WAREHOUSE_VN);
            }
            if (!empty($dates[0])) {
                $query->where('order_package_status_times.created_at', '>=', $dates[0] . ' 00:00:00');
            }
            if (!empty($dates[1])) {
                $query->where('order_package_status_times.created_at', '<=', $dates[1] . ' 23:59:59');
            }
        }

        if (!empty($params['priority'])) {
            $level = $params['priority'];

            $query->where(function ($q) use ($level) {
                $q->where('order_package_status_times.key', PackageConstant::STATUS_WAREHOUSE_VN );

                switch ($level) {
                    case CustomerConstant::REPORT_PACKAGE_VN_LV1:
                        $q->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) <= UNIX_TIMESTAMP(DATE_ADD(
                            order_package_status_times.created_at,INTERVAL 24 HOUR))');
                        break;
                    case CustomerConstant::REPORT_PACKAGE_VN_LV2:
                        $q->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) > UNIX_TIMESTAMP(DATE_ADD(
                            order_package_status_times.created_at,INTERVAL 24 HOUR)) AND
                        UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) <= UNIX_TIMESTAMP(DATE_ADD(
                            order_package_status_times.created_at,INTERVAL 72 HOUR))');
                        break;
                    case CustomerConstant::REPORT_PACKAGE_VN_LV3:
                        $q->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) > UNIX_TIMESTAMP(DATE_ADD(
                            order_package_status_times.created_at,INTERVAL 72 HOUR))');
                        break;
                }
            });
        }

        if (empty($extra)) {
            $queryClone = clone $query;
            $totalShippingCost = $queryClone->select(
                DB::raw('SUM(international_shipping_cost + china_shipping_cost + insurance_cost + inspection_cost
	                + woodworking_cost + shock_proof_cost + storage_cost + delivery_cost) as total_shipping_cost'))
                ->pluck('total_shipping_cost');

            $queryClone->join('orders', 'orders.id', '=', 'order_package.order_id');
            $totalOrderDebtCost = $queryClone->select(
                DB::raw('SUM((orders.order_cost + orders.order_fee - orders.discount_cost + orders.inspection_cost
	                + (orders.china_shipping_cost * orders.exchange_rate)) - orders.deposit_cost) as total_debt_cost'))
                ->pluck('total_debt_cost');
            $extra = [
                'summary' => [
                    'totalShippingCost' => $totalShippingCost,
                    'totalDebtCost' => $totalOrderDebtCost,
                ]
            ];
        }

        $query->distinct('customers.id');
        //dd($query->toSql());
        if($isExport){
            return $query->get();
        }
        $customerOrders = $query->paginate($perPage);

        return $customerOrders;
    }


    public function getCustomerPotential($params, $isTotalDeposit = false)
    {
        $perPage = !empty($params['per_page']) ? $params['per_page'] : 25;
        $query = Customer::query();
        $query->select(
                'customers.id',
                'customers.name',
                'customers.phone_number',
                'customers.code',
                'customers.email',
                'customers.point_way',
                'customers.point_care',
                'customers.chatbiz_total_call',
                'customers.staff_group_id',
                'customers.service',
                'customers.via',
                'customers.business_type',
                'customers.warehouse_id',
                'customers.source',
            );
        $query->with(
            'orders', 'orders.details.category',
            'orders.orderStatusTime', 'staffGroup', 'pointWay'
        );

        if (!empty($params['code'])) {
            $query->code();
        }
        if (data_get($params, 'source') !== null) {
            $query->where('source', $params['source']);
        }
        if (!empty($params['name'])) {
            $query->name();
        }
        if (!empty($params['phone_number'])) {
            $query->phoneNumber();
        }
        if (!empty($params['wait_deposit_level'])) {
            $query->waitDepositLevel();
        }
        if (isset($params['point_way'])) {
            $query->where('point_way', $params['point_way']);
        }
        if (!empty($params['staff_group_id'])) {
            $query->where('staff_group_id', $params['staff_group_id']);
        }
        if (!empty($params['start_date'])) {
            Carbon::parse($params['start_date']);
            $query->where('created_at', '>=', Carbon::parse($params['start_date']));
        }
        if (!empty($params['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($params['end_date'])->endOfDay());
        }
        if(data_get(getCurrentUser()->roles, 0)) {
            $role = data_get(getCurrentUser()->roles, 0);
            $displayName = Str::slug($role->display_name);
            if(!($displayName == UserConstant::ROLE_MANAGER_GROUP || $displayName == 'admin')) {
                $query->where('staff_group_id', getCurrentUser()->id);
            }
        }


        // Nếu không có quyền xem all thì mặc định lọc theo userLogin
        $userOnline = Auth::user();
        $viewAll = $userOnline->checkUserHasPer('report.view-all-consulting-manage-customer-potential');
        if (!$viewAll) {
            $staffGroupIds = [(int) $userOnline->id];
            $query->whereIn('staff_group_id', $staffGroupIds);
        }

        if (!empty($params['potential'])) {
            $query->where('customers.point_way', '>=', '40');
        }
        if ($isTotalDeposit) {
            $query->where('customers.point_way', '>=', '40');
            $query->whereNull('customers.deleted_at');
            return $query->count();
        }


        return $query->paginate($perPage);
    }

    public function getStaffQuotation(array $params)
    {
        $auth = getCurrentUser();
        $query = User::query();
        $query->whereHas('departmentQuote');
        $query->where('users.status', 1);
        if($auth->hasPermissionName('report.view-only-quote-by-self')) {
            $query->where('users.id', $auth->id);
        }
        if (!empty($params['code'])) {
            $query->where("users.code", 'like', '%'. $params['code'] .'%');
        }
        if (!empty($params['name'])) {
            $query->where("users.name", 'like', '%'. $params['name'] .'%');
        }
        $queryListUser = clone $query;
        $queryListData = clone $query;
        $listUser = $queryListUser->select('id', 'name', 'code', 'reset_staff_allow')->get()->toArray();
        $today = Carbon::today()->toDateString();
        $listData = $queryListData->selectRaw("
            users.code,
            users.name,
            users.id,
            users.reset_staff_allow,
            SUM(CASE
                WHEN orders.status = '" . OrderConstant::KEY_STATUS_WAITING_QUOTE . "'
                THEN 1 ELSE 0
            END) AS totalWaitingQuote,
            SUM(CASE
                WHEN DATE(order_status_times.status_1) = '" . $today . "'
            THEN 1 ELSE 0
            END) AS totalQuoted,
            SUM(
                CASE
                    WHEN orders.status = '" . OrderConstant::KEY_STATUS_WAITING_QUOTE . "'
                    THEN 1 ELSE 0
                END
                +
                CASE
                    WHEN DATE(order_status_times.status_1) = '" . $today . "'
                    THEN 1 ELSE 0
                END
            ) AS totalAll
        ")
        ->leftJoin('orders', 'users.id', '=', 'orders.staff_quotation_id')
        ->leftJoin('order_status_times', 'order_status_times.order_id', '=', 'orders.id')
        ->whereNull('orders.deleted_at')->groupBy('users.id')->get()->toArray();
        $dataFinal = array_map(function($item) use ($listData){
            $result = [
                'id' => $item['id'],
                'code' => $item['code'],
                'name' => $item['name'],
                'totalWaitingQuote' => 0,
                'totalQuoted' => 0,
                'totalAll' => 0,
                'setOrderStatus' => $item['reset_staff_allow']
            ];
            foreach($listData as $data) {
                if($item['id'] == $data['id']) {
                    $result['totalWaitingQuote'] = $data['totalWaitingQuote'];
                    $result['totalQuoted'] = $data['totalQuoted'];
                    $result['totalAll'] = $data['totalAll'];
                }
            }
            return $result;
        }, $listUser);
        return $dataFinal;
    }

    public function getStaffOrder(array $params)
    {
        $auth = getCurrentUser();
        $query = User::query();
        $query->where('users.status', 1);
        $query->whereHas('departmentOrder');
        if($auth->hasPermissionName('report.view-only-quote-by-self')) {
            $query->where('users.id', $auth->id);
        }
        if (!empty($params['code'])) {
            $query->where("users.code", 'like', '%'. $params['code'] .'%');
        }
        if (!empty($params['name'])) {
            $query->where("users.name", 'like', '%'. $params['name'] .'%');
        }
        $queryListUser = clone $query;
        $queryListData = clone $query;
        $listUser = $queryListUser->select('id', 'name', 'code', 'reset_staff_allow')->get()->toArray();
        $listData = $queryListData->selectRaw(
            "users.code, users.name, users.id, users.reset_staff_allow,
            SUM(CASE WHEN (order_status_times.`status_2` IS NOT NULL AND order_status_times.`status_8` IS NULL) THEN 1 ELSE 0 END) AS `totalOrdered`,
            SUM(CASE WHEN (order_status_times.`status_8` IS NOT NULL AND order_status_times.`status_3` IS NULL) THEN 1 ELSE 0 END) AS `totalWaitingOrder`,
            SUM(CASE WHEN (order_status_times.`status_3` IS NOT NULL AND order_status_times.`status_4` IS NULL) THEN 1 ELSE 0 END) AS `totalPayment`
        ")->leftJoin('orders', 'users.id', '=', 'orders.staff_order_id')
        ->join('order_status_times', 'orders.id', '=', 'order_status_times.order_id')
        ->whereNull('orders.deleted_at');
        $time = $this->getDateTimeRange($params['time'] ?? '');
        $listData = $listData->whereBetween('order_status_times.updated_at', $time)->groupBy('users.id')->get()->toArray();
        $dataFinal = array_map(function($item) use ($listData){
            $result = [
                'id' => $item['id'],
                'code' => $item['code'],
                'name' => $item['name'],
                'totalWaitingOrder' => 0,
                'totalPayment' => 0,
                'totalOrdered' => 0,
                'totalAll' => 0,
                'setOrderStatus' => $item['reset_staff_allow']
            ];
            foreach($listData as $data) {
                if($item['id'] == $data['id']) {
                    $result['totalWaitingOrder'] = $data['totalWaitingOrder'];
                    $result['totalPayment'] = $data['totalPayment'];
                    $result['totalOrdered'] = $data['totalOrdered'];
                    $result['totalAll'] = $data['totalWaitingOrder'] + $data['totalPayment'] + $data['totalOrdered'];
                }
            }
            return $result;
        }, $listUser);
        return $dataFinal;
    }

    public function getStaffComplain(array $params)
    {
        $auth = getCurrentUser();
        $query = User::query();
        $query->where('users.status', 1);
        $query->whereHas('departmentComplain');

        if($auth->hasPermissionName('report.view-only-quote-by-self')) {
            $query->where('users.id', $auth->id);
        }
        if (!empty($params['code'])) {
            $query->where("users.code", 'like', '%'. $params['code'] .'%');
        }
        if (!empty($params['name'])) {
            $query->where("users.name", 'like', '%'. $params['name'] .'%');
        }
        $queryListUser = clone $query;
        $queryListData = clone $query;
        $listUser = $queryListUser->select('id', 'name', 'code', 'reset_staff_allow')->get()->toArray();
        $listData = $queryListData->selectRaw("users.code, users.name, users.id, users.reset_staff_allow,
            SUM(CASE WHEN complains.status = '". ComplainConstant::KEY_STATUS_PENDING  ."' THEN 1 ELSE 0 END) AS `totalPending`,
            SUM(CASE WHEN complains.status = '". ComplainConstant::KEY_STATUS_PROCESS  ."' THEN 1 ELSE 0 END) AS `totalProcessing`,
            SUM(CASE WHEN complains.status in ('status_2', 'status_3') THEN 1 ELSE 0 END) AS totalProcessed,
            SUM(CASE WHEN (complains.status IS NOT NULL) THEN 1 ELSE 0 END) AS `totalAll`
        ")->leftJoin('complains', 'users.id', '=', 'complains.staff_complain_id');
        $time = $this->getDateTimeRange($params['time'] ?? '');
        $listData = $listData->whereBetween('complains.created_at', $time)->groupBy('users.id')->get()->toArray();
        $dataFinal = array_map(function($item) use ($listData){
            $result = [
                'id' => $item['id'],
                'code' => $item['code'],
                'name' => $item['name'],
                'totalPending' => 0,
                'totalProcessing' => 0,
                'totalProcessed' => 0,
                'totalAll' => 0,
                'setOrderStatus' => $item['reset_staff_allow']
            ];
            foreach($listData as $data) {
                if($item['id'] == $data['id']) {
                    $result['totalPending'] = $data['totalPending'];
                    $result['totalProcessing'] = $data['totalProcessing'];
                    $result['totalProcessed'] = $data['totalProcessed'];
                    $result['totalAll'] = $data['totalAll'];
                }
            }
            return $result;
        }, $listUser);
        return $dataFinal;
    }

    public function getTotalOrderCancel($params)
    {
        $query = OrderRefund::query();
        $query->where('order_refunds.status', OrderRefund::STATUS_PROCESSED);
        $query->where('order_refunds.type_refund', OrderRefund::TYPE_CANCEL);
        if(!empty($params['date_from']) && !empty($params['date_to'])) {
            $start = Carbon::parse($params['date_from'])->startOfDay();
            $end = Carbon::parse($params['date_to'])->endOfDay();
            $query->whereBetween('order_refunds.created_at', [$start, $end]);
        }
        if(!empty($params['date_from']) && empty($params['date_to'])) {
            $start = Carbon::parse($params['date_from'])->startOfDay();
            $end = Carbon::now()->endOfDay();
            $query->whereBetween('order_refunds.created_at', [$start, $end]);
        }
        if(empty($params['date_from']) && !empty($params['date_to'])) {
            $start = Carbon::parse('2025-01-01')->startOfDay();
            $end = Carbon::parse($params['date_to'])->endOfDay();
            $query->whereBetween('order_refunds.created_at', [$start, $end]);
        }
        return $query->count();
    }

    public function getStaffCancelOrder(array $params)
    {
        $auth = getCurrentUser();
        $perPage = $params['per_page'] ?? 50;
        $query = OrderRefund::query();
        $query->selectRaw("users.id AS user_id, users.name AS user_name, COUNT(order_refunds.id) AS total_canceled_orders");
        $query->leftJoin('users', 'users.id', '=', 'order_refunds.created_user_id');
        if($auth->hasPermissionName('report.view-only-quote-by-self')) {
            $query->where('users.id', $auth->id);
        }
        $query->where('order_refunds.status', OrderRefund::STATUS_PROCESSED);
        $query->where('order_refunds.type_refund', OrderRefund::TYPE_CANCEL);

        if(!empty($params['user_name'])){
            $query->where("users.name", 'like', '%'. $params['user_name'] .'%');
        }
        if(!empty($params['date_from']) && !empty($params['date_to'])) {
            $start = Carbon::parse($params['date_from'])->startOfDay();
            $end = Carbon::parse($params['date_to'])->endOfDay();
            $query->whereBetween('order_refunds.created_at', [$start, $end]);
        }
        if(!empty($params['date_from']) && empty($params['date_to'])) {
            $start = Carbon::parse($params['date_from'])->startOfDay();
            $end = Carbon::now()->endOfDay();
            $query->whereBetween('order_refunds.created_at', [$start, $end]);
        }
        if(empty($params['date_from']) && !empty($params['date_to'])) {
            $start = Carbon::parse('2025-01-01')->startOfDay();
            $end = Carbon::parse($params['date_to'])->endOfDay();
            $query->whereBetween('order_refunds.created_at', [$start, $end]);
        }
        $query->groupBy(['users.id', 'users.name']);

        return $query->paginate($perPage);
    }

    public function getStaffCancelOrderChart(array $params)
    {
        $auth = getCurrentUser();
        $query = OrderRefund::query();
        $query->selectRaw("order_refunds.reason_create_cancel, COUNT(order_refunds.reason_create_cancel) AS total_reason_canceled_orders");
        $query->leftJoin('users', 'users.id', '=', 'order_refunds.created_user_id');
        if($auth->hasPermissionName('report.view-only-quote-by-self')) {
            $query->where('users.id', $auth->id);
        }
        $query->where('order_refunds.status', OrderRefund::STATUS_PROCESSED);
        $query->where('order_refunds.type_refund', OrderRefund::TYPE_CANCEL);

        if(!empty($params['user_name'])){
            $query->where("users.name", 'like', '%'. $params['user_name'] .'%');
        }
        if(!empty($params['date_from']) && !empty($params['date_to'])) {
            $start = Carbon::parse($params['date_from'])->startOfDay();
            $end = Carbon::parse($params['date_to'])->endOfDay();
            $query->whereBetween('order_refunds.created_at', [$start, $end]);
        }
        if(!empty($params['date_from']) && empty($params['date_to'])) {
            $start = Carbon::parse($params['date_from'])->startOfDay();
            $end = Carbon::now()->endOfDay();
            $query->whereBetween('order_refunds.created_at', [$start, $end]);
        }
        if(empty($params['date_from']) && !empty($params['date_to'])) {
            $start = Carbon::parse('2025-01-01')->startOfDay();
            $end = Carbon::parse($params['date_to'])->endOfDay();
            $query->whereBetween('order_refunds.created_at', [$start, $end]);
        }
        $query->groupBy('order_refunds.reason_create_cancel');

        return $query->get();
    }

    /**
     * @param array $params
     * @return mixed
     */
    public function getStaffCareAndCounselor($params)
    {
        $permissionTakeCare = User::checkUserHasPer(RoleConstant::PERMISSION_TAKE_CARE_CUSTOMER);
        $permissionCustomerConsulting = User::checkUserHasPer(RoleConstant::PERMISSION_CUSTOMER_CONSULTING);
        if(!$permissionTakeCare || !$permissionCustomerConsulting) {
            return false;
        }
        $perPage = !empty($params['per_page']) ? $params['per_page'] : 25;

        $query = User::query();

        $query->selectRaw("users.code, users.name, users.id,
            SUM(CASE WHEN order_status_times.`status_0` THEN 1 ELSE 0 END) AS `totalWaitingQuote`,
            SUM(CASE WHEN order_status_times.`status_1` THEN 1 ELSE 0 END) AS `totalWaitingDeposit`,
            SUM(CASE WHEN order_status_times.`status_3` THEN 1 ELSE 0 END) AS `totalOrdered`,
            SUM(CASE WHEN order_status_times.`status_4` THEN 1 ELSE 0 END) AS `totalDone`,
            SUM(orders.id) AS `totalAll`
            ");

        $query->leftJoin('orders', function($join) use ($params) {
            $join->on(function ($q) use ($params) {
                $q->on('users.id', '=', 'orders.staff_care_id');
                $q->orOn('users.id','=', 'orders.staff_counselor_id');
            });

            if (!empty($params['filter_by'])) {
                list($startDate, $endDate) = $this->getDateByFilterDayType($params['filter_by']);

                $join->whereRaw("orders.created_at >= '".$startDate."' AND orders.created_at <= '".$endDate."'");
            }
        });

        $query->leftJoin('order_status_times', 'orders.id', '=', 'order_status_times.order_id');
        $query->whereNull('orders.deleted_at');

        $query->where('users.status', UserConstant::STATUS_ACTIVE);

        if (!empty($params['keyword'])) {
            $query->where(function ($q) use ($params) {
                $q->where("users.code", 'like', '%'. $params['keyword'] .'%');
                $q->orWhere("users.name", 'like', '%'. $params['keyword'] .'%');
            });
        }

//        if (!empty($params['filter_by'])) {
//            list($startDate, $endDate) = $this->getDateByFilterDayType($params['filter_by']);
//
//            $query->whereDate('orders.created_at', '>=', $startDate);
//            $query->whereDate('orders.created_at', '<=', $endDate);
//        }

        $query->groupBy('users.id');
        $query->orderBy('users.name', 'ASC');
        //dd($query->toSql());
        return $query->paginate($perPage);
    }

    public function getDateByFilterDayType($type)
    {
        $startDate = date('Y-m-d 00:00:00');
        $endDate   = date('Y-m-d 23:59:59');

        switch ($type) {
            case ReportConstant::STAFF_CARE_COUNSELOR_FILTER_BY_MONTH:
                $startDate = date('Y-m-01 00:00:00');
                $endDate   = date('Y-m-t 23:59:59');
                break;
            case ReportConstant::STAFF_CARE_COUNSELOR_FILTER_BY_YEAR:
                $startDate = date('Y-01-01 00:00:00');
                $endDate   = date('Y-12-t 23:59:59');
                break;
        }

        return [$startDate, $endDate];
    }

    /**
     * @param array $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPurchaseChannel(array $params)
    {
        $select = [
            "orders.ecommerce",
            DB::raw('count(orders.id) AS count_order'),
            DB::raw('SUM(orders.order_fee) AS total_order_fee'),
            DB::raw('SUM(orders.order_cost) AS total_order_amount'),
            DB::raw('SUM((orders.order_cost - orders.order_cost_real) +
                ((orders.china_shipping_cost - orders.china_shipping_cost_real) * orders.exchange_rate) +
                (orders.bonus * orders.exchange_rate)) AS total_negotiate'
            )
        ];

        $res = Order::query()
            ->select($select)
            ->where('orders.status', OrderConstant::KEY_STATUS_DONE)
            ->whereBetween('orders.date_done', [$params['startDate'], $params['endDate']])
            ->groupBy(['orders.ecommerce'])
            ->orderBy('ecommerce', 'desc')
            //->toSql();
            ->paginate($params['per_page']);

        return $res;
    }

    public function getTransportFee()
    {
        $time = data_get(request(), 'time');
        $type = data_get(request(), 'type');
        $warehouseId = data_get(request(), 'warehouseId');
        $year = data_get(request(), 'year');
        [$startDate, $endDate] = getStartDateEndDateByType($time, $year);
        $selectOrder = [
            DB::raw('SUM(inspection_cost) AS total_inspection_cost'),
            DB::raw('SUM(woodworking_cost) AS total_woodworking_cost')
        ];
        if(!empty($time) && $time == "year"){
            array_push($selectOrder, DB::raw('DATE_FORMAT(date_done, "%m") as date'));
            $interval = DateInterval::createFromDateString('1 month');
            $formatDate = $returnDate = "m";
        }else{
            array_push($selectOrder, DB::raw('DATE(date_done) as date'));
            $interval = DateInterval::createFromDateString('1 day');
            $formatDate = "Y-m-d";
            $returnDate = "d";
        }

        $selectPack = array_merge($selectOrder, [
            DB::raw('SUM(international_shipping_cost + (china_shipping_cost * exchange_rate) + insurance_cost + inspection_cost + woodworking_cost + shock_proof_cost + delivery_cost - discount_cost) AS total_amount')
        ]);
        $orderPackages = OrderPackage::select($selectPack)->where('status', PackageConstant::STATUS_RECEIVED_GOODS)->whereBetween('date_done', [$startDate, $endDate])->whereNotNull('date_done')
                                        ->when($type == 'order' || $type == 'consignment', function($q) use ($type){
                                            if($type == 'order') {
                                                $q->where('order_type', Order::class);
                                            }
                                            if($type == 'consignment') {
                                                $q->where('order_type', Consignment::class);
                                            }
                                        })
                                        ->when($type == 'unconfirmed', function($q){
                                            $q->whereNull('order_type');
                                        })
                                        ->when($warehouseId, function($q) use ($warehouseId){
                                            $q->where('warehouse_id', $warehouseId);
                                        })->groupByRaw('date')->get();
        if($type == null || $type == 'order'){
            $orders = Order::select($selectOrder)->where('status', OrderConstant::KEY_STATUS_DONE)->where('inspection_cost', '>', 0)->whereBetween('date_done', [$startDate, $endDate])->whereNotNull('date_done')
                            ->when($warehouseId, function($q) use ($warehouseId){
                                $q->where('warehouse_id', $warehouseId);
                            })->groupByRaw('date')->get();
        }else{
            $orders = collect([]);
        }

        $period = new DatePeriod($startDate, $interval, $endDate);
        $response = [];
        foreach ($period as $key => $interval){
            $timeCheck = $interval->format($formatDate);
            $resultOrder = $orders->filter(function ($item) use ($timeCheck) {
                return $item->date == $timeCheck;
            })->first();
            $resultPack = $orderPackages->filter(function ($item) use ($timeCheck) {
                return $item->date == $timeCheck;
            })->first();
            // Phí kiểm đếm check theo đơn
            // Phí đóng gỗ check theo kiện
            $response[$key]['interval'] =  $interval->format($returnDate);
            $response[$key]['total_inspection_cost'] =  (int)data_get($resultOrder, 'total_inspection_cost');
            // $response[$key]['total_woodworking_cost'] =  (int)data_get($resultOrder, 'total_woodworking_cost') + (int)data_get($resultPack, 'total_woodworking_cost');
            $response[$key]['total_woodworking_cost'] =  (int)data_get($resultPack, 'total_woodworking_cost');
            $response[$key]['total_amount'] =  (int) data_get($resultPack, 'total_amount');
        }
        $sumTotalInspectionCost = 0;
        $sumTotalWoodworkingCost = 0;
        $sumTotalAmountCost = 0;
        foreach($response as $item){
            $sumTotalInspectionCost += $item['total_inspection_cost'];
            $sumTotalWoodworkingCost += $item['total_woodworking_cost'];
            $sumTotalAmountCost += $item['total_amount'];
        }
        $data = [
            'items' => $response,
            'result' => [
                'sum_total_inspection_cost' => $sumTotalInspectionCost,
                'sum_total_woodworking_cost' => $sumTotalWoodworkingCost,
                'sum_total_amount_cost' => $sumTotalAmountCost
            ]
        ];
        return $data;
    }

    public function saveReconciliationWarehouse($items)
    {
        foreach ($items as $item) {
            ReconciliationWarehouse::query()->updateOrCreate(['bill_code' => $item['bill_code']], $item);
        }
    }

    public function reconciliationWarehouse($params)
    {
        $query = ReconciliationWarehouse::query();
        if(!empty($params['customer_code'])){
            $query->where('customer_code', $params['customer_code']);
        }
        if(!empty($params['customer_name'])){
            $query->where('customer_name', 'like', '%' . $params['customer_name'] .'%');
        }
        if(!empty($params['customer_phone'])){
            $query->where('customer_phone', $params['customer_phone']);
        }
        if(!empty($params['type'])){
            $query->where('type', $params['type']);
        }
        if(!empty($params['user_name'])){
            $query->where('user_name', 'like', '%' . $params['user_name'] .'%');
        }
        if(!empty($params['warehouse_code'])){
            $query->where('warehouse_code', $params['warehouse_code']);
        }
        $dateFrom = $params['date_from'];
        $dateTo = $params['date_to'];
        if (!empty($dateFrom)) {
            $dateFrom = $dateFrom . ' 00:00:00';
            $query->where('created_at', '>=',  $dateFrom);
        }
        if (!empty($dateTo)) {
            $dateTo = $dateTo . ' 23:59:59';
            $query->where('created_at', '<=', $dateTo);
        }
        $extra = [
            'total_transport_fee' => $query->sum('transport_fee'),
            'total_other_fee' => $query->sum('other_fee'),
            'total_amount' => $query->sum('total_amount'),

        ];

        $query->orderBy('id', 'desc');
        return new PaginateJsonResource(
            $query->paginate(PaginateHelper::getPerPage()),
            ReportReconciliationWarehouseResource::class,
            $extra
        );
    }

    public function customerPackVNExport($params)
    {
        $extra = [1]; // Thêm cái này để k cần lấy query count nữa
        $customers = $this->getCustomerPackageVN($params, $extra, true);
        $result = [];
        foreach($customers as $key => $customer){
            $packages =  $customer->packagesVN;
            foreach($packages as $package){
                $statusTimes = $package->statusTimes->first();
                if(!empty($statusTimes)){
                    $date = Carbon::parse($statusTimes->created_at);
                    $now = Carbon::now();
                    $diffHours = $date->diffInHours($now);
                    if($date->diffInDays($now) == 0){
                        $diff = $date->diffInHours($now). ' giờ trước';
                    }else{
                        $diff = $date->diffInDays($now). ' ngày trước';
                    }
                }else{
                    $diff = '';
                }

                if($diffHours <= 24){
                    $diffText = 'Kiện hàng về VN dưới 24 giờ';
                }elseif($diffHours > 24 && $diffHours <= 72){
                    $diffText = 'Kiện hàng về VN dưới 72 giờ';
                }else{
                    $diffText = 'Kiện hàng về VN trên 72 giờ';
                }
                $order = data_get($package, 'order');
                $balance = $order ? $order->total_amount - $order->deposit_cost : 0;
                $newItem = [
                    $customer->email,
                    $customer->name,
                    $customer->phone_number,
                    $package->bill_code,
                    $package->order_kind_of,
                    $package->order_code,
                    number_format($package->shipping_cost,0,".",".") . ' đ',
                    number_format($balance,0,".",".") . ' đ',
                    $diff,
                    $package->note_ordered,
                    $diffText
                ];
                $result[] = $newItem;
            }

        }
        return $result;
    }
    public function calculatorDatetime($now, $options=[]): array
    {
        $subDay = $now->subDays(1);
        if (!empty($options['update-now'])) {
            $subDay = $now;
        }
        $subDay = $subDay->format('Y-m-d');
        $date = new DateTime($subDay);
        $week = $date->format("W");
        $year = $date->format("Y");
        $dto = new DateTime();
        $dto->setISODate($year, $week);
        $data['week_start_date'] = $dto->format('Y-m-d'). ' 00:00:00';
        $dto->modify('+6 days');
        $data['week_end_date'] = $dto->format('Y-m-d'). ' 23:59:59';
        $data['year'] = $year;
        $data['week'] = $week;
        $data['subDay'] = $subDay;
        return $data;
    }

    public function getStartDateAndEndDateOfWeek($week, $year): array
    {
        $dto = new DateTime();
        $dto->setISODate($year, $week);
        $data['week_start_date'] = $dto->format('Y-m-d'). ' 00:00:00';
        $dto->modify('+6 days');
        $data['week_end_date'] = $dto->format('Y-m-d'). ' 23:59:59';
        $data['year'] = $year;
        $data['week'] = $week;
        return $data;
    }

    public function updateOrderPerformanceHistory(Order $order)
    {
        $year         = date('Y');
        $month        = date('n');
        $week         = date('W');
        $date         = date('Y-m-d');

        $customerId   = $order->customer_id;
        $staffGroupId = $order->staff_group_id;

        $customer = $order->customer ?? '';
        if (!$customer) {
            return;
        }

        $checkCustomerExistByYear = Order::query()
            ->where('customer_id', $customerId)
            ->whereYear('created_at', $year)
            ->exists();

        if ($checkCustomerExistByYear) {
            return;
        }

        // update order performance histories
        // $timeSale             = strtotime($order->created_at) - strtotime($customer->created_at); // time performance
        $timeSale             = $customer->staff_group_time_assign ?
            strtotime($order->created_at) - strtotime($customer->staff_group_time_assign) :
            strtotime($order->created_at) - strtotime($customer->created_at) ; // time performance
        $lastOrderPerformance = OrderPerformanceHistory::query()
            ->where('staff_id', $staffGroupId)
            ->orderBy('id', 'DESC')
            ->first();

        $dataPerformanceHistory = [
            'order_id'           => $order->id,
            'customer_id'        => $customerId,
            'staff_id'           => $staffGroupId,
            'date'               => $date,
            'week'               => $week,
            'month'              => $month,
            'year'               => $year,
            'time_sale'          => $timeSale,
            'total_orders_day'   => $lastOrderPerformance && $lastOrderPerformance->date == $date ?
                $lastOrderPerformance->total_orders_day + 1 : 1,
            'total_orders_week'  => $lastOrderPerformance && $lastOrderPerformance->week == $week ?
                $lastOrderPerformance->total_orders_week + 1 : 1,
            'total_orders_month' => $lastOrderPerformance && $lastOrderPerformance->month == $month ?
                $lastOrderPerformance->total_orders_month + 1 : 1,
            'total_orders_year'  => $lastOrderPerformance && $lastOrderPerformance->year == $year ?
                $lastOrderPerformance->total_orders_year + 1 : 1,
        ];
        OrderPerformanceHistory::create($dataPerformanceHistory);
        // update report performance staff sale
        $staffPerformance = ReportPerformanceStaffSale::query()->where('user_id', $staffGroupId)->first();
        if (!$staffPerformance) {
            $dataReportPerformance = [
                'user_id'           => $staffGroupId,
                'user_name'         => optional($order->staffGroup)->name,
                'time_sale_fastest' => $timeSale,
                'time_sale_slowest' => $timeSale,
                'time_sale_average' => $timeSale,
                'total_orders'      => 1,
            ];
            ReportPerformanceStaffSale::create($dataReportPerformance);
            return;
        }

        if ($staffPerformance->time_sale_fastest == 0 || $staffPerformance->time_sale_fastest > $timeSale) { // 0 value default
            $staffPerformance->time_sale_fastest = $timeSale;
        }
        if ($staffPerformance->time_sale_slowest < $timeSale) {
            $staffPerformance->time_sale_slowest = $timeSale;
        }

        $totalTimePerformance = OrderPerformanceHistory::query()
            ->where('staff_id', $staffGroupId)
            ->sum('time_sale');
        $totalTimePerformance = intval($totalTimePerformance);
        $totalTimePerformance = $totalTimePerformance > 0 ? $totalTimePerformance : $timeSale;

        $timeAverage          = $staffPerformance->total_orders > 0 ?
            round($totalTimePerformance / ($staffPerformance->total_orders + 1)) :
            $totalTimePerformance;
        $staffPerformance->time_sale_average = $timeAverage;

        $staffPerformance->total_orders += 1;
        $staffPerformance->save();
    }

    public function updateOrganizationOrder(array $data)
    {
        $user = Auth::user();

        $query = Order::query();
        $query->whereNull('deleted_at');

        $dataUpdate = [];
        foreach (OrderConstant::STATUSES as $status => $statusName) {
            $quantity = (clone $query)->where('status', $status)->count();

            $dataUpdate[$status] = $quantity;
        }

        // Kiểm tra xem có report chưa
        $reportOrgOrderDB = ReportOrganizationOrder::where('organization_id', $user->organization_id)->first();
        if (is_null($reportOrgOrderDB)) {
            $data_org = [
                'organization_id' => $user->organization_id
            ];
            ReportOrganizationOrder::create($data_org);
        }

        return ReportOrganizationOrder::query()->where('organization_id', $user->organization_id)->update($dataUpdate);
    }

    public function updateQuantityOrganizationOrder(Order $order, $type = 'update')
    {
        $user = Auth::user();

        $report = ReportOrganizationOrder::query()->where('organization_id', $user->organization_id)->first();
        if (!$report) {
            return true;
        }

        $oldStatus = $order->getOriginal($order->status);
        $newStatus = $order->status;

        if ($type == 'create') {
            $report->status_0 += 1;
            $report->save();
            return true;
        }

        if ($type == 'delete') {
            $report->{$newStatus} = $report->{$newStatus} - 1 > 0 ? $report->{$newStatus} - 1 : 0;
            $report->save();
            return true;
        }
        // update
        if ($oldStatus === $newStatus) {
            return true;
        }

        $report->{$oldStatus} = $report->{$oldStatus} - 1 > 0 ? $report->{$oldStatus} - 1 : 0;
        $report->{$newStatus} += 1;
        $report->save();
    }

    public function updateOrganizationDelivery(array $data)
    {
        $user = Auth::user();

        $query = Delivery::query();
        //$query->whereNull('deleted_at');

        $dataUpdate = [];
        foreach (DeliveryConstant::STATUSES as $status => $statusName) {
            $quantity = (clone $query)->where('status', $status)->count();

            $dataUpdate[$status] = $quantity;
        }

        // Kiểm tra xem có report chưa
        $reportOrgOrderDB = ReportDelivery::where('organization_id', $user->organization_id)->first();
        if (is_null($reportOrgOrderDB)) {
            $data_org = [
                'organization_id' => $user->organization_id
            ];
            ReportDelivery::create($data_org);
        }

        return ReportDelivery::query()->where('organization_id', $user->organization_id)->update($dataUpdate);
    }

    public function reportConsignment($params, &$arraySum)
    {
        $perPage = !empty($params['per_page']) ? $params['per_page'] : 25;
        $query = OrderPackage::query();
        $query->with(['statusTimes', 'customer', 'staffGroup']);
        $query->where('status', PackageConstant::STATUS_RECEIVED_GOODS);
        if(!empty($params['order_type'])) {
            if($params['order_type'] == 1) {
                $query->where('order_type', Order::class);
            } else {
                $query->where('order_type', Consignment::class);
            }
        }
        if(!empty($params['bill_code'])) {
            $query->where('bill_code', $params['bill_code']);
        }
        if(!empty($params['code_warehouse'])) {
            $query->where('code_warehouse', $params['code_warehouse']);
        }
        $staffGroupIds = is_array(request()->query('staff_group_id')) ? request()->query('staff_group_id') : [request()->query('staff_group_id')];


        // Nếu không có quyền xem all thì mặc định lọc theo userLogin
        $userOnline = \Auth::user();
        $viewAll = $userOnline->checkUserHasPer('report.view-all-package-consignment');
        if (!$viewAll) {
            $staffGroupIds = [(int) $userOnline->id];
            $query->whereIn('staff_group_id', $staffGroupIds);
        }


        if(!empty($staffGroupIds)) {
            if(!empty($params['staff_group_id'])) {
                $query->withoutGlobalScopes()->where(function($q) use ($staffGroupIds) {
                    $q->whereIn('staff_group_id', $staffGroupIds);
                    if (in_array(0, $staffGroupIds)) {
                        $q->orWhereNull('staff_group_id');
                    }
                });
            }
        }
        if(!empty($params['service'])) {
            if(is_array($params['service'])) {
                $arrayServices = $params['service'];
                $query->withoutGlobalScopes()->where( function ($query) use ($arrayServices) {
                    foreach ($arrayServices as $k => $service) {
                        if($k == 0) {
                            $query->where($service, true);
                        } else {
                            $query->orWhere($service, true);
                        }
                    }
                });
            }
        }
        if(!empty($params['type_quantitative'])) {
            if($params['type_quantitative'] == PackageConstant::WEIGHT) {
                $query->where('type_quantitative', $params['type_quantitative']);
            } else {
                $query->where('type_quantitative', '!=', PackageConstant::WEIGHT);
            }
        }
        if(!empty($params['date_done']) && empty($params['range_time'])) {
            $query->dateDone();
        }
        if(!empty($params['range_time'])) {
            $date = implode(',', $this->getDateTimeRange($params['range_time']));
            $query = (new FilterService())->rangeDateFilter($query, $date, 'date_done');
        }

        if(!empty($params['official'])) {
            if($params['official'] == 3) {
                $query->where('official_is_partner', 1);
            }elseif($params['official'] == 2) {
                $query->where('official', OrderConstant::OFFICIAL)->where('official_is_partner', 0);
            }else {
                $query->where('official', $params['official'])->where('official_is_partner', 0);
            }
        }

        // Sort theo yêu cầu  ngày 21-01-2025
        $query->orderBy('date_done', 'DESC');


        $querySum = clone $query;
        $sum = $querySum->select(
            DB::raw("SUM(international_shipping_cost) as total_international_shipping"),
            DB::raw("SUM(delivery_cost) as total_delivery_cost"),
            DB::raw("SUM(china_shipping_cost * exchange_rate) as total_china_shipping"),
            DB::raw("SUM(inspection_cost) as total_inspection_cost"),
            DB::raw("SUM(insurance_cost) as total_insurance_cost"),
            DB::raw("SUM(woodworking_cost) as total_woodworking_cost"),
            DB::raw("SUM(shock_proof_cost) as total_shock_proof_cost"),
            DB::raw("SUM(IF(official=1,0,official_service_fee)) as total_official_service_fee"),
            DB::raw("SUM(discount_cost) as total_discount_cost"),
        )->first();
        $totalAmount = ($sum->total_international_shipping ?? 0) +
                        ($sum->total_insurance_cost ?? 0) +
                        ($sum->total_inspection_cost ?? 0) +
                        ($sum->total_woodworking_cost ?? 0) +
                        ($sum->total_shock_proof_cost ?? 0) +
                        ($sum->total_delivery_cost ?? 0) +
                        ($sum->total_china_shipping ?? 0) -
                        ($sum->total_discount_cost ?? 0);
        $arraySum = [
            'total_amount' => round($totalAmount ?? 0),
            'total_inspection_cost' => round($sum->total_inspection_cost ?? 0),
            'total_insurance_cost' => round($sum->total_insurance_cost ?? 0),
            'total_woodworking_cost' => round($sum->total_woodworking_cost ?? 0),
            'total_shock_proof_cost' => round($sum->total_shock_proof_cost ?? 0),
            'total_official_service_fee' => round($sum->total_official_service_fee ?? 0),
            'total_discount_cost' => round($sum->total_discount_cost ?? 0),
        ];

        return  $query->paginate($perPage);
    }

    public function reportFeeService($params, &$arraySum)
    {
        $perPage = !empty($params['per_page']) ? $params['per_page'] : 25;
        $query = OrderPackage::query();
        $query->selectRaw('order_package.id, sum(order_package.insurance_cost) as insurance_cost, sum(order_package.woodworking_cost) as woodworking_cost,
         sum(order_package.shock_proof_cost) as shock_proof_cost, order_package.customer_id, order_package.staff_group_id, order_package.order_id,
         sum(orders.inspection_cost) as total_inspection_cost
         ');
        $query->with(['customer', 'staffGroup']);
        $query->join('orders', 'orders.id', '=', 'order_package.order_id');
        $query->where('order_package.status', PackageConstant::STATUS_RECEIVED_GOODS);
        $query->whereYear('order_package.date_done', '>=', '2025');
        $query->whereNotNull('order_package.staff_group_id');
        $query->where(function($q) {
            $q->where('orders.inspection_cost', '>', 0)
                ->orWhere('order_package.insurance_cost', '>', 0)
                ->orWhere('order_package.woodworking_cost', '>', 0)
                ->orWhere('order_package.shock_proof_cost', '>', 0);
        });
        $staffGroupIds = is_array(request()->query('staff_group_id'))
            ? request()->query('staff_group_id')
            : [request()->query('staff_group_id')];

        // Nếu không có quyền xem all thì mặc định lọc theo userLogin
        $userOnline = \Auth::user();
        $viewAll = $userOnline->checkUserHasPer('report.view-all-package-consignment');
        if (!$viewAll) {
            $staffGroupIds = [(int) $userOnline->id];
            $query->whereIn('order_package.staff_group_id', $staffGroupIds);
        }


        if(!empty($staffGroupIds)) {
            if(!empty($params['staff_group_id'])) {
                $query->withoutGlobalScopes()->where(function($q) use ($staffGroupIds) {
                    $q->whereIn('order_package.staff_group_id', $staffGroupIds);
                    if (in_array(0, $staffGroupIds)) {
                        $q->orWhereNull('order_package.staff_group_id');
                    }
                });
            }
        }
        if(!empty($params['date_done'])) {
            $query->dateDone();
        }
        $totalInspectionCost = $query->sum('orders.inspection_cost');
        $totalInsuranceCost = $query->sum('order_package.insurance_cost');
        $totalWoodworkingCost = $query->sum('order_package.woodworking_cost');
        $totalShockProofCost = $query->sum('order_package.shock_proof_cost');
        $arraySum = [
            'total_amount' => $totalInspectionCost + $totalInsuranceCost + $totalWoodworkingCost + $totalShockProofCost,
            'total_insurance_cost' => $totalInsuranceCost,
            'total_inspection_cost' => $totalInspectionCost,
            'total_woodworking_cost' => $totalWoodworkingCost,
            'total_shock_proof_cost' => $totalShockProofCost,
        ];
        $query->groupBy('order_package.customer_id');

        return $query->paginate($perPage);
    }
    public function reportSlowQuote($params)
    {
        $perPage = !empty($params['per_page']) ? $params['per_page'] : 25;
        $query = Order::query();
        $query->select('id', 'code', 'staff_quotation_id', 'created_at', 'code_parent');
        $query->with(['staffQuotation', 'orderStatusTime']);
        $query->where('is_sent_notification', 2);
        $query->where('created_at', '>', '2025-05-20: 00:00:00');
        $query->whereNull('code_parent');
        $query->whereNotNull('orders.staff_quotation_id');
        $staffQuotationIds = $params['staff_quotation_id'] ?? [];
        $auth = getCurrentUser();
        if($auth->hasPermissionName('report.view-only-quote-by-self')) {
            $query->where('orders.staff_quotation_id', $auth->id);
        }
        if(!empty($staffQuotationIds)) {
            $query->whereIn('orders.staff_quotation_id', $staffQuotationIds);
        }
        $date_created = $params['date_created'] ?? null;

        if(!empty($date_created)) {
            $arrayRangeDate = explode(',', $date_created);
            $query->whereBetween('orders.created_at', [$arrayRangeDate[0] . ' 00:00:00', $arrayRangeDate[1] . ' 23:59:59']);
        }

        $time = $params['time'] ?? 'desc';
        $query->orderBy('orders.created_at', $time);

        return $query->paginate($perPage);
    }

    public function reportSlowestQuote($params)
    {
        $perPage = !empty($params['per_page']) ? $params['per_page'] : 25;
        $query = Order::query();
        $query->selectRaw('id, staff_quotation_id, code_parent, created_at, count(id) as total_order');
        $query->with(['staffQuotation']);
        $query->whereHas('staffQuotation', fn ($q) => $q->where('users.status', 1));
        $query->where('is_sent_notification', 2);
        $query->where('created_at', '>', '2025-05-20: 00:00:00');
        $query->whereNull('code_parent');
        $query->whereNotNull('orders.staff_quotation_id');
        $staffQuotationIds = $params['staff_quotation_id'] ?? [];
        $auth = getCurrentUser();
        if($auth->hasPermissionName('report.view-only-quote-by-self')) {
            $query->where('orders.staff_quotation_id', $auth->id);
        }
        if(!empty($staffQuotationIds)) {
            $query->whereIn('orders.staff_quotation_id', $staffQuotationIds);
        }
        $date_created = $params['date_created'] ?? null;

        if(!empty($date_created)) {
            $arrayRangeDate = explode(',', $date_created);
            $query->whereBetween('orders.created_at', [$arrayRangeDate[0] . ' 00:00:00', $arrayRangeDate[1] . ' 23:59:59']);
        }
        $query->orderBy('total_order', 'desc');
        $query->groupBy('orders.staff_quotation_id');

        return $query->paginate($perPage);
    }

    public function getDateTimeRange($time)
    {
        $timeQueryStart = \Carbon\Carbon::now()->startOfDay();
        $timeQueryEnd = Carbon::now()->endOfDay();
        if ($time == 'w') {
            $timeQueryStart = Carbon::now()->startOfWeek();
            $timeQueryEnd = Carbon::now()->endOfWeek();
        }
        if ($time == 'm') {
            $timeQueryStart = Carbon::now()->startOfMonth();
            $timeQueryEnd = Carbon::now()->endOfMonth();
        }

        return [$timeQueryStart, $timeQueryEnd];
    }
}
