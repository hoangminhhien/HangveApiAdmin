<?php

namespace App\Services;

use App\Jobs\Report\ReportCustomerReOrdersByCursorJob;
use App\Models\Customer;
use App\Models\Label;
use App\Models\Order;
use App\Models\ReportCustomerReOrder;
use App\Models\User;
use App\Models\Consignment;
use App\Models\OrderPackage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Constants\OrderConstant;

class ReportCustomerReOrders
{
    private function getUserGroup($group_id = false, $options = []): array
    {
        $query = User::query();
        $query = $query->whereExists(function ($query) use ($group_id, $options){
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
            ->select('id');
        return $query->pluck('id')
            ->toArray();
    }

    public function getReport(array $request): array
    {
        $numberLastMonth = data_get($request, 'last_month') ? data_get($request, 'last_month') : 1;
        $lastMonth = Carbon::now()->subMonths($numberLastMonth);
        $group_id = $request['group_id'] ?? '';
        $month_start = date('m/Y', strtotime($lastMonth));
        $month_end = date('m/Y');
        $user_group_ids = $this->getUserGroup($group_id, ['month' => [$month_start, $month_end]]);
        if ($group_id && empty($user_group_ids)) {
            return [];
        }
        // Nếu không có quyền xem all thì mặc định lọc theo userLogin
        $userOnline = Auth::user();
        $viewAll = $userOnline->checkUserHasPer('report.view-all-view-week');
        if (!$viewAll) {
            $user_group_ids = [(int) $userOnline->id];
        }
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($list->year, $list->month, $list->week);
        $start_date = $calculatorDatetime['week_start_date'] . ' 00:00:00';
        $end_date = $calculatorDatetime['week_end_date'] . ' 23:59:59';

        // $customerIds = $this->getCustomerIdLongTimeOrder($numberLastMonth);
        $totalCustomer = $this->totalCustomer($numberLastMonth, $user_group_ids, $start_date)->total_customer ?? 0;
        $totalCustomerReOrder = $this->totalCustomerOrder(
            $start_date,
            $end_date,
            $user_group_ids,
            $numberLastMonth
        );
        $totalCustomerOrder = data_get($totalCustomerReOrder, 'total_customer') ?? 0;
        $totalOrder = data_get($totalCustomerReOrder, 'total_order') ?? 0;
        $totalConsignment = data_get($totalCustomerReOrder, 'total_consignment') ?? 0;

        return [
            'total_customer_order_time' => $totalCustomer ,
            'total_customer_order_time_has_order' => $totalCustomerOrder,
            'total_order' => number_format($totalOrder),
            'total_consignment' => number_format($totalConsignment),
            'week' => $list->week,
            'month' => $list->month,
            'year' => $list->year,
            'created_at' => $calculatorDatetime['week_start_date'],
            'updated_at' => $calculatorDatetime['week_end_date']
        ];
    }

    private function totalCustomer($numberLastMonth, $user_group_ids, $start_date)
    {
        $source = request()->input('source') ?? '';
        $lastMonth = Carbon::now()->subMonths($numberLastMonth);
        $sql = Customer::query();
        $sql->select(DB::raw('COUNT(DISTINCT customers.id) as total_customer'));
        $sql->where(DB::raw("IF(customers.last_order_at IS NOT NULL, customers.last_order_at, customers.created_at)"), '<', $lastMonth)
            ->whereDoesntHave('packages', function ($q) use ($lastMonth){
                $q->whereBetween('created_at', [$lastMonth, Carbon::now()])
                    ->where('order_type', Consignment::class);
            })
            ->where('customers.status', 1);
        if(request()->input('group_id')) {
            $sql->whereIn('customers.staff_group_id', $user_group_ids);
        }
        if($source !== "") {
            $sql->where('customers.source', $source);
        }
        return $sql->first();
    }

    private function totalCustomerOrder(
        $start_date,
        $end_date,
        $user_group_ids,
        $numberLastMonth
    )
    {
        $customerIds = [];
        $totalOrder = 0;
        $totalConsignment = 0;
        $totalPrice = 0;
        $source = request()->input('source') ?? '';
        $orders = Customer::query()
            ->select(
                DB::raw('DISTINCT customers.id AS customer_id'),
                DB::raw('COUNT(DISTINCT orders.id) AS total_order'),
            )
            ->join('orders', function($q) use ($start_date, $end_date){
                $q->on('orders.customer_id', '=', 'customers.id')
                    ->whereBetween('orders.created_at', [$start_date, $end_date]);
                }
            )
            ->whereNotNull('customers.last_order_at')
            ->whereDoesntHave('order', function ($order) use ($numberLastMonth, $start_date, $end_date){
                $order->whereRaw(DB::raw('orders.created_at BETWEEN DATE_ADD(customers.last_order_at, INTERVAL -'.$numberLastMonth.' MONTH) AND DATE_ADD(customers.last_order_at, INTERVAL -1 MINUTE)'))
                    ->whereNotBetween('orders.created_at', [$start_date, $end_date]);
            })
            ->where('customers.created_at', '<', Carbon::now()->subMonths($numberLastMonth))
            ->where('customers.status', 1)
            ->when(request()->input('group_id'), function($q) use($user_group_ids) {
                $q->whereIn('customers.staff_group_id', $user_group_ids);
            })
            ->when($source !== "", function($q) use ($source) {
                $q->where('customers.source', $source);
            })
            ->groupBy('customers.id')
            ->get();
        $consignments = Customer::query()
            ->select(
                DB::raw('DISTINCT customers.id AS customer_id'),
                DB::raw('COUNT(DISTINCT consignments.id) AS total_order'),
            )
            ->join('consignments', function($q) use ($start_date, $end_date){
                $q->on('consignments.customer_id', '=', 'customers.id')
                    ->whereBetween('consignments.created_at', [$start_date, $end_date]);
                }
            )
            ->whereNotNull('customers.last_order_at')
            ->whereDoesntHave('consignments', function ($order) use ($numberLastMonth, $start_date, $end_date){
                $order->whereRaw(DB::raw('consignments.created_at BETWEEN DATE_ADD(customers.last_order_at, INTERVAL -'.$numberLastMonth.' MONTH) AND DATE_ADD(customers.last_order_at, INTERVAL -1 MINUTE)'))
                    ->whereNotBetween('consignments.created_at', [$start_date, $end_date]);
            })
            ->where('customers.created_at', '<', Carbon::now()->subMonths($numberLastMonth))
            ->where('customers.status', 1)
            ->when(request()->input('group_id'), function($q) use($user_group_ids) {
                $q->whereIn('customers.staff_group_id', $user_group_ids);
            })
            ->when($source !== "", function($q) use ($source) {
                $q->where('customers.source', $source);
            })
            ->groupBy('customers.id')
            ->get();
        $orderPackage = Customer::query()
            ->select(
                DB::raw('DISTINCT customers.id AS customer_id'),
                DB::raw('COUNT(DISTINCT order_package.id) AS total_order'),
            )
            ->join('order_package', function($q) use ($start_date, $end_date){
                $q->on('order_package.customer_id', '=', 'customers.id')
                    ->whereBetween('order_package.created_at', [$start_date, $end_date])
                    ->whereNull('order_package.order_id')
                    ->where('order_package.order_type', Consignment::class);
                }
            )
            ->whereNotNull('customers.last_order_at')
            ->whereDoesntHave('orderPackage', function ($order) use ($numberLastMonth, $start_date, $end_date){
                $order->whereRaw(DB::raw('order_package.created_at BETWEEN DATE_ADD(customers.last_order_at, INTERVAL -'.$numberLastMonth.' MONTH) AND DATE_ADD(customers.last_order_at, INTERVAL -1 MINUTE)'))
                    ->whereNotBetween('order_package.created_at', [$start_date, $end_date])
                    ->whereNull('order_package.order_id')
                    ->where('order_package.order_type', Consignment::class);
            })
            ->where('customers.created_at', '<', Carbon::now()->subMonths($numberLastMonth))
            ->where('customers.status', 1)
            ->when(request()->input('group_id'), function($q) use($user_group_ids) {
                $q->whereIn('customers.staff_group_id', $user_group_ids);
            })
            ->groupBy('customers.id')
            ->get();
        foreach($orders as $order) {
            array_push($customerIds, $order->customer_id);
            $totalOrder += $order->total_order ?? 0;
        }
        foreach($consignments as $consignment) {
            array_push($customerIds, $consignment->customer_id);
            $totalConsignment += $consignment->total_order ?? 0;
        }
        foreach($orderPackage as $item) {
            array_push($customerIds, $item->customer_id);
            $totalConsignment += $item->total_order ?? 0;
        }
        return [
            'total_customer' => count(array_unique($customerIds)),
            'total_order' => $totalOrder,
            'total_consignment' => $totalConsignment,
        ];
    }

    private function getCustomerIdLongTimeOrder($numberLastMonth)
    {
        // lấy tất cả KH lâu chưa lên đơn
        $lastMonth = Carbon::now()->subMonths($numberLastMonth);
        return Customer::query()
            ->where('customers.last_order_at', '<', $lastMonth)
            ->whereNotNull('customers.last_order_at')
            ->whereDoesntHave('packages', function ($q) use ($lastMonth){
                $q->whereBetween('created_at', [$lastMonth, Carbon::now()])
                    ->where('order_type', Consignment::class);
            })
            ->groupBy('customers.id')
            ->pluck('customers.id')
            ->toArray();



    }

    public function cronjobInsertData($request): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $subDay = date('Y-m-d', strtotime($list->date. ' -30 days'));
        $query = Customer::query();
        $date = date('Y-m-d', strtotime($list->date));
        $query->select(
            'id'
        )
            ->whereHas('order', function ($order) use ($subDay) {
                $order->whereNull('orders.deleted_at')
                    ->whereDate('orders.created_at', '<=', $subDay);
            });
        $query->chunk(500, function ($users) use ($date, $list) {
                $userIds = $users->pluck('id')->toArray();
                $job = new ReportCustomerReOrdersByCursorJob($date, $userIds, $list);
                dispatch($job)->onQueue('sync-data-report');

            });
        return true;
    }

    public function cursorQuery($userIds, $date, $list)
    {
        $arrayCalculator = [
            'revenue' => 0,
            'total_customer' => 0,
            'total_order' => 0,
            'date' => $list->date,
            'week' => $list->week,
            'month' => $list->month,
            'year' => $list->year
        ];
        $calculator = Order::query()->select
        (
            DB::raw('COUNT(DISTINCT orders.customer_id) as total_customer_has_re_order'),
            DB::raw('COUNT(orders.id) as total_order_re_order'),
            DB::raw('SUM(orders.order_cost + orders.inspection_cost
                                            + orders.international_shipping_cost
                                            + orders.order_fee
                                            + (orders.china_shipping_cost * orders.exchange_rate)
                                            - orders.discount_cost
                                            ) as total_price'
            )
        )
            ->whereIn('customer_id', $userIds)
            ->whereBetween('orders.created_at',[$date . ' 00:00:00', $date . ' 23:59:59'])
            ->cursor();
        foreach ($calculator as $cursor) {
            $arrayCalculator['revenue'] += $cursor->total_price ?? 0;
            $arrayCalculator['total_customer'] += $cursor->total_customer_has_re_order ?? 0;
            $arrayCalculator['total_order'] += $cursor->total_order_re_order ?? 0;
        }
        if (
            $arrayCalculator['revenue'] > 0
            || $arrayCalculator['total_customer'] > 0
            || $arrayCalculator['total_order'] > 0
        ) {
            $this->insert($arrayCalculator);
        }
    }

    public function insert($data): bool
    {
        try {
            ReportCustomerReOrder::query()->create($data);
            return true;
        } catch (\Exception $e) {
            Log::info('cursorQueryInsertData ' . $e->getMessage());
            return true;
        }
    }

    public function delete($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return ReportCustomerReOrder::query()
            ->whereBetween('date', [$list->date . ' 00:00:00', $list->date . ' 23:59:59'])
            ->where('week', $list->week)
            ->where('year', $list->year)
            ->where('month', $list->month)
            ->delete();
    }

    public function getReport1($request): array
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $results = ReportCustomerReOrder::query()->where('week', $list->week)
            ->where('month', $list->month)
            ->where('year', $list->year)
            ->cursor();
        $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($list->year, $list->month, $list->week);
        $weekStartDate = $calculatorDatetime['week_start_date'];
        $weekEndDate = $calculatorDatetime['week_end_date'];
        $array = [
            'revenue' => 0,
            'total_customer' => 0,
            'total_order' => 0,
            'week' => $list->week,
            'month' => $list->month,
            'year' => $list->year,
            'created_at' => date('d-m-Y',strtotime($weekStartDate)),
            'updated_at' => date('d-m-Y',strtotime($weekEndDate)),
        ];
        foreach ($results as $result) {
            $array['revenue'] += $result->revenue ?? 0;
            $array['total_customer'] += $result->total_customer ?? 0;
            $array['total_order'] += $result->total_order ?? 0;
        }
       return $array;
    }
}
