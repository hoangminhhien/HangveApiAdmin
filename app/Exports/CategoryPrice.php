<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use Maatwebsite\Excel\Concerns\Exportable;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CategoryPrice implements FromView, ShouldAutoSize
{
    // use Exportable;

    protected $categoryDBs;

    function __construct()
    {
        $categoryModel = \App::make('App\Models\Category');
        $this->categoryDBs = $categoryModel->all();
    }

    public function view(): View
    {
        return view('exports.category.price', [
            'categoryDBs' => $this->categoryDBs
        ]);
    }
}
