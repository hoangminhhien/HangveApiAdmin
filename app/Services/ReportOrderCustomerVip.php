<?php

namespace App\Services;

use App\Constants\OrderConstant;
use App\Jobs\ReportOrderCustomerVipManyJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Group;
use App\Models\Label;
use App\Models\ReportCustomerVipOrder;
use App\Models\User;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Constants\CustomerConstant;

class ReportOrderCustomerVip
{
    function __construct()
    {

    }

    public function getWeekReport(): array
    {
        $listDates = CalculatorWeekReport::calculatorDateReportCustomerVip();
        $strListDates = [];
        foreach ($listDates as $listDate) {
            $strListDates[] = implode(' đến ', $listDate);
        }
        return array_reverse($strListDates);
    }

    public function getGroup(){
        return Group::query()->select('id', 'name')->get();
    }

    private function getLabel($params): array
    {
        if (empty($params['code'])) {
            return [];
        }
        $query = Label::query();
        return $query->select('id')
            ->whereIn('code', $params['code'])
            ->pluck('id')
            ->toArray();
    }

    /**
     * @throws BindingResolutionException
     */
    public function cronjobInsertData($request = []): bool
    {
        $currentDate = date('Y-m-d');
        if (!empty($request['date'])) {
            $currentDate = date('Y-m-d', strtotime($request['date']));
        }
        $listDates = CalculatorWeekReport::calculatorDateReportCustomerVip([]);
        $start_date = '';
        $end_date = '';
        foreach ($listDates as $listDate) {
            if (
                strtotime($listDate[1]) >= strtotime($currentDate)
                && strtotime($listDate[0]) <= strtotime($currentDate)
            ) {
                $start_date = $listDate[0]. ' 00:00:00';
                $end_date = $listDate[1]. ' 23:59:59';
                break;
            }
        }
        $level = $this->getLevel();
        $user_group_ids = $this->getUserGroup();
        $weekStartDate = $start_date;
        $weekEndDate = $end_date;
        $label_order_new = $this->getLabel(['code' => ['THAM_KHAO', 'DANG_FOLLOW', 'DA_LEN_DON', 'DA_DAT_HANG']]);
        $label_order = $this->getLabel(['code' => ['THAM_KHAO', 'DANG_FOLLOW']]);
        $label_ordered = $this->getLabel(['code' => ['DA_DAT_HANG']]);
        $this->processData(
            $weekStartDate,
            $weekEndDate,
            $user_group_ids,
            $label_order,
            $label_order_new,
            $label_ordered,
            $level
        );
        return true;
    }

    /**
     * @throws BindingResolutionException
     */
    public function processData(
        $weekStartDate,
        $weekEndDate,
        $user_group_ids,
        $label_order,
        $label_order_new,
        $label_ordered,
        $level
    ): void
    {
        $formatLevel = $this->formatLevel($level, $user_group_ids, $weekStartDate, $weekEndDate) ;
        $totalCustomerOrder = $this->getTotalCustomer(
            $weekStartDate,
            $weekEndDate,
            $user_group_ids,
            $label_order
        );
        $totalCustomerOrdered = $this->getTotalCustomerOrdered(
            $weekStartDate,
            $weekEndDate,
            $user_group_ids
        );
        $customerOrderedIds = [];
        foreach ($totalCustomerOrdered as $item) {
            $customerOrderedIds[] = $item->id;
        }
        $totalCustomerOrderNew = $this->getTotalCustomerNew(
            $weekStartDate,
            $weekEndDate,
            $user_group_ids,
            $label_order_new
        );
        $totalCustomerOrderedNew = $this->getTotalCustomerNew(
            $weekStartDate,
            $weekEndDate,
            $user_group_ids,
            $label_ordered
        );
        $customerOrderedNewIds = [];
        foreach ($totalCustomerOrderedNew as $item) {
            $customerOrderedNewIds[] = $item->id;
        }
        $totalOrder = $this->getTotalOrder(
            $weekStartDate,
            $weekEndDate,
            $user_group_ids,
            false,
            $customerOrderedIds
        );
        $totalOrderNew = $this->getTotalOrder(
            $weekStartDate,
            $weekEndDate,
            $user_group_ids,
            true,
            $customerOrderedNewIds
        );
        $data = $this->mappingData(
            $totalCustomerOrder,
            $totalCustomerOrdered,
            $totalCustomerOrderNew,
            $totalCustomerOrderedNew,
            $totalOrder,
            $totalOrderNew,
            $formatLevel
        );
        $this->insert($data);
    }

    public function cronjobInsertDataMany($request = []): bool
    {
        $level = $this->getLevel();
        $user_group_ids = $this->getUserGroup();
        $label_order_new = $this->getLabel(['code' => ['THAM_KHAO', 'DANG_FOLLOW', 'DA_LEN_DON', 'DA_DAT_HANG']]);
        $label_order = $this->getLabel(['code' => ['THAM_KHAO', 'DANG_FOLLOW']]);
        $label_ordered = $this->getLabel(['code' => ['DA_DAT_HANG']]);
        $not_group_id = !empty($request['not_group_id']) ? $request['not_group_id'] : '';
        $listDates = CalculatorWeekReport::calculatorDateReportCustomerVip([]);
        foreach ($listDates as $listDate) {
            $saturday = $listDate[0] . ' 00:00:00';
            $friday = $listDate[1] . ' 23:59:59';
            $weekStartDate = $saturday;
            $weekEndDate = $friday;
            $jobs = new ReportOrderCustomerVipManyJob(
                $weekStartDate,
                $weekEndDate,
                $user_group_ids,
                $label_order,
                $label_order_new,
                $label_ordered,
                $level,
                $not_group_id
            );
            dispatch($jobs)->onQueue('sync-data-report');
        }
        return true;
    }


    private function mappingData(
        $totalCustomerOrder,
        $totalCustomerOrdered,
        $totalCustomerOrderNew,
        $totalCustomerOrderedNew,
        $totalOrder,
        $totalOrderNew,
        $formatLevel
    ): array
    {
        foreach ($formatLevel as $keyLevel => &$levels) {
            foreach ($levels as $keySource => &$sources) {
                foreach ($sources as $keyStaff => &$staff) {
                    foreach ($totalCustomerOrder['customerCreateOrder'] as $customerCreateOrder) {
                        if ($keyLevel == $customerCreateOrder->level_customer 
                            && $keySource == $customerCreateOrder->source
                            && $keyStaff == $customerCreateOrder->staff_group_id
                        ) {
                            if(array_key_exists($customerCreateOrder->staff_group_id, $sources)) {
                                $sources[$customerCreateOrder->staff_group_id]['total_customer_order'] += 1;
                            }else {
                                $sources[0]['total_customer_order'] += 1;
                            }
                        }
                    }
                    foreach ($totalCustomerOrder['newCustomer'] as $newCustomer) {
                        if ($keyLevel == $newCustomer->level_customer 
                            && $keySource == $newCustomer->source
                            && $keyStaff == $newCustomer->staff_group_id
                        ) {
                            if(array_key_exists($newCustomer->staff_group_id, $sources)) {
                                $sources[$newCustomer->staff_group_id]['total_customer_order'] += 1;
                            }else {
                                $sources[0]['total_customer_order'] += 1;
                            }
                        }
                    }
                    foreach ($totalCustomerOrdered as $customer) {
                        if ($keyLevel == $customer->level_customer 
                            && $keySource == $customer->source
                            && $keyStaff == $customer->staff_group_id
                        ) {
                            if(array_key_exists($customer->staff_group_id, $sources)) {
                                $sources[$customer->staff_group_id]['total_customer_ordered'] += 1;
                            }else {
                                $sources[0]['total_customer_ordered'] += 1;
                            }
                        }
                    }
                    foreach ($totalCustomerOrderNew as $customer) {
                        if ($keyLevel == $customer->level_customer 
                            && $keySource == $customer->source
                            && $keyStaff == $customer->staff_group_id
                        ) {
                            if(array_key_exists($customer->staff_group_id, $sources)) {
                                $sources[$customer->staff_group_id]['total_customer_order_new'] += 1;
                            }else {
                                $sources[0]['total_customer_order_new'] += 1;
                            }
                        }
                    }
                    foreach ($totalCustomerOrderedNew as $customer) {
                        if ($keyLevel == $customer->level_customer 
                            && $keySource == $customer->source
                            && $keyStaff == $customer->staff_group_id
                        ) {
                            if(array_key_exists($customer->staff_group_id, $sources)) {
                                $sources[$customer->staff_group_id]['total_customer_ordered_new'] += 1;
                            }else {
                                $sources[0]['total_customer_ordered_new'] += 1;
                            }
                        }
                    }
                    foreach ($totalOrder as $order) {
                        if ($keyLevel == $order->level_customer 
                            && $keySource == $order->source
                            && $keyStaff == $order->staff_group_id
                        ) {
                            if(array_key_exists($order->staff_group_id, $sources)) {
                                $sources[$order->staff_group_id]['total_order'] += 1;
                                $sources[$order->staff_group_id]['total_price'] += $order->order_cost;
                                $sources[$order->staff_group_id]['total_fee'] += $order->order_fee;
                            }else {
                                $sources[0]['total_order'] += 1;
                                $sources[0]['total_price'] += $order->order_cost;
                                $sources[0]['total_fee'] += $order->order_fee;
                            }
                        }
                    }
                    foreach ($totalOrderNew as $order) {
                        if ($keyLevel == $order->level_customer 
                            && $keySource == $order->source
                            && $keyStaff == $order->staff_group_id
                        ) {
                            if(array_key_exists($order->staff_group_id, $sources)) {
                                $sources[$order->staff_group_id]['total_order_new'] += 1;
                                $sources[$order->staff_group_id]['total_price_new'] += $order->order_cost;
                                $sources[$order->staff_group_id]['total_fee_new'] += $order->order_fee;
                            }else {
                                $sources[0]['total_order_new'] += 1;
                                $sources[0]['total_price_new'] += $order->order_cost;
                                $sources[0]['total_fee_new'] += $order->order_fee;
                            }
                        }
                    }
                }
            }
        }

        return $formatLevel;
    }

    private function formatLevel($level, $groups,  $saturday, $friday): array
    {
        $sources = $this->getSource();
        $formatLevel = [];
        foreach ($level as $key => $lv) {
            foreach ($groups as $group) {
                foreach ($sources as $keySource => $source) {
                    $formatLevel[$key][$keySource][$group]['name'] = $lv;
                    $formatLevel[$key][$keySource][$group]['level'] = $key;
                    $formatLevel[$key][$keySource][$group]['source'] = $keySource;
                    $formatLevel[$key][$keySource][$group]['staff_group_ids'] = $group;
                    $formatLevel[$key][$keySource][$group]['total_customer_order'] = 0;
                    $formatLevel[$key][$keySource][$group]['total_customer_ordered'] = 0;
                    $formatLevel[$key][$keySource][$group]['total_customer_order_new'] = 0;
                    $formatLevel[$key][$keySource][$group]['total_customer_ordered_new'] = 0;
                    $formatLevel[$key][$keySource][$group]['total_order'] = 0;
                    $formatLevel[$key][$keySource][$group]['total_order_new'] = 0;
                    $formatLevel[$key][$keySource][$group]['total_price'] = 0;
                    $formatLevel[$key][$keySource][$group]['total_price_new'] = 0;
                    $formatLevel[$key][$keySource][$group]['total_fee'] = 0;
                    $formatLevel[$key][$keySource][$group]['total_fee_new'] = 0;
                    $formatLevel[$key][$keySource][$group]['start_date'] = $saturday;
                    $formatLevel[$key][$keySource][$group]['end_date'] = $friday;
                    // Những KH nào mà nhân viên quản lý không có trong nhóm kinh doanh thì cho hết vào staff_group_id = 0
                    $formatLevel[$key][$keySource][0]['name'] = $lv;
                    $formatLevel[$key][$keySource][0]['level'] = $key;
                    $formatLevel[$key][$keySource][0]['source'] = $keySource;
                    $formatLevel[$key][$keySource][0]['staff_group_ids'] = 0;
                    $formatLevel[$key][$keySource][0]['total_customer_order'] = 0;
                    $formatLevel[$key][$keySource][0]['total_customer_ordered'] = 0;
                    $formatLevel[$key][$keySource][0]['total_customer_order_new'] = 0;
                    $formatLevel[$key][$keySource][0]['total_customer_ordered_new'] = 0;
                    $formatLevel[$key][$keySource][0]['total_order'] = 0;
                    $formatLevel[$key][$keySource][0]['total_order_new'] = 0;
                    $formatLevel[$key][$keySource][0]['total_price'] = 0;
                    $formatLevel[$key][$keySource][0]['total_price_new'] = 0;
                    $formatLevel[$key][$keySource][0]['total_fee'] = 0;
                    $formatLevel[$key][$keySource][0]['total_fee_new'] = 0;
                    $formatLevel[$key][$keySource][0]['start_date'] = $saturday;
                    $formatLevel[$key][$keySource][0]['end_date'] = $friday;                }
            }

        }
        return $formatLevel;
    }


    private function insert($data): void
    {
        try {

            foreach ($data as $keyLevel => $levels) {
                foreach ($levels as $keySource => $sources) {
                    foreach ($sources as $keyStaff => $staff) {
                        ReportCustomerVipOrder::query()
                            ->updateOrCreate(
                                [
                                    'level' => $keyLevel,
                                    'source' => $keySource,
                                    'staff_group_ids' => $keyStaff,
                                    'start_date' => $staff['start_date'],
                                    'end_date' => $staff['end_date']
                                ],
                                $staff);
                    }
                }
            }

            return;
        } catch (\Exception $e) {
            Log::info('insert data report customer vip order fails ' . $e->getMessage());
            return;
        }

    }

    public function delete($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return ReportCustomerVipOrder::query()->where('week', $list->week)
            ->where('year', $list->year)
            ->where('month', $list->month)
            ->delete();
    }

    private function getUserGroup($group_id = false, $options = []): array
    {
        $query = User::query();
        $query = $query->whereExists(function ($query) use ($group_id, $options) {
                $query->select("users_group.id")
                    ->from('users_group')
                    ->whereRaw('users_group.user_id = users.id');
                if ($group_id) {
                    $query->where('users_group.group_id', $group_id);
                }
                if (!empty($options['month'])) {
                    if (is_array($options['month'])) {
                        $query->whereIn('users_group.month', $options['month']);
                    } else {
                        $query->where('users_group.month', $options['month']);
                    }
                }
            })
            ->where('status', 1)
            /*->where('users.id', 463)*/
            ->select('id');
            return $query->pluck('id')
                ->toArray();
    }

    /**
     * @throws BindingResolutionException
     */
    private function getTotalCustomer(
        $weekStartDate,
        $weekEndDate,
        $user_group_ids,
        $label_ids = []
    ): array
    {
        $query = Customer::query();
        $query = $query->select
        (
            DB::raw('DISTINCT customers.id, customers.level as level_customer, customers.staff_group_id, customers.source')
        )
        ->where('customers.status', 1)
        ->where('customers.level', '>', 0);
        $query->join('orders', function($join) use ($weekStartDate, $weekEndDate){
            $join->on('orders.customer_id', '=', 'customers.id')
                ->whereBetween('orders.created_at', [$weekStartDate, $weekEndDate]);
            }
        );
        $results = $query->get();
        $queryWithLabel = Customer::query();
        $queryWithLabel = $queryWithLabel->select
        (
            DB::raw('DISTINCT customers.id, customers.level as level_customer, customers.staff_group_id, customers.source')
        )
            ->where('customers.status', 1)
            ->where('customers.level', '>', 0);
        $queryWithLabel->whereBetween('customers.created_at', [$weekStartDate, $weekEndDate]);
        $queryWithLabel->join('customer_label', function($join) use ($label_ids, $weekStartDate, $weekEndDate){
            $join->on('customer_label.customer_id', '=', 'customers.id')
                ->whereIn('customer_label.label_id', $label_ids);
                /*->whereBetween('customer_label.created_at', [$weekStartDate, $weekEndDate]);*/
            }
        );
        return [
            'customerCreateOrder' => $results,
            'newCustomer' => $queryWithLabel->get()
        ];
    }
    /* Tổng KH đã đặt hàng */
    private function getTotalCustomerOrdered($weekStartDate, $weekEndDate, $user_group_ids)
    {
        $query = Customer::query();
        $query = $query->select
        (
            DB::raw('DISTINCT customers.id, customers.level as level_customer, customers.staff_group_id, customers.source')
        )
            ->where('customers.status', 1)
            ->join('orders', function($join) use ($weekStartDate, $weekEndDate){
                $join->on('orders.customer_id', '=', 'customers.id')
                    ->whereBetween('orders.created_at', [$weekStartDate, $weekEndDate])
                    ->whereIn('orders.status', [
                        OrderConstant::KEY_STATUS_DEPOSITED,
                        OrderConstant::KEY_STATUS_ORDERED,
                        OrderConstant::KEY_STATUS_WAIT_TO_PAY
                    ]);
                }
            );

        return $query->get();
    }

    /**
     * @throws BindingResolutionException
     */
    private function getTotalCustomerNew($weekStartDate, $weekEndDate, $user_group_ids, $label_ids = [], $not_group_id = '')
    {
        $query = Customer::query();
        $query = $query->select
        (
            DB::raw('DISTINCT customers.id, customers.level as level_customer, customers.staff_group_id, customers.source')
        )
            ->where('customers.status', 1)
            ->join('customer_label', function($join) use ($label_ids){
                $join->on('customer_label.customer_id', '=', 'customers.id')
                    ->whereIn('customer_label.label_id', $label_ids);
                }
            )
            ->where('customers.level', '>', 0)
            ->whereBetween('customers.created_at', [$weekStartDate, $weekEndDate]);
        return $query->get();
    }

    private function getTotalOrder(
        $weekStartDate,
        $weekEndDate,
        $user_group_ids,
        $customer_new = false,
        $customerOrderedIds = []
    )
    {
        $query = Order::query();
        $query = $query->select
        (
            DB::raw('DISTINCT orders.id, customers.level as level_customer, customers.staff_group_id, orders.order_cost, orders.inspection_cost, orders.international_shipping_cost, orders.order_fee, orders.china_shipping_cost, orders.exchange_rate, orders.discount_cost, customers.source')
        )
        ->join('customers','orders.customer_id','=','customers.id')
        ->whereNull('orders.deleted_at')
        ->where('customers.status', 1)
        ->where('customers.level', '>', 0)
        ->whereBetween('orders.created_at', [$weekStartDate, $weekEndDate]);
        if ($customer_new) {
            $query->whereBetween('customers.created_at', [$weekStartDate, $weekEndDate]);
        } else {
            $query->whereIn('orders.status', [
                OrderConstant::KEY_STATUS_DEPOSITED,
                OrderConstant::KEY_STATUS_ORDERED,
                OrderConstant::KEY_STATUS_WAIT_TO_PAY
            ]);
        }
        if (!empty($customerOrderedIds)) {
            $query->whereIn('orders.customer_id', $customerOrderedIds);
        }
       return $query->get();
    }

    private function getLevel(): array
    {
        return [
            '1' => 'Cấp độ 1',
            '2' => 'Cấp độ 2',
            '3' => 'Cấp độ 3',
            '4' => 'Cấp độ 4',
            '5' => 'Cấp độ 5',
        ];
    }

    private function getSource(): array
    {
        return CustomerConstant::CUSTOMER_SOURCES;
    }

    public function getReport($request): array
    {
        $group_id = $request['group_id'] ?? '';
        $time = $request['time'] ?? [];
        $source = $request['source'] ?? '';
        $start_date = '';
        $end_date = '';
        if (!empty($time)) {
            $time = explode(' đến ', $time);
            $start_date = $time[0]. ' 00:00:00';
            $end_date = $time[1]. ' 23:59:59';
        } else {
            $currentDate = date('Y-m-d');
            $listDates = CalculatorWeekReport::calculatorDateReportCustomerVip([]);
            foreach ($listDates as $listDate) {
                if (
                    strtotime($listDate[1]) >= strtotime($currentDate)
                    && strtotime($listDate[0]) <= strtotime($currentDate)
                ) {
                    $start_date = $listDate[0]. ' 00:00:00';
                    $end_date = $listDate[1]. ' 23:59:59';
                    break;
                }
            }
        }
        $month_start = date('m/Y', strtotime($start_date));
        $month_end = date('m/Y', strtotime($end_date));
        $user_group_ids = $this->getUserGroup($group_id, ['month' => [$month_start, $month_end]]);
        Log::info('bao cao vip ' . json_encode($user_group_ids));

        $reports = ReportCustomerVipOrder::query()
            ->select(
                'name as name',
                DB::raw('SUM(total_customer_order) as total_customer_order'),
                DB::raw('SUM(total_customer_ordered) as total_customer_ordered'),
                DB::raw('SUM(total_customer_order_new) as total_customer_order_new'),
                DB::raw('SUM(total_customer_ordered_new) as total_customer_ordered_new'),
                DB::raw('SUM(total_order) as total_order'),
                DB::raw('SUM(total_order_new) as total_order_new'),
                DB::raw('SUM(total_price) as total_price'),
                DB::raw('SUM(total_price_new) as total_price_new'),
                DB::raw('SUM(total_fee) as total_fee'),
                DB::raw('SUM(total_fee_new) as total_fee_new'),
            )
            ->where('start_date', '>=', $start_date)
            ->where('end_date', '<=', $end_date)
            ->when($source !== '', function($q) use ($source) {
                $q->where('source', $source);
            })
            ->groupBy('name')
            ->when($group_id, function($q) use ($user_group_ids) {
                $q->whereIn('staff_group_ids', $user_group_ids);
            })
            ->get()->toArray();

        $data = [];
        $total_total_customer_order = 0;
        $total_total_customer_ordered = 0;
        $total_total_customer_order_new = 0;
        $total_total_customer_ordered_new = 0;
        $tableCustomer = [];
        $total_order = 0;
        $total_order_new = 0;
        $tableOrder = [];
        $total_price = 0;
        $total_price_new = 0;
        $total_fee = 0;
        $total_fee_new = 0;
        $tablePrice = [];
        foreach ($reports as &$report) {
            $total_total_customer_order += $report['total_customer_order'];
            $total_total_customer_ordered += $report['total_customer_ordered'];
            $total_total_customer_order_new += $report['total_customer_order_new'];
            $total_total_customer_ordered_new += $report['total_customer_ordered_new'];
            $tableCustomer[] = [
                'name' => $report['name'],
                'total_customer_order' => $report['total_customer_order'],
                'total_customer_ordered' => $report['total_customer_ordered'],
                'total_customer_order_new' => $report['total_customer_order_new'],
                'total_customer_ordered_new' => $report['total_customer_ordered_new'],
                'percent_order' => $report['total_customer_order'] > 0
                    ? round($report['total_customer_ordered'] * 100 / $report['total_customer_order'], 3)
                    : 0,
                'percent_ordered' => $report['total_customer_order_new'] > 0
                    ? round($report['total_customer_ordered_new'] * 100 / $report['total_customer_order_new'], 3)
                    : 0,

            ];
            $total_order += $report['total_order'];
            $total_order_new += $report['total_order_new'];
            $tableOrder[] = [
                'name' => $report['name'],
                'total_order' => $report['total_order'],
                'total_order_new' => $report['total_order_new'],
                'percent' => $report['total_order'] > 0
                    ? round($report['total_order_new'] * 100 / $report['total_order'], 3)
                    : 0,

            ];
            $total_price += $report['total_price'];
            $total_price_new += $report['total_price_new'];
            $total_fee += $report['total_fee'];
            $total_fee_new += $report['total_fee_new'];
            $tablePrice[] = [
                'name' => $report['name'],
                'total_price' => $report['total_price'],
                'total_price_new' => $report['total_price_new'],
                'total_fee' => $report['total_fee'],
                'total_fee_new' => $report['total_fee_new'],
                'percent' => $report['total_order'] > 0
                    ? round($report['total_price'] / $report['total_order'], 3)
                    : 0,

            ];
        }
        $tableCustomer[] = [
            'name' => 'Tổng',
            'total_customer_order' => $total_total_customer_order,
            'total_customer_ordered' => $total_total_customer_ordered,
            'total_customer_order_new' => $total_total_customer_order_new,
            'total_customer_ordered_new' => $total_total_customer_ordered_new,
            'percent_order' => $total_total_customer_order > 0
                ? round($total_total_customer_ordered * 100 / $total_total_customer_order, 3)
                : 0,
            'percent_ordered' => $total_total_customer_order_new > 0
                ? round($total_total_customer_ordered_new * 100 / $total_total_customer_order_new, 3)
                : 0,
        ];
        $tableOrder[] = [
            'name' => 'Tổng',
            'total_order' => $total_order,
            'total_order_new' => $total_order_new,
            'percent' => $total_order > 0
                ? round($total_order_new * 100 / $total_order, 3)
                : 0,

        ];
        $tablePrice[] = [
            'name' => 'Tổng',
            'total_price' => $total_price,
            'total_price_new' => $total_price_new,
            'total_fee' => $total_fee,
            'total_fee_new' => $total_fee_new,
            'percent' => $total_order > 0
                ? round($total_price / $total_order, 3)
                : 0,

        ];

        $data['total']['total_total_customer_order'] = $total_total_customer_order;
        $data['total']['total_total_customer_ordered'] = $total_total_customer_ordered;
        $data['total']['total_total_customer_order_new'] = $total_total_customer_order_new;
        $data['total']['total_total_customer_ordered_new'] = $total_total_customer_ordered_new;
        $data['total']['total_percent_order'] = $total_total_customer_order > 0
            ? round($total_total_customer_ordered * 100 / $total_total_customer_order, 3)
            : 0;
        $data['total']['total_percent_ordered'] = $total_total_customer_order_new > 0
            ? round($total_total_customer_ordered_new * 100 / $total_total_customer_order_new, 3)
            : 0;
        $data['date']['week_start_date'] = Carbon::parse($start_date)->format('d/m/Y');
        $data['date']['week_end_date'] = Carbon::parse($end_date)->format('d/m/Y');
        $data['data']['table_customer'] = $tableCustomer;
        $data['data']['table_order'] = $tableOrder;
        $data['data']['table_price'] = $tablePrice;

        return $data;
    }

    private function getTime($request): array
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($list->year, $list->month, $list->week);
        if($request->input('time')) {
            $time = $request->input('time');
            $weekStartDate = $time[0] . ' 00:00:00';
            $weekEndDate = $time[1] . ' 23:59:59';
        }else {
            $weekStartDate = $calculatorDatetime['week_start_date'] . ' 00:00:00';
            $weekEndDate = $calculatorDatetime['week_end_date'] . ' 23:59:59';
        }
        return [$weekStartDate, $weekEndDate];
    }
}
