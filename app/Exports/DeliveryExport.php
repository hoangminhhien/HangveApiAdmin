<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Constants\TimeConstant;
use App\Helpers\TimeHelper;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\Carbon;
use App\Constants\DeliveryConstant;


class DeliveryExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $deliveries;

    public function __construct($deliveries)
    {
        $this->deliveries = $deliveries;
    }

    public function headings(): array
    {
        return [
            'Ngày tạo',
            'Mã giao hàng',
            'Khách hàng',
            'Tình trạng',
            'Thông tin giao hàng',
            'Thanh toán',
            'Ghi chú khách',
            'Nhân viên ghi chú',
            'Mã bưu kiện',
        ];
    }
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('E:E')->getAlignment()->setWrapText(true);

        return [];
    }

    public function collection()
    {
        return collect($this->deliveries)->map(function ($item) {

            $customerInfo = '';
            if ($item->customer) {
                $customerInfo =
                    ($item->customer->name ?? '') .
                    ' / ' .
                    ($item->customer->phone_number ?? '') .
                    ' / ' .
                    number_format(optional($item->customer->report)->balance_amount ?? 0);
            }

            $carrier = optional($item->transporter)->name;

            if ($item->transporterDetail) {
                $carrier .=
                    ($item->transporterDetail->name ? ' - '.$item->transporterDetail->name : '') .
                    ($item->transporterDetail->phone_number ? ' - '.$item->transporterDetail->phone_number : '') .
                    ($item->transporterDetail->address ? ' - '.$item->transporterDetail->address : '');
            }

            $shippingInfo = implode(PHP_EOL, array_filter([
                'Hình thức: ' . $carrier,
                'Thanh toán: ' . (DeliveryConstant::PAYMENTS[$item->payment] ?? ''),
                'Người nhận: ' . optional($item->customerDelivery)->custom_name,
                'Số MVĐ: ' . optional($item->orderPackages)->count(),
                'Thời gian giao hàng: ' . TimeHelper::format($item->date, TimeConstant::DATE_VI),
            ]));

            $staffNote = $item->note ?? '';


            return [
                Carbon::parse($item->created_at)->format('d/m/Y H:i'),
                $item->code,
                $customerInfo,
                DeliveryConstant::STATUSES[$item->status] ?? '',
                $shippingInfo,
                number_format($item->fee_missing ?? 0),
                $item->note_customer ?? '',
                $staffNote,
                $item->carrier_code ?? ''
            ];
        });
    }
}
