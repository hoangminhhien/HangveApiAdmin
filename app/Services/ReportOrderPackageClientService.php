<?php

namespace App\Services;

use App\Abstracts\AbstractBaseService;
use App\Constants\PackageConstant;
use App\Jobs\ReportOrderPackageClient\ReportOrderPackageClientDateJob;
use App\Jobs\ReportOrderPackageClient\ReportOrderPackageClientMonthJob;
use App\Jobs\ReportOrderPackageClient\ReportOrderPackageClientYearJob;
use App\Models\OrderPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ReportOrderPackageClientService extends AbstractBaseService
{

    protected function setModel()
    {
        $namespaceModel = 'App\Models\\' . str_replace('Service', '', class_basename($this));
        $this->_model = class_exists($namespaceModel) ? new $namespaceModel() : null;
    }

    public function syncReport($params): JsonResponse
    {
        dispatch(new ReportOrderPackageClientDateJob($params))
            ->onQueue('sync-data-report')->delay(1);
        dispatch(new ReportOrderPackageClientMonthJob($params))
            ->onQueue('sync-data-report')->delay(1);
        dispatch(new ReportOrderPackageClientYearJob($params))
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
                    'total_package_warehouse_vn' => $result['total_package_warehouse_vn'] ?? 0,
                    'total_package_received_good' => $result['total_package_received_good'] ?? 0,
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
                        'total_package_warehouse_vn' => $result['total_package_warehouse_vn'] ?? 0,
                        'total_package_received_good' => $result['total_package_received_good'] ?? 0,
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
            'total_package_warehouse_vn' => $resultCurrent['total_package_warehouse_vn'] ?? 0,
            'total_package_received_good' => $resultCurrent['total_package_received_good'] ?? 0,
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
                    'total_package_received_good' => $result['total_package_received_good'] ?? 0,
                    'total_package_warehouse_vn' => $result['total_package_warehouse_vn'] ?? 0,
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
            'total_package_warehouse_vn' => $result['total_package_warehouse_vn'] ?? 0,
            'total_package_received_good' => $result['total_package_received_good'] ?? 0,
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
                    'total_package_warehouse_vn' => $resultSub['total_package_warehouse_vn'] ?? 0,
                    'total_package_received_good' => $resultSub['total_package_received_good'] ?? 0,
                ];
                $this->store($dataSub);
            }
        }
        return true;
    }

    private function getSql($start_date, $end_date, $package_status, $customer_id)
    {
        $sql = OrderPackage::query();
        $sql->select('customer_id', DB::raw('COUNT(*) as total'));
        $sql->where('customer_id', $customer_id);
        if ($package_status == PackageConstant::STATUS_WAREHOUSE_VN) {
            $sql->where('status',PackageConstant::STATUS_WAREHOUSE_VN);
            $sql->whereHas('statusTimes', function ($orderStatusTime) use ($start_date, $end_date) {
                $orderStatusTime->where('key', PackageConstant::STATUS_WAREHOUSE_VN)
                    ->whereBetween('created_at', [$start_date, $end_date]);
            });
        }
        if ($package_status == PackageConstant::STATUS_RECEIVED_GOODS) {
            $sql->where('status',PackageConstant::STATUS_RECEIVED_GOODS);
            $sql->whereHas('statusTimes', function ($orderStatusTime) use ($start_date, $end_date) {
                $orderStatusTime->where('key', PackageConstant::STATUS_RECEIVED_GOODS)
                    ->whereBetween('created_at', [$start_date, $end_date]);
            });
        }
        return $sql->groupBy('customer_id')->first();
    }

    private function processData($start_date, $end_date, $customer_id): array
    {
        $resultOrderPackageWarehouseVn = $this->getSql(
            $start_date, $end_date, PackageConstant::STATUS_WAREHOUSE_VN, $customer_id
        );
        $resultOrderPackageReceivedGood = $this->getSql(
            $start_date, $end_date, PackageConstant::STATUS_RECEIVED_GOODS, $customer_id
        );
        $total_package_warehouse_vn = $resultOrderPackageWarehouseVn->total ?? 0;
        $total_package_received_good = $resultOrderPackageReceivedGood->total ?? 0;
        return [
            'total_package_warehouse_vn' => $total_package_warehouse_vn,
            'total_package_received_good' => $total_package_received_good
        ];
    }
}
