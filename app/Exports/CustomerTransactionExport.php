<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CustomerTransactionExport implements FromCollection, WithHeadings, WithEvents, ShouldAutoSize
{
    protected array $data;    
    public function __construct(array $data)
    {
        $this->data = $data;
    }


    public function collection()
    {
        return collect($this->data);
    }

    public function headings(): array
    {
        return [
            'STT',
            'Thời gian',
            'Loại giao dịch',
            'Mã giao dịch',
            'Mã đơn hàng',
            'Nội dung',
            'Giá trị giao dịch',
            'Còn lại'
        ];
    }
    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $cellRange = 'A1:H1'; // All headers
                $styleArray = [
                    'font'  => [
                        'bold'  => true,
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                ];
                $event->sheet->getStyle($cellRange)->applyFromArray($styleArray);
            },
        ];

    }
}
