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
use App\Helpers\TimeHelper;
use App\Http\Resources\Complain\ComplainResource;
use App\Http\Resources\CustomerDelivery\CustomerDeliveryResource;
use App\Http\Resources\Delivery\DeliveryInOrderResource;
use App\Http\Resources\ListResource;
use App\Http\Resources\OnlyIdNameResource;
use App\Http\Resources\Package\OrderPackageResource;
use App\Http\Resources\ReportStatusResource;
use App\Models\CustomerDelivery;
use App\Models\Ecommerce;
use App\Models\Order;
use App\Models\OrderStatusTime;
use App\Models\Staff;
use App\Services\AccountingService;
use App\Services\ComplainService;
use App\Services\ConfigService;
use App\Services\DeliveryService;
use App\Services\NotificationService;
use App\Services\OrderPackageService;
use App\Services\OrderService;
use App\Services\ReportCustomerService;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

class OrderListResource extends JsonResource
{
    public function toArray($request): array
    {
        $key = Ecommerce::CACHE_KEY_ECOMMERCE;
        $lists = Cache::get($key, []);
        Cache::forget($key);
        if (empty($lists)) {
            $lists = Ecommerce::query()->get()->toArray();
            Cache::put($key, $lists);
        }
        $customer = $this->customer;
        // Logic mới ko cần quan tâm thuongdo_id nữa:
        $totalAmount = $this->getTotalAmount(false);
        $totalAmountDisplay = $this->getTotalAmount();
        $totalAmountNotShip = $this->getTotalAmountNotShip();

        $depositAmount  = $this->deposit_cost;
        $purchaseAmount = roundMoney($totalAmount) - (float) $depositAmount;
        $depositPercentByCustomer = (new AccountingService())->getDepositPercent(
            $this->total_amount, data_get($customer, 'offer.deposit_rate')
        );
        $depositPercent = $this->deposit_percent ?: $depositPercentByCustomer;
        $orderProvisionalDate = $this->orderProvisionalDate ?? [];
        if (!empty($orderProvisionalDate)) {
            $count = 1;
            foreach ($orderProvisionalDate as &$date) {
                $date->order_provisional_date_note = date('d-m-Y', strtotime($date->order_provisional_date));
                if (!empty($date->supplier)) {
                    $date->supplier_name = 'NCC ' . $count;
                }
                $count++;
            }
            unset($date);
        }
        $orderPercent = $this->order_percent;
        $subDiff = $this->getDiff($this);
        $statusAt = '';
        if($this->{$request->get('status')}) {
            $statusAt = Carbon::parse($this->{$request->get('status')})->format('d-m-Y H:i');
        } else {
            if($this->{$this->status}) {
                $statusAt = Carbon::parse($this->{$this->status})->format('d-m-Y H:i');
            }
        }
        return [
            'info' => [
                'id' => $this->id,
                'code' => $this->code,
                'orderSlowQuote' => $this->is_sent_notification == OrderConstant::IS_SENT_NOTIFICATION_TRUE
                    && $this->status == OrderConstant::KEY_STATUS_WAITING_QUOTE
                    ? true : false,
                'code_parent' => $this->code_parent,
                'created_at' => TimeHelper::format($this->created_at, TimeConstant::DATETIME_BY_DAY_HI),
                'status_at' => $statusAt,
                'notes' => $this->notePublic->count(),
                'note_ncc' => (int) ($this->supplier_note_count ?? 0),
                'order_percent' => $orderPercent,
                'total_amount' => roundMoney($totalAmountDisplay),
                'is_waiting_for_sale' => $this->is_waiting_for_sale,
                'is_waiting_confirm' => $this->is_waiting_confirm,
                'official' => $this->official,
                'type_official' => $this->type_official,
                'type_official_text' => $this->official_is_partner ? 'Đi bên đối tác' : ($this->type_official == OrderConstant::TYPE_OFFICIAL_1 ? OrderConstant::TYPE_OFFICIAL_1_TEXT : OrderConstant::TYPE_OFFICIAL_2_TEXT),
                'official_text' => $this->official == OrderConstant::OFFICIAL ? OrderConstant::OFFICIAL_TEXT : '',
                'is_order_now' => $this->is_order_now,
                'discount_percent' => $this->discount_percent,
                'inspection_cost' => $this->inspection_cost,
                'advanced_inspection_cost' => $this->advanced_inspection_cost,
                'deposit_amount' => roundMoney($depositAmount) ?? 0,
                'deposit_cost_reality' => roundMoney(((float) $totalAmountNotShip * (int) $this->deposit_percent) / 100), // số tiền cần đặt cọc thực thế
                'deposit_percent' => $depositPercent,
                'purchase_amount' => AccountingHelper::getCosts($purchaseAmount),
                'packages_number' => $this->packages_number,
                'exchange_rate' => $this->exchange_rate,
                'count_link' => $this->details->count(),
                'status' => new ReportStatusResource(
                    $this->status,
                    OrderConstant::STATUSES,
                    OrderConstant::STATUSES_COLOR
                ),
                'is_edit_ecommerce' => in_array(
                    $this->status , [
                        OrderConstant::KEY_STATUS_WAITING_QUOTE,
                        OrderConstant::KEY_STATUS_WAITING_DEPOSIT,
                        OrderConstant::KEY_STATUS_DEPOSITED
                    ]) || $this->ecommerce == null,
                'international_shipping_cost' => $this->orderSuppliers->sum('international_shipping_cost'),
                'ecommerce' => $this->ecommerce,
                'china_shipping_cost_real' => $this->china_shipping_cost_real,
                'isContract' => $this->orderContracts->count() > 0 ? true : false,
                'order_cost_and_ship_tq' => ($this->exchange_rate ? ($this->orderSuppliers->sum('order_cost')/$this->exchange_rate) : $this->orderSuppliers->sum('order_cost')) + $this->china_shipping_cost,
            ],
            'customer' => [
                'id' => optional($customer)->id,
                'name' => optional($customer)->name,
                'phone_number' => optional($customer)->phone_number,
                'warehouse' => optional(optional(optional($customer)->warehouse)->province)->name,
                'level' => ($customer ? $customer->reportCustomerOrders->count() == 0  : null )? 'KH Mới' : null,
                'notes' => $customer ? $customer->customerNotes->count() : 0,
                'notes_type_0' => $customer ? $customer->customerNotesType2And0->count() : 0,
                'source' => $customer ? $customer->source : 0,
            ],
            'staff' => [
                'staff_order_id' => $this->staff_order_id,
                'staff_quotation_id' => $this->staff_quotation_id,
                'staff_group_id' => $this->staff_group_id,

                'staff_order_name' => optional($this->staffOrder)->name,
                'staff_quotation_name' => optional($this->staffQuotation)->name,
                'staff_group_name' => optional($this->staffGroup)->name,
            ],
            'is_cancel' => in_array(
                $this->status, [OrderConstant::KEY_STATUS_WAITING_QUOTE, OrderConstant::KEY_STATUS_WAITING_DEPOSIT]
            ),
            'has_edit' => $this->has_edit,
            // Thêm key đánh dấu đây là đơn đồng bộ
            'thuongdo_id' => $this->thuongdo_id,
            'thuongdo_tong_tien_tam_ung' => $this->thuongdo_tong_tien_tam_ung,
            'thuongdo_tong_tien_don_hang' => $this->thuongdo_tong_tien_don_hang,

            // Thêm phần của giao hàng hoàn thành
            'delivery_pay_debt_cost' => $this->delivery_pay_debt_cost + $subDiff,
            'delivery_pay_shipping_cost' => $this->delivery_pay_shipping_cost,
            'order_provisional_date' => $orderProvisionalDate,
            'organization_id' => $this->organization_id,
            'is_inspection' =>$this->is_inspection,
            'is_advanced_inspection' => $this->is_advanced_inspection,
            'reason_confirm_order' => in_array($this->status, [OrderConstant::KEY_STATUS_WAITING_QUOTE, OrderConstant::KEY_STATUS_RECONFIRM]) ? data_get($this, 'reasonConfirmOrder.name') : '',
            'is_up_order_cost' => in_array($this->status, [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY, OrderConstant::KEY_STATUS_ORDERED]) ? $this->is_up_order_cost : 0,
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
}
