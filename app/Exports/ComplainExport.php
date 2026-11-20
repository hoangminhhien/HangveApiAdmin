<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Carbon;
use App\Constants\ComplainConstant;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ComplainExport implements FromCollection, WithHeadings, WithMapping, WithEvents, ShouldAutoSize
{
    /**
    * @return \Illuminate\Support\Collection
    */
    protected $data;
    protected $authId;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $cellRange = 'A1:J1'; // All headers
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
        return collect($this->data);
    }

    public function headings(): array
    {
        return [
            'Mã đơn hàng',
            'Khách hàng',
            'Loại khiếu nại',
            'Ngày khiếu nại',
            'Phương án',
            'Trạng thái',
            'NV đặt hàng',
            'NV chăm sóc',
            'NV tư vấn',
            'Khiếu nại'
        ];
    }
    public function map($item): array
    {
        $order = $item->order;
        $customer = $item->customer;
        $complainType = $item->complainType;
        $solution = $item->solution;
        $staffOrder = $item->staffOrder;
        $staffCare = $item->staffCare;
        $staffCounselor = $item->staffCounselor;
        $staffComplain = $item->staffComplain;
        return [
            data_get($order, 'code'),
            data_get($customer, 'name'),
            data_get($complainType, 'name'),
            Carbon::parse($item->created_at)->format('d-m-Y H:i:s'),
            data_get($solution, 'name'),
            ComplainConstant::STATUSES[$item->status],
            data_get($staffOrder, 'name'),
            data_get($staffCare, 'name'),
            data_get($staffCounselor, 'name'),
            data_get($staffComplain, 'name'),
        ];
    }
}
