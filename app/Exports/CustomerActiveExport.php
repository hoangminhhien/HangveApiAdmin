<?php

namespace App\Exports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Jobs\ExportExcelJob;
use Throwable;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Illuminate\Contracts\Queue\ShouldQueue;

class CustomerActiveExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithEvents, ShouldQueue
{
    use Exportable;

    private $current_row = 1;
    protected $params;
    protected $authId;

    public function __construct($params, $authId)
    {
        $this->params = $params;
        $this->authId = $authId;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $cellRange = 'A1:H1'; // All headers
                $event->sheet->getStyle($cellRange)->applyFromArray([
                    'font' => [
                        'bold' => true
                    ],
                ]);
            },
        ];
    }
    public function query()
    {
        
        $data = \App::make('App\Services\CustomerService')->getDataExportListActive($this->params);
        return $data;
    }

    public function headings(): array
    {
        return [
            'Mã khách hàng',
            'Tên đăng nhập',
            'Họ và tên',
            'Số điện thoại',
            'Ngày lên đơn gần nhất',
            'Đơn đang chờ đặt cọc',
            'Giỏ hàng ',
        ];
    }
    public function map($customer): array
    {
        $categoryArray = []; 
        foreach($customer->orders as $order){
            foreach($order->details as $detail){
                if($detail && $detail->category){
                    array_push($categoryArray, $detail->category->name);
                }
            }
        }
        return [
            $customer->code,
            $customer->email, 
            $customer->name, 
            $customer->phone_number,
            $customer->last_order_at ? Carbon::parse($customer->last_order_at)->format('d/m/Y H:m:s') : '',
            $customer->orders->count(),
            implode( ", ", array_unique($categoryArray)),
        ];
    }

    public function failed(Throwable $exception): void
    {
        \Log::error($exception);
        dispatch((new ExportExcelJob(11, 11004, $this->authId)));
    }
}