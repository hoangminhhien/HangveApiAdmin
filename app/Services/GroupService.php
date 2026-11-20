<?php


namespace App\Services;

use App\Constants\DepartmentConstant;
use App\Constants\GroupConstant;
use App\Constants\NotifyConstant;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\ResourceConstant;
use App\Constants\RoleConstant;
use App\Constants\UserConstant;
use App\Models\Group;
use App\Models\Order;
use App\Models\OrderPackage;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use App\Models\Customer;
use App\Http\Resources\Group\GroupResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GroupService extends BaseService
{
    protected string $_resource = GroupResource::class;

    protected $userGroupModel;
    protected $groupKpi;

    public function __construct()
    {
        parent::__construct();

        $this->userGroupModel = \App::make('App\Models\UserGroup');
        $this->groupKpi       = \App::make('App\Models\GroupKpi');
    }

    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();

        $query = UserGroup::query();

        $query->select('users_group.*');

        $params = request()->only('user_name', 'group_id', 'status', 'month');

        $query->with([
            'user', 'group.manager', 'group.admins', 'group.kpiMonth'
        ]);

        $query->join('groups', 'groups.id', '=', 'users_group.group_id');
        $query->join('users', 'users.id', '=', 'users_group.user_id');
        $query->where('users.status', 1);

        $query = $this->getQueryByParams($query, $params);
        $query->orderBy('users.name');

        $data = $query->paginate($perPage);

        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }

    /**
     * @param Builder $query
     * @param array $params
     * @return mixed
     */
    public function getQueryByParams($query, $params)
    {
        $month = !empty($params['month']) ? $params['month'] : date('m/Y');
        $query->where('users_group.month', $month);

        if (isset($params['group_id'])) {
            $query->where('users_group.group_id', $params['group_id']);
        }

        if (!empty($params['user_name'])) {
            $query->where(function ($subQ) use ($params) {
               $subQ->where('users.email', 'LIKE', '%'.$params['user_name'].'%')
                   ->orwhere('users.phone_number', 'LIKE', '%'.$params['user_name'].'%');
            });
        }

        if (isset($params['status'])) {
            $query->where('users_group.status', $params['status']);
        }

//        if (!empty($params['filter_range'])) {
//            $range = $params['filter_range'];
//            list($startDate, $endDate) = $this->getDateByRange($range);
//            if ($startDate && $endDate) {
//                $query->where('users_group.status', $params['status']);
//            }
//        }

        return $query;
    }

    /**
     * @param integer $range
     */
    public function getDateByRange($range = null) {
        $now = Carbon::now();

        $startDate = $now->format('Y-m-d 00:00:00');
        $endDate   = $now->format('Y-m-d 23:59:59');

        if(!$range) {
            return [$startDate, $endDate];
        }

        switch ($range) {
            case GroupConstant::FILTER_BY_DAY:
                $startDate = $now->format('Y-m-d 00:00:00');
                $endDate   = $now->format('Y-m-d 23:59:59');
                break;
            case GroupConstant::FILTER_BY_WEEK:
                $startDate = $now->startOfWeek()->format('Y-m-d 00:00:00');
                $endDate   = $now->endOfWeek()->format('Y-m-d 23:59:59');
                break;
            case GroupConstant::FILTER_BY_MONTH:
                $startDate = $now->startOfMonth()->format('Y-m-d 00:00:00');
                $endDate   = $now->endOfMonth()->format('Y-m-d 23:59:59');
                break;
        }

        return [$startDate, $endDate];
    }

    /**
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|JsonResponse
     */
    public function store(array $data)
    {
        $month = date('m/Y');

        $dataGroup = [
            'name'         => $data['name'],
            'manager_id'   => $data['manager_id'],
            'admin_id'     => implode(',', $data['admin_id']),
            'created_at'   => date('Y-m-d H:i:s')
        ];
        $groupId = $this->_model->insertGetId($dataGroup);

        $dataGroupKpi = [
            'group_id'     => $groupId,
            'month'        => $month,
            'kpi'          => $data['kpi'] ?? GroupConstant::KPI_SALES,
            'kpi_sales'    => $data['kpi_sales'],
            'kpi_services' => $data['kpi_services'],
            'kpi_transports' => $data['kpi_transports'],
            'note'         => $data['note'],
            'created_at'   => date('Y-m-d H:i:s')
        ];
        $this->groupKpi->insert($dataGroupKpi);

        $users = $data['users'];
        $dataUserGroups = [];

        // remove users exist in other group
        $this->removeUserInOtherGroup($users, $month);
        // create new group for users
        $monthKpi     = date('Y-m');
        $realByStaffs = $this->getRealSalesAndServicesByStaffs($users, $monthKpi);

        foreach ($users as $userId) {
            $realSales    = !empty($realByStaffs[$userId]['total_amount']) ? $realByStaffs[$userId]['total_amount'] : 0;
            $realServices = !empty($realByStaffs[$userId]['total_order_fee']) ? $realByStaffs[$userId]['total_order_fee'] : 0;
            $realTransports = !empty($realByStaffs[$userId]['total_transports']) ? $realByStaffs[$userId]['total_transports'] : 0;
            $status       = (
                ($data['kpi_sales'] <= $realSales) && ($data['kpi_services'] <= $realServices)
                && ($data['kpi_transports'] <= $realTransports)
            ) ? GroupConstant::KPI_STATUS_PASS : GroupConstant::KPI_STATUS_FAIL;

            $dataUserGroups[] = [
                'user_id'       => $userId,
                'group_id'      => $groupId,
                'month'         => $month,
                'kpi'           => $data['kpi'] ?? GroupConstant::KPI_SALES,
                'kpi_sales'     => $data['kpi_sales'],
                'kpi_services'  => $data['kpi_services'],
                'kpi_transports'  => $data['kpi_transports'],
                'real_sales'    => $realSales,
                'real_services' => $realServices,
                'real_transports'    => $realTransports,
                'status'        => $status,
                'created_at'    => date('Y-m-d H:i:s')
            ];
        }

        $this->userGroupModel->insert($dataUserGroups);
    }

    /**
     * @param int $id
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|JsonResponse
     */
    public function modifies(int $id, array $data)
    {
        $month = date('m/Y');

        $group = $this->_model->findOrFail($id);

        $dataGroup = [
            //'name'       => $data['name'],
            'manager_id' => $data['manager_id'],
            'admin_id'   => implode(',', $data['admin_id']),
            'created_at' => date('Y-m-d H:i:s')
        ];

        foreach ($dataGroup as $key => $val) {
            $group->{$key} = $val;
        }
        $group->save();

        $dataGroupKpi = [
            //'group_id'     => $group->id,
            //'month'        => $month,
            'kpi'          => $data['kpi'] ?? GroupConstant::KPI_SALES,
            'kpi_sales'    => $data['kpi_sales'],
            'kpi_services' => $data['kpi_services'],
            'kpi_transports' => $data['kpi_transports'],
            'note'         => $data['note'],
            'updated_at'   => date('Y-m-d H:i:s')
        ];
        $this->groupKpi->where([
            ['group_id', $group->id],
        ])->update($dataGroupKpi);

        // delete users old
        $this->userGroupModel->where([
            ['group_id', $group->id], ['month', $month]
        ])->delete();

        $users = $data['users'];

        $monthKpi     = date('Y-m');
        $realByStaffs = $this->getRealSalesAndServicesByStaffs($users, $monthKpi);

        $dataUserGroups = [];
        foreach ($users as $userId) {
            $realSales    = !empty($realByStaffs[$userId]['total_amount']) ? $realByStaffs[$userId]['total_amount'] : 0;
            $realServices = !empty($realByStaffs[$userId]['total_order_fee']) ? $realByStaffs[$userId]['total_order_fee'] : 0;
            $realTransports = !empty($realByStaffs[$userId]['total_transports']) ? $realByStaffs[$userId]['total_transports'] : 0;
            $status       = (
                ($data['kpi_sales'] <= $realSales) && ($data['kpi_services'] <= $realServices)
                && ($data['kpi_transports'] <= $realTransports)
            ) ? GroupConstant::KPI_STATUS_PASS : GroupConstant::KPI_STATUS_FAIL;

            $dataUserGroups[] = [
                'user_id'       => $userId,
                'group_id'      => $group->id,
                'month'         => $month,
                'kpi'           => $data['kpi'] ?? GroupConstant::KPI_SALES,
                'kpi_sales'     => $data['kpi_sales'],
                'kpi_services'  => $data['kpi_services'],
                'kpi_transports'  => $data['kpi_transports'],
                'real_sales'    => $realSales,
                'real_services' => $realServices,
                'real_transports' => $realTransports,
                'status'        => $status,
                'created_at'    => date('Y-m-d H:i:s')
            ];
        }

        $this->userGroupModel->insert($dataUserGroups);
    }

    /**
     * @param UserGroup $userGroup
     * @param array $data
     * @return mixed
     */
    public function edit(UserGroup $userGroup, array $data)
    {
        Log::info(json_encode($data));
        $groupId = $data['group_id'];
        if ($userGroup->group_id != $groupId) {
            $group                = $this->_model->findOrFail($groupId);
            $groupKpi             = optional($group->kpiMonth);
            $data['kpi_services'] = $groupKpi->kpi_services;
            $data['kpi_transports'] = $groupKpi->kpi_transports;
            $data['kpi_sales']    = $groupKpi->kpi_sales;
        }

        $realSales    = $userGroup->real_sales;
        $realServices = $userGroup->real_services;
        $realTransports = $userGroup->real_transports;

        $data['status'] = (
            ($data['kpi_sales'] <= $realSales) && ($data['kpi_services'] <= $realServices)
            && ($data['kpi_transports'] <= $realTransports)
        ) ? GroupConstant::KPI_STATUS_PASS : GroupConstant::KPI_STATUS_FAIL;

        foreach ($data as $key => $val) {
            $userGroup->{$key} = $val;
        }

        $userGroup->save();
    }

    /**
     * @param null|array $columns
     */
    public function getList($columns = null)
    {
        return $this->_model->newQuery()
            ->limit(500)
            ->orderBy("sort_view")
            ->orderBy("id")
            ->get($columns);
    }

    /**
     * @param array $params
     */
    public function getListUser($params)
    {
        $keySearch = data_get($params, 'key_search');
        $type = data_get($params, 'type', UserConstant::LIST_USER_TYPE_STAFF);
        return User::query()
            ->select(['id', 'name', 'code', 'email', 'phone_number'])
            ->where('status', 1)
            // chỉ lấy trạng thái active
            ->whereActive()
            // lọc theo email hoặc sđt
            ->where(function ($q) use ($keySearch) {
                if (!empty($keySearch)) {
                    $q->where('users.email', 'LIKE', '%'.$keySearch.'%')
                        ->orWhere('users.phone_number', 'LIKE', '%'.$keySearch.'%');
                }
            })
            // lọc theo phòng ban
            ->when(
                in_array($type, UserConstant::LIST_USER_TYPE_BUSINESS_AVAILABLE),
                fn ($query) => $query->whereHas(
                    'department',
                    fn ($q) => $q->where('type', DepartmentConstant::TYPE_BUSINESS)
                )
            )
            // các constraint khác
            ->take(ResourceConstant::PER_PAGE_LIST_USER)->get();

        //$query = User::query();
        //$type        = !empty($params['type']) ? $params['type'] : 'staff';
        ////$permissions = $this->getPermissionsByType($type);
        //$query->whereHas('department', function ($role) use ($type) {
        //    if (in_array($type, ['staff', 'manager'])) {
        //        $role->where('type', DepartmentConstant::TYPE_BUSINESS);
        //    }
        //});
        //$query->where('users.status', UserConstant::STATUS_ACTIVE);
        //if (!empty($params['key_search'])) {
        //    $query->where(function ($q) use ($params) {
        //        $q->where('users.email', 'LIKE', '%'.$params['key_search'].'%')
        //            ->orWhere('users.phone_number', 'LIKE', '%'.$params['key_search'].'%');
        //    });
        //}
        //$data = $query->limit(50)
        //    ->selectRaw(DB::raw('DISTINCT(users.id), users.name, users.code, users.email, users.phone_number'))
        //    ->get();
        //return $data;
    }

    public function getPermissionsByType($type) {
        $permissions = [];
        switch ($type) {
            case 'staff':
                $permissions = [RoleConstant::PERMISSION_TAKE_CARE_CUSTOMER, RoleConstant::PERMISSION_CUSTOMER_CONSULTING];
                break;
            case 'manager':
                break;
            case 'admin':
                break;
        }

        return $permissions;
    }

    /**
     * @param array $params
     */
    public function updateCustomerForUser($params)
    {
        return $this->userGroupModel->whereIn('id', $params['ids'])->update(['customer_id' => $params['customer_id']]);
    }

    /**
     * @param array $params
     */
    public function getListCustomer($params)
    {
        if (empty($params['key_search'])) {
            return [];
        }

        return Customer::query()
            ->where(function ($q) use ($params) {
                $q->where('name', 'LIKE', '%'.$params['key_search'].'%')
                    ->orWhere('phone_number', 'LIKE', '%'.$params['key_search'].'%');
            })
            ->limit(50)
            ->get(['name', 'id', 'code'])
            ;
    }

    /**
     * @param array $params
     */
    public function changeStatusGetCustomer($params)
    {
        if (empty($params['ids']) || !count($params['ids']) || !isset($params['status'])) {
            return;
        }

        return $this->userGroupModel->whereIn('id', $params['ids'])->update(['is_get_customer' => $params['status']]);
    }

    /**
     * set staff for customer
     */
    public function setStaffForCustomer(Customer $customer)
    {
        // set staff for customer
        $staffId = $customer->staff_group_id;
        if (!$staffId) {
            $staffId = $this->setStaffCustomer($customer);
//            if ($staffId) {
//                $this->incrementTotalCustomerByStaffId($staffId);
//            }
        }
    }

    public function setStaffCustomer(Customer $customer)
    {
        $staffId = '';

        $staffNoCustomer = $this->getStaffForCustomer(false);
        if ($staffNoCustomer) {
            $staff = $staffNoCustomer;
        } else {
            $staff = $this->getStaffForCustomer(true);
        }

        if ($staff) {
            $staffId                  = $staff->id;
            $customer->staff_group_id = $staffId;
            $customer->staff_group_time_assign = date('Y-m-d H:i:s');
            $customer->save();
        }

        return $staffId;
    }


    /**
     * get staff for customer
     * @param bool $hasCustomer
     */
    public function getStaffForCustomer($hasCustomer = false) {
        $month = date('m/Y');

        $query = User::query();

        $query->selectRaw(DB::raw('DISTINCT(users.id)'));
        $query->leftJoin('users_group', 'users.id', '=', 'users_group.user_id');
        if (!$hasCustomer) {
            $query->leftJoin('customers', 'users.id', '=', 'customers.staff_group_id');
        } else {
            $query->join('customers', 'users.id', '=', 'customers.staff_group_id');
        }

        $query->whereHas('department', function($role) {
            $role->where('type', DepartmentConstant::TYPE_BUSINESS);
        });

        $query->where('users.status', UserConstant::STATUS_ACTIVE);

        if (!$hasCustomer) {
            $query->whereRaw(DB::raw(('((customers.staff_group_id IS NULL) AND
                ((`users_group`.`user_id` IS NULL) OR (`users_group`.`is_get_customer` = 1 AND `users_group`.`month` = "'. $month .'" AND `users_group`.`user_id` IS NOT NULL)))'
            )));
        } else {
            $query->whereRaw(DB::raw(('((customers.staff_group_id IS NOT NULL) AND
                ((`users_group`.`user_id` IS NULL) OR (`users_group`.`is_get_customer` = 1 AND `users_group`.`month` = "'. $month .'" AND `users_group`.`user_id` IS NOT NULL)))'
            )));
        }

        if ($hasCustomer) {
            $query->orderBy('users.total_customers', 'ASC');
        }

        $query->orderBy('users.created_at', 'DESC');

        $staff = $query->first();

        return $staff;
    }

    /**
     * change staff for customer
     * @param string $staffId
     */
    public function changeStaffCustomerRelation(Customer $customer)
    {
        try {
            $currentStaff = $customer->staff_group_id;
            $oldStaff     = $customer->getOriginal('staff_group_id');

            if ($currentStaff && (!$oldStaff || ($currentStaff != $oldStaff))) {
                // increment customer for new staff
                $this->incrementTotalCustomerByStaffId($currentStaff);
                // decrement customer for old staff
                if ($oldStaff) {
                    $this->decrementTotalCustomerByStaffId($oldStaff);
                }
                // change staff for order
                $this->changeStaffOrder($customer, $currentStaff);
                // change staff for package consignment
                $customer->packages()
                    ->whereNotIn('status', [
                        PackageConstant::STATUS_CANCEL,
                        PackageConstant::STATUS_RECEIVED_GOODS,
                    ])
                    ->update(['staff_group_id' => $currentStaff]);
                // Thêm log chuyển nhân viên
//                $logService = \App::make('App\Services\ActivityService');
//                $content    = 'Cập nhật nhân viên quản lý';
//                $logService->activitiesCustomer($customer);
            }
        } catch (\Exception $e) {
            Log::error('change staff customer('. $customer->name.') error: '. $e->getMessage());
        }
    }

    public function changeStaffOrder(Customer $customer, $staffId)
    {
        $orders = Order::query()
            ->where('status', '!=', OrderConstant::KEY_STATUS_DONE)
            ->where('customer_id', $customer->id)
            ->get();
        $orderService = app()->make(OrderService::class);
        $onlyStaffGroup = true;
        foreach ($orders as $order) {
            $order->staff_group_id = $staffId;
            $order->save();
            // send noti change order for staff care, counselor
            $orderService->sendNotiOrder($order, $onlyStaffGroup);
        }
    }

    /**
     * get staff for order
     */
    public function getStaffForOrder(Customer $customer)
    {
        // set staff for customer
        $staffId = $customer->staff_group_id;
        if ($staffId && optional($customer->staffGroup)->is_get_customer) {
            return $staffId;
        }

        $staffNoCustomer = $this->getStaffForCustomer(false);
        if ($staffNoCustomer) {
            $staff = $staffNoCustomer;
        } else {
            $staff = $this->getStaffForCustomer(true);
        }

        return $staff->id;
    }

    /**
     * @param array $staffIds
     * @param string $month
     */
    public function removeUserInOtherGroup(array $staffIds, string $month)
    {
        return $this->userGroupModel->whereIn('user_id', $staffIds)
            ->where('month', $month)
            ->delete();
    }

    /**
     * @param array $staffIds
     * @param string $month
     */
    public function getRealSalesAndServicesByStaffs(array $staffIds, string $month)
    {
        $data = [];

        $startDate = date('Y-m-01 00:00:00', strtotime($month));
        $endDate   = date('Y-m-t 23:59:59', strtotime($month));

        $query = Order::query();

        $query->select(
            DB::raw('
                staff_group_id,
                SUM(
                    ((china_shipping_cost * exchange_rate) + inspection_cost + order_cost + order_fee) - discount_cost
                ) AS totalOrderAmount,
                SUM( order_fee ) AS totalOrderFee
            '))
            ->where('date_ordered', '>=', $startDate)
            ->where('date_ordered', '<=', $endDate)
            ->whereIn('staff_group_id', $staffIds)
            ->groupBy('staff_group_id');

        $reals = $query->get();

        if ($reals->isNotEmpty()) {
            foreach ($reals as $item) {
                $data[$item->staff_group_id] = [
                    'total_amount'    => roundMoney(floatval($item->totalOrderAmount)),
                    'total_order_fee' => floatval($item->totalOrderFee),
                ];
            }
        }

        /*$realConsignment = OrderPackage::query();
        $realConsignment->select(
            DB::raw('
                staff_group_id,
                SUM( international_shipping_cost ) AS totalTransport
            '))
            ->where('date_ordered', '>=', $startDate)
            ->where('date_ordered', '<=', $endDate)
            ->whereIn('staff_group_id', $staffIds)
            ->groupBy('staff_group_id');
        $realConsignments = $realConsignment->get();*/


        $realConsignments = OrderPackage::query()
            ->withoutGlobalScopes()
            ->from('order_package as op')
            ->join('order_package_status_times as opst', function ($join) {
                $join->on('op.id', '=', 'opst.order_package_id')
                    ->where('opst.key', PackageConstant::STATUS_RECEIVED_GOODS);
            })
            ->select(DB::raw('
                op.staff_group_id,
                SUM(op.international_shipping_cost) AS totalTransport
            '))
            ->whereBetween('opst.updated_at', [$startDate, $endDate])
            ->whereIn('op.staff_group_id', $staffIds)
            ->whereNull('op.deleted_at')
            ->groupBy('op.staff_group_id')
            ->reorder()
            ->get();


        if ($realConsignments->isNotEmpty()) {
            foreach ($realConsignments as $item) {
                if (!isset($data[$item->staff_group_id])) {
                    $data[$item->staff_group_id] = [];
                }

                $data[$item->staff_group_id]['total_transports'] = roundMoney(floatval($item->totalTransport));
            }
        }

        return $data;
    }

    /**
     * update kpi for staff
     *
     */
    public function updateKpiStaff(Order $order)
    {
        $month     = date('m/Y', strtotime($order->date_ordered));
        $staffId   = $order->staff_group_id;
        $condition = [
            'user_id' => $staffId,
            'month'   => $month
        ];

        $userGroup = $this->userGroupModel->where($condition)->first();
        if ($userGroup) {
            $totalAmount  = $order->total_amount;
            $orderFee     = $order->order_fee;

            $realSales    = $userGroup->real_sales;
            $realServices = $userGroup->real_services;
            $newSales     = $realSales + roundMoney($totalAmount);
            $newServices  = $realServices + $orderFee;
            $realTransports = $userGroup->real_transports;
            $kpiTransports = $userGroup->kpi_transports;

            $kpi         = $userGroup->kpi;
            $kpiSales    = $userGroup->kpi_sales;
            $kpiServices = $userGroup->kpi_services;

            $status = ($newSales >= $kpiSales && $newServices >= $kpiServices && $realTransports >= $kpiTransports)
                ? GroupConstant::KPI_STATUS_PASS
                : GroupConstant::KPI_STATUS_FAIL;

            $userGroup->real_sales    = $newSales;
            $userGroup->real_services = $newServices;
            $userGroup->status        = $status;
            $userGroup->total_orders  = $userGroup->total_orders + 1;
            $userGroup->updated_at    = date('Y-m-d H:i:s');
            $userGroup->save();
        }
    }

    public function updateKpiStaffForOrderPackage(OrderPackage $orderPackage, $status)
    {
        $month     = date('m/Y', strtotime($orderPackage->date_done));
        $staffId   = $orderPackage->staff_group_id;
        $condition = [
            'user_id' => $staffId,
            'month'   => $month
        ];

        $userGroup = $this->userGroupModel->where($condition)->first();
        if ($userGroup) {
            $realSales = $userGroup->real_transports;
            $kpiSales = $userGroup->kpi_sales;
            $realServices = $userGroup->real_services;
            $kpiServices = $userGroup->kpi_services;
            $realTransports = $userGroup->real_transports;
            $kpiTransports = $userGroup->kpi_transports;
            // Nếu chuyển trạng thái sang đã nhận hàng thì tính, còn k thì phải trừ
            if($status == PackageConstant::STATUS_RECEIVED_GOODS) {
                $newTransports  = $realTransports + $orderPackage->international_shipping_cost;
            } else {
                $newTransports  = $realTransports - $orderPackage->international_shipping_cost;
            }
            $status = ($realSales >= $kpiSales && $realServices >= $kpiServices && $newTransports >= $kpiTransports)
                ? GroupConstant::KPI_STATUS_PASS
                : GroupConstant::KPI_STATUS_FAIL;
            $userGroup->status        = $status;
            $userGroup->updated_at    = date('Y-m-d H:i:s');
            $userGroup->real_transports = $newTransports;
            $userGroup->save();
        }
    }


    /**
     * generate group kpi monthly
     */
    public function generateGroupKpiMonthly()
    {
        try {
            DB::beginTransaction();

            $month = request()->get('month') ? request()->get('month') : date('m/Y');
            $lastMonth = Carbon::now()->subMonth()->endOfMonth()->format('m/Y');

            $userGroups = UserGroup::query()
                ->where('month', $lastMonth)
                ->get();
            $dataUserGroups = [];

            foreach ($userGroups as $userGroup) {

                $dataUserGroups[] = [
                    'user_id'       => $userGroup->user_id,
                    'group_id'      => $userGroup->group_id,
                    'month'         => $month,
                    'kpi'           => GroupConstant::KPI_SALES,
                    'kpi_sales'     => $userGroup->kpi_sales,
                    'kpi_services'  => $userGroup->kpi_services,
                    'kpi_transports'  => $userGroup->kpi_transports,
                    'status'        => GroupConstant::KPI_STATUS_FAIL
                ];
            }
            foreach($dataUserGroups as $user) {
                $this->userGroupModel->firstOrCreate([
                    'user_id' => $user['user_id'],
                    'group_id' => $user['group_id'],
                    'month' => $user['month'],
                ], $user);
            }

            DB::commit();
            Log::info('Run sync group kpi success');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Generate kpi group monthly failed: ' . $e->getMessage());
        }
    }

    /**
     * @param string $staffGroupId
     */
    public function incrementTotalOrderByStaffId(string $staffGroupId)
    {
        $month     = date('m/Y');
        $userGroup = $this->userGroupModel->withoutGlobalScopes()
            ->where([
                ['user_id', $staffGroupId], ['month', $month]
            ])
            ->first();

        if ($userGroup) {
            $userGroup->total_orders += 1;
            $userGroup->save();
        }
    }

    /**
     * @param string $staffGroupId
     */
    public function decrementTotalOrderByStaffId(string $staffGroupId)
    {
        $month     = date('m/Y');
        $userGroup = $this->userGroupModel->withoutGlobalScopes()
            ->where([
                ['user_id', $staffGroupId], ['month', $month]
            ])
            ->first();

        if ($userGroup) {
            $userGroup->total_orders = $userGroup->total_orders > 0 ? $userGroup->total_orders - 1 : 0;
            $userGroup->save();
        }
    }

    /**
     * @param string $staffGroupId
     */
    public function incrementTotalCustomerByStaffId(string $staffGroupId)
    {
        $month     = date('m/Y');
        $userGroup = $this->userGroupModel->withoutGlobalScopes()
            ->where([
                ['user_id', $staffGroupId], ['month', $month]
            ])
            ->first();

        if ($userGroup) {
            $userGroup->total_customers += 1;
            $userGroup->save();
        }

        $userStaff = User::find($staffGroupId);
        if ($userStaff) {
            $userMaxTotalCustomer = $this->getUserMaxTotalCustomer();
            if ($userMaxTotalCustomer->max > 1 && $userStaff->total_customers == 0) { // nv mới đã quá 1 vòng
                $userStaff->total_customers = $userMaxTotalCustomer->max;
            } else {
                $userStaff->total_customers += 1;
            }

            $userStaff->save();
        }
    }

    public function getUserMaxTotalCustomer()
    {
        return User::query()->selectRaw(DB::raw('id, name, MAX(total_customers) as max'))->first();
    }

    /**
     * @param string $staffGroupId
     */
    public function decrementTotalCustomerByStaffId(string $staffGroupId)
    {
        $month     = date('m/Y');
        $userGroup = $this->userGroupModel->withoutGlobalScopes()
            ->where([
                ['user_id', $staffGroupId], ['month', $month]
            ])
            ->first();

        if ($userGroup) {
            $userGroup->total_customers = $userGroup->total_customers > 0 ? $userGroup->total_customers - 1 : 0;
            $userGroup->save();
        }

        $userStaff = User::find($staffGroupId);
        if ($userStaff) {
            $userStaff->total_customers = $userStaff->total_customers > 0 ? $userStaff->total_customers - 1 : 0;
            $userStaff->save();
        }
    }

    /**
     * reset total customer for staff group
     */
    public function resetTotalCustomer()
    {
        return User::query()->update(['total_customers' => 0]);
    }

    public function assignUserToGroup($userId, $groupId)
    {
        $month = date('m/Y');

        // 1. Lấy group + KPI
        $group = Group::with('kpiMonth')->find($groupId);

        if (!$group || !$group->kpiNearest) {
            throw new \Exception('Group chưa có KPI');
        }

        $kpi = $group->kpiNearest;

        // 2. remove khỏi group khác
        $this->removeUserInOtherGroup([$userId], $month);

        // 3. lấy real data
        $monthKpi     = date('Y-m');
        $realByStaffs = $this->getRealSalesAndServicesByStaffs([$userId], $monthKpi);

        $realSales = $realByStaffs[$userId]['total_amount'] ?? 0;
        $realServices = $realByStaffs[$userId]['total_order_fee'] ?? 0;
        $realTransports = $realByStaffs[$userId]['total_transports'] ?? 0;

        // 4. status
        $status = (
            ($kpi->kpi_sales <= $realSales) &&
            ($kpi->kpi_services <= $realServices) &&
            ($kpi->kpi_transports <= $realTransports)
        ) ? GroupConstant::KPI_STATUS_PASS : GroupConstant::KPI_STATUS_FAIL;

        // 5. insert/update user_group
        $this->userGroupModel->updateOrInsert(
            [
                'user_id' => $userId,
                'month'   => $month
            ],
            [
                'group_id'       => $groupId,
                'kpi'            => $kpi->kpi,
                'kpi_sales'      => $kpi->kpi_sales,
                'kpi_services'   => $kpi->kpi_services,
                'kpi_transports' => $kpi->kpi_transports,
                'real_sales'     => $realSales,
                'real_services'  => $realServices,
                'real_transports'=> $realTransports,
                'status'         => $status,
                'created_at'     => now()
            ]
        );
    }
}
