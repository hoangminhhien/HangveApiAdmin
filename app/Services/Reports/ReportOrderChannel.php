<?php

namespace App\Services\Reports;

use App\Constants\OrderConstant;
use App\Models\OrderSupplier;
use App\Services\CalculatorWeekReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportOrderChannel
{
    public function chart(array $request): array
    {
        $request['select'] = [DB::raw('COUNT(DISTINCT order_supplier.order_id) as total'), 'source_type'];
        $responses = $this->buildSql($request)->get();
        $data = $this->getMappingData();
        foreach ($data as $key => &$item) {
            foreach ($responses as $response) {
                if ($response->source_type == $key) {
                    $item['total_order'] = $response->total;
                }
            }
        }
        unset($item);
        return array_values($data);
    }

    public function table(array $request): array
    {
        $data = $this->getMappingData();
        $request['select'] = [DB::raw('COUNT(DISTINCT order_supplier.order_id) as total'), 'source_type'];
        $totalOrder = $this->buildSql($request)->get()->toArray();
        $totalStatusDone = $this->totalStatusDone($request)->toArray();
        $totalStatusCancel = $this->totalStatusCancel($request)->toArray();
        $totalStatusDeposited = $this->totalStatusDeposited($request)->toArray();
        $totalStatusOrdered = $this->totalStatusOrdered($request)->toArray();
        $totalStatusWaitToPay = $this->totalStatusWaitToPay($request)->toArray();
        foreach ($data as $key => &$item) {
            foreach ($totalOrder as $total) {
                if ($total['source_type'] == $key) {
                    $item['total_order'] = $total['total'] ?? 0;
                }
            }
            foreach ($totalStatusDone as $totalDone) {
                if ($totalDone['source_type'] == $key) {
                    $item['total_status_done'] = $totalDone['total'] ?? 0;
                }
            }
            foreach ($totalStatusCancel as $totalCancel) {
                if ($totalCancel['source_type'] == $key) {
                    $item['total_status_cancel'] = $totalCancel['total'] ?? 0;
                }
            }
            foreach ($totalStatusDeposited as $totalDeposit) {
                if ($totalDeposit['source_type'] == $key) {
                    $item['total_status_deposited'] = $totalDeposit['total'] ?? 0;
                }
            }
            foreach ($totalStatusOrdered as $totalOrdered) {
                if ($totalOrdered['source_type'] == $key) {
                    $item['total_status_ordered'] = $totalOrdered['total'] ?? 0;
                }
            }
            foreach ($totalStatusWaitToPay as $totalWaitToPay) {
                if ($totalWaitToPay['source_type'] == $key) {
                    $item['total_status_wait_to_pay'] = $totalWaitToPay['total'] ?? 0;
                }
            }
            $per_cent = 0;
            $item['total_order'] += $item['total_status_ordered'];
            $item['total_order'] += $item['total_status_done'];
            $item['total_order'] += $item['total_status_deposited'];
            $item['total_order'] += $item['total_status_ordered'];
            $item['total_order'] += $item['total_status_wait_to_pay'];
            if ($item['total_order'] > 0) {
                $per_cent = round(
                    ($item['total_status_ordered'] + $item['total_status_deposited'] + $item['total_status_done']) * 100
                    / $item['total_order']);
            }
            $item['per_cent'] = $per_cent;
        }
        unset($item);

        return array_values($data);
    }

    private function getMappingData(): array
    {
        return [
            OrderSupplier::SOURCE_ADDON => [
                'total_order' => 0,
                'source_type_name' => OrderSupplier::LIST_SOURCE_TYPE[OrderSupplier::SOURCE_ADDON],
                'total_status_done' => 0,
                'total_status_cancel' => 0,
                'total_status_deposited' => 0,
                'total_status_ordered' => 0,
                'total_status_wait_to_pay' => 0,
            ],
            OrderSupplier::SOURCE_APP => [
                'total_order' => 0,
                'source_type_name' => OrderSupplier::LIST_SOURCE_TYPE[OrderSupplier::SOURCE_APP],
                'total_status_done' => 0,
                'total_status_cancel' => 0,
                'total_status_deposited' => 0,
                'total_status_ordered' => 0,
                'total_status_wait_to_pay' => 0,
            ],
            OrderSupplier::SOURCE_API_WEB => [
                'total_order' => 0,
                'source_type_name' => OrderSupplier::LIST_SOURCE_TYPE[OrderSupplier::SOURCE_API_WEB],
                'total_status_done' => 0,
                'total_status_cancel' => 0,
                'total_status_deposited' => 0,
                'total_status_ordered' => 0,
                'total_status_wait_to_pay' => 0,
            ],
            OrderSupplier::SOURCE_SEARCH_FORM => [
                'total_order' => 0,
                'source_type_name' => OrderSupplier::LIST_SOURCE_TYPE[OrderSupplier::SOURCE_SEARCH_FORM],
                'total_status_done' => 0,
                'total_status_cancel' => 0,
                'total_status_deposited' => 0,
                'total_status_ordered' => 0,
                'total_status_wait_to_pay' => 0,
            ],
        ];
    }

    private function totalStatusDone($request)
    {
        $request['status'] = OrderConstant::KEY_STATUS_DONE;
        return $this->buildSql($request)->get();
    }

    private function totalStatusCancel($request)
    {
        $request['status'] = OrderConstant::KEY_STATUS_CANCEL;
        return $this->buildSql($request)->get();
    }

    private function totalStatusWaitToPay($request)
    {
        $request['status'] = OrderConstant::KEY_STATUS_WAIT_TO_PAY;
        return $this->buildSql($request)->get();
    }

    private function totalStatusDeposited($request)
    {
        $request['status'] = OrderConstant::KEY_STATUS_DEPOSITED;
        return $this->buildSql($request)->get();
    }

    private function totalStatusOrdered($request)
    {
        $request['status'] = OrderConstant::KEY_STATUS_ORDERED;
        return $this->buildSql($request)->get();
    }


    private function buildSql(array $request): Builder
    {
        $query = OrderSupplier::query();
        $select = $request['select'] ?? '*';
        $query->whereIn('source_type', [
            OrderSupplier::SOURCE_ADDON,
            OrderSupplier::SOURCE_APP,
            OrderSupplier::SOURCE_API_WEB,
            OrderSupplier::SOURCE_SEARCH_FORM
        ]);
        $query->select($select);
        $status = $request['status'] ?? '';
        $table = 'order_supplier';
        $field = 'created_at';
        if ($status) {
            $table = 'order_status_times';
            $field = $status;
            $query->where('orders.status', $status);
            $query->join('orders', 'orders.id', '=', 'order_supplier.order_id');
            $query->join('order_status_times', 'order_status_times.order_id', '=', 'orders.id');
        }

        if (!empty($request['month']))
        {
            $array = explode('-', $request['month']);
            $year = $array[0] ?? date('Y');
            $month = $array[1] ?? date('m');
            $calculatorDatetime = CalculatorWeekReport::getStartAndEndDateOfMonth([
                'month' => $month,
                'year' => $year,
            ]);
            $fistDayOfMonth = $calculatorDatetime->fistDayOfMonth;
            $lastDayOfMonth = $calculatorDatetime->lastDayOfMonth ;
            $query->whereBetween($table.'.created_at', [$fistDayOfMonth . ' 00:00:00', $lastDayOfMonth . ' 23:59:59']);
        }
        else if (!empty($request['time']))
        {
            $currentDate = date('Y-m-d');
            $payload['date'] = $currentDate;
            $list = CalculatorWeekReport::getWeekMonthYear($payload);

            if ($request['time'] == 'day') {
                $startDate = $currentDate . ' 00:00:00';
                $endDate = $currentDate . ' 23:59:59';
            } else if ($request['time'] == 'week') {
                $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($list->year, $list->month, $list->week);
                $startDate = $calculatorDatetime['week_start_date'] . ' 00:00:00';
                $endDate = $calculatorDatetime['week_end_date'] . ' 23:59:59';
            } else if ($request['time'] == 'month') {
                $calculatorDatetime = CalculatorWeekReport::getStartAndEndDateOfMonth(['date' => $currentDate]);
                $startDate = $calculatorDatetime->fistDayOfMonth . ' 00:00:00';
                $endDate = $calculatorDatetime->lastDayOfMonth . ' 23:59:59';
            } else if ($request['time'] == 'year') {
                $startDate = $list->year.'-01-01 00:00:00';
                $endDate = $list->year.'-12-31 23:59:59';
            } else {
                $startDate = $currentDate . ' 00:00:00';
                $endDate = $currentDate . ' 23:59:59';
            }

            $query->whereBetween($table.'.'.$field, [$startDate, $endDate]);
        }

        $query->groupBy('source_type');

        return $query;
    }
}
