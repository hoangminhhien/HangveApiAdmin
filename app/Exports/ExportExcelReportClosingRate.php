<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class ExportExcelReportClosingRate implements FromView
{
    protected $data;

    function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('exports.reports.report-closing-rate', [
            'data' => $this->data->toArray(),
        ]);
    }
}
