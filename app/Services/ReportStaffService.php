<?php


namespace App\Services;

use App\Constants\ConversationConstant;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Models\Cart;
use App\Models\Consignment;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPackage;
use App\Models\UserGroup;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DatePeriod;
use DateInterval;
use Carbon\CarbonPeriod;
use App\Constants\UserConstant;

class ReportStaffService implements Service
{
    public function updateKpi()
    {
        $userGroups = UserGroup::query()->where('month', '01/2025')->get();
        foreach ($userGroups as $userGroup) {
            $query = Order::query();
            $query->selectRaw("sum(order_fee) as total_fee");
            $query->where('staff_group_id', $userGroup->user_id);
            $query->whereBetween('date_done', $this->getDateTimeRange('m'));
            $query->where('status', OrderConstant::KEY_STATUS_DONE);
            $userGroup->update(['real_services' => $query->first()->total_fee]);
        }
    }
    public function totalOrder(): array
    {
        $getDateTimeRange = $this->getDateTimeRange(request()->get('time'));
        $statuses = [
            OrderConstant::KEY_STATUS_WAITING_QUOTE,
            OrderConstant::KEY_STATUS_WAITING_DEPOSIT,
            OrderConstant::KEY_STATUS_ORDERED,
            OrderConstant::KEY_STATUS_DONE
        ];
        $staffGroupIds = $this->getStaffGroupUser();
        $data = Order::query()->selectRaw("count(id) as count, status")
                                ->whereIn('status', $statuses)
                                ->whereBetween('updated_at', $getDateTimeRange)
                                ->whereIn('staff_group_id', $staffGroupIds)
                                ->groupBy('status')
                                ->get();
        $totalWaitingQuote = 0;
        $totalWaitingDeposit = 0;
        $totalOrdered = 0;
        $totalDone = 0;
        $totalPackgeNotTake = 0;
        foreach ($data as $item) {
            if ($item->status == OrderConstant::KEY_STATUS_WAITING_QUOTE) {
                $totalWaitingQuote = $item->count;
            } elseif ($item->status == OrderConstant::KEY_STATUS_WAITING_DEPOSIT) {
                $totalWaitingDeposit = $item->count;
            } elseif ($item->status == OrderConstant::KEY_STATUS_ORDERED) {
                $totalOrdered = $item->count;
            } elseif ($item->status == OrderConstant::KEY_STATUS_DONE) {
                $totalDone = $item->count;
            }
        }
        $totalPackgeNotTake = OrderPackage::query()->whereBetween('created_at', $getDateTimeRange)
                            ->where('status', PackageConstant::STATUS_WAREHOUSE_VN)
                            ->whereIn('staff_group_id', $staffGroupIds)
                            ->count();
        return [
            'total_waiting_quote' => $totalWaitingQuote,
            'total_waiting_deposit' => $totalWaitingDeposit,
            'total_ordered' => $totalOrdered,
            'total_done' => $totalDone,
            'total_package_not_take' => $totalPackgeNotTake
        ];
    }

    public function totalConsignment(): array
    {
        $getDateTimeRange = $this->getDateTimeRange(request()->get('time'));
        $userLogin = Auth::user();
        $userId = $userLogin->id;
        $query = OrderPackage::query();
        $query->where('order_type', Consignment::class);
        $query->where('staff_group_id', $userId);
        $query->whereBetween('date_done', $getDateTimeRange);
        $query->selectRaw("
            SUM(CASE WHEN order_package.status = '" . PackageConstant::STATUS_RECEIVED_GOODS . "' THEN 1 ELSE 0 END)
                AS `totalPackageDone`,
            SUM(CASE WHEN order_package.status = '" . PackageConstant::STATUS_RECEIVED_GOODS . "'
                THEN order_package.international_shipping_cost ELSE 0 END) AS `total_money`,
            COUNT(order_package.status) AS `totalPackage`
            ");
        $package = $query->first();

        $data['total_package_done'] = $package->totalPackageDone ?? 0;
        $data['total_package'] = $package->totalPackage ?? 0;
        $data['total_money'] = $package->total_money ?? 0;

        return $data;
    }

    public function orderPackageVn()
    {
        $getDateTimeRange = $this->getDateTimeRange(request()->get('time'));
        $userLogin = Auth::user();
        $userId = $userLogin->id;
        $query = Order::query();
        $query->join('order_package', 'orders.id', '=', 'order_package.order_id');
        $query->join('order_package_status_times', 'order_package.id', '=',
            'order_package_status_times.order_package_id');

        $query->where('order_package.order_type', Order::class);
        $query->where('order_package_status_times.key', PackageConstant::STATUS_WAREHOUSE_VN);
        $query->where(function ($q) {
            $q->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) > UNIX_TIMESTAMP(DATE_ADD(
                order_package_status_times.created_at,INTERVAL 72 HOUR))');
        });
        $query->whereBetween('order_package_status_times.created_at', $getDateTimeRange);
        $query->whereIn('order_package.status', [
                PackageConstant::STATUS_WAREHOUSE_VN,
                PackageConstant::STATUS_ON_THE_WAY_HCM,
                PackageConstant::STATUS_CHECKING_GOODS_HCM,
                PackageConstant::STATUS_WAITING_CODE,
                PackageConstant::STATUS_IN_PROGRESS
            ]
        );
        $query->where('orders.staff_group_id', $userId);
        $queryCount = clone $query;
        $query->selectRaw("
            sum(orders.order_cost + orders.order_fee + orders.inspection_cost + orders.china_shipping_cost * orders.exchange_rate - orders.discount_cost - orders.deposit_cost) as total_remaining_amount,
            count(order_package.id) as total_package,
            sum(orders.international_shipping_cost) as total_international_shipping_cost");
        $total_order = $queryCount->selectRaw(" count(distinct orders.id) as total_order")->first() ?? 0;
        $data = $query->first();
        $total = $data->total_remaining_amount + $data->total_international_shipping_cost;
        return [
            'total_remaining_amount' => $data->total_remaining_amount ?? 0,
            'total_order' => $total_order->total_order ?? 0,
            'total_package' => $data->total_package ?? 0,
            'total_international_shipping_cost' => $data->total_international_shipping_cost ?? 0,
            'remaining_percent' => $total > 0 ? round(($data->total_remaining_amount / $total) * 100) : 0,
            'international_shipping_cost_percent' => $total > 0 ? 100 - round(($data->total_remaining_amount / $total) * 100) : 0,
        ];
    }

    public function packageVn()
    {
        $getDateTimeRange = $this->getDateTimeRange(request()->get('time'));
        $userLogin = Auth::user();
        $userId = $userLogin->id;
        $query = OrderPackage::query();
        $query->where('order_package.staff_group_id', $userId);
        $query->join('order_package_status_times', 'order_package.id', '=',
            'order_package_status_times.order_package_id');

        $query->where('order_package.order_type', Consignment::class);
        $query->where('order_package_status_times.key', PackageConstant::STATUS_WAREHOUSE_VN);
        $query->where(function ($q) {
            $q->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) > UNIX_TIMESTAMP(DATE_ADD(
                order_package_status_times.created_at,INTERVAL 72 HOUR))');
        });
        $query->whereBetween('order_package_status_times.created_at', $getDateTimeRange);
        $query->whereIn('order_package.status', [
                PackageConstant::STATUS_WAREHOUSE_VN,
                PackageConstant::STATUS_ON_THE_WAY_HCM,
                PackageConstant::STATUS_CHECKING_GOODS_HCM,
                PackageConstant::STATUS_WAITING_CODE,
                PackageConstant::STATUS_IN_PROGRESS
            ]
        );
        $queryCount = clone $query;
        $total_package = $queryCount->selectRaw(" count(distinct order_package.id) as total_package")->first() ?? 0;
        $query->selectRaw("
            sum(order_package.international_shipping_cost) as total_international_shipping_cost"
        );

        $data = $query->first();

        return [
            'total_package' => $total_package->total_package ?? 0,
            'total_international_shipping_cost' => $data->total_international_shipping_cost ?? 0,
        ];
    }

    public function customerPotential()
    {
        $getDateTimeRange = $this->getDateTimeRange(request()->get('time'));
        $userLogin = Auth::user();
        $userId = $userLogin->id;
        // lấy tổng KH
        $query = Customer::query()->selectRaw("count(*) as total_customers");
        $query->leftJoin('orders', 'orders.customer_id', '=', 'customers.id');
        $query->leftJoin('carts', 'carts.customer_id', '=','customers.id');
        $query->whereBetween('customers.created_at', $getDateTimeRange);
        $query->where('customers.staff_group_id', $userId);
        $query->whereNull('orders.id');
        $query->whereNull('carts.id');
        $dataCountCustomer = $query->first();
        $countCustomer = $dataCountCustomer->total_customers ?? 0;

        $queryOrder = Order::query();
        $queryOrder->selectRaw("count(DISTINCT customer_id) AS total_customers");
        $queryOrder
            ->whereBetween('created_at', $getDateTimeRange)
            ->where('staff_group_id', $userId)
            ->whereIn('status',
                [
                    OrderConstant::KEY_STATUS_WAITING_QUOTE,
                    OrderConstant::KEY_STATUS_WAITING_DEPOSIT,
                    OrderConstant::KEY_STATUS_RECONFIRM
                ]
            );

        $countOrder = $queryOrder->first()->total_customers ?? 0;

        $queryCart = Cart::query();
        $queryCart->selectRaw("count(DISTINCT carts.customer_id) as total_customers");
        $queryCart->leftJoin('orders', 'carts.customer_id', '=','orders.customer_id');
        $queryCart->whereNull('carts.customer_id')
            ->whereBetween('carts.created_at', $getDateTimeRange)
            ->where('orders.staff_group_id', $userId);

        $countCart = $queryCart->first()->total_customers ?? 0;

        return [
            'total_cart' => $countCart ?? 0,
            'total_customer' => $countCustomer ?? 0,
            'total_order' => $countOrder ?? 0,
        ];
    }

    public function conversationTotal()
    {
        $userLogin = Auth::user();
        $userId = $userLogin->id;
        $query = Conversation::query();
        $query->where('user_id', $userId);
        $query->where('unread_admin', ">", 0);
        $query->selectRaw("
            SUM(CASE WHEN type = '" . ConversationConstant::TYPE_ORDER . "' THEN 1 ELSE 0 END) AS `total_order`,
            SUM(CASE WHEN type = '" . ConversationConstant::TYPE_PACKAGE . "' THEN 1 ELSE 0 END) AS `total_package`,
            SUM(CASE WHEN type = '" . ConversationConstant::TYPE_COMPLAIN . "' THEN 1 ELSE 0 END) AS `total_complain`
        ");
        $data = $query->first();

        return [
            'total_package' => $data->total_package ?? 0,
            'total_complain' => $data->total_complain ?? 0,
            'total_order' => $data->total_order ?? 0,
        ];
    }

    public function chartKpi()
    {
        $userLogin = Auth::user();
        $userId = $userLogin->id;
        $query = Order::query();
        $query->selectRaw("
            DAY(date_done) as day,
            sum(order_fee) as total_fee
        ");
        $query->where('staff_group_id', $userId);
        $query->whereBetween('date_done', $this->getDateTimeRange('m'));
        $query->where('status', OrderConstant::KEY_STATUS_DONE);
        $query->groupBy('day');
        $kpiOrders = $query->get();
        $dataDay = [];
        $dataFee = [];
        $orderFee = 0;
        $checkDayNow = false;
        $dayNow = Carbon::now()->day;
        $count = $kpiOrders->count();
        $dataLast = 0;
        foreach ($kpiOrders as $key => $kpiOrder) {
            $orderFee = $kpiOrder->total_fee + $orderFee;
            $dataDay[] = 'Ngày ' . $kpiOrder->day;
            if($dayNow == $kpiOrder->day) {
                $checkDayNow = true;
            }
            $dataFee[] = $orderFee;
            if($key == $count - 1) {
                $dataLast = $orderFee;
            }
        }
        if(!$checkDayNow && $count > 0) {
            $dataDay = array_merge($dataDay, ['Ngày ' . $dayNow]);
            $dataFee = array_merge($dataFee, [$dataLast]);
        }

        return ['day' => $dataDay, 'total_fee' => $dataFee];
    }

    public function totalKpi()
    {
        $month = Carbon::now()->format('m/Y');
        $userGroup = UserGroup::query()
            ->select('kpi_services', 'real_services', 'kpi_transports', 'real_transports')
            ->where('user_id', Auth::id())
            ->where('month', '=', $month)
            ->first();
        if(!$userGroup) {
            return [
                'kpi_services' => 0,
                'real_services' => 0,
                'kpi_transports' => 0,
                'real_transports' => 0,
                'percent_transport' => 0,
            ];
        }
        return [
            'kpi_services' => $userGroup->kpi_services ?? 0,
            'real_services' => $userGroup->real_services ?? 0,
            'kpi_transports' => $userGroup->kpi_transports ?? 0,
            'real_transports' => $userGroup->real_transports ?? 0,
            'percent_transport' => $userGroup->kpi_transports > 0 ?
                round(($userGroup->real_transports / $userGroup->kpi_transports) * 100) : 0,
        ];
    }

    public function getDateTimeRange($time)
    {
        $timeQueryStart = Carbon::now()->startOfDay();
        $timeQueryEnd = Carbon::now()->endOfDay();
        if ($time == 'w') {
            $timeQueryStart = Carbon::now()->startOfWeek();
            $timeQueryEnd = Carbon::now()->endOfWeek();
        }
        if ($time == 'm') {
            $timeQueryStart = Carbon::now()->startOfMonth();
            $timeQueryEnd = Carbon::now()->endOfMonth();
        }
        if ($time == 'q') {
            $timeQueryStart = Carbon::now()->startOfQuarter();
            $timeQueryEnd = Carbon::now()->endOfQuarter();
        }
        if ($time == 'y') {
            $timeQueryStart = Carbon::now()->startOfYear();
            $timeQueryEnd = Carbon::now()->endOfYear();
        }

        return [$timeQueryStart, $timeQueryEnd];
    }

    public function totalCustomer()
    {
        $getDateTimeRange = $this->getDateTimeRange(request()->get('time'));
        $staffGroupIds = $this->getStaffGroupUser();
        $baseCustomerQuery = Customer::query()->whereIn('customers.staff_group_id', $staffGroupIds)
                                ->where('customers.status', 1)
                                ->whereBetween('customers.created_at', $getDateTimeRange);
        $labelOrder = [6, 7];// Là id của 2 nhãn Tham khảo và Đang follow 
        $labelConsignment = [11];// Là id của Kí gửi
        $labelConsignmentOrder = [12];// Là id của Kí gửi đã lên đơn
        $customerOrdered = (clone $baseCustomerQuery)
            ->join('orders', 'orders.customer_id', '=', 'customers.id')
            ->where('customers.service', 0)
            ->whereBetween('orders.created_at', $getDateTimeRange)
            ->distinct('customers.id')
            ->pluck('customers.id');
        $customerOrdereByLabel = (clone $baseCustomerQuery)
            ->join('customer_label', 'customer_label.customer_id', '=', 'customers.id')
            ->where('customers.service', 0)
            ->whereIn('customer_label.label_id', $labelOrder)
            ->distinct('customers.id')
            ->pluck('customers.id');
        $customerConsignment = (clone $baseCustomerQuery)
            ->join('consignments', 'consignments.customer_id', '=', 'customers.id')
            ->join('customer_label', 'customer_label.customer_id', '=', 'customers.id')
            ->whereIn('customer_label.label_id', $labelConsignment)
            ->where('customers.service', 1)
            ->distinct('customers.id')
            ->pluck('customers.id');
        $customerConsignmentByLabel = (clone $baseCustomerQuery)
            ->where('customers.service', 1)
            ->join('customer_label', 'customer_label.customer_id', '=', 'customers.id')
            ->whereIn('customer_label.label_id', $labelConsignmentOrder)
            ->distinct('customers.id')
            ->pluck('customers.id');
        $totalCustomerBox1 = collect()
            ->merge($customerOrdered)
            ->merge($customerOrdereByLabel)
            ->merge($customerConsignment)
            ->merge($customerConsignmentByLabel)
            ->unique()
            ->count();
        $totalCustomerBox2 = (clone $baseCustomerQuery)
                ->join('orders', 'orders.customer_id', '=', 'customers.id')
                ->whereIn('orders.status', [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_ORDERED, OrderConstant::KEY_STATUS_WAIT_TO_PAY, OrderConstant::KEY_STATUS_DONE])
                ->whereBetween('orders.created_at', $getDateTimeRange)
                ->count(DB::raw('DISTINCT customers.id'));
        $totalCustomerBox3 = (clone $baseCustomerQuery)
                ->join('order_package', 'order_package.customer_id', '=', 'customers.id')
                ->where('order_package.order_type', Consignment::class)
                ->whereBetween('order_package.created_at', $getDateTimeRange)
                ->count(DB::raw('DISTINCT customers.id'));
        $totalCustomerOrder = collect()
            ->merge($customerOrdered)
            ->merge($customerConsignment)
            ->unique()
            ->count();
        $totalCustomer = (clone $baseCustomerQuery)->count();
        $performanceBox1 = $totalCustomer > 0 ? round((($totalCustomerOrder / $totalCustomer) * 100), 2) : 0;
        $totalCustomerOrdered = (clone $baseCustomerQuery)
            ->where('customers.service', 0)
            ->distinct('customers.id')
            ->count();
        $performanceBox2 = $totalCustomerOrdered > 0 ? round((($totalCustomerBox2 / $totalCustomerOrdered) * 100), 2) : 0;
        $totalCustomerConsignment = (clone $baseCustomerQuery)
            ->where('customers.service', 1)
            ->distinct('customers.id')
            ->count();
        $performanceBox3 = $totalCustomerConsignment > 0 ? round((($totalCustomerBox3 / $totalCustomerConsignment) * 100), 2) : 0;
        return [
            'total_customer' => $totalCustomer,
            'total_customer_box1' => $totalCustomerBox1,
            'total_customer_box2' => $totalCustomerBox2,
            'total_customer_box3' => $totalCustomerBox3,
            'performance_box1' => $performanceBox1,
            'performance_box2' => $performanceBox2,
            'performance_box3' => $performanceBox3,
        ];
    }

    public function totalSale()
    {
        $labelId = 13;// Là id của ĐÃ ĐẶT HÀNG
        $getDateTimeRange = $this->getDateTimeRange(request()->get('time'));
        $staffGroupIds = $this->getStaffGroupUser();
        if(in_array(request()->get('time'), ['y', 'q'])){
            $groupByOrder = DB::raw("DATE_FORMAT(orders.created_at, '%m') AS by_time");
            $groupByOrderPackage = DB::raw("DATE_FORMAT(order_package.created_at, '%m') AS by_time");
            $interval = DateInterval::createFromDateString('1 month');
            $formatDate = $returnDate = "m";
        }else{
            $groupByOrder = DB::raw("DATE_FORMAT(orders.created_at, '%d') AS by_time");
            $groupByOrderPackage = DB::raw("DATE_FORMAT(order_package.created_at, '%d') AS by_time");
            $interval = DateInterval::createFromDateString('1 day');
            $formatDate = "Y-m-d";
            $returnDate = "d";
        }
        $orders = Order::query()->select([
                            DB::raw('SUM(orders.order_cost) AS total_order_cost'),
                            DB::raw('SUM(orders.order_fee) AS total_order_fee'),
                            DB::raw('SUM(orders.international_shipping_cost + orders.china_shipping_cost * orders.exchange_rate) AS total_shipping_cost'),
                            $groupByOrder
                        ])
                        ->join('customer_label', 'customer_label.customer_id', '=', 'orders.customer_id')
                        ->where('customer_label.label_id', $labelId)
                        ->whereIn('orders.staff_group_id', $staffGroupIds)
                        ->whereBetween('orders.created_at', $getDateTimeRange)
                        ->groupBy("by_time")
                        ->get();
        $packageConsignment = OrderPackage::query()
                        ->select([
                            DB::raw('SUM(order_package.international_shipping_cost + (order_package.china_shipping_cost * order_package.exchange_rate)) AS total_shipping_cost'),
                            $groupByOrderPackage
                        ])
                        ->join('customer_label', 'customer_label.customer_id', '=', 'order_package.customer_id')
                        ->where('customer_label.label_id', $labelId)
                        ->where('order_package.order_type', Consignment::class)
                        ->whereIn('order_package.staff_group_id', $staffGroupIds)
                        ->whereBetween('order_package.created_at', $getDateTimeRange)
                        ->groupBy("by_time")
                        ->get();
        $period = new DatePeriod($getDateTimeRange[0], $interval, $getDateTimeRange[1]);
        $dataOrder = [];
        $dataConsignment = [];
        $totalOrderCost = 0;
        $totalConsignment = 0;
        foreach ($period as $key => $interval){
            $timeCheck = $interval->format($returnDate);
            $resultOrder = $orders->filter(function ($item) use ($timeCheck) {
                return $item->by_time == $timeCheck;
            })->first();
            $resultConsignment = $packageConsignment->filter(function ($item) use ($timeCheck) {
                return $item->by_time == $timeCheck;
            })->first();
            if($resultOrder) {
                $totalOrderCost += $resultOrder->total_order_cost + $resultOrder->total_order_fee + $resultOrder->total_shipping_cost;
            }
            if($resultConsignment) {
                $totalConsignment += $resultConsignment->total_shipping_cost;
            }
            $dataOrder[$key]['interval'] = $dataConsignment[$key]['interval'] = $interval->format($returnDate);
            $dataOrder[$key]['total_order_cost'] =  (int)data_get($resultOrder, 'total_order_cost');
            $dataOrder[$key]['total_order_fee'] =  (int)data_get($resultOrder, 'total_order_fee');
            $dataOrder[$key]['total_shipping_cost'] =  (int) data_get($resultOrder, 'total_shipping_cost');
            $dataConsignment[$key]['total_shipping_cost'] =  (int) data_get($resultConsignment, 'total_shipping_cost');
        }
        $orderPackage = OrderPackage::query()
                                    ->select([
                                        DB::raw('SUM(order_package.insurance_cost) AS total_insurance_cost'),
                                        DB::raw('SUM(order_package.woodworking_cost) AS total_woodworking_cost'),
                                        DB::raw('SUM(order_package.shock_proof_cost) AS total_shock_proof_cost'),
                                    ])
                                    ->join('customer_label', 'customer_label.customer_id', '=', 'order_package.customer_id')
                                    ->where('customer_label.label_id', $labelId)
                                    ->whereIn('order_package.staff_group_id', $staffGroupIds)
                                    ->whereBetween('order_package.created_at', $getDateTimeRange)
                                    ->first();
        $totalInspectionCost = Order::query()
                                    ->join('customer_label', 'customer_label.customer_id', '=', 'orders.customer_id')
                                    ->where('customer_label.label_id', $labelId)
                                    ->whereIn('orders.staff_group_id', $staffGroupIds)
                                    ->whereBetween('orders.created_at', $getDateTimeRange)
                                    ->sum(DB::raw('orders.inspection_cost + orders.advanced_inspection_cost'));
        
        $totalServiceInsuranceCost = $orderPackage->total_insurance_cost ?? 0;
        $totalServiceWoodworkingCost = $orderPackage->total_woodworking_cost ?? 0;
        $totalServiceShockProofCost = $orderPackage->total_shock_proof_cost ?? 0;
        return [
            'total_order_cost' => (int) $totalOrderCost,
            'total_consignment_cost' => (int) $totalConsignment,
            'list_order' => $dataOrder,
            'list_consignment' => $dataConsignment,
            'total_service_inspection_cost' => (int) $totalInspectionCost,
            'total_service_insurance_cost' => (int) $totalServiceInsuranceCost,
            'total_service_woodworking_cost' => (int) $totalServiceWoodworkingCost,
            'total_service_shock_proof_cost' => (int) $totalServiceShockProofCost,
        ];
    }

    public function followWeek()
    {
        $staffGroupIds = $this->getStaffGroupUser();
        $labelId = 13; //Lấy theo nhãn ĐÃ ĐẶT HÀNG
        $staffGroupId = getCurrentUserId();
        $startDate = Carbon::now()->startOfYear();
        $endDate = Carbon::now()->endOfYear();
        $orders = Order::query()->select([
                                DB::raw("
                                    DATE_FORMAT(orders.created_at, '%m') as month
                                "),
                                DB::raw("
                                    CASE
                                        WHEN DAY(orders.created_at) <= 15 THEN 1
                                        ELSE 2
                                    END as period
                                "),
                                DB::raw("SUM(orders.order_fee) as total_order_fee"),
                            ])
                            ->join('customer_label', 'customer_label.customer_id', 'orders.customer_id')
                            ->where('customer_label.label_id', $labelId)
                            ->whereBetween('orders.created_at', [$startDate, $endDate])
                            ->whereIn('orders.staff_group_id', $staffGroupIds)
                            ->groupBy('month', 'period')
                            ->orderBy('month')
                            ->orderBy('period')
                            ->get();
        $interval = DateInterval::createFromDateString('1 month');
        $period = new DatePeriod($startDate, $interval, $endDate);
        $dataList = [];
        foreach ($period as $key => $interval){
            $timeCheck = $interval->format('m');
            $resultOrders = $orders->filter(function ($item) use ($timeCheck) {
                return $item->month == $timeCheck;
            });
            $dataList[$key]['interval']  = $interval->format('m');
            $dataList[$key]['period_1']  = 0;
            $dataList[$key]['period_2']  = 0;
            foreach($resultOrders as $itemOrder) {
                if($itemOrder->period == 1) {
                    $dataList[$key]['period_1'] = (int) data_get($itemOrder, 'total_order_fee');
                }
                if($itemOrder->period == 2) {
                    $dataList[$key]['period_2'] = (int) data_get($itemOrder, 'total_order_fee');
                }
            }
        }
        return $dataList;
    }

    public function totalChatbiz()
    {
        $getDateTimeRange = $this->getDateTimeRange(request()->get('time'));
        $staffGroupIds = $this->getStaffGroupUser();
        $userChatbizModel = new \App\Models\UserChatbizPing();
        $listData = $userChatbizModel::query()->select([
                                                    DB::raw('SUM(livechat_late) AS total_livechat_late'), // chat bị nhỡ
                                                    DB::raw('SUM(phone_call_out - phone_call_out_success) AS total_phone_call_out_miss'), // Cuộc gọi không gặp khách hàng 
                                                    DB::raw('SUM(phone_call_out_success) AS total_phone_call_out_success'), // Cuộc gọi thành công gặp khách hàng
                                                    DB::raw('SUM(phone_call_in - phone_call_in_success) AS total_phone_in_miss'), // Số gọi vào nhỡ
                                                    DB::raw('SUM(phone_call_out - phone_call_out_success) AS total_phone_call_out_miss_1'), // Cuộc gọi nhỡ 
                                                    DB::raw('SUM(phone_call_in) AS total_phone_call_in'), // Số cuộc gọi vào
                                                ])
                                                ->whereIn('user_id', $staffGroupIds)
                                                ->whereBetween('created_at', $getDateTimeRange)
                                                ->first();
        return [
            'total_livechat_late' => $listData->total_livechat_late ?? 0,
            'total_phone_call_out_miss' => $listData->total_phone_call_out_miss ?? 0,
            'total_phone_call_out_success' => $listData->total_phone_call_out_success ?? 0,
            'total_phone_in_miss' => $listData->total_phone_in_miss ?? 0,
            'total_phone_call_out_miss_1' => $listData->total_phone_call_out_miss_1 ?? 0,
            'total_phone_call_in' => $listData->total_phone_call_in ?? 0,
        ];
    }

    public function getListDateByPeriod($format = 'Y-m-d')
    {
        $now = Carbon::now();
        $startDate = Carbon::createFromFormat('Y-m-d', '2024-01-01')->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();
        $period = CarbonPeriod::create(
            $startDate,
            '1 month',
            $endDate
        );
        $result = [];
        foreach ($period as $month) {
            // kỳ 1
            $result[] = [
                'period' => 'Kì 1',
                'start'  => $month->copy()->startOfMonth()->format($format),
                'end'    => $month->copy()->day(15)->format($format),
            ];

            // kỳ 2
            $result[] = [
                'period' => 'Kì 2',
                'start'  => $month->copy()->day(16)->format($format),
                'end'    => $month->copy()->endOfMonth()->format($format),
            ];
        }
        if(Carbon::now()->day < 16) {
            array_pop($result);
        }
        return array_reverse($result);
    }

    public function listDateMonth()
    {
        $now = Carbon::now();
        $startDate = Carbon::createFromFormat('Y-m-d', '2024-01-01')->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();
        $period = CarbonPeriod::create(
            $startDate,
            '1 month',
            $endDate
        );
        $result = [];
        foreach ($period as $month) {
            $result[] = [
                'date'  => $month->copy()->startOfMonth()->format('m/Y'),
            ];
        }
        return array_reverse($result);
    }

    public function totalOrderFee()
    {
        $time = request()->time;
        if(is_null($time)) {
            return resError('Chưa chọn thời gian tham chiếu', 422);
        }
        $labelId = 13; //Lấy theo nhãn ĐÃ ĐẶT HÀNG
        $explodeTime = explode('-', $time);
        $startDate = Carbon::createFromFormat('d/m/Y', $explodeTime[0])->startOfDay();
        $endDate = Carbon::createFromFormat('d/m/Y', $explodeTime[1])->endOfDay();
        $staffGroupIds = $this->getStaffGroupUser();
        $totalFee = Order::query()
                                ->whereIn('orders.staff_group_id', $staffGroupIds)
                                ->whereBetween('orders.created_at', [$startDate, $endDate])
                                ->join('customer_label', 'customer_label.customer_id', 'orders.customer_id')
                                ->where('customer_label.label_id', $labelId)
                                ->sum('orders.order_fee');
        return $totalFee;
    }

    public function totalOrderFeeStaff()
    {
        $time = request()->time;
        if(is_null($time)) {
            return resError('Chưa chọn thời gian tham chiếu', 422);
        }
        $labelId = 13; //Lấy theo nhãn ĐÃ ĐẶT HÀNG
        $month = Carbon::createFromFormat('m/Y', $time);
        // kỳ 1
        $period1 = [
            $month->copy()->startOfMonth(),
            $month->copy()->day(15),
        ];

        // kỳ 2
        $period2 = [
            $month->copy()->day(16),
            $month->copy()->endOfMonth(),
        ];
        $staffGroupIds = $this->getStaffGroupUser();
        $listUserPeriod1 = $listUserPeriod2 = User::whereIn('id', $staffGroupIds)->select('name', 'id')->get()->toArray();
        $baseQuery = Order::query()->whereIn('orders.staff_group_id', $staffGroupIds)
                                ->join('customer_label', 'customer_label.customer_id', 'orders.customer_id')
                                ->where('customer_label.label_id', $labelId);
        $dataUserPeriod1 = (clone $baseQuery)
            ->whereBetween('orders.created_at', $period1)
            ->join('users', 'users.id', '=', 'orders.staff_group_id')
            ->selectRaw('
                users.id as staff_id,
                users.name as staff_name,
                SUM(orders.order_fee) as total_order_fee
            ')
            ->groupBy('users.id', 'users.name')
            ->get();
        $dataUserPeriod2 = (clone $baseQuery)
            ->whereBetween('orders.created_at', $period2)
            ->join('users', 'users.id', '=', 'orders.staff_group_id')
            ->selectRaw('
                users.id as staff_id,
                users.name as staff_name,
                SUM(orders.order_fee) as total_order_fee
            ')
            ->groupBy('users.id', 'users.name')
            ->get();
        $totalFeePeriod1 = $dataUserPeriod1->sum('total_order_fee');
        $totalFeePeriod2 = $dataUserPeriod2->sum('total_order_fee');
        foreach ($listUserPeriod1 as $key => $item) {
            $filter = $dataUserPeriod1->filter(function ($user) use ($item) {
                return $user->staff_id == $item['id'];
            })->first();
            $totalFee = $filter ? $filter->total_order_fee : 0;
            $listUserPeriod1[$key]['total_order_fee'] = (int) $totalFee;
        }
        foreach ($listUserPeriod2 as $key => $item) {
            $filter = $dataUserPeriod2->filter(function ($user) use ($item) {
                return $user->staff_id == $item['id'];
            })->first();
            $totalFee = $filter ? $filter->total_order_fee : 0;
            $listUserPeriod2[$key]['total_order_fee'] = (int) $totalFee;
        }
        usort($listUserPeriod1, function ($a, $b) {
            return $b['total_order_fee'] <=> $a['total_order_fee'];
        });
        usort($listUserPeriod2, function ($a, $b) {
            return $b['total_order_fee'] <=> $a['total_order_fee'];
        });
        return [
            'period_1' => $period1[0]->format('d/m/Y') . ' - ' . $period1[1]->format('d/m/Y'),
            'period_2' => $period2[0]->format('d/m/Y') . ' - ' . $period2[1]->format('d/m/Y'),
            'list_user_period_1' => $listUserPeriod1,
            'list_user_period_2' => $listUserPeriod2,
            'total_fee_period_1' => (int) $totalFeePeriod1,
            'total_fee_period_2' => (int) $totalFeePeriod2,
        ];
    }

    public function getStaffGroupUser()
    {
        $auth = getCurrentUser();
        $isRoleStaffGroup = $auth->hasRoleName(UserConstant::ROLE_STAFF_GROUP);
        if($isRoleStaffGroup == true) {
            return [$auth->id];
        }
        $query = User::join('users_group', 'users_group.user_id', '=', 'users.id')
                    ->where('users_group.month', date('m/Y'));
        $isRoleManagerGroup = $auth->hasRoleName(UserConstant::ROLE_MANAGER_GROUP);
        if($isRoleManagerGroup == true) {
            $groupIds = Group::query()->where('manager_id', getCurrentUserId())->pluck('id')->toArray();
            $query->whereIn('users_group.group_id', $groupIds);
        }
        $userIds = $query->distinct('users.id')->pluck('users.id')->toArray();
        return $userIds;
    }
}
