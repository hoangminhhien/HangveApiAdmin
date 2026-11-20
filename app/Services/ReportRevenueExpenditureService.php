<?php

namespace App\Services;

use App\Jobs\Report\InsertDataReportRevenueExpenditureDateJob;
use App\Jobs\Report\InsertDataReportRevenueExpenditureMonthJob;
use App\Jobs\Report\InsertDataReportRevenueExpenditureQuarterJob;
use App\Jobs\Report\InsertDataReportRevenueExpenditureWeekJob;
use App\Jobs\Report\InsertDataReportRevenueExpenditureYearJob;
use App\Models\FundTypePay;
use App\Models\ReportRevenueExpenditure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ReportRevenueExpenditureService
{
    protected ReportService $service;
    const THU = 1;
    const CHI = 2;
    const DAY = 'DAY';
    const WEEK = 'WEEK';
    const MONTH = 'MONTH';
    const QUARTER = 'QUARTER';
    const YEAR = 'YEAR';
    function __construct(ReportService $service)
    {
        $this->service = $service;
    }

    public static function calculatorDateQuarter($month): array
    {
        if (in_array($month, [1, 2, 3])) return [
            'month' => [1, 2, 3],
            'quarter' => 1
        ];
        if (in_array($month, [4, 5, 6])) return [
            'month' => [4, 5, 6],
            'quarter' => 2
        ];
        if (in_array($month, [7, 8, 9])) return [
            'month' => [7, 8, 9],
            'quarter' => 3
        ];
        if (in_array($month, [10, 11, 12])) return [
            'month' => [10, 11, 12],
            'quarter' => 4
        ];
        return [
            'month' => [],
            'quarter' => 0
        ];
    }

    public function syncReportRevenueExpenditureByTime($params): JsonResponse
    {
        dispatch(new InsertDataReportRevenueExpenditureDateJob($params))
            ->onQueue('sync-data-report')->delay(1);
        dispatch(new InsertDataReportRevenueExpenditureWeekJob($params))
            ->onQueue('sync-data-report')->delay(1);
        dispatch(new InsertDataReportRevenueExpenditureMonthJob($params))
            ->onQueue('sync-data-report')->delay(1);
        dispatch(new InsertDataReportRevenueExpenditureYearJob($params))
            ->onQueue('sync-data-report')->delay(1);
        dispatch(new InsertDataReportRevenueExpenditureQuarterJob($params))
            ->onQueue('sync-data-report')->delay(1);
        return resSuccess();
    }

    public function cronjobInsertData($request): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $startDate = date('Y-m-d', strtotime($list->date)). ' 00:00:00';
        $endDate = date('Y-m-d', strtotime($list->date)). ' 23:59:59';
        $calculatorDateQuarter = self::calculatorDateQuarter($list->month);
        $quarter = $calculatorDateQuarter['quarter'];
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'date' => $startDate,
            'week' => $list->week,
            'month' => $list->month,
            'quarter' => $quarter,
            'year' => $list->year,
            'type' => self::DAY
        ];
        $results = $this->mappingData($params);
        foreach ($results as $result) {
            ReportRevenueExpenditure::query()->updateOrCreate(
                [
                    'fund_id' => $result['fund_id'],
                    'fund_type' => $result['fund_type'],
                    'year' => $list->year,
                    'month' => $list->month,
                    'week' => $list->week,
                    'date' => $startDate,
                    'quarter' => $quarter,
                    'type' => self::DAY
                ],
                $result);
        }
        return true;
    }

    public function cronjobInsertDataWeek($request): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($list->year, $list->month, $list->week);
        $weekStartDate = $calculatorDatetime['week_start_date'];
        $weekEndDate = $calculatorDatetime['week_end_date'];
        $calculatorDateQuarter = self::calculatorDateQuarter($list->month);
        $quarter = $calculatorDateQuarter['quarter'];
        $params = [
            'start_date' => $weekStartDate,
            'end_date' => $weekEndDate,
            'date' => null,
            'week' => $list->week,
            'month' => $list->month,
            'quarter' => $quarter,
            'year' => $list->year,
            'type' => self::WEEK
        ];
        $results = $this->mappingData($params);
        foreach ($results as $result) {
            ReportRevenueExpenditure::query()->updateOrCreate(
                [
                    'fund_id' => $result['fund_id'],
                    'fund_type' => $result['fund_type'],
                    'year' => $list->year,
                    'month' => $list->month,
                    'week' => $list->week,
                    'quarter' => $quarter,
                    'type' => self::WEEK
                ],
                $result);
        }
        return true;
    }

    public function cronjobInsertDataMonth($request): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $fistDayOfMonth = date("Y-m-d", strtotime("first day of ".$list->year . "-" . $list->month));
        $lastDayOfMonth = date("Y-m-d", strtotime("last day of ".$list->year . "-" . $list->month));
        $calculatorDateQuarter = self::calculatorDateQuarter($list->month);
        $quarter = $calculatorDateQuarter['quarter'];
        $params = [
            'start_date' => $fistDayOfMonth,
            'end_date' => $lastDayOfMonth,
            'date' => null,
            'week' => 0,
            'month' => $list->month,
            'quarter' => $quarter,
            'year' => $list->year,
            'type' => self::MONTH
        ];
        $results = $this->mappingData($params);
        foreach ($results as $result) {
            ReportRevenueExpenditure::query()->updateOrCreate(
                [
                    'fund_id' => $result['fund_id'],
                    'fund_type' => $result['fund_type'],
                    'year' => $list->year,
                    'month' => $list->month,
                    'quarter' => $quarter,
                    'type' => self::MONTH
                ],
                $result);
        }
        return true;
    }

    public function cronjobInsertDataQuarter($request): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $calculatorDateQuarter = self::calculatorDateQuarter($list->month);
        $fistMonthOfQuarter = $calculatorDateQuarter['month'][0];
        $lastMonthOfQuarter = $calculatorDateQuarter['month'][2];
        $quarter = $calculatorDateQuarter['quarter'];
        $fistDayOfFistMonthOfQuarter = date("Y-m-d", strtotime("first day of ".$list->year . "-" . $fistMonthOfQuarter));
        $lastDayOfLastMonthOfQuarter = date("Y-m-d", strtotime("last day of ".$list->year . "-" . $lastMonthOfQuarter));
        $params = [
            'start_date' => $fistDayOfFistMonthOfQuarter,
            'end_date' => $lastDayOfLastMonthOfQuarter,
            'date' => null,
            'week' => 0,
            'month' => 0,
            'quarter' => $quarter,
            'year' => $list->year,
            'type' => self::QUARTER
        ];
        $results = $this->mappingData($params);
        foreach ($results as $result) {
            ReportRevenueExpenditure::query()->updateOrCreate(
                [
                    'fund_id' => $result['fund_id'],
                    'fund_type' => $result['fund_type'],
                    'year' => $list->year,
                    'quarter' => $quarter,
                    'type' => self::QUARTER
                ],
                $result);
        }
        return true;
    }

    public function cronjobInsertDataYear($request): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $fistDayOfJanuary = date("Y-m-d", strtotime("first day of ".$list->year . "-" . 1));
        $lastDayOfDecember = date("Y-m-d", strtotime("last day of ".$list->year . "-" . 12));
        $params = [
            'start_date' => $fistDayOfJanuary,
            'end_date' => $lastDayOfDecember,
            'date' => null,
            'week' => 0,
            'month' => 0,
            'quarter' => 0,
            'year' => $list->year,
            'type' => self::YEAR
        ];
        $results = $this->mappingData($params);
        foreach ($results as $result) {
            ReportRevenueExpenditure::query()->updateOrCreate(
                [
                    'fund_id' => $result['fund_id'],
                    'fund_type' => $result['fund_type'],
                    'year' => $list->year,
                    'type' => self::YEAR
                ],
                $result);
        }
        return true;
    }

    private function mappingData($params): array
    {
        $results = $this->getSql($params['start_date'], $params['end_date']);
        $import = [];
        foreach ($results as $item) {
            $item = $item->toArray();
            $import[] = [
                'fund_id' => $item['id'] ?? '',
                'fund_name' => $item['name'] ?? '',
                'fund_type' => $item['type_pay'] ?? '',
                'fund_price' => $item['total'] ?? '',
                'week' => $params['week'] ?? 0,
                'month' => $params['month'] ?? 0,
                'quarter' => $params['quarter'] ?? 0,
                'year' => $params['year'] ?? 0,
                'date' => $params['date'] ?? null,
                'type' => $params['type'] ?? ''
            ];
        }
        return $import;
    }

    private function getSql($startDate, $endDate): LazyCollection
    {
        return FundTypePay::query()
            ->select
            (
        'fund_type_pays.name',
                'fund_type_pays.id',
                'fund_transactions.type_pay',
                DB::raw('SUM(fund_transactions.money_update) as total')
            )
            ->join('fund_transactions','fund_transactions.fund_type_pay_id','=','fund_type_pays.id')
            ->whereBetween('fund_transactions.updated_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('fund_type_pays.status', 1)
            ->whereIn('fund_transactions.type_pay', [self::THU, self::CHI])
            ->groupBy('fund_type_pays.id','fund_type_pays.name','fund_transactions.type_pay')->cursor();
    }
    public function deleteDay($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $startDate = date('Y-m-d', strtotime($list->date)). ' 00:00:00';
        $endDate = date('Y-m-d', strtotime($list->date)). ' 23:59:59';
        return ReportRevenueExpenditure::query()->where('year', $list->year)
            ->where('type',self::DAY)
            ->where('month', $list->month)
            ->where('week', $list->week)
            ->whereBetween('created_at',[$startDate, $endDate])
            ->delete();
    }
    public function deleteWeek($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return ReportRevenueExpenditure::query()->where('year', $list->year)
            ->where('type',self::WEEK)
            ->where('month', $list->month)
            ->where('week', $list->week)
            ->delete();
    }
    public function deleteMonth($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return ReportRevenueExpenditure::query()->where('year', $list->year)
            ->where('type',self::MONTH)
            ->where('month', $list->month)
            ->delete();
    }
    public function deleteQuarter($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $calculatorDateQuarter = self::calculatorDateQuarter($list->month);
        $quarter = $calculatorDateQuarter['quarter'];
        return ReportRevenueExpenditure::query()->where('year', $list->year)
            ->where('type',self::QUARTER)
            ->where('quarter', $quarter)
            ->delete();
    }

    public function deleteYear($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return ReportRevenueExpenditure::query()->where('year', $list->year)
            ->where('type',self::YEAR)
            ->delete();
    }

    public function insert($data){
        try {
            ReportRevenueExpenditure::query()->insert($data);
            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function getReport($request): array
    {
        $query = ReportRevenueExpenditure::query();
        $query = $query->select('fund_name','fund_type','fund_price');
        $type = $request->type ?? '';
        if ($type == self::YEAR) {
            $query->where('type', self::YEAR)->where('year', date('Y'));
        }
        if ($type == self::QUARTER) {
            $list = CalculatorWeekReport::getWeekMonthYear($request);
            $calculatorDateQuarter = self::calculatorDateQuarter($list->month);
            $quarter = $calculatorDateQuarter['quarter'];
            $query->where('type', self::QUARTER)
                ->where('quarter', $quarter)
                ->where('year', $list->year);
        }
        if ($type == self::MONTH) {
            $query->where('type', self::MONTH)
                ->where('year', date('Y'))
                ->where('month', date('m'))
            ;
        }
        if ($type == self::WEEK) {
            $list = CalculatorWeekReport::getWeekMonthYear($request);
            $query->where('type', self::WEEK)
                ->where('year', date('Y'))
                ->where('month', date('m'))
                ->where('week', $list->week)
            ;
        }
        if ($type == self::DAY) {
            $query->where('type', self::DAY)
                ->whereBetween('date', [date('Y-m-d') . ' 00:00:00', date('Y-m-d') . ' 23:59:59'])
            ;
        }
        $results = $query->get();
        $revenue = $results->filter(function ($item) {
            return $item->fund_type == 1;
        })->values();
        $expenditure = $results->filter(function ($item) {
            return $item->fund_type == 2;
        })->values();
        $totalRevenue = $revenue->sum('fund_price') ?? 0;
        $totalExpenditure= $expenditure->sum('fund_price') ?? 0;
        return [
            'revenue' => $revenue,
            'expenditure' => $expenditure,
            'totalRevenue' => number_format($totalRevenue),
            'totalExpenditure' => number_format($totalExpenditure)
        ];
    }
}
