<?php
/**
 * Created by PhpStorm.
 * User: yoona
 * Date: 31/07/23
 * Time: 09:11
 */

namespace App\Exports;

use App\Models\Order;
use App\Services\OrderService;
//use Maatwebsite\Excel\Concerns\FromCollection;
//use Maatwebsite\Excel\Concerns\FromQuery;
//use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
//use Maatwebsite\Excel\Concerns\WithHeadings;
//use Maatwebsite\Excel\Concerns\Exportable;
//use Maatwebsite\Excel\Concerns\ShouldAutoSize;
//use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Facades\DB;
use Nikazooz\Simplesheet\Concerns\FromCollection;
use Nikazooz\Simplesheet\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
//use Nikazooz\Simplesheet\Concerns\WithEvents;
//use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Carbon;
use Nikazooz\Simplesheet\Concerns\Exportable;
use Nikazooz\Simplesheet\Concerns\FromQuery;
use Nikazooz\Simplesheet\Concerns\WithHeadings;
use Nikazooz\Simplesheet\Concerns\WithMapping;
use Nikazooz\Simplesheet\Concerns\WithCustomChunkSize;
//use Nikazooz\Simplesheet\Concerns\WithBatchInserts;

class OrderExport implements WithHeadings, WithMapping, WithCustomChunkSize, FromQuery
{
    use Exportable;

    private $current_row = 1;
    protected $data;
    protected $perPage;

    public function __construct($data, $showphone, $perPage)
    {
        $this->data = $data;
        $this->showphone = $showphone;
        $this->perPage = $perPage;
    }

    public function query()
    {
        //return (new OrderService())->exportV2($this->perPage);

        $query = Order::query()->with([
            'customer' => function ($customer) {
                return $customer->select('id', 'name', 'phone_number');
            },
            'staffOrder' => function ($staffOrder) {
                return $staffOrder->select('id', 'name');
            },
            'staffQuotation' => function ($staffQuotation) {
                return $staffQuotation->select('id', 'name');
            },
            //'staffCare',
            //'staffCounselor',
            'staffGroup' => function ($staffGroup) {
                return $staffGroup->select('id', 'name');
            }
        ]);

        $query->select('id', 'customer_id', 'staff_order_id', 'staff_quotation_id', 'staff_group_id', 'code',
            'created_at', 'date_quotation', 'date_purchased', 'date_ordered','date_done', 'order_fee', 'deposit_cost',
            DB::raw('((order_cost + order_fee + inspection_cost + (china_shipping_cost * exchange_rate)) -
            discount_cost) as totalAmount'));

        if (!empty(request()->query('ecommerces'))) {
            if (request()->query('ecommerces') == 'other') {
                $query->whereNull('ecommerce');
            } else {
                $query->where('ecommerce', request()->query('ecommerces'));
            }
        }

//        $startDate = Carbon::today()->subYears(1)->startOfMonth()->toDateString();
//        $endDate   = Carbon::today()->endOfMonth()->toDateString();
//        $query->whereDate('created_at', '>=', $startDate);
//        $query->whereDate('created_at', '<=', $endDate);
        //dd($query->toSql());
        return $query;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

//    public function batchSize(): int
//    {
//        return 1000;
//    }

//    public function registerEvents(): array
//    {
//        return [
//            AfterSheet::class    => function(AfterSheet $event) {
//                $cellRange = 'A1:P1'; // All headers
//                $event->sheet->getStyle($cellRange)->applyFromArray([
//                    'font' => [
//                        'bold' => true,
//                        'color' => 'red'
//                    ],
//                ]);
//            },
//        ];
//    }

//    public function collection()
//    {
//        //return $this->data;
//        return (new OrderService())->exportV2($this->perPage);
//    }

    public function headings(): array
    {
        return [
            'STT',
            'Mã đơn hàng',
            'Ngày tạo đơn',
            'Ngày báo giá',
            'Ngày đặt cọc',
            'Ngày đặt hàng',
            'Ngày hoàn thành',
            'Họ và tên',
            // 'Số điện thoại',
            $this->showphone ? 'Số điện thoại' : '',
            'NV đặt hàng',
            'NV báo giá',
            'NV chăm sóc/tư vấn',
            'Tổng tiền',
            'Phí dịch vụ',
            'Tạm ứng',
            'Còn lại',
        ];
    }

    public function map($item): array
    {
        $customer = $item->customer;
        $staffOrder = $item->staffOrder;
        $staffQuotation = $item->staffQuotation;
        //$staffCare = $item->staffCare;
        //$staffCounselor = $item->staffCounselor;
        $staffGroup = $item->staffGroup;

        $debt_cost = roundMoney($item->totalAmount) - $item->deposit_cost;

        return [
            $this->current_row ++ ,
            $item->code,
            $item->created_at ? Carbon::parse($item->created_at)->format('d-m-Y H:i:s') : '',
            $item->date_quotation ? Carbon::parse($item->date_quotation)->format('d-m-Y H:i:s') : '',
            $item->date_purchased ? Carbon::parse($item->date_purchased)->format('d-m-Y H:i:s') : '',
            $item->date_ordered ? Carbon::parse($item->date_ordered)->format('d-m-Y H:i:s') : '',
            $item->date_done ? Carbon::parse($item->date_done)->format('d-m-Y H:i:s') : '',
            $customer ? $customer->name : '',
            // $customer ? $customer->phone_number : '',
            $this->showphone ? ($customer ? $customer->phone_number : '') : '', // có quyền order.show_phone_number mới hiện số đt
            $staffOrder ? $staffOrder->name : '',
            $staffQuotation ? $staffQuotation->name : '',
            //$staffCare ? $staffCare->name : ($staffCounselor ? $staffCounselor->name : ''),
            $staffGroup ? $staffGroup->name : '',
            //number_format($item->total_amount,0,".",".") . ' đ',
            number_format($item->totalAmount,0,".",".") . ' đ',
            number_format($item->order_fee,0,".",".") . ' đ',
            number_format($item->deposit_cost,0,".",".") . ' đ',
           //number_format($item->debt_cost,0,".",".") . ' đ',
            number_format($debt_cost,0,".",".") . ' đ',
        ];
    }
}
