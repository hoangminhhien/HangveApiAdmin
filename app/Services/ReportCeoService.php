<?php


namespace App\Services;

use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\ReportConstant;
use App\Constants\TimeConstant;
use App\Helpers\AccountingHelper;
use App\Models\Category;
use App\Models\Consignment;
use App\Models\Fund;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderPackage;
use App\Models\Province;
use App\Models\ReportCustomer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class ReportCeoService implements Service
{
    public function getChartWarehouses(?string $warehouseId = null): array
    {
        $provinces = Province::query()->whereIn(
            'name',
            ['Hà Nội', 'Hải Phòng', 'Hồ Chí Minh']
        )->pluck('name', 'id')->all();
        $data = [];
        $orders = Order::query()->selectRaw('month(orders.created_at) month, count(*) count, warehouses.province_id as province_id')
                ->join('warehouses', 'warehouses.id', 'orders.warehouse_id')
                ->whereIn('warehouses.province_id', array_keys($provinces))->whereYear('orders.created_at', date('Y'))
                ->groupBy(['month', 'province_id'])
                ->get();
        $consignments = Consignment::query()->selectRaw('month(consignments.created_at) month, count(*) count, warehouses.province_id as province_id')
                ->join('warehouses', 'warehouses.id', 'consignments.warehouse_vi')
                ->whereIn('warehouses.province_id', array_keys($provinces))->whereYear('consignments.created_at', date('Y'))
                ->groupBy(['month', 'province_id'])
                ->get();
        foreach($provinces as $id => $province) {
            $data[$id] = ['name' => $province];
            $orderProvince = $orders->filter(function ($item) use ($id){
                return $item->province_id == $id;
            });
            $consignmentProvince = $consignments->filter(function ($item) use ($id){
                return $item->province_id == $id;
            });
            for ($i = 1; $i < 13; $i++) {
                $or = $orderProvince->filter(function ($item) use ($i) {
                    return $item->month == $i;
                })->first();
                $co = $consignmentProvince->filter(function ($item) use ($i) {
                    return $item->month == $i;
                })->first();
                $data[$id]['data'][] = data_get($or, 'count', 0) + data_get($co, 'count', 0);
            }
        }

        return array_values($data);
    }

    public function getChartCategories($params = []): array
    {
        $type = $params['type'] ?? 'day';
        $year = $params['year'] ?? (int) date('Y');
        $start_date = $year.'-'.date('m-d') . ' 00:00:00';
        $end_date = $year.'-'.date('m-d') . ' 23:59:59';
       /* $range = $this->getFromTo($type);*/
        if ($type != 'day') {
            $start_date = $year.'-'.date('m') . '-01 00:00:00';
            $end_date = $year.'-'.date('m') . '-31 23:59:59';
        }
        $report = OrderPackage::query()
            ->withoutGlobalScopes()
            ->whereNull('order_package.deleted_at')
            ->whereBetween('order_package.created_at', [$start_date, $end_date])->join(
            'categories',
            'categories.id',
            '=',
            'order_package.category_id'
        )
        ->selectRaw(
            'count(*) as count, categories.name'
        )
        ->groupBy('order_package.category_id')->orderByDesc('count')->get();

        return request()->query('type') && request()->query('type') == 'percent' ? $this->getChartCategoriesByPercent(
            $report, $type
        ) : $this->getChartCategoriesByCount($report);
    }

    private function getFromTo(?string $type = 'day', ?int $subtract = 0): array
    {
        $from = Carbon::now()->subDays($subtract)->startOfDay()->format(TimeConstant::DATETIME);
        $to = Carbon::now()->subDays($subtract)->endOfDay()->format(TimeConstant::DATETIME);
        if ($type != 'day') {
            $from = Carbon::now()->subMonths($subtract)->startOfMonth()->format(TimeConstant::DATETIME);
            $to = Carbon::now()->subMonths($subtract)->endOfMonth()->format(TimeConstant::DATETIME);
        }
        return [$from, $to];
    }

    private function getChartCategoriesByPercent(Collection $report, string $type): array
    {
        $data = [];
        $total = $report->sum('count');
        foreach ($report as $item) {
            $data[$item->category_id] = [
                'name' => $item->name,
                'quantity' => $item->count,
                'percent' => roundXPrecision($item->count / $total * 100),
                'quantity_old' => 0
            ];
            if (count($data) == 7) {
                break;
            }
        }
        if (count($data) <= 7) {
            foreach (
                Category::query()->whereNotIn('id', $report->pluck('category_id')->all())->take(7 - count($data))->get(
                ) as $item
            ) {
                $data[$item->id] = [
                    'name' => $item->name,
                    'quantity' => 0,
                    'percent' => 0,
                    'quantity_old' => 0,
                ];
            }
        }
        foreach (
            OrderDetail::query()->whereBetween('created_at', $this->getFromTo($type, 1))->whereIn(
                'category_id',
                array_keys($data)
            )->selectRaw(
                'count(*) as count, category_id'
            )->groupBy('category_id')->get() as $item
        ) {
            $data[$item->category_id]['quantity_old'] = $item->count;
        }
        return array_values($data);
    }

    private function getChartCategoriesByCount(Collection $report): array
    {
        $data = [];
        foreach ($report as $item) {
            $data[] = [$item->name, $item->count];
            if (count($data) == 7) {
                break;
            }
        }
        if (count($data) <= 7) {
            foreach (
                Category::query()
                    ->whereNotIn('id', $report->pluck('category_id')->all())
                    ->take(7 - count($data))
                    ->get(
                ) as $item
            ) {
                $data[] = [$item->name, 0];
            }
        }
        return $data;
    }

    public function getFund(): array
    {
        $funds = Fund::query()->select('current_balance')
            ->where(['type_currency' => 2, 'unit_currency' => 2])
            ->cursor();
        $cn = 0;
        foreach ($funds as $fund) {
            $cn += $fund->current_balance;
        }
        $vn = $cn * getExchangeRate();
        return [
            'vnd' => round($vn),
            'cny' => $cn
        ];
    }

    public function totalAmountPayable(): float
    {
        $total_order_cost = Order::query()
            ->select('order_cost')
            ->whereIn('status', [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY])
            ->sum('order_cost');

        return (float)$total_order_cost;
    }

    public function totalAccountBalance(): float
    {
        return ReportCustomer::query()
            ->select('balance_amount')
            ->sum('balance_amount');
    }

    public function totalShippingWareHouseVnCeo(): float
    {
        $total_international_shipping_cost = OrderPackage::query()
            ->where('status', PackageConstant::STATUS_WAREHOUSE_VN)
            ->select('international_shipping_cost')
            ->sum('international_shipping_cost');
        return (float)$total_international_shipping_cost;
    }

    public function getTotalRevenue(array $params): array
    {
        $start = Carbon::now()->startOfYear()->subYear();
        $end = Carbon::now()->endOfYear();
        $current = (int) date('Y');
        if (!empty($params['year'])) {
            $current = $params['year'];
        }
        $last = $current - 1;
        if (!empty($params['year'])) {
            $start = $last . '-01-01 00:00:00';
            $end = $current . '-12-31 23:59:59';
        }
        $selectOrderFee =
            'year(date_done) year,
                sum(order_cost + order_fee + international_shipping_cost + inspection_cost + (china_shipping_cost * exchange_rate) - discount_cost)
                as total_order_fee';
        $orders = Order::query()
            ->selectRaw($selectOrderFee)
            ->where('status', OrderConstant::KEY_STATUS_DONE)
            ->whereBetween('date_done', [$start, $end])
            ->groupBy('year')->cursor();
        $data['current_year'] = 0;
        $data['last_year'] = 0;
        foreach ($orders as $order) {
            if ($order->year == $current) {
                $data['current_year'] += $order->total_order_fee;
            }
            if ($order->year == $last) {
                $data['last_year'] += $order->total_order_fee;
            }
        }
        $data['percent'] = $data['last_year'] > 0
            ? (($data['current_year'] - $data['last_year']) / $data['last_year']) * 100 : 0;
        return $data;
    }

    public function getTotalShippingReceivedGood(array $params = []): array
    {
        $start_date = Carbon::now()->startOfYear();
        $end_date = Carbon::now()->endOfYear();
        if (!empty($params['year'])) {
            $start_date = $params['year'].'-01-01 00:00:00';
            $end_date = $params['year'].'-12-31 23:59:59';
        }
        $orderPackages = OrderPackage::query()
            ->where('status', PackageConstant::STATUS_RECEIVED_GOODS)
            ->whereBetween('date_done', [$start_date, $end_date])
            ->select(
                DB::raw("SUM(CASE WHEN `type_quantitative` != 'weight' THEN (width * length * height / 1000000) ELSE 0 END) AS `total_volume`"),
                DB::raw("SUM(CASE WHEN `type_quantitative` = 'weight' THEN weight ELSE 0 END) AS `total_weight`"),
                DB::raw('SUM(international_shipping_cost) as total_international_shipping_cost')
            )->first();
        $total_volume = $orderPackages->total_volume ?? 0;
        $total_weight = $orderPackages->total_weight ?? 0;
        return [
            'package' =>[
                [
                    'key' => ReportConstant::SHIPPING_KG,
                    'value' => number_format(
                        (float)$total_weight
                        , 2
                        , '.'
                        , ''
                    ),
                    'unit' => 'kg'
                ],
                [
                    'key' => ReportConstant::SHIPPING_M3,
                    'value' => number_format(
                        (float)$total_volume
                        , 2
                        , '.'
                        , ''
                    ),
                    'unit' => 'm3'
                ]
            ],
            'total_shipping' => $orderPackages->total_international_shipping_cost ?? 0
        ];
    }

    public function totalFundVnCeo()
    {
        return Fund::query()->select('current_balance')
            ->where(['type_currency' => 2, 'unit_currency' => 1])
            ->whereNotIn('name', ['NH Trừ tiền', 'NH cho vay'])
            ->sum('current_balance');
    }

    public function totalRemainingAmountCeo()
    {
        return Order::query()->select('remaining_amount')->whereIn('status', [
            OrderConstant::KEY_STATUS_DEPOSITED,
            OrderConstant::KEY_STATUS_WAIT_TO_PAY,
            OrderConstant::KEY_STATUS_ORDERED
        ])->sum('remaining_amount');
    }

}
