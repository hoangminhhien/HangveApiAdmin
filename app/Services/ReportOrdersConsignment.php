<?php

namespace App\Services;

use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Label;
use App\Models\ReportOrderConsignment;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Constants\PackageConstant;

class ReportOrdersConsignment
{
    protected ReportService $service;
    function __construct(ReportService $service)
    {
        $this->service = $service;
    }

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

    private function processData($start_date, $end_date, $list, $type): array
    {
        $user_group_ids = $this->getUserGroup();
        $label = Label::query()->select('id')->where('code', 'KI_GUI_DA_LEN_DON')->first();
        $label_id = $label->id ?? 0;
        $calculator = $this->calculator($start_date, $end_date, $user_group_ids, $label_id);
        $totalCustomers = $this->totalCustomer($start_date, $end_date, $user_group_ids, $label_id);
        $totalCustomerAllWithLabels = $this->getTotalCustomerAllWithLabel($user_group_ids, $label_id);
        $totalCustomerAllWithCustomerLabels = $this->getTotalCustomerAllWithCustomerLabel(
            $start_date,
            $end_date,
            $user_group_ids,
            $label_id,
            $type
        );
        $groupMapping = $this->mappingGroup($list, $type);
        foreach ($totalCustomers as $totalCustomer) {
            if (isset($groupMapping[$totalCustomer->group_id])) {
                $groupMapping[$totalCustomer->group_id]['total_customer'] += $totalCustomer->total_customer;
            }
        }

        foreach ($totalCustomerAllWithLabels as $totalCustomerAllWithLabel) {
            if (isset($groupMapping[$totalCustomerAllWithLabel->group_id])) {
                $groupMapping[$totalCustomerAllWithLabel->group_id]['total_customer']
                    += $totalCustomerAllWithLabel->total_customer;
            }
        }

        foreach ($totalCustomerAllWithCustomerLabels as $totalCustomerAllWithCustomerLabel) {
            if (isset($groupMapping[$totalCustomerAllWithCustomerLabel->group_id])) {
                $groupMapping[$totalCustomerAllWithCustomerLabel->group_id]['total_customer']
                    += $totalCustomerAllWithCustomerLabel->total_customer;
            }
        }

        foreach ($calculator as $item) {
            if (isset($groupMapping[$item->group_id])) {
                $groupMapping[$item->group_id]['total_customer_order']
                    += $item->total_customer_order;
                $groupMapping[$item->group_id]['total_weight']
                    += $item->total_weight;
                $groupMapping[$item->group_id]['total_volume']
                    += $item->total_volume;
                $groupMapping[$item->group_id]['fee_ship']
                    += $item->fee_ship;
            }
        }
        return $groupMapping;
    }

    private function mappingGroup($list, $type): array
    {
        $groups = $this->getGroupId();
        $arrayData = [];
        foreach ($groups as $group) {
            $arrayData[$group] = [
                'total_customer' => 0,
                'user_id' => 0,
                'user_group_id' => $group,
                'fee_ship' => 0,
                'total_customer_order' => 0,
                'total_weight' => 0,
                'total_volume' => 0,
                'month' => $list->month,
                'year' => $list->year,
                'type' => $type,
                'week' => $type == 'week' ? $list->week : 0,
            ];
        }
        return $arrayData;
    }

    private function getGroupId(): array
    {
        $results = UserGroup::query()->select('group_id')->pluck('group_id')->toArray();
        return array_unique($results);
    }

    public function cronjobInsertData($request): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($list->year, $list->month, $list->week);
        $weekStartDate = $calculatorDatetime['week_start_date'] . ' 00:00:00';
        $weekEndDate = $calculatorDatetime['week_end_date'] . ' 23:59:59';
        $results = $this->processData($weekStartDate, $weekEndDate, $list, 'week');
        foreach ($results as $key => $result) {
            ReportOrderConsignment::query()->updateOrCreate([
                'user_group_id' => $key,
                'week' => $list->week,
                'month' => $list->month,
                'year' => $list->year,
            ], $result);
        }
        return true;
    }

    public function cronjobInsertDataMonth($request): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $calculatorDatetime = CalculatorWeekReport::getStartAndEndDateOfMonth($request);
        $fistDayOfMonth = $calculatorDatetime->fistDayOfMonth . ' 00:00:00';
        $lastDayOfMonth = $calculatorDatetime->lastDayOfMonth . ' 23:59:59';

        $results = $this->processData($fistDayOfMonth, $lastDayOfMonth, $list, 'month');
        foreach ($results as $key => $result) {
            ReportOrderConsignment::query()->updateOrCreate([
                'user_group_id' => $key,
                'month' => $list->month,
                'year' => $list->year,
            ], $result);
        }
        return true;
    }

    private function totalCustomer($start_date, $end_date, $user_group_ids, $label_id)
    {
        $flagDate = '2024-12-06 11:06:59';
        $month = date('m', strtotime($start_date));
        $year = date('Y', strtotime($start_date));
        if ($month < 12 && $year <= '2024') {

        } else {
            if (strtotime($start_date) < strtotime($flagDate)) {
                $start_date = $flagDate;
            }
        }
        $query = Customer::query()->select
        (
            DB::raw('COUNT(DISTINCT customers.id) as total_customer'),
            'users_group.group_id'
        )
            ->join('users_group','users_group.user_id', 'customers.staff_group_id')
            ->join('customer_label','customer_label.customer_id','=','customers.id')
            ->where('customer_label.label_id', $label_id)
            ->whereBetween('customer_label.created_at',[$start_date, $end_date])
            ->where('customers.status', 1)
            ->whereIn('customers.staff_group_id', $user_group_ids)
            ->groupBy('users_group.group_id');
        return $query->get();
    }

    private function getTotalCustomerAllWithCustomerLabel($start_date, $end_date, $user_group_ids, $label_id, $type)
    {
        $flagDate = '2024-12-06 11:06:59';
        $month = date('m', strtotime($start_date));
        $year = date('Y', strtotime($start_date));
        if ($month < 12 && $year <= '2024') {

        } else {
            if ($type == 'month') {
                if (strtotime($start_date) < strtotime($flagDate)) {
                    $start_date = $flagDate;
                }
            }
        }
        $query = Customer::query()->select
        (
            DB::raw('COUNT(DISTINCT customers.id) as total_customer'),
            'users_group.group_id'
        )
            ->join('customer_label','customer_label.customer_id','=','customers.id')
            ->join('users_group','users_group.user_id', 'customers.staff_group_id')
            ->where('customer_label.created_at', '<', $start_date)
            ->where('customer_label.label_id', $label_id)
            ->where('customers.status', 1)
            ->whereIn('customers.staff_group_id', $user_group_ids)
            ->groupBy('users_group.group_id');
        return $query->get();
    }

    private function getTotalCustomerAllWithLabel($user_group_ids, $label_id)
    {
        $query = Customer::query()->select
        (
            DB::raw('COUNT(DISTINCT customers.id) as total_customer'),
            'users_group.group_id'
        )
            ->join('users_group','users_group.user_id', 'customers.staff_group_id')
            ->where('customers.label_id', $label_id)
            ->where('customers.status', 1)
            ->whereIn('customers.staff_group_id', $user_group_ids)
            ->groupBy('users_group.group_id');
        return $query->get();
    }

    public function calculator($start_date, $end_date, $user_group_ids, $label_id)
    {
        $query = Consignment::query()
            ->select(
                'users_group.group_id as group_id',
                DB::raw('COUNT(DISTINCT consignments.customer_id) as total_customer_order'),
                DB::raw('SUM(order_package.international_shipping_cost) as fee_ship'),
                DB::raw('SUM(order_package.weight) as total_weight'),
                DB::raw('SUM(order_package.height/100 * order_package.length/100 * order_package.width/100) as total_volume')
            )
            ->join('consignment_details','consignment_details.consignment_id','=','consignments.id')
            ->join('order_package','consignment_details.order_package_id','=','order_package.id')
            ->join('customers','customers.id', 'consignments.customer_id')
            ->join('users_group','users_group.user_id', 'customers.staff_group_id')
            ->join('customer_label','customer_label.customer_id','=','customers.id')
            ->where('customer_label.label_id', $label_id)
            ->whereBetween('consignments.created_at',[$start_date, $end_date])
            ->whereIn('users_group.user_id', $user_group_ids)
            ->whereNull('order_package.deleted_at')
            ->whereNull('consignments.deleted_at')
            ->whereNull('consignment_details.deleted_at')
            ->groupBy('users_group.group_id');
        return $query->get();
    }

    public function delete($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return ReportOrderConsignment::query()->where('week', $list->week)
            ->where('year', $list->year)
            ->where('type','week')
            ->where('month', $list->month)
            ->delete();
    }

    public function deleteMonth($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return ReportOrderConsignment::query()->where('year', $list->year)
            ->where('type','month')
            ->where('month', $list->month)
            ->delete();
    }

    public function insert($data){
        try {
            ReportOrderConsignment::query()->insert($data);
            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    public function getReport($params, $default = true): array
    {
        if($default === true) {
            $weekEnd = $params['weekEnd'];
            $monthEnd = $params['monthEnd'];
            $yearEnd = $params['yearEnd'];
            $weekStart = $params['weekStart'];
            $monthStart = $params['monthStart'];
            $yearStart = $params['yearStart'];
            $weekTwo = $params['weekTwo'];
            $monthTwo = $params['monthTwo'];
            $yearTwo = $params['yearTwo'];
            if (!empty($weekTwo)) {
                $res = CalculatorWeekReport::getDayOfWeekOfMonth($yearTwo, $monthTwo, $weekTwo);
                $startTime1 = date('d-m-Y',strtotime($res['week_start_date']));
                $endTime1 = date('d-m-Y',strtotime($res['week_end_date']));
            }
            if (!empty($weekStart)) {
                $res = CalculatorWeekReport::getDayOfWeekOfMonth($yearStart, $monthStart, $weekStart);
                $startTime2 = date('d-m-Y',strtotime($res['week_start_date']));
                $endTime2 = date('d-m-Y',strtotime($res['week_end_date']));
            }

            if (!empty($weekEnd)) {
                $res = CalculatorWeekReport::getDayOfWeekOfMonth($yearEnd, $monthEnd, $weekEnd);
                $startTime3 = date('d-m-Y',strtotime($res['week_start_date']));
                $endTime3 = date('d-m-Y',strtotime($res['week_end_date']));
            }
        } else {
            if (!empty($params['time1'])) {
                $time1 = $params['time1'];
                $startTime1 = Carbon::createFromFormat('Y-m-d', data_get($time1, 0))->format('d-m-Y');
                $endTime1 = Carbon::createFromFormat('Y-m-d', data_get($time1, 1))->format('d-m-Y');
            }
            if (!empty($params['time2'])) {
                $time2 = $params['time2'];
                $startTime2 = Carbon::createFromFormat('Y-m-d', data_get($time2, 0))->format('d-m-Y');
                $endTime2 = Carbon::createFromFormat('Y-m-d', data_get($time2, 1))->format('d-m-Y');
            }
            if (!empty($params['time3'])) {
                $time3 = $params['time3'];
                $startTime3 = Carbon::createFromFormat('Y-m-d', data_get($time3, 0))->format('d-m-Y');
                $endTime3 = Carbon::createFromFormat('Y-m-d', data_get($time3, 1))->format('d-m-Y');
            }
        }
        /*Tổng khách kí gửi*/
        $totalCustomerTime1 = $this->getTotalCustomer($startTime1, $endTime1);
        $totalCustomerTime2 = $this->getTotalCustomer($startTime2, $endTime2);
        $totalCustomerTime3 = $this->getTotalCustomer($startTime3, $endTime3);
        $quantityTotalCustomerChange = $totalCustomerTime3 - $totalCustomerTime2;
        $percentTotalCustomer = $totalCustomerTime2 > 0 ? round($quantityTotalCustomerChange*100/$totalCustomerTime2) : 0;
        /*KH Đã Nhận Hàng*/
        $totalCustomerReceivedGood1 = $this->getTotalCustomerReceivedGoods($startTime1, $endTime1);
        $totalCustomerReceivedGood2 = $this->getTotalCustomerReceivedGoods($startTime2, $endTime2);
        $totalCustomerReceivedGood3 = $this->getTotalCustomerReceivedGoods($startTime3, $endTime3);
        $quantityCustomerReceivedGood = $totalCustomerReceivedGood3 - $totalCustomerReceivedGood2;
        $percentCustomerReceivedGood = $totalCustomerReceivedGood2 > 0 ? round($quantityCustomerReceivedGood*100/$totalCustomerReceivedGood2) : 0;
        /*KH Lên Đơn Kí Gửi*/
        $totalCustomerOrderTime1 = $this->getTotalCustomerConsignment($startTime1, $endTime1);
        $totalCustomerOrderTime2 = $this->getTotalCustomerConsignment($startTime2, $endTime2);
        $totalCustomerOrderTime3 = $this->getTotalCustomerConsignment($startTime3, $endTime3);
        $consignmentTime1 = $this->getTotalCustomerOrder($startTime1, $endTime1, false);
        $consignmentTime2 = $this->getTotalCustomerOrder($startTime2, $endTime2, false);
        $consignmentTime3 = $this->getTotalCustomerOrder($startTime3, $endTime3, false);
        $quantityCustomerOrderChange = $totalCustomerOrderTime3 - $totalCustomerOrderTime2;
        $percentCustomerOrder = $totalCustomerOrderTime2 > 0 ? round($quantityCustomerOrderChange*100/$totalCustomerOrderTime2) : 0;
        /* Phí Vận Chuyển Kí Gửir*/
        $serviceChargeTime1 = data_get($consignmentTime1, 'fee_ship') ?? 0;
        $serviceChargeTime2 = data_get($consignmentTime2, 'fee_ship') ?? 0;
        $serviceChargeTime3 = data_get($consignmentTime3, 'fee_ship') ?? 0;
        $quantityServiceCharge = $serviceChargeTime3 - $serviceChargeTime2;
        $percentServiceCharge = $serviceChargeTime2 > 0 ? round($quantityServiceCharge*100/$serviceChargeTime2) : 0;
        /*Tổng cân nặng KG*/
        $totalWeightTime1 = data_get($consignmentTime1, 'total_weight') ?? 0;
        $totalWeightTime2 = data_get($consignmentTime2, 'total_weight') ?? 0;
        $totalWeightTime3 = data_get($consignmentTime3, 'total_weight') ?? 0;
        $quantityWeightChange = $totalWeightTime3 - $totalWeightTime2;
        $percentWeight = $totalWeightTime2 > 0 ? round($quantityWeightChange*100/$totalWeightTime2) : 0;
        /*Tổng cân nặng M3*/
        $totalVolumeTime1 = data_get($consignmentTime1, 'total_volume') ?? 0;
        $totalVolumeTime2 = data_get($consignmentTime2, 'total_volume') ?? 0;
        $totalVolumeTime3 = data_get($consignmentTime3, 'total_volume') ?? 0;
        $quantityVolumeChange = $totalVolumeTime3 - $totalVolumeTime2;
        $percentVolume = $totalVolumeTime2 > 0 ? round($quantityVolumeChange*100/$totalVolumeTime2) : 0;
        /* Tỷ lệ*/
        $percentTime1 = $totalCustomerTime1 > 0 ? round($totalCustomerOrderTime1*100/$totalCustomerTime1) : 0;
        $percentTime2 = $totalCustomerTime2 > 0 ? round($totalCustomerOrderTime2*100/$totalCustomerTime2) : 0;
        $percentTime3 = $totalCustomerTime3 > 0 ? round($totalCustomerOrderTime3*100/$totalCustomerTime3) : 0;
        $quantityPercent = $percentTime3 - $percentTime2;
        $percentPercent = $percentTime2 > 0 ? round($quantityPercent*100/$percentTime2) : 0;

        $isDisplayTime1 =  'Thời gian tham chiếu 1';
        $isDisplayTime2 =  'Thời gian tham chiếu 2';
        $isDisplayTime3 =  'Thời gian xem';

        $data['format'] = [
            [
                'data_one' => '',
                'data_two' => $isDisplayTime1.' ( '.$startTime1 .' đến '.$endTime1.' )',
                'data_three' => $isDisplayTime2.' ( '.$startTime2 .' đến '.$endTime2.' )',
                'data_four' => $isDisplayTime3.' ( '.$startTime3 .' đến '.$endTime3.' )',
                'data_five' => 'Số lượng tăng/ giảm',
                'data_six' => '% Tăng trưởng',
            ],
            [
                'data_one' => 'Tổng khách kí gửi',
                'data_two' => number_format($totalCustomerTime1,0,',','.'),
                'data_three' => number_format($totalCustomerTime2,0,',','.'),
                'data_four' => number_format($totalCustomerTime3,0,',','.'),
                'data_five' => number_format($quantityTotalCustomerChange,0,',','.'),
                'data_six' => $percentTotalCustomer.'%',
            ],
            [
                'data_one' => 'KH Lên Đơn Kí Gửi',
                'data_two' => number_format($totalCustomerOrderTime1,0,',','.'),
                'data_three' => number_format($totalCustomerOrderTime2,0,',','.'),
                'data_four' => number_format($totalCustomerOrderTime3,0,',','.'),
                'data_five' => number_format($quantityCustomerOrderChange,0,',','.'),
                'data_six' => $percentCustomerOrder.'%',
            ],
            [
                'data_one' => 'KH Đã Nhận Hàng',
                'data_two' => number_format($totalCustomerReceivedGood1,0,',','.'),
                'data_three' => number_format($totalCustomerReceivedGood2,0,',','.'),
                'data_four' => number_format($totalCustomerReceivedGood3,0,',','.'),
                'data_five' => number_format($quantityCustomerReceivedGood,0,',','.'),
                'data_six' => $percentCustomerReceivedGood.'%',
            ],
            [
                'data_one' => 'Phí Vận Chuyển Kí Gửi',
                'data_two' => number_format($serviceChargeTime1,0,',','.'),
                'data_three' => number_format($serviceChargeTime2, 0,',','.') ,
                'data_four' => number_format($serviceChargeTime3,0,',','.'),
                'data_five' => number_format($quantityServiceCharge,0,',','.'),
                'data_six' => $percentServiceCharge.'%',
            ],
            [
                'data_one' => 'Tổng Cân Nặng (kg)',
                'data_two' => round($totalWeightTime1, 3),
                'data_three' => round($totalWeightTime2,3),
                'data_four' => round($totalWeightTime3, 3),
                'data_five' => round($quantityWeightChange, 3),
                'data_six' => ($percentWeight).'%'
            ],
            [
                'data_one' => 'Tổng thể tích (m3)',
                'data_two' => round($totalVolumeTime1, 3),
                'data_three' => round($totalVolumeTime2, 3),
                'data_four' => round($totalVolumeTime3, 3),
                'data_five' => round($quantityVolumeChange, 3),
                'data_six' => ($percentVolume).'%',
            ],
            [
                'data_one' => 'Tỉ lệ khách Kí Gửi',
                'data_two' => ($percentTime1).'%',
                'data_three' => ($percentTime2).'%',
                'data_four' => ($percentTime3).'%',
                'data_five' => ($quantityPercent).'%',
                'data_six' => ($percentPercent).'%',
            ],
        ];
        $tableFirst =
            [
                [
                    'name' => $isDisplayTime1,
                    'data' => [$totalCustomerTime1, $totalCustomerOrderTime1]
                ],
                [
                    'name' => $isDisplayTime2,
                    'data' => [$totalCustomerTime2, $totalCustomerOrderTime2]
                ],
                [
                    'name' => $isDisplayTime3,
                    'data' => [$totalCustomerTime3, $totalCustomerOrderTime3]
                ]
            ];
        $tableSecond =
            [
                [
                    'name' => $isDisplayTime1,
                    'data' => [(int)$serviceChargeTime1]
                ],
                [
                    'name' => $isDisplayTime2,
                    'data' => [(int)$serviceChargeTime2]
                ],
                [
                    'name' => $isDisplayTime3,
                    'data' => [(int)$serviceChargeTime3]
                ]
            ];
        $data['tableFirst'] = $tableFirst;
        $data['tableSecond'] = $tableSecond;
        return $data;
    }

    private function getTotalCustomer($start_date, $end_date, $label_ids = [])
    {
        $kiGuiDaLenDon = Label::query()
            ->select('id')
            ->where('code', 'KI_GUI_DA_LEN_DON')
            ->pluck('id')
            ->toArray();
        $kiGui = Label::query()
            ->where('code', 'KI_GUI')
            ->pluck('id')
            ->toArray();
        $groupId = data_get(request()->all(), 'group_id');
        $month_start = date('m/Y', strtotime($start_date));
        $month_end = date('m/Y', strtotime($end_date));
        $user_group_ids = $this->getUserGroup($groupId, ['month' => [$month_start, $month_end]]);
        $start = Carbon::createFromFormat('d-m-Y', $start_date)->startOfDay();
        $end = Carbon::createFromFormat('d-m-Y', $end_date)->endOfDay();
        $query = Customer::query();
        $sql1 = $query->clone()->select(DB::raw('COUNT(DISTINCT customers.id) AS total_customer'))
            ->join('customer_label', function($join) use ($kiGui){
                $join->on('customer_label.customer_id', '=', 'customers.id')
                    ->whereIn('customer_label.label_id', $kiGui);
            }
            )
            ->whereBetween('customers.created_at',[$start, $end])
            ->when($groupId, function($q) use ($user_group_ids){
                return $q->whereIn('customers.staff_group_id', $user_group_ids);
            })
            ->when(request()->input('source'), function($q) {
                $q->where('customers.source', request()->input('source'));
            })
            ->where('customers.status', 1)->first();
        $sql2 = $query->clone()->select(DB::raw('COUNT(DISTINCT customers.id) AS total_customer'))
            ->join('customer_label', function($join) use ($kiGuiDaLenDon){
                $join->on('customer_label.customer_id', '=', 'customers.id')
                    ->whereIn('customer_label.label_id', $kiGuiDaLenDon);
            }
            )
            ->join('order_package', function($join) use ($start, $end){
                $join->on('order_package.customer_id', '=', 'customers.id')
                    ->whereBetween('order_package.created_at',[$start, $end])
                    ->where('order_package.order_type', Consignment::class);
            }
            )
            ->when($groupId, function($q) use ($user_group_ids){
                return $q->whereIn('customers.staff_group_id', $user_group_ids);
            })
            ->when(request()->input('source'), function($q) {
                $q->where('customers.source', request()->input('source'));
            })
            ->where('customers.status', 1)->first();
        return (data_get($sql1, 'total_customer') ?? 0) + (data_get($sql2, 'total_customer') ?? 0);
    }
    private function getTotalCustomerOrder($start_date, $end_date, $label_id)
    {
        $groupId = data_get(request()->all(), 'group_id');
        $month_start = date('m/Y', strtotime($start_date));
        $month_end = date('m/Y', strtotime($end_date));
        $user_group_ids = $this->getUserGroup($groupId, ['month' => [$month_start, $month_end]]);
        $start = Carbon::createFromFormat('d-m-Y', $start_date)->startOfDay();
        $end = Carbon::createFromFormat('d-m-Y', $end_date)->endOfDay();
        $query = Customer::query();
        $query = $query
            ->select(
                DB::raw('COUNT(DISTINCT order_package.customer_id) as total_customer_order'),
                DB::raw('SUM(order_package.international_shipping_cost) as fee_ship'),
                DB::raw("SUM(IF(order_package.type_quantitative='weight',order_package.weight,0)) as total_weight"),
                DB::raw("SUM(CASE
                            WHEN order_package.type_quantitative = 'weight' THEN 0
                            WHEN order_package.volume_enter > 0 THEN order_package.volume_enter
                            ELSE (order_package.height/100 * order_package.length/100 * order_package.width/100) * IF(order_package.package_number IS NULL OR order_package.package_number < 1,1,order_package.package_number)
                            END) AS total_volume"
                )
            )
            ->join('order_package','order_package.customer_id','=','customers.id')
            ->where('order_package.status', PackageConstant::STATUS_RECEIVED_GOODS)
            ->where('order_package.order_type', Consignment::class)
            ->whereBetween('order_package.date_done',[$start, $end])
            ->whereNull('order_package.deleted_at')
            ->when(request()->input('source'), function($q) {
                $q->where('customers.source', request()->input('source'));
            })
            ->when($groupId, function($q) use ($user_group_ids){
                return $q->whereIn('customers.staff_group_id', $user_group_ids);
            });
        if (data_get(request()->all(), 'getTotalCustomerOrder')) {
            dd($query->toSql(), $query->getBindings());
        }
        return $query->first();
    }

    private function getTotalCustomerReceivedGoods($start_date, $end_date)
    {
        $groupId = data_get(request()->all(), 'group_id');
        $month_start = date('m/Y', strtotime($start_date));
        $month_end = date('m/Y', strtotime($end_date));
        $user_group_ids = $this->getUserGroup($groupId, ['month' => [$month_start, $month_end]]);
        $start = Carbon::createFromFormat('d-m-Y', $start_date)->startOfDay();
        $end = Carbon::createFromFormat('d-m-Y', $end_date)->endOfDay();
        $query = Customer::query();
        $sql = $query
            ->select(DB::raw('COUNT(DISTINCT customers.id) AS total_customer'))
            ->join('order_package', function($join) use ($start, $end){
                $join->on('order_package.customer_id', '=', 'customers.id')
                    ->where('order_package.status', PackageConstant::STATUS_RECEIVED_GOODS)
                    ->whereBetween('order_package.date_done',[$start, $end])
                    ->whereNull('order_package.deleted_at')
                    ->where('order_package.order_type', Consignment::class);
            }
            )
            ->when($groupId, function($q) use ($user_group_ids){
                return $q->whereIn('customers.staff_group_id', $user_group_ids);
            })
            ->when(request()->input('source'), function($q) {
                $q->where('customers.source', request()->input('source'));
            })
            ->where('customers.status', 1);
        if (data_get(request()->all(), 'getTotalCustomerReceivedGoods')) {
            dd($sql->toSql(), $sql->getBindings());
        }
        return data_get($sql->first(), 'total_customer') ?? 0;
    }

    private function getTotalCustomerConsignment($start_date, $end_date)
    {
        $groupId = data_get(request()->all(), 'group_id');
        $month_start = date('m/Y', strtotime($start_date));
        $month_end = date('m/Y', strtotime($end_date));
        $user_group_ids = $this->getUserGroup($groupId, ['month' => [$month_start, $month_end]]);
        $start = Carbon::createFromFormat('d-m-Y', $start_date)->startOfDay();
        $end = Carbon::createFromFormat('d-m-Y', $end_date)->endOfDay();
        $query = Customer::query();
        $sql = $query
            ->select(DB::raw('COUNT(DISTINCT customers.id) AS total_customer'))
            ->join('order_package', function($join) use ($start, $end){
                $join->on('order_package.customer_id', '=', 'customers.id')
                    ->whereBetween('order_package.created_at',[$start, $end])
                    ->whereNull('order_package.deleted_at')
                    ->where('order_package.order_type', Consignment::class);
            }
            )
            ->when($groupId, function($q) use ($user_group_ids){
                return $q->whereIn('customers.staff_group_id', $user_group_ids);
            })
            ->when(request()->input('source'), function($q) {
                $q->where('customers.source', request()->input('source'));
            })
            ->where('customers.status', 1)->first();
        return data_get($sql, 'total_customer') ?? 0;
    }
}
