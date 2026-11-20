<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class CustomerDebtExport implements FromCollection, WithHeadings, ShouldAutoSize, WithMapping, WithEvents
{
    use Exportable;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }
    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Mã KH',
            'Họ và tên',
            'Nhân viên quản lý',
            'Đơn còn thiếu',
            'Vận chuyển cần thanh toán',
            'Cần thanh toán',
            'Số dư  tài khoản',
        ];
    }
    public function map($item): array
    {
        return [
            $item->customer_code,
            $item->customer_name,
            $item->user_name,
            $this->formatNumber($item->order_missing),
            $this->formatNumber($item->total_shipping_cost),
            $this->formatNumber($item->total_amount),
            $this->formatNumber($item->balance_amount),
        ];
    }
    private function formatNumber($number)
    {
        if ($number === null || $number === '') {
            return '0';
        }

        return number_format((float)$number, 0, '', ',');
    }
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getStyle('A1:G1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                ]);
                $event->sheet->getStyle('D:G')->applyFromArray([
                    'alignment' => [
                        'horizontal' => 'right',
                    ],
                ]);
            },
        ];
    }
}
