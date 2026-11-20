<?php
namespace App\Exports\DeliveryCod\Component;

use App\Constants\LocateConstant;
use App\Services\FundService;
use App\Services\TransporterService;
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

class CodSheet implements FromCollection, WithHeadings, WithEvents, ShouldAutoSize, WithStyles
{
    use Exportable;

    protected $users;
    protected $selects;
    protected $row_count;
    protected $column_count;
    protected $fundService;

    public function __construct()
    {
        $this->fundService    = new FundService();

        $funds   = $this->fundService->getAllFundForExcel()->toArray();

        $selects = [  //selects should have column_name and options
            ['columnsName' => 'D', 'options' => $funds, 'sheetName' => 'fundList',
                'startCell' => 2, 'endCell' => count($funds) + 1],
        ];
        $this->selects      = $selects;

        $this->row_count    = 100;//number of rows that will have the dropdown
        $this->column_count = 2;//number of columns to be auto sized
    }

    public function collection()
    {
        $data   = [];
        $data[] = [
            'STT',
            'Mã bưu kiện',
            'Số tiền',
            'Quỹ nạp'
        ];

        for($i = 1; $i <= 100; $i++) {
            $data[] = [
                $i, '', '', ''
            ];
        }
        //dd($data);
        return collect($data);
    }

    public function headings(): array
    {
        return [
            'stt',
            'postcode',
            'money',
            'fund_name'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A2:D102')
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK));

        return [
            1 => [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['argb' => 'FF0000']
                ],
                'font' => [
                    'color' => ['argb' => 'FF0000']
                ]
            ],
        ];
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            // handle by a closure.
            AfterSheet::class => function(AfterSheet $event) {

                $row_count    = $this->row_count;
                $column_count = $this->column_count;

                foreach ($this->selects as $select) {
                    $drop_column = $select['columnsName'];
                    $options     = $select['options'];
                    $sheetName   = $select['sheetName'];
                    $startCell   = $select['startCell'];
                    $endCell     = $select['endCell'];
                    // set dropdown list for first data row
                    $validation = $event->sheet->getCell("{$drop_column}2")->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST );
                    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION );
                    $validation->setAllowBlank(false);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setShowDropDown(true);
                    $validation->setErrorTitle('Nhập lỗi');
                    $validation->setError('Giá trị không có trong danh sách.');
                    $validation->setPromptTitle('Chọn mục trong danh sách');
                    $validation->setPrompt('Xin vui lòng chọn 1 mục trong danh sách.');
                    //$validation->setFormula1(sprintf('"%s"',implode(',',$options)));
                    $validation->setFormula1($sheetName . '!$A$'. $startCell .':$A$' . $endCell);
                    // clone validation to remaining rows
                    for ($i = 3; $i <= $row_count; $i++) {
                        $event->sheet->getCell("{$drop_column}{$i}")->setDataValidation(clone $validation);
                    }
                    // set columns to autosize
                    for ($i = 1; $i <= $column_count; $i++) {
                        $column = Coordinate::stringFromColumnIndex($i);
                        $event->sheet->getColumnDimension($column)->setAutoSize(true);
                    }
                }

            },
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'codeList';
    }
}
