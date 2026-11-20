<?php

namespace App\Services\ValidateReport;

use App\Services\CalculatorWeekReport;
use Carbon\Carbon;
abstract class AbstractValidateReport implements InterfaceValidateReport
{
    protected $errors;
    protected $params;
    protected $success;
    protected $weekEnd;
    protected $monthEnd;
    protected $yearEnd;
    protected $weekStart;
    protected $monthStart;
    protected $yearStart;
    protected $isClickReport;
    protected $startMonthAndYear;
    protected $endMonthAndYear;
    protected $numberWeekEnd;
    protected $numberWeekStart;
    protected $isNullWeek;
    protected $filterByCustomer;
    protected $filterByGroupId;
    protected $monthAndYearOrderV;
    protected $numberWeekOrderV;

    function __construct($params){
        $this->isNullWeek = $params->isNullWeek ?? '';
        $this->numberWeekStart = $params->numberWeekStart ?? '';
        $this->numberWeekEnd = $params->numberWeekEnd ?? '';
        $this->endMonthAndYear = $params->endMonthAndYear ?? '';
        $this->startMonthAndYear = $params->startMonthAndYear ?? '';
        $this->isClickReport = $params->isClickReport ?? '';
        $this->filterByCustomer = $params->filterByCustomer ?? '';
        $this->filterByGroupId = $params->filterByGroupId ?? '';
        $this->monthAndYearOrderV = $params->monthAndYearOrderV ?? '';
        $this->numberWeekOrderV = $params->numberWeekOrderV ?? '';
    }
    public function validate(): AbstractValidateReport
    {
        if ($this->isClickReport == 'true') {
            if (empty($this->startMonthAndYear)) {
                $this->errors = 'Bạn chưa chọn Tháng/Năm tham chiếu';
                return $this;
            }
            if (empty($this->endMonthAndYear)) {
                $this->errors = 'Bạn chưa chọn Tháng/Năm xem';
                return $this;
            }
            extract($this->extractTime(), EXTR_PREFIX_SAME, "wddx");
            if ($yearEnd < $yearStart) {
                $this->errors = 'Năm tham chiếu phải nhỏ hơn Năm hiện tại';
                return $this;
            }
            if ($yearEnd > (int)date('Y')) {
                $this->errors = 'Năm xem phải nhỏ hơn Năm hiện tại';
                return $this;
            }
            if ($yearStart > (int)date('Y')) {
                $this->errors = 'Năm tham chiếu phải nhỏ hơn Năm hiện tại';
                return $this;
            }
            if ($yearEnd == $yearStart) {
                if ($monthStart == $monthEnd && $this->isNullWeek == 'true') {
                    $this->errors = 'Tháng tham chiếu phải nhỏ hơn Tháng xem';
                    return $this;
                }
                if ($monthStart > $monthEnd) {
                    $this->errors = 'Tháng tham chiếu phải nhỏ hơn Tháng xem';
                    return $this;
                }
                if ($monthEnd > (int)date('m')) {
                    $this->errors = 'Tháng xem phải nhỏ hơn hoặc bằng Tháng hiện tại';
                    return $this;
                }
            }
            if (
                $this->isNullWeek == 'false'
            ) {
                if (
                    (
                        (
                            empty($this->startMonthAndYear) && !empty($this->endMonthAndYear)
                        )
                        ||!empty($this->startMonthAndYear) && empty($this->endMonthAndYear)
                    )
                    || !empty($this->startMonthAndYear) && !empty($this->endMonthAndYear)
                ) {
                    if (empty($this->numberWeekEnd)) {
                        $this->errors = 'Bạn chưa chọn Tuần xem';
                        return $this;
                    }
                    if (empty($this->numberWeekStart)) {
                        $this->errors = 'Bạn chưa chọn Tuần tham chiếu';
                        return $this;
                    }
                }
                $weekStart = (int)$this->numberWeekStart;
                $weekEnd = (int)$this->numberWeekEnd;
                if ($monthStart == $monthEnd) {
                    if ($weekStart >= $weekEnd) {
                        $this->errors = 'Tuần tham chiếu phải nhỏ hơn Tuần xem';
                        return $this;
                    }
                }

                if (
                    $weekEnd > $this->getListTimeCurrent()->week
                    && $monthEnd == $this->getListTimeCurrent()->month
                    && $yearEnd == $this->getListTimeCurrent()->year
                ) {
                    $this->errors = 'Tuần xem phải nhỏ hơn hoặc bằng Tuần hiện tại';
                    return $this;
                }
            }
        }
        return $this;
    }

    protected function extractTime(): array
    {
        $arrStart = explode('-', $this->startMonthAndYear);
        $arrEnd = explode('-', $this->endMonthAndYear);
        $arrV = explode('-', $this->monthAndYearOrderV);
        return [
            'yearStart' => $arrStart[0],
            'monthStart' => $arrStart[1],
            'yearEnd' => $arrEnd[0],
            'monthEnd' => $arrEnd[1],
            'yearV' => count($arrV) > 2 ? $arrV[0] : "",
            'monthV' => count($arrV) > 2 ? $arrV[1] : "",
        ];
    }

    public function getTimeData(): AbstractValidateReport
    {
        if (!empty($this->numberWeekStart)) {
            $this->weekStart = (int)$this->numberWeekStart;
        } else {
            $this->weekStart = (int)$this->getListTimeStart()->week;
        }
        if (!empty($this->startMonthAndYear)) {
            $arr = explode('-', $this->startMonthAndYear);
            $this->yearStart = $arr[0];
            $this->monthStart = $arr[1];
        } else {
            $this->monthStart = (int)$this->getListTimeStart()->month;
            $this->yearStart = (int)$this->getListTimeStart()->year;
        }
        if (!empty($this->numberWeekEnd)) {
            $this->weekEnd = (int)$this->numberWeekEnd;
        } else {
            $this->weekEnd = (int)$this->getListTimeEnd()->week;
        }
        if (!empty($this->endMonthAndYear)) {
            $arr = explode('-', $this->endMonthAndYear);
            $this->yearEnd = $arr[0];
            $this->monthEnd = $arr[1];
        } else {
            $this->monthEnd = (int)$this->getListTimeEnd()->month;
            $this->yearEnd = (int)$this->getListTimeEnd()->year;
        }
        
        $weekTwo = "";
        $monthTwo = "";
        $yearTwo = "";
        if($this->isNullWeek == 'true' && $this->monthAndYearOrderV) {
            $parse = explode('-', $this->monthAndYearOrderV);
            $yearTwo = $parse[0];
            $monthTwo = $parse[1];
        }else {
            if($this->numberWeekOrderV && $this->monthAndYearOrderV) {
                $weekTwo = $this->numberWeekOrderV;
                $parse = explode('-', $this->monthAndYearOrderV);
                $yearTwo = $parse[0];
                $monthTwo = $parse[1];
            }else {
                if($this->weekStart == 1) {
                    if($this->monthStart == 1) {
                        $monthTwo = 12;
                        $yearTwo = $this->yearStart - 1;
                    }else {
                        $monthTwo = $this->monthStart - 1;
                        $yearTwo = $this->yearStart;
                    }
                    $createDate = Carbon::createFromDate($yearTwo, $monthTwo);
                    $weekTwo = $createDate->endOfMonth()->weekNumberInMonth;
                }else {
                    $weekTwo = $this->weekStart - 1;
                    $monthTwo = $this->monthStart;
                    $yearTwo = $this->yearStart;
                }
            }
        }
        if ($this->isNullWeek == 'true') {
            $this->weekEnd = '';
            $this->weekStart = '';
            $weekTwo = "";
        }   
        
        $this->success = [
            'weekEnd' => $this->weekEnd,
            'monthEnd' => (int)$this->monthEnd,
            'yearEnd' => (int)$this->yearEnd,
            'weekStart' => $this->weekStart,
            'monthStart' => (int)$this->monthStart,
            'yearStart' => (int)$this->yearStart,
            'byCustomer' => $this->filterByCustomer,
            'groupId' => $this->filterByGroupId,
            'weekTwo' => $weekTwo, // Bỏ sung thêm 3 key này để phục vụ thêm 1 tuần tham chiếu nữa
            'monthTwo' => $monthTwo,
            'yearTwo' => $yearTwo,
        ];
        return $this;
    }

    protected function getListTimeEnd(): \stdClass
    {
        return CalculatorWeekReport::getWeekMonthYear($this->params);
    }

    protected function calculatorTimeEnd(): array
    {
        return CalculatorWeekReport::getDayOfWeekOfMonth(
            $this->getListTimeEnd()->year,
            $this->getListTimeEnd()->month,
            $this->getListTimeEnd()->week
        );
    }

    protected function getListTimeStart(): \stdClass
    {
        $startDateOfCurrent['date'] = date('Y-m-d', strtotime($this->calculatorTimeEnd()['week_start_date'].' 00:00:00'. ' -1 days'));
        return CalculatorWeekReport::getWeekMonthYear($startDateOfCurrent);
    }

    protected function getListTimeCurrent(): \stdClass
    {
        $data['date'] = date('Y-m-d');
        return CalculatorWeekReport::getWeekMonthYear($data);
    }

    protected function dataError($message): array
    {
        return [
            'code' => 400,
            'message' => $message
        ];
    }

    protected function dataSuccess($message): array
    {
        return [
            'code' => 200,
            'message' => $message
        ];
    }

    public function exec(): array
    {
        if (!empty($this->errors)) {
            return $this->dataError($this->errors);
        }
        return $this->dataSuccess($this->success);
    }
}
