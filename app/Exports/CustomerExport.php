<?php

namespace App\Exports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;
use App\Jobs\ExportExcelJob;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use App\Constants\CustomerConstant;

class CustomerExport implements FromQuery, WithHeadings, WithMapping, WithEvents, ShouldAutoSize
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
                $cellRange = 'A1:W1'; // All headers
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
        $data = \App::make('App\Services\CustomerService')->getDataExportList($this->params);
        return $data;

    }

    public function headings(): array
    {
        return [
            'Mã khách hàng',
            'Tên',
            'Số điện thoại',
            'Mức vip',
            'Loại hình',
            'Emai',
            'Tỉnh TP',
            'Quận huyện',
            'Phường/xã',
            'Địa chỉ',
            'Ngày đăng ký',
            'Tổng đơn hàng',
            'Tổng tiền nạp ví',
            'Số dư tài khoản',
            'Tình trạng',
            //'NV tư vấn',
            //'NV chăm sóc',
            'NV chăm sóc/tư vấn',
            'HN-Kg',
            'M3',
            'HP-Kg',
            'M3',
            'HCM-Kg',
            'M3',
            'Ngày đặt hàng gần nhất'
        ];
    }
    public function map($customer): array
    {
        $staffCounselor = $customer->staffCounselor;
        $staffCare = $customer->staffCare;
        $ordersLast = $customer->ordersLast;
        $offer = $customer->offer;
        $deliveryDefault = $customer->deliveryDefault;
        $province = data_get($deliveryDefault, 'province');
        $district = data_get($deliveryDefault, 'district');
        $ward = data_get($deliveryDefault, 'ward');
        $staffGroup = $customer->staffGroup;
        return [
            $customer->code,
            $customer->name, 
            $customer->phone_number,
            'Cấp '. $customer->level,
            $customer->business_type ? CustomerConstant::CUSTOMER_BUSINESS_TYPE[$customer->business_type] : '',
            $customer->email,
            $province ? $province->name : '',
            $district ? $district->name : '',
            $ward ? $ward->name : '',
            $deliveryDefault ? $deliveryDefault->address : '',
            Carbon::parse($customer->created_at)->format('d-m-Y H:i:s'),
            $customer->orders_count,
            $customer->report ? number_format($customer->report->deposited_amount,0,".",".") . ' đ'  : '',
            $customer->report ? number_format($customer->report->balance_amount,0,".",".") . ' đ'  : '',
            $customer->status == CustomerConstant::STATUS_ACTIVE ? 'Đang hoạt động' : 'Đã khóa',
            //$staffCounselor ? $staffCounselor->name : '',
            //$staffCare ? $staffCare->name : '',
            $staffGroup ? $staffGroup->name : '',
            $offer ? $offer->trans_weight_hn_fee : '',
            $offer ? $offer->trans_volumn_hn_fee : '',
            $offer ? $offer->trans_weight_hp_fee : '',
            $offer ? $offer->trans_volumn_hp_fee : '',
            $offer ? $offer->trans_weight_hcm_fee : '',
            $offer ? $offer->trans_volumn_hcm_fee : '',
            $ordersLast ? Carbon::parse($ordersLast->created_at)->format('d-m-Y H:i:s') : '',
        ];
    }

    public function failed(Throwable $exception): void
    {
        \Log::error($exception);
        dispatch((new ExportExcelJob(11, 11002, $this->authId)));
    }
}