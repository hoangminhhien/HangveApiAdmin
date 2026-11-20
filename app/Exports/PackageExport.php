<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Carbon;
use App\Constants\PackageConstant;

class PackageExport implements FromCollection, WithHeadings, WithEvents, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    use Exportable;
    private $current_row = 1;
    protected $data;
    public function __construct($data, $showphone)
    {
        $this->data = $data;
        $this->showphone = $showphone;
    }
    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'STT',
            'Ngày tạo',
            'Ngày đến kho TQ',
            'Ngày đến kho VN',
            'Ngày nhận hàng',
            'Mã đơn hàng',
            'NV đặt hàng',
            'NV tư vấn/chăm sóc',
            'Tên KH',
            // 'Số điện thoại',
            $this->showphone ? 'Số điện thoại' : '',
            'Mã vận đơn',
            'Mã đặt hàng',
            'Hãng vận chuyển',
            'Loại hàng',
            'Danh mục',
            'Tình trạng',
            'Phí nội địa',
            'Trọng lượng',
            'Tổng phí'
        ];
    }

    public function map($item): array
    {
        $unitStr = $item->volume > 0 ? 'm3' : 'kg';
        $staffOrder = $item->staffOrder;
        $staffCare = $item->staffCare;
        $staffCounselor = $item->staffCounselor;
        $customer = $item->customer;
        $categoryRelation = $item->categoryRelation;
        $transporterRelation = $item->transporterRelation;
        $statusTimeVN = $item->statusTimeVN;
        $statusTimeTQ = $item->statusTimeTQ;
        return [
            $this->current_row ++ ,
            $item->created_at ? Carbon::parse($item->created_at)->format('d-m-Y H:i:s') : '',
            $statusTimeTQ ? Carbon::parse($statusTimeTQ->created_at)->format('d-m-Y H:i:s') : '',
            $statusTimeVN ? Carbon::parse($statusTimeVN->created_at)->format('d-m-Y H:i:s') : '',
            $item->date_done ? Carbon::parse($item->date_done)->format('d-m-Y H:i:s') : '',
            $item->order_code,
            $staffOrder ? $staffOrder->name : '',
            $staffCare ? $staffCare->name : ($staffCounselor ? $staffCounselor->name : ''),
            $customer ? $customer->name : '',
            // $customer ? $customer->phone_number : '',
            $this->showphone ? $customer ? $customer->phone_number : '' : '', // có quyền order.show_phone_number mới hiện số đt
            $item->bill_code,
            $item->code_po,
            $transporterRelation ? $transporterRelation->name : '',
            $item->order_kind_of,
            $categoryRelation ? $categoryRelation->name : '',
            PackageConstant::STATUSES[$item->status],
            number_format($item->china_shipping_cost,0,".",".") . ' đ',
            ($item->volume > 0 ? $item->volume : $item->weight) . $unitStr,
            number_format($item->amount,0,".",".") . ' đ',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $cellRange = 'A1:S1'; // All headers
                $event->sheet->getStyle($cellRange)->applyFromArray([
                    'font' => [
                        'bold' => true
                    ],
                ]);
            },
        ];
    }
}
