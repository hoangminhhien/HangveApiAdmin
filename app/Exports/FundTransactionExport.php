<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Carbon;

class FundTransactionExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents, WithMapping
{

    protected $data;
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $cellRange = 'A1:P1'; // All headers
                $event->sheet->getStyle($cellRange)->applyFromArray([
                    'font' => [
                        'bold' => true
                    ],
                ]);
            },
        ];
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Người tạo',
            'Ngày tạo',
            'Loại thu chi',
            'Loại thanh toán',
            'Tên quỹ',
            'Đối tượng',
            'Loại sổ',
            'Tên KH',
            'Số điện thoại KH',
            'Mã KH',
            'Số tiền',
            'Số dư',
            'Nội dung',
        ];
    }

    public function map($item): array
    {
        $user = $item->user;
        $customer = $item->customer;
        $sign = $item->type_pay == 1 ? '+' : '-'; // Nếu khoản thu thí đấu + và ngược lại
        return [
            data_get($user, 'name'),
            $item->created_at ? Carbon::parse($item->created_at)->format('d-m-Y H:i:s') : '',
            $item->getTypePay(),
            $item->fund_type_pay->name ?? '' ,
            $item->fund_name,
            $item->getTypeObject(),
            $item->getFundTypeCurrency(),
            $item->customer_name,
            $item->customer_phone,
            $item->customer_code,
            $sign . number_format($item->money_update, 0, '', ','),
            number_format($item->balance, 0, '', ','),
            $item->note,
        ];
    }
}
