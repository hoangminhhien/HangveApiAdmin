<?php

namespace App\Exports;

use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use App\Services\OrderPackageService;

class PackPrintReceiveGoodsExport implements FromView, WithEvents
{
    use Exportable;

    // private int $_count;
    private $data;
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        // dd($this->data);
        return view(
            'print.warehouse_pack',
            $this->data
        );
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getStyle('A')->getAlignment()->setWrapText(true);
                // $event->sheet->getDelegate()->getStyle('A1:N'.$this->_count)
                //     ->getFont()
                //     ->setName('Roboto');
                // $event->sheet->getDelegate()->getStyle('A1:N'.$this->_count)
                //     ->getFont()
                //     ->setName('Roboto')
                //     ->setSize(10);
            },
        ];
    }
}
