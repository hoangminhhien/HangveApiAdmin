<?php
namespace App\Exports\DeliveryCod;

use App\Exports\DeliveryCod\Component\FundSheet;
use App\Exports\DeliveryCod\Component\CodSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;

class DeliveryCodTemplate implements WithMultipleSheets
{
    use Exportable;

    protected $users;
    protected $selects;
    protected $row_count;
    protected $column_count;
    protected $transporterService;
    protected $categoryService;

    public function __construct()
    {
        //
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [
            'cod'      => new CodSheet(),
            'fundList' => new FundSheet()
        ];

        return $sheets;
    }
}
