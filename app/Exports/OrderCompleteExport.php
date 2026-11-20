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

class OrderCompleteExport implements FromCollection, WithHeadings, WithMapping, WithEvents, ShouldQueue, ShouldAutoSize
{
    /**
    * @return \Illuminate\Support\Collection
    */

    protected $params;

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $cellRange = 'A1:L1'; // All headers
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
        $data = \App::make('App\Services\ReportService')->getOrderComplete($this->params, false, true);
        return $data;
    }

    public function headings(): array
    {
        return [
            'Mã đơn hàng',
            'Mã khách hàng',
            'NV quản lý',
            'Tổng tiền',
            'Phí dịch vụ',
            'Đơn số',
            'Ngày hoàn thành'
        ];
    }
    public function map($item): array
    {
        return [
            $item->code,
            data_get($item->customer, 'code'),
            data_get($item->staffGroup, 'name'),
            roundMoney($item->getTotalAmount()),
            $item->order_fee,
            $item->reset_position,
            Carbon::parse($item->dateDone)->format('d-m-Y H:i:s')
        ];
    }
}
