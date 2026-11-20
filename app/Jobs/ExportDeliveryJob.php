<?php

namespace App\Jobs;

use Throwable;
use Carbon\Carbon;
use App\Models\Customer;
use App\Models\Delivery;
use App\Helpers\TimeHelper;
use App\Constants\TimeConstant;
use Illuminate\Bus\Queueable;
use App\Constants\DeliveryConstant;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Entity\Style\CellAlignment;

class ExportDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $params;
    protected $auth;
    protected $pathFile;

    public function __construct($params, $auth, $pathFile)
    {
        $this->params = $params;
        $this->auth = $auth;
        $this->pathFile = $pathFile;
    }

    public function handle()
    {
        \Log::info('-------Start queue export danh sách giao hàng--------');

        if (!file_exists(public_path(getPathFileExport('delivery')))) {
            mkdir(public_path(getPathFileExport('delivery')), 0777, true);
        }

        $headerStyle = (new Style())
            ->setFontBold()
            ->setFontSize(12)
            ->setBackgroundColor('D9D9D9')
            ->setCellAlignment(CellAlignment::CENTER);

        /*$rowStyle = (new Style())
            ->setShouldWrapText(true)
            ->setCellAlignment(CellAlignment::TOP);*/

        (new FastExcel($this->generator()))
            ->headerStyle($headerStyle)
//            ->rowsStyle($rowStyle)
            ->export(public_path($this->pathFile), function ($item) {

                $carrier = optional($item->transporter)->name;

                if ($item->transporterDetail) {
                    $carrier .=
                        ($item->transporterDetail->name ? ' - ' . $item->transporterDetail->name : '') .
                        ($item->transporterDetail->phone_number ? ' - ' . $item->transporterDetail->phone_number : '') .
                        ($item->transporterDetail->address ? ' - ' . $item->transporterDetail->address : '');
                }

                return [
                    'Ngày tạo' => $item->created_at
                        ? Carbon::parse($item->created_at)->format('d/m/Y H:i')
                        : '',

                    'Mã giao hàng' => $item->code,

                    'Tên KH' => data_get($item->customer, 'name'),

                    'SĐT' => data_get($item->customer, 'phone_number'),

                    'Số dư' => number_format(
                        optional(data_get($item->customer, 'report'))->balance_amount ?? 0
                    ),

                    'Tình trạng' => DeliveryConstant::STATUSES[$item->status] ?? '',

                    'ĐVVC' => optional($item->transporter)->name,

                    'TT shipper' => $carrier,

                    'TT thanh toán' => DeliveryConstant::PAYMENTS[$item->payment] ?? '',

                    'Người nhận' => optional($item->customerDelivery)->custom_name,

                    'Số lượng MVĐ' => optional($item->orderPackages)->count(),

                    'TG giao hàng' => $item->date
                        ? TimeHelper::format($item->date, TimeConstant::DATE_VI)
                        : '',

                    'Tiền thiếu' => number_format($item->fee_missing ?? 0),

                    'GC KH' => $item->note_customer ?? '',

                    'GC NV' => $item->note ?? '',

                    'Mã BK' => $item->carrier_code ?? '',
                ];
            });

        $this->senNoti(
            11,
            11012,
            data_get($this->auth, 'id'),
            $this->pathFile
        );

        \Log::info('-------End queue export danh sách giao hàng--------');
    }

    private function generator()
    {
        $perService = app('App\Services\PerService');

        $query = Delivery::query()
            ->select('deliveries.*')
            ->with([
                'notesStaff',
                'deliveryOrderStatus',
                'transporter',
                'transporterDetail',
                'customerDelivery',
                'customer.warehouse',
                'customer.report',
                'order:id,code',

                'customer' => function ($q) {
                    $q->select(
                        'id',
                        'name',
                        'phone_number',
                        'warehouse_id',
                        'source'
                    );
                },

                'orderPackages' => function ($q) {
                    $q->select(
                        'id',
                        'delivery_id',
                        'bill_code'
                    );
                },
            ])
            ->when(data_get($this->params, 'is_received') != null, function ($q) {
                $q->where(
                    'deliveries.status',
                    data_get($this->params, 'is_received') == 1 ? '=' : '!=',
                    DeliveryConstant::KEY_STATUS_DONE
                );
            });

        $query = $perService->getConditionByUserLogin($query);

        $phoneNumber = data_get($this->params, 'phone_number');

        if ($phoneNumber) {
            $customerDB = Customer::where('phone_number', $phoneNumber)->first();

            $query->where(function ($q) use ($phoneNumber, $customerDB) {
                $q->where('deliveries.phone_number', $phoneNumber);

                if ($customerDB) {
                    $q->orWhere('deliveries.customer_id', $customerDB->id);
                }
            });
        }
        $params = $this->params;

// receiver
        if (!empty($params['receiver'])) {
            $query->where(
                'deliveries.receiver',
                'like',
                '%' . $params['receiver'] . '%'
            );
        }

// carrier_code
        if (!empty($params['carrier_code'])) {
            $query->where('deliveries.carrier_code', $params['carrier_code']);
        }

// shipper_phone_number
        if (!empty($params['shipper_phone_number'])) {
            $query->where(
                'deliveries.shipper_phone_number',
                $params['shipper_phone_number']
            );
        }

// transporter_id
        if (!empty($params['transporter_id'])) {
            $query->where(
                'deliveries.transporter_id',
                $params['transporter_id']
            );
        }

// transporter_detail_id
        if (!empty($params['transporter_detail_id'])) {
            $query->where(
                'deliveries.transporter_detail_id',
                $params['transporter_detail_id']
            );
        }

// payment
        if (
            isset($params['payment']) &&
            $params['payment'] !== ''
        ) {
            $query->where(
                'deliveries.payment',
                $params['payment']
            );
        }

// warehouse_id
        if (!empty($params['warehouse_id'])) {
            $query->join(
                'order_package',
                'deliveries.id',
                '=',
                'order_package.delivery_id'
            )->where(
                'order_package.warehouse_id',
                $params['warehouse_id']
            );
        }

// bill_code
        if (!empty($params['bill_code'])) {
            if (!str_contains($query->toSql(), 'order_package')) {
                $query->join(
                    'order_package',
                    'deliveries.id',
                    '=',
                    'order_package.delivery_id'
                );
            }

            $query->where(
                'order_package.bill_code',
                $params['bill_code']
            );
        }

// order_code
        if (!empty($params['order_code'])) {
            $query->join(
                'delivery_orders',
                'deliveries.id',
                '=',
                'delivery_orders.delivery_id'
            )
                ->join(
                    'orders',
                    'delivery_orders.order_id',
                    '=',
                    'orders.id'
                )
                ->where(
                    'orders.code',
                    $params['order_code']
                )
                ->select('deliveries.*')
                ->distinct();
        }

// date
        if (!empty($params['date'])) {
            $dates = explode(',', $params['date']);

            if (count($dates) == 2) {
                $query->whereBetween('deliveries.date', [
                    Carbon::parse($dates[0])->startOfDay(),
                    Carbon::parse($dates[1])->endOfDay(),
                ]);
            }
        }

        if (!empty($params['date_from'])) {
            $query->where(
                'deliveries.date',
                '>=',
                Carbon::parse($params['date_from'])->startOfDay()
            );
        }

        if (!empty($params['date_to'])) {
            $query->where(
                'deliveries.date',
                '<=',
                Carbon::parse($params['date_to'])->endOfDay()
            );
        }

        if (!empty($params['created_at_from']) || !empty($params['created_at_to'])) {

            if (!empty($params['created_at_from'])) {
                $query->where(
                    'deliveries.created_at',
                    '>=',
                    Carbon::parse($params['created_at_from'])->startOfDay()
                );
            }

            if (!empty($params['created_at_to'])) {
                $query->where(
                    'deliveries.created_at',
                    '<=',
                    Carbon::parse($params['created_at_to'])->endOfDay()
                );
            }
        }

// type_time
        if (!empty($params['type_time'])) {
            switch ($params['type_time']) {
                case DeliveryConstant::ORDER_IN_DAY:
                    $query->whereDate(
                        'deliveries.created_at',
                        date('Y-m-d')
                    );
                    break;

                case DeliveryConstant::ORDER_IN_WEEK:
                    $query->whereBetween(
                        'deliveries.created_at',
                        [
                            Carbon::now()->startOfWeek(),
                            Carbon::now()->endOfWeek()
                        ]
                    );
                    break;

                case DeliveryConstant::ORDER_IN_MONTH:
                    $query->whereMonth(
                        'deliveries.created_at',
                        date('m')
                    )->whereYear(
                        'deliveries.created_at',
                        date('Y')
                    );
                    break;
            }
        }

        $query->groupBy('deliveries.id');

        foreach (
            $query->orderByDesc('created_at')
                ->lazy(500) as $delivery
        ) {
            yield $delivery;
        }
    }

    public function failed(Throwable $exception): void
    {
        \Log::error($exception);

        $this->senNoti(
            11,
            11011,
            data_get($this->auth, 'id')
        );

        \Log::info('-------Export danh sách giao hàng thất bại--------');
    }

    private function senNoti($group, $name, $userId, $pathFile = null)
    {
        $notificationService = app('App\Services\NotificationService');

        $dataInfo = [
            'path_file' => $pathFile
        ];

        $data = [
            'group'       => $group,
            'name'        => $name,
            'data'        => json_encode($dataInfo),
            'time_create' => time(),
            'status'      => 1
        ];

        $notificationService->storeNoti($userId, $data);
    }
}
