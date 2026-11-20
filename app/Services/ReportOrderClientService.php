<?php

namespace App\Services;

use App\Abstracts\AbstractBaseService;
use App\Constants\OrderConstant;
use App\Jobs\ReportOrderClient\ReportOrderClientDateJob;
use App\Jobs\ReportOrderClient\ReportOrderClientMonthJob;
use App\Jobs\ReportOrderClient\ReportOrderClientYearJob;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportOrderClientService extends AbstractBaseService
{

    protected function setModel()
    {
        $namespaceModel = 'App\Models\\' . str_replace('Service', '', class_basename($this));
        $this->_model = class_exists($namespaceModel) ? new $namespaceModel() : null;
    }

    public function syncReport($params): JsonResponse
    {
        dispatch(new ReportOrderClientDateJob($params))
            ->onQueue('sync-data-report')->delay(1);
        dispatch(new ReportOrderClientMonthJob($params))
            ->onQueue('sync-data-report')->delay(1);
        dispatch(new ReportOrderClientYearJob($params))
            ->onQueue('sync-data-report')->delay(1);
        return resSuccess();
    }

    public function calculatorReportDay($params): bool
    {
        if (!empty($params['date'])) {
            $start_date = date('Y-m-d', strtotime($params['date']));
        } else {
            $start_date = date('Y-m-d');
        }
        $list = CalculatorWeekReport::getWeekMonthYear($params);
        $subDay = date('Y-m-d', strtotime($start_date. ' -14 days'));
        $dateRanger = CalculatorWeekReport::dateRangeV2($start_date, $subDay);
        foreach ($dateRanger as $ranger) {
            $startDate = $ranger . ' 00:00:00';
            $sendDate = $ranger . ' 23:59:59';
            if ((int)date('m', strtotime($ranger)) != $list->month) {
                $list = CalculatorWeekReport::getWeekMonthYear(['date' => $startDate]);
            }
            if (strtotime($ranger) == strtotime($start_date)) {
                $result = $this->processData($startDate, $sendDate, $params['customer_id']);
                $data = [
                    'type' => 'date',
                    'customer_id' => $params['customer_id'],
                    'time' => strtotime($startDate),
                    'date' => $startDate,
                    'month' => $list->month,
                    'year' => $list->year,
                    'total_order_done' => $result['total_order_done'] ?? 0,
                    'total_order_deposited' => $result['total_order_deposited'] ?? 0,
                ];
                $fieldFind = [
                    'type' => 'date',
                    'customer_id' => $params['customer_id'],
                    'date' => $startDate,
                    'month' => $list->month,
                    'year' => $list->year
                ];
                $this->updateOrCreate($fieldFind, $data);
            } else {
                if (
                    !$this->_model->query()
                        ->where([
                            'type' => 'date',
                            'customer_id' => $params['customer_id'],
                            'date' => $startDate,
                            'month' => $list->month,
                            'year' => $list->year
                        ])
                        ->first()
                ) {
                    $result = $this->processData($startDate, $sendDate, $params['customer_id']);
                    $data = [
                        'type' => 'date',
                        'customer_id' => $params['customer_id'],
                        'time' => strtotime($startDate),
                        'date' => $startDate,
                        'month' => $list->month,
                        'year' => $list->year,
                        'total_order_done' => $result['total_order_done'] ?? 0,
                        'total_order_deposited' => $result['total_order_deposited'] ?? 0,
                    ];
                    $this->store($data);
                }
            }
        }
        return true;
    }
    public function calculatorReportMonth($params): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($params);
        $calculatorDatetime = CalculatorWeekReport::getStartAndEndDateOfMonth($params);
        $start_date = $calculatorDatetime->fistDayOfMonth . ' 00:00:00';
        $end_date = $calculatorDatetime->lastDayOfMonth . ' 23:59:59';
        $subMonth = CalculatorWeekReport::getMonthRanger(11, $start_date);
        $resultCurrent = $this->processData($start_date, $end_date, $params['customer_id']);
        $dataCurrent = [
            'type' => 'month',
            'date' => null,
            'customer_id' => $params['customer_id'],
            'time' => strtotime($start_date),
            'month' => $list->month,
            'year' => $list->year,
            'total_order_done' => $resultCurrent['total_order_done'] ?? 0,
            'total_order_deposited' => $resultCurrent['total_order_deposited'] ?? 0,
        ];
        $this->updateOrCreate([
            'type' => 'month',
            'customer_id' => $params['customer_id'],
            'month' => $list->month,
            'year' => $list->year
        ], $dataCurrent);
        foreach ($subMonth as $month) {
            $firstDate = '01-'.$month;
            $list = CalculatorWeekReport::getWeekMonthYear(['date' => $firstDate]);
            $calculatorDatetime = CalculatorWeekReport::getStartAndEndDateOfMonth(['date' => $firstDate]);
            $start_date = $calculatorDatetime->fistDayOfMonth . ' 00:00:00';
            $end_date = $calculatorDatetime->lastDayOfMonth . ' 23:59:59';
            if (
                !$this->_model->query()
                    ->where([
                        'type' => 'month',
                        'customer_id' => $params['customer_id'],
                        'month' => $list->month,
                        'year' => $list->year
                    ])
                    ->first()
            ) {
                $result = $this->processData($start_date, $end_date, $params['customer_id']);
                $data = [
                    'type' => 'month',
                    'date' => null,
                    'customer_id' => $params['customer_id'],
                    'time' => strtotime($start_date),
                    'month' => $list->month,
                    'year' => $list->year,
                    'total_order_done' => $result['total_order_done'] ?? 0,
                    'total_order_deposited' => $result['total_order_deposited'] ?? 0,
                ];
                $this->store($data);
            }

        }
        return true;
    }

    public function calculatorReportYear($params): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($params);
        $start_date = $list->year.'-01-01 00:00:00';
        $end_date = $list->year.'-12-31 23:59:59';
        $subYear = CalculatorWeekReport::getYearRanger(2, $start_date);
        $result = $this->processData($start_date, $end_date, $params['customer_id']);
        $data = [
            'type' => 'year',
            'customer_id' => $params['customer_id'],
            'date' => null,
            'time' => strtotime($start_date),
            'month' => 0,
            'year' => $list->year,
            'total_order_done' => $result['total_order_done'] ?? 0,
            'total_order_deposited' => $result['total_order_deposited'] ?? 0,
        ];
        $this->updateOrCreate([
            'customer_id' => $params['customer_id'],
            'year' => $list->year,
            'type' => 'year',
        ], $data);
        foreach ($subYear as $year) {
            $start_date = $year.'-01-01 00:00:00';
            $end_date = $year.'-12-31 23:59:59';
            if (
                !$this->_model->query()
                ->where([
                    'type' => 'year',
                    'customer_id' => $params['customer_id'],
                    'year' => $year
                ])
                ->first())
            {
                $resultSub = $this->processData($start_date, $end_date, $params['customer_id']);
                $dataSub = [
                    'type' => 'year',
                    'customer_id' => $params['customer_id'],
                    'date' => null,
                    'time' => strtotime($start_date),
                    'month' => 0,
                    'year' => $year,
                    'total_order_done' => $resultSub['total_order_done'] ?? 0,
                    'total_order_deposited' => $resultSub['total_order_deposited'] ?? 0,
                ];
                $this->store($dataSub);
            }
        }
        return true;
    }

    private function getSql($start_date, $end_date, $order_status, $customer_id)
    {
        $sql = Order::query();
        $sql->select('customer_id', DB::raw('COUNT(*) as total'));
        $sql->where('customer_id', $customer_id);
        if ($order_status == OrderConstant::KEY_STATUS_DEPOSITED) {
            $sql->where('status',OrderConstant::KEY_STATUS_DEPOSITED);
            $sql->whereHas('orderStatusTime', function ($orderStatusTime) use ($start_date, $end_date) {
                $orderStatusTime->whereBetween('status_2', [$start_date, $end_date]);
            });
        }
        if ($order_status == OrderConstant::KEY_STATUS_DONE) {
            $sql->where('status',OrderConstant::KEY_STATUS_DONE);
            $sql->whereBetween('date_done', [$start_date, $end_date]);
        }
        return $sql->groupBy('customer_id')->first();
    }

    private function processData($start_date, $end_date, $customer_id): array
    {
        $resultOrderDone = $this->getSql(
            $start_date,
            $end_date,
            OrderConstant::KEY_STATUS_DONE, $customer_id
        );
        $resultOrderDeposited = $this->getSql(
            $start_date,
            $end_date,
            OrderConstant::KEY_STATUS_DEPOSITED, $customer_id
        );
        $total_order_done = $resultOrderDone->total ?? 0;
        $total_order_deposited = $resultOrderDeposited->total ?? 0;
        return [
            'total_order_done' => $total_order_done,
            'total_order_deposited' => $total_order_deposited
        ];
    }
}
