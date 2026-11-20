<?php

namespace App\Exports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CustomerPackageVNExport implements FromCollection, WithHeadings, WithEvents, ShouldAutoSize
{
    protected $data;
    public function __construct($data)
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
            'Tên đăng nhập',
            'Họ và tên',
            'Số điện thoại',
            'Mã vận đơn',
            'Loại kiện',
            'Mã đơn hàng',
            'Phí vận chuyển',
            'Tiền hàng còn thiếu',
            'Số ngày về VN',
            'Ghi chú',
            'Độ ưu tiên'
        ];
    }
public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $cellRange = 'A1:K1'; // All headers
                $event->sheet->getStyle($cellRange)->applyFromArray([
                    'font' => [
                        'bold' => true
                    ],
                ]);
            },
        ];
    }
}
