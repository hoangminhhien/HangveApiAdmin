<?php

namespace App\Services;

use App\Abstracts\AbstractBaseService;
use App\Constants\TransactionConstant;
use App\Jobs\FinancialReport\FinancialReportDateJob;
use App\Jobs\FinancialReport\FinancialReportMonthJob;
use App\Jobs\FinancialReport\FinancialReportYearJob;
use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\LazyCollection;

class FinancialReportService extends AbstractBaseService
{

    protected function setModel()
    {
        $namespaceModel = 'App\Models\\' . str_replace('Service', '', class_basename($this));
        $this->_model = class_exists($namespaceModel) ? new $namespaceModel() : null;
    }

    public function syncReport($params): JsonResponse
    {
        dispatch(new FinancialReportDateJob($params))
            ->onQueue('sync-data-report')->delay(1);
        dispatch(new FinancialReportMonthJob($params))
            ->onQueue('sync-data-report')->delay(1);
        dispatch(new FinancialReportYearJob($params))
            ->onQueue('sync-data-report')->delay(1);
        return resSuccess();
    }

    private function getSql($start_date, $end_date, $customer_id)
    {
        $query = Customer::query();
        $query->select('id', 'name');
        $query->where('customers.id', $customer_id);
        $query->with(['transactions' => function ($transactions) use ($start_date, $end_date) {
            $transactions->select('customer_id', 'id', 'amount', 'status');
            $transactions->whereBetween('created_at', [$start_date, $end_date]);
            $transactions->whereIn('status', [
                TransactionConstant::STATUS_DEPOSIT,
                TransactionConstant::STATUS_WITHDRAWAL,
                TransactionConstant::STATUS_PURCHASE
            ]);
        }]);
        return $query->first();
    }

    private function processData($start_date, $end_date, $customer_id)
    {
        return $this->getSql($start_date, $end_date, $customer_id);
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
            $endDate = $ranger . ' 23:59:59';
            if ((int)date('m', strtotime($ranger)) != $list->month) {
                $list = CalculatorWeekReport::getWeekMonthYear(['date' => $startDate]);
            }
            if (strtotime($ranger) == strtotime($start_date)) {
                $result = $this->getSql(
                    $startDate ,
                    $endDate,
                    $params['customer_id']
                );
                $total_deposit = 0;
                $total_payment = 0;
                $total_withdrawal = 0;
                foreach ($result->transactions as $transaction) {
                    if ($transaction->status == TransactionConstant::STATUS_DEPOSIT) {
                        $total_deposit += $transaction->amount ?? 0;
                    }
                    if ($transaction->status == TransactionConstant::STATUS_WITHDRAWAL) {
                        $total_withdrawal += $result->amount ?? 0;
                    }
                    if ($transaction->status == TransactionConstant::STATUS_PURCHASE) {
                        $total_payment += $result->amount ?? 0;
                    }
                }
                $data = [
                    'type' => 'date',
                    'customer_id' => $result->id ?? 0,
                    'time' => strtotime($ranger),
                    'date' => $ranger,
                    'month' => $list->month,
                    'year' => $list->year,
                    'total_deposit' => $total_deposit,
                    'total_payment' => $total_payment,
                    'total_withdrawal' => $total_withdrawal,
                ];
                $this->updateOrCreate([
                    'type' => 'date',
                    'customer_id' => $result->id ?? 0,
                    'date' => $ranger,
                    'month' => $list->month,
                    'year' => $list->year
                ], $data);
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
                    $result = $this->getSql(
                        $startDate,
                        $endDate,
                        $params['customer_id']
                    );
                    $total_deposit = 0;
                    $total_payment = 0;
                    $total_withdrawal = 0;
                    foreach ($result->transactions as $transaction) {
                        if ($transaction->status == TransactionConstant::STATUS_DEPOSIT) {
                            $total_deposit += $transaction->amount ?? 0;
                        }
                        if ($transaction->status == TransactionConstant::STATUS_WITHDRAWAL) {
                            $total_withdrawal += $result->amount ?? 0;
                        }
                        if ($transaction->status == TransactionConstant::STATUS_PURCHASE) {
                            $total_payment += $result->amount ?? 0;
                        }
                    }
                    $data = [
                        'type' => 'date',
                        'customer_id' => $result->id ?? 0,
                        'time' => strtotime($ranger),
                        'date' => $ranger,
                        'month' => $list->month,
                        'year' => $list->year,
                        'total_deposit' => $total_deposit,
                        'total_payment' => $total_payment,
                        'total_withdrawal' => $total_withdrawal,
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

        $total_deposit = 0;
        $total_payment = 0;
        $total_withdrawal = 0;
        foreach ($resultCurrent->transactions as $transaction) {
            if ($transaction->status == TransactionConstant::STATUS_DEPOSIT) {
                $total_deposit += $transaction->amount ?? 0;
            }
            if ($transaction->status == TransactionConstant::STATUS_WITHDRAWAL) {
                $total_withdrawal += $result->amount ?? 0;
            }
            if ($transaction->status == TransactionConstant::STATUS_PURCHASE) {
                $total_payment += $result->amount ?? 0;
            }
        }
        $dataCurrent = [
            'type' => 'month',
            'date' => null,
            'customer_id' => $result->id ?? 0,
            'time' => strtotime($start_date),
            'month' => $list->month,
            'year' => $list->year,
            'total_deposit' => $total_deposit,
            'total_payment' => $total_payment,
            'total_withdrawal' => $total_withdrawal,
        ];
        $this->updateOrCreate([
            'type' => 'month',
            'customer_id' => $resultCurrent->id ?? 0,
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
                $total_deposit = 0;
                $total_payment = 0;
                $total_withdrawal = 0;
                foreach ($resultCurrent->transactions as $transaction) {
                    if ($transaction->status == TransactionConstant::STATUS_DEPOSIT) {
                        $total_deposit += $transaction->amount ?? 0;
                    }
                    if ($transaction->status == TransactionConstant::STATUS_WITHDRAWAL) {
                        $total_withdrawal += $result->amount ?? 0;
                    }
                    if ($transaction->status == TransactionConstant::STATUS_PURCHASE) {
                        $total_payment += $result->amount ?? 0;
                    }
                }
                $data = [
                    'type' => 'month',
                    'date' => null,
                    'customer_id' => $result->id ?? 0,
                    'time' => strtotime($start_date),
                    'month' => $list->month,
                    'year' => $list->year,
                    'total_deposit' => $total_deposit,
                    'total_payment' => $total_payment,
                    'total_withdrawal' => $total_withdrawal,
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
        $resultCurrent = $this->getSql($start_date, $end_date, $params['customer_id']);
        $total_deposit = 0;
        $total_payment = 0;
        $total_withdrawal = 0;
        foreach ($resultCurrent->transactions as $transaction) {
            if ($transaction->status == TransactionConstant::STATUS_DEPOSIT) {
                $total_deposit += $transaction->amount ?? 0;
            }
            if ($transaction->status == TransactionConstant::STATUS_WITHDRAWAL) {
                $total_withdrawal += $result->amount ?? 0;
            }
            if ($transaction->status == TransactionConstant::STATUS_PURCHASE) {
                $total_payment += $result->amount ?? 0;
            }
        }
        $dataCurrent = [
            'type' => 'year',
            'date' => null,
            'month' => 0,
            'year' => $list->year,
            'customer_id' => $resultCurrent->id ?? 0,
            'time' => strtotime($start_date),
            'total_deposit' => $total_deposit,
            'total_payment' => $total_payment,
            'total_withdrawal' => $total_withdrawal,
        ];
        $this->updateOrCreate([
            'type' => 'year',
            'customer_id' => $resultCurrent->id ?? 0,
            'year' => $list->year
        ], $dataCurrent);

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
                $total_deposit = 0;
                $total_payment = 0;
                $total_withdrawal = 0;
                foreach ($resultSub->transactions as $transaction) {
                    if ($transaction->status == TransactionConstant::STATUS_DEPOSIT) {
                        $total_deposit += $transaction->amount ?? 0;
                    }
                    if ($transaction->status == TransactionConstant::STATUS_WITHDRAWAL) {
                        $total_withdrawal += $result->amount ?? 0;
                    }
                    if ($transaction->status == TransactionConstant::STATUS_PURCHASE) {
                        $total_payment += $result->amount ?? 0;
                    }
                }
                $dataSub = [
                    'type' => 'year',
                    'date' => null,
                    'month' => 0,
                    'year' => $list->year,
                    'customer_id' => $resultSub->id ?? 0,
                    'time' => strtotime($start_date),
                    'total_deposit' => $total_deposit,
                    'total_payment' => $total_payment,
                    'total_withdrawal' => $total_withdrawal,
                ];
                $this->store($dataSub);
            }
        }
        return true;
    }
}
