<?php

namespace App\Http\Resources\Order;

use App\Constants\ColorConstant;
use App\Constants\ComplainConstant;
use App\Constants\ConfigConstant;
use App\Constants\DeliveryConstant;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\TimeConstant;
use App\Helpers\AccountingHelper;
use App\Helpers\MediaHelper;
use App\Helpers\TimeHelper;
use App\Http\Resources\Complain\ComplainResource;
use App\Http\Resources\CustomerDelivery\CustomerDeliveryResource;
use App\Http\Resources\Delivery\DeliveryInOrderResource;
use App\Http\Resources\ListResource;
use App\Http\Resources\OnlyIdNameResource;
use App\Http\Resources\Package\OrderPackageResource;
use App\Http\Resources\ReportStatusResource;
use App\Models\CustomerDelivery;
use App\Models\OrderRefund;
use App\Models\OrderStatusTime;
use App\Models\Staff;
use App\Services\AccountingService;
use App\Services\ComplainService;
use App\Services\ConfigService;
use App\Services\DeliveryService;
use App\Services\OrderPackageService;
use App\Services\OrderService;
use App\Services\ReportCustomerService;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\StatusHelper;
use App\Models\OrderPackage;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\Log;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        $customer = $this->customer;
        $totalAmount = ($this->thuongdo_id != null && $this->has_edit == 0)
            ? $this->thuongdo_tong_tien_don_hang : $this->getTotalAmount(false);
        $totalAmountDisplay = ($this->thuongdo_id != null && $this->has_edit == 0)
            ? $this->thuongdo_tong_tien_don_hang : $this->getTotalAmount();

        // Logic mới ko cần quan tâm thuongdo_id nữa:
        $totalAmount = $this->getTotalAmount(false);
        $totalAmountDisplay = $this->getTotalAmount();
        $totalAmountNotShip = $this->getTotalAmountNotShip();
        // dd($totalAmountNotShip);
        $depositAmount  = $this->deposit_cost;
        $purchaseAmount = $totalAmount - $depositAmount;
        $depositPercentByCustomer = (new AccountingService())->getDepositPercent(
            $this->total_amount, data_get($customer, 'offer.deposit_rate')
        );
        $depositPercent = $this->deposit_percent ?: $depositPercentByCustomer;

        $customer_deliveryDB = optional($this->customerDelivery)->only('id', 'custom_name', 'province_id', 'receiver');
        if(isset($customer_deliveryDB)) {
            $customer_deliveryDB['id'] = (int) $customer_deliveryDB['id'];
        }

        $orderPercent = $this->order_percent;
        $subDiff = $this->getDiff($this);

        $subFix = 0;
        if ($this->id == 2899) {
            $subFix = 277200;
        }
        $orderRefunds = $this->orderRefunds ?? null;
        $is_edit_with_out_inspection = true;
        if ($orderRefunds && $orderRefunds->count() > 0) {
            foreach ($orderRefunds as $refund) {
                if ($refund->status == OrderRefund::STATUS_PROCESSING) {
                    $is_edit_with_out_inspection = false;
                    break;
                }
            }
        }
        return [
            'info' => [
                'id' => $this->id,
                'code' => $this->code,
                'code_parent' => $this->code_parent,
                'created_at' => TimeHelper::format($this->created_at, TimeConstant::DATETIME_BY_DAY_HI),
                'order_percent' => $orderPercent,
                'total_amount' => $totalAmountDisplay,
                'version_edit' => $this->version_edit,
                'is_waiting_for_sale' => $this->is_waiting_for_sale,
                'official' => $this->official,
                'type_official' => $this->type_official,
                'is_quoting' => $this->is_quoting,
                'is_china_shipping' => $this->is_china_shipping,
                'deposit_amount' => roundMoney($depositAmount) ?? 0,
                'deposit_cost_reality' => roundMoney(($totalAmountNotShip * $this->deposit_percent) / 100), // số tiền cần đặt cọc thực thế
                'deposit_percent' => $depositPercent,
                'purchase_amount' => AccountingHelper::getCosts($purchaseAmount),
                'packages_number' => $this->packages_number,
                'exchange_rate' => $this->exchange_rate,
                'is_edit_with_out_inspection' => $is_edit_with_out_inspection,
                'status' => new ReportStatusResource(
                    $this->status,
                    OrderConstant::STATUSES,
                    OrderConstant::STATUSES_COLOR
                ),
                'is_multiple_supplier' => $this->orderSuppliers()->count() > 1 ? true : false,
                'warehouse' => optional($this->warehouse)->only('id', 'custom_name', 'province_id'),
                'customer_delivery' => $customer_deliveryDB,
                'ecommerce' => $this->ecommerce,
                'china_shipping_cost_real' => $this->china_shipping_cost_real,
                'isContract' => $this->orderContracts->count() > 0 ? true : false,
                'order_contracts' => $this->orderContracts,
                'is_request_deposit_advence' => $this->is_request_deposit_advence,
                'orderDepositPercent' => $this->orderDepositPercent,
                'deposit_percent_old' => data_get($this->orderDepositPercent, 'deposit_percent_old')
            ],
            'customer' => [
                 'id' => optional($customer)->id,
                 'source' => optional($customer)->source,
                'deliveries' => new ListResource(
                    data_get($customer, 'delivery'),
                    CustomerDeliveryResource::class
                )
            ],
            'accounting' => $this->only(
                'order_fee',
                'woodworking_cost',
                'discount_cost',
                'inspection_cost',
                'advanced_inspection_cost',
                'china_shipping_cost',
                'international_shipping_cost',
                'order_cost',
                'total_amount',
                'deposit_cost',
                'actual_payment',
                'shock_proof_cost'
            ),
            'reports' => [
                'packages' => [
                    'quantity' => $this->packages_number,
                    'quantity_packages_number' => $this->packagesOrder->count() ?? 0,
                    'color' => $this->getColorByObject($this->id, $this->packages_number, 'packages'),
                    'data' => $this->getDataOrderPackage($this->packagesOrder)
                ],
                'complains' => [
                    'quantity' => $this->complains_number,
                    'color' => $this->getColorByObject($this->id, $this->complains_number, 'complains'),
                    'data' => $this->getDataComplain($this->complains)
                ],
                'deliveries' => [
                    'quantity' => $this->deliveries_number,
                    'color' => $this->getColorByObject($this->id, $this->deliveries_number, 'deliveries'),
                    'data' => $this->getDataDelivery($this->deliveriesPivot)
                ]
            ],


            // Thêm key error_logs bên kế toán
            'error_log' => $this->getErrorLog(),

            // Thêm key đánh dấu đây là đơn đồng bộ
            'thuongdo_id' => $this->thuongdo_id,
            'has_edit' => $this->has_edit,
            'thuongdo_tong_tien_tam_ung' => $this->thuongdo_tong_tien_tam_ung + $subFix,
            'thuongdo_tong_tien_hang' => $this->getTongTienHang(),
            'thuongdo_tong_tien_don_hang' => $this->thuongdo_tong_tien_don_hang + $subFix,

            // Thêm phần của giao hàng hoàn thành
            'delivery_pay_debt_cost' => (($this->debt_cost_pending == 1) ? 0 : $this->delivery_pay_debt_cost) + $subDiff,
            'delivery_pay_shipping_cost' => $this->delivery_pay_shipping_cost,
            'order_provisional_date' => !empty($this->order_provisional_date) ? date('d-m-Y H:i:s', strtotime($this->order_provisional_date)) : '',
            'is_can_edit_service' => $this->isCanEditService(),
            'staff_group_name' => data_get($this->staffGroup, 'name'),
            'is_inspection' => $this->is_inspection ?? 0,
            'is_advanced_inspection' => $this->is_advanced_inspection ?? 1,
            'type_order' => $this->type_order,
        ];
    }


    public function getDiff($orderDB)
    {
        $diffReturn = 0;
        // Với những đơn trước ngày 2024-10-07 thì mới cần tính
        if ($orderDB->id < 444724) {
            $totalAmountDisplay = $orderDB->getTotalAmount(false);
            $delivery_pay_debt_cost = $orderDB->delivery_pay_debt_cost;
            $delivery_pay_shipping_cost = $orderDB->delivery_pay_shipping_cost;
            $deposit_cost = $orderDB->deposit_cost;

            $diff =  round($totalAmountDisplay) - $delivery_pay_debt_cost - $delivery_pay_shipping_cost - $deposit_cost;

            if ($diff > -500 && $diff < 500) {
                $diffReturn = $diff;
            }
        }
        return $diffReturn;
    }


    public function getTongTienHang()
    {
        // phí kiểm đếm+ phí ship TQ+ phí đặt hàng+ tổng tiền hàng - chiết khấu
        return roundMoneyReverse(
            $this->order_fee
            + $this->order_cost
            + $this->inspection_cost
            + $this->china_shipping_cost * $this->exchange_rate
            - $this->discount_cost
        );
    }

    /**
     * @param  string  $orderId
     * @param  int  $quantity
     * @param  string  $object
     * @return string
     */
    public function getColorByObject(string $orderId, int $quantity, string $object): string
    {
        switch ($object) {
            case 'packages':
                $statusDone = optional($this->packages)->where('status', PackageConstant::STATUS_RECEIVED_GOODS)->count();
                break;
            case 'complains':
                $statusDone = optional($this->complains)->where('status', ComplainConstant::KEY_STATUS_DONE)->count();
                break;
            // Deliveries
            default:
                $statusDone = optional($this->deliveries)->where('status', DeliveryConstant::KEY_STATUS_DONE)->count();
                break;
        }

        return $this->getColor($statusDone === $quantity);
    }

    /**
     * @param  bool  $isDone
     * @return string
     */
    private function getColor(bool $isDone): string
    {
        $red   = ColorConstant::RED;
        $green = ColorConstant::GREEN;

        if ($isDone) {
            return $green;
        }

        return $red;
    }

    private function getErrorLog()
    {
        if ($this->error_logs == null) {
            return null;
        }

        $error_logsDB = array_reverse(json_decode($this->error_logs));

        return !empty($error_logsDB[0]) ? $error_logsDB[0] : null;
    }

    public function checkIsNewCustomer($customer)
    {
        $isNew = false;

        // $reportCustomer = optional($customer->report);
        $reportCustomer = data_get($customer, 'report');

        if (!$reportCustomer) {
            return $isNew;
        }

        $count  = 0;
        $status = OrderConstant::getStatusKeys();
        foreach ($status as $i_status) {
            $count += $reportCustomer->{$i_status};
        }

        return $count == 1 ? true : false;
    }

    public function getDataOrderPackage($orderPackage)
    {
        return $orderPackage->transform(
            function ($item) {
                $weight = $item->weight ?? 0;
                $volume = $item->volume ?? 0;
                return [
                    'id' => $item->id,
                    'order_id' => $this->id,
                    'order_code' => $this->code,
                    'bill_code' => $item->bill_code,
                    'code_po' => $item->code_po,
                    'is_inspection' => (boolean)$item->is_inspection,
                    'is_advanced_inspection' => (boolean)($item->is_advanced_inspection == 2 ? 1 : 0),
                    'is_insurance' => (boolean)$item->is_insurance,
                    'is_woodworking' => (boolean)$item->is_woodworking,
                    'is_shock_proof' => (boolean)$item->is_shock_proof,
                    'is_delivery' => $item->is_delivery,
                    'delivery_cost' => $item->delivery_cost,
                    'china_shipping_cost' => $item->china_shipping_cost * $item->exchange_rate,
                    'shipping_cost' => $item->shipping_cost ?? 0,
                    'inspection_cost' => $item->inspection_cost ?? 0,
                    'insurance_cost' => $item->insurance_cost ?? 0,
                    'woodworking_cost' => $item->woodworking_cost,
                    'shock_proof_cost' => $item->shock_proof_cost,
                    'international_shipping_cost' => $item->international_shipping_cost,
                    'weight_or_volume_v2' => $item->type_quantitative == 'weight' ? "$weight kg" : "$volume m³" ,
                    'amount' => $amount = $item->amount ?? 0,
                    'note' => $item->note,
                    'transporter' => $item->transporter,
                    'quantity' => $item->quantity,
                    'status' => $status = (new OrderPackageService())->getStatus($item->status) + StatusHelper::getTime(
                            $item->id,
                            OrderPackage::class,
                            $item->status
                        ),
                    'warehouse_code' => $item->warehouse->code ?? '',
                    'discount_cost' => $item->discount_cost
                ];
            }
        );
    }

    public function getDataComplain($complains)
    {
        return $complains->transform(
            function ($item) {
                $orderDetails = $item->orderDetails;
                $complainDetails = $item->complainDetails;
                return [
                    'id' => $item->id,
                    'image' => data_get($orderDetails->first(), 'name'),
                    'image_v2' => str_contains(data_get($orderDetails->first(), 'image'), 'http') ? data_get($orderDetails->first(), 'image') :  MediaHelper::getURL(data_get($orderDetails->first(), 'image')),
                    'packages_v2' => $this->getPackagesV2($complainDetails),
                    'complain_type' => data_get($item->complainType, 'name'),
                    'status' => StatusHelper::getInfo($item->status, ComplainConstant::class),
                    'created_at' => TimeHelper::format($item->created_at),
                    'comment_number' => $item->comment_number
                ];
            }
        );
    }
    public function getDataDelivery($deliveries)
    {
        return $deliveries->transform(
            function ($item) {
                return [
                    'id' => $this->id,
                    'created_at' => TimeHelper::format($item->created_at, TimeConstant::DATE_VI),
                    'date' => TimeHelper::format($item->date, TimeConstant::DATE_VI),
                    'transporter' => data_get($item->transporter, 'name'),
                    'bill_code' => optional($item->packages)->pluck('bill_code')->all(),
                    'delivery_cost' => $item->delivery_cost,
                    'amount' => $item->delivery_cost == 0 ? 'Chờ báo giá' : $item->amount,
                    'payment' => array_key_exists($item->payment, DeliveryConstant::PAYMENTS) ? DeliveryConstant::PAYMENTS[$item->payment] : "...",
                    'delivery' => [
                        'receiver' => $item->receiver,
                        'phone_number' => $item->phone_number,
                        'address' => $item->address,
                    ],
                    'status' => StatusHelper::getInfo($item->status, DeliveryConstant::class),
                ];
            }
        );
    }
    private function getPackagesV2($complainDetails)
    {
        $orderPackageIds = $complainDetails->pluck('order_package_id')->toArray();
        return (new OrderPackageService())->getPackageIds($orderPackageIds);
    }

    private function isCanEditService()
    {
        $userOnline = \Auth::user();
        $hasPerr = $userOnline->checkUserHasPer('order.is_edit_when_pack_in_qc');
        if ($hasPerr) {
            $orderPackges = $this->packages;
            if(count($orderPackges) > 1) {
                $flag = false;
                foreach ($orderPackges as $key => $item) {
                    if (!in_array($item->status, PackageConstant::ARRAY_STATUS_UPDATE_SERVICE_WITH_PER)) {
                        $flag = true;
                        break;

                    }
                }
                if ($flag) {
                    return false;
                }
            }
            return true;
        }

        foreach($this->packages as $package) {
            if(!in_array($package->status, PackageConstant::ARRAY_STATUS_UPDATE_SERVICE)) {
                return false;
            }
        }
        $auth = getCurrentUser();
        if(in_array($this->status, [OrderConstant::KEY_STATUS_ORDERED, OrderConstant::KEY_STATUS_WAIT_TO_PAY])) {
            if($auth->hasPermissionName('order.edit_service', $auth)) {
                return true;
            }else {
                return false;
            }
        }else {
            return true;
        }
    }
}
