<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class ExportReportOrderConsignment implements FromView
{
    protected $data;
    function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('exports.reports.report-order-consignment', [
            'data' => $this->data['format']
        ]);
    }
}
