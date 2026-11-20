<?php

namespace App\Services\ValidateReport;

class ValidateReportClosingRateStaff extends AbstractValidateReport
{
    public function __construct($params)
    {
        parent::__construct($params);
        $this->numberWeekEnd = $params->numberWeekEnd ?? '';
        $this->endMonthAndYear = $params->endMonthAndYear ?? '';
        $this->isClickReport = $params->isClickReport ?? '';
    }

    public function validate(): AbstractValidateReport
    {
        if ($this->isClickReport == 'true') {
            if (empty($this->endMonthAndYear)) {
                $this->errors = 'Bạn chưa chọn Tháng/Năm xem';
                return $this;
            }
            extract($this->extractTime(), EXTR_PREFIX_SAME, "wddx");
            if ($yearEnd > (int)date('Y')) {
                $this->errors = 'Năm xem phải nhỏ hơn Năm hiện tại';
                return $this;
            }

            if ($yearEnd == (int)date('Y') && $monthEnd > (int)date('m')) {
                $this->errors = 'Tháng xem phải nhỏ hơn Tháng hiện tại';
                return $this;
            }

            if (empty($this->numberWeekEnd)) {
                $this->errors = 'Bạn chưa chọn Tuần xem';
                return $this;
            }
            $weekEnd = (int)$this->numberWeekEnd;
            if (
                $weekEnd > $this->getListTimeCurrent()->week
                && $monthEnd == $this->getListTimeCurrent()->month
                && $yearEnd == $this->getListTimeCurrent()->year
            ) {
                $this->errors = 'Tuần xem phải nhỏ hơn hoặc bằng Tuần hiện tại';
                return $this;
            }
        }
        return $this;
    }
    protected function extractTime(): array
    {
        $arrEnd = explode('-', $this->endMonthAndYear);
        return [
            'yearEnd' => $arrEnd[0],
            'monthEnd' => $arrEnd[1],
        ];
    }
    public function getTimeData(): AbstractValidateReport
    {
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

        $this->success = [
            'weekEnd' => $this->weekEnd,
            'monthEnd' => (int)$this->monthEnd,
            'yearEnd' => (int)$this->yearEnd,
        ];
        return $this;
    }
}
