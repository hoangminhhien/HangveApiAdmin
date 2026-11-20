<?php
namespace App\Exports\DeliveryCod\Component;

use App\Services\FundService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\Exportable;

class FundSheet implements FromCollection,WithHeadings,WithTitle
{
    use Exportable;

    protected $fundService;

    public function __construct()
    {
        $this->fundService = new FundService();
    }

    public function collection()
    {
        $data = [];

        $funds = $this->fundService->getAllFundForExcel();
        foreach ($funds as $index => $fund) {
            $data[] = [$fund];
        }

        return collect($data);
    }

    public function headings(): array
    {
        return [
            'Danh sách quỹ'
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'fundList';
    }
}
