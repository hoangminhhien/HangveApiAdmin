<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class ExportExcelReportClosingRateStaff implements FromView
{
    protected $data;

    function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('exports.reports.report-closing-rate-staff', [
            'data' => $this->data['format'],
            'date' => $this->data['time']
        ]);
    }
}
