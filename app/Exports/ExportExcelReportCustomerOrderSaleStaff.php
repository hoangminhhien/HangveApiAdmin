<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class ExportExcelReportCustomerOrderSaleStaff implements FromView
{
    protected $data;

    function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('exports.reports.report-customer-order-sale-staff', [
            'data' => $this->data['format'],
        ]);
    }
}
