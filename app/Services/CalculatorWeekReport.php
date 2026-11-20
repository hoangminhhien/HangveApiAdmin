<?php

namespace App\Services;
use Carbon\Carbon;
use DateTime;

class CalculatorWeekReport
{
    function __construct()
    {

    }
    /* Tính tuần theo tháng*/
    public static function weekOfMonth($date): int
    {
        $date = strtotime($date);
        $firstOfMonth = strtotime(date("Y-m-01", $date));
        return CalculatorWeekReport::totalWeekOfYear($date) - CalculatorWeekReport::totalWeekOfYear($firstOfMonth) + 1;
    }

    public static function calculatorDateReportCustomerVip($params = []): array
    {
        $startYear = strtotime('2024-01-01 00:00:00');
        $nowYear = date('Y');
        $rangerYear = (int) ($nowYear - 2024) + 1;
        // $aYearLater = strtotime('+'.$rangerYear.' Year', $startYear);
        $aYearLater = strtotime("+1 month");
        $allDates = [];
        $saturday = strtotime('Last Saturday', strtotime('-1 Day', $startYear));
        $friday = strtotime('Next Friday', strtotime('-1 Day', $startYear));
        while(1){
            if($friday > $aYearLater)
                break 1;
            $allDates[] = date('Y-m-d', $friday);
            if($saturday > $aYearLater)
                break 1;
            $allDates[] = date('Y-m-d', $saturday);

            $friday = strtotime('+1 Week', $friday);
            $saturday = strtotime('+1 Week', $saturday);
        }
        $arrayDates = [];
        $countDate = [];
        $currentDate = date('Y-m-d');
        foreach ($allDates as $allDate) {
            if (sizeof($countDate) < 2 && $allDate) {
                $countDate[] = $allDate;
            }
            if (sizeof($countDate) == 2) {
                $arrayDates[] = array_reverse($countDate);
                $countDate = [];
            }
        }

        foreach ($arrayDates as $key => $arrayDate) {
            if (
                strtotime($arrayDate[1]) > strtotime($currentDate)
                && strtotime($arrayDate[0]) > strtotime($currentDate)
            ) {
                unset($arrayDates[$key]);
            }
        }
        return $arrayDates;
    }

    public static function totalWeekOfYear($date): int
    {
        $weekOfYear = intval(date("W", $date));
        if (date('n', $date) == "1" && $weekOfYear > 51) {
            return 0;
        }
        else if (date('n', $date) == "12" && $weekOfYear == 1) {
            return 53;
        }
        else {
            return $weekOfYear;
        }
    }

    public static function getWeekMonthYear($request): \stdClass
    {
        $date = !empty($request['date']) ? date('Y-m-d',strtotime($request['date'])). ' 00:00:00' : date('Y-m-d'). ' 00:00:00';
        $week = !empty($request['week']) ? $request['week'] : CalculatorWeekReport::weekOfMonth($date);
        $month = !empty($request['month']) ? $request['month'] : intval(date("m", strtotime($date)));
        $year = !empty($request['year']) ? $request['year'] : intval(date("Y", strtotime($date)));
        $stdClass = new \stdClass();
        $stdClass->date = $date;
        $stdClass->week = $week;
        $stdClass->month = $month;
        $stdClass->year = $year;
        return $stdClass;
    }

    public static function getMonthRanger($number, $start_date): array
    {
        $subMonth = [];
        for ($i = 1; $i <= $number; $i ++) {
            $subMonth[] = date('m-Y', strtotime($start_date. '-'.$i.' months'));
        }
        return $subMonth;
    }

    public static function getYearRanger($number, $start_date): array
    {
        $subMonth = [];
        for ($i = 1; $i <= $number; $i ++) {
            $subMonth[] = date('Y', strtotime($start_date. '-'.$i.' years'));
        }
        return $subMonth;
    }

    public static function getDayOfWeekOfMonth($yearRequest, $monthRequest, $weekRequest): array
    {
        $startDateOfWeek = '';
        $endDateOfWeek = '';
        $fistDayOfMonth = date("Y-m-d", strtotime("first day of ".$yearRequest . "-" . $monthRequest));
        $lastDayOfMonth = date("Y-m-d", strtotime("last day of ".$yearRequest . "-" . $monthRequest));
        $dates = CalculatorWeekReport::dateRange($fistDayOfMonth, $lastDayOfMonth, '+1 day', 'Y-m-d' );
        $weeks = [];
        foreach ($dates as $value) {
            $day = date('d/m/Y',strtotime($value));
            $ofWeek = Carbon::createFromFormat('d/m/Y', $day)->weekNumberInMonth;
            if($ofWeek == (int)$weekRequest){
                $weeks[] = $value;
            }
        }
        $formatDay = CalculatorWeekReport::formatDay();
        $dates = [];
        foreach ($weeks as $value) {
            $day = date('D',strtotime($value));
            $day = $formatDay[$day];
            $format = date('d',strtotime($value));
            $dates[$format] = $day .' ('. date('d-m',strtotime($value)) .')';
        }
        if($weeks){
            if(sizeof($weeks) == 1){
                $startDateOfWeek = $endDateOfWeek = $weeks[0];
            } else {
                $startDateOfWeek = $weeks[0];
                $endDateOfWeek = $weeks[sizeof($weeks) - 1];
            }
        }
        $data['week_start_date'] = $startDateOfWeek;
        $data['week_end_date'] = $endDateOfWeek;
        $data['day'] = $dates;
        $data['date'] = $startDateOfWeek.', '.$endDateOfWeek;
        $data['dateRange'] = 'Tuần '.$weekRequest.' [ Từ '. date('d-m-Y',strtotime($startDateOfWeek)) . ' đến '. date('d-m-Y',strtotime($endDateOfWeek)).' ]';
        $data['week'] = $weekRequest;
        return $data;
    }

    public static function getStartAndEndDateOfMonth($request): \stdClass
    {
        $year = date('Y');
        $month = date('m');
        if (!empty($request['date'])) {
            $year = date('Y',strtotime($request['date']));
            $month = date('m',strtotime($request['date']));
        } elseif (!empty($request['month']) && !empty($request['year'])){
            $year = (int)$request['year'];
            $month = (int)$request['month'];
        }
        $fistDayOfMonth = date("Y-m-d", strtotime("first day of ".$year . "-" . $month));
        $lastDayOfMonth = date("Y-m-d", strtotime("last day of ".$year . "-" . $month));
        $stdClass = new \stdClass();
        $stdClass->fistDayOfMonth = $fistDayOfMonth;
        $stdClass->lastDayOfMonth = $lastDayOfMonth;
        return $stdClass;
    }

    public static function getAllWeekOfMonth($request): array
    {
        $data = [];
        if ($endMonthAndYearOrder = $request->endMonthAndYearOrder) {
            $arrCurrent = explode('-', $endMonthAndYearOrder);
            $data['year'] = $arrCurrent[0];
            $data['month'] = $arrCurrent[1];
        }
        elseif ($startMonthAndYearOrder = $request->startMonthAndYearOrder) {
            $arrBefore = explode('-', $startMonthAndYearOrder);
            $data['year'] = $arrBefore[0];
            $data['month'] = $arrBefore[1];
        }
        if (!empty($data)) {
            $results = CalculatorWeekReport::getStartAndEndDateOfMonth($data);
            $lastDayOfMonth = $results->lastDayOfMonth;
            $weekNumber = CalculatorWeekReport::weekOfMonth($lastDayOfMonth);
            $arrayWeek = [];
            $array = [];
            for($i = 1; $i <= $weekNumber; $i++){
                $arrayWeek[] = $i;
            }
            foreach ($arrayWeek as $value){
                $array[] = CalculatorWeekReport::getDayOfWeekOfMonth($data['year'], $data['month'], $value);
            }

            return $array;
        }
        return [];
    }

    public static function dateRange($startDate, $endDate, $step = '+1 day', $outputFormat = 'Y-m-d' ): array
    {
        $dates = array();
        $startDate = strtotime($startDate);
        $endDate = strtotime($endDate);
        while( $startDate <= $endDate ) {
            $dates[] = date($outputFormat, $startDate);
            $startDate = strtotime($step, $startDate);
        }
        return $dates;
    }

    public static function dateRangeV2($startDate, $endDate, $step = '+1 day', $outputFormat = 'Y-m-d' ): array
    {
        $dates = array();
        $startDate = strtotime($startDate);
        $endDate = strtotime($endDate);
        while ($endDate <= $startDate) {
            $date = date($outputFormat, $endDate);
            $dates[] = $date;
            $endDate = strtotime($step, $endDate);
        }
        return $dates;
    }

    public static function formatDay(): array
    {
        return [
            'Sun' => 'Chủ nhật',
            'Sat' => 'Thứ 7',
            'Fri' => 'Thứ 6',
            'Thu' => 'Thứ 5',
            'Wed' => 'Thứ 4',
            'Tue' => 'Thứ 3',
            'Mon' => 'Thứ 2'
        ];
    }

    /* Tính ngày bắt đầu và kết thúc của tuần theo năm*/
    public static function getStartDateAndDateWithNowWeek($now, $options =[]): array
    {
        $subDay = $now->subDays(1);
        if (!empty($options['update-now'])) {
            $subDay = $now;
        }
        $subDay = $subDay->format('Y-m-d');
        $date = new DateTime($subDay);
        $week = $date->format("W");
        $year = $date->format("Y");
        $dto = new DateTime();
        $dto->setISODate($year, $week);
        $data['week_start_date'] = $dto->format('Y-m-d'). ' 00:00:00';
        $dto->modify('+6 days');
        $data['week_end_date'] = $dto->format('Y-m-d'). ' 23:59:59';
        $data['year'] = $year;
        $data['week'] = $week;
        $data['subDay'] = $subDay;
        return $data;
    }

    public static function getStartDateAndDateWithParams($week, $year): array
    {
        $dto = new DateTime();
        $dto->setISODate($year, $week);
        $data['week_start_date'] = $dto->format('Y-m-d'). ' 00:00:00';
        $dto->modify('+6 days');
        $data['week_end_date'] = $dto->format('Y-m-d'). ' 23:59:59';
        $data['year'] = $year;
        $data['week'] = $week;
        return $data;
    }
}
