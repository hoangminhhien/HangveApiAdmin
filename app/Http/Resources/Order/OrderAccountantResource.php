<?php

namespace App\Http\Resources\Order;

use App\Constants\ColorConstant;
use App\Constants\OrderConstant;
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
use App\Models\OrderStatusTime;
use App\Models\Staff;
use App\Services\ComplainService;
use App\Services\DeliveryService;
use App\Services\OrderPackageService;
use App\Services\ReportCustomerService;
use Illuminate\Http\Resources\Json\JsonResource;

// Phục vụ cho màn bên kế toán nó ít dữ liệu hơn
class OrderAccountantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // dd($this);
        $actualPayment = $this->order_cost_real / $this->exchange_rate + $this->china_shipping_cost_real ;
        $codePo = !$this->packages->isEmpty() ? $this->packages[0] : '';
        if($codePo) {
            $codePo = $codePo->code_po;
        }
        $orderStatusTime = $this->orderStatusTime;
        $orderCostReal = round($this->order_cost_real / $this->exchange_rate, 2);
        $orderCost = round($this->order_cost / $this->exchange_rate, 2);
        return [
            'info' => [
                'id' => $this->id,
                'code' => $this->code, // Mã đơn hàng
                'code_po' => $codePo ?? '', // Mã đặt hàng
                'arr_code_po' => $this->packages()->pluck('code_po'),
                'created_at' => TimeHelper::format($this->created_at, TimeConstant::DATETIME_BY_DAY_HI),
                'deposit_at' => data_get($orderStatusTime, 'status_2') ? TimeHelper::format(data_get($orderStatusTime, 'status_2'), TimeConstant::DATETIME_BY_DAY_HI) : null, // Thời gian đặt cọc
                'wait_to_pay_at' => data_get($orderStatusTime, 'status_8') ? TimeHelper::format(data_get($orderStatusTime, 'status_8'), TimeConstant::DATETIME_BY_DAY_HI) : null, // Thời gian chờ thanh toán
                'exchange_rate' => $this->exchange_rate,
                'actual_payment' => $actualPayment,
                'ecommerce' => $this->ecommerce,
                'ecommerce_name' => $this->ecommerce ? (!empty(OrderConstant::ECOMMERCE[$this->ecommerce]) ?
                    OrderConstant::ECOMMERCE[$this->ecommerce] : 'Khác') : '',
                'status' => new ReportStatusResource(
                    $this->status,
                    OrderConstant::STATUSES,
                    OrderConstant::STATUSES_COLOR
                ),
                'date_ordered' => TimeHelper::format($this->date_ordered, TimeConstant::DATETIME_BY_DAY_HI),
            ],
            'staff' => [
                'order' => new OnlyIdNameResource($this->staffOrder),
            ],
            'accounting' => $this->only(
                'order_cost_real',
                'china_shipping_cost_real',
                'order_cost',
                'china_shipping_cost',
            ),
            // Custom dữ liệu
            'exchange_rate' => $this->exchange_rate,
            'order_cost_real' => $orderCostReal,
            'order_cost' => $orderCost,

            'china_shipping_cost_real' => $this->china_shipping_cost_real ?? 0,
            'china_shipping_cost' => $this->china_shipping_cost,

            'diff_china_shipping_cost' => $this->china_shipping_cost_real - $this->china_shipping_cost,
            'diff_order_cost' => $orderCostReal - $orderCost,

            'diff_order_and_shipping_cost' => $this->getDiff(),
            'diff_order_and_shipping_cost_abs' => abs($this->getDiff()),
            'bonus' => $this->bonus,
            'organization_id' => $this->organization_id,
            'deposit_percent' => $this->deposit_percent,
            'customer' => [
                'id' => data_get($this->customer, 'id'),
                'source' => data_get($this->customer, 'source'),
            ],
        ];
    }

    public function getDiff()
    {
        /**
         * Công thức
         * Tiền hàng đã trả + Phí TQ đã trả - (Tiền hàng thực tế + phí ship TQ thực tế)
        */
        $orderCostReal = round((float)$this->order_cost_real / $this->exchange_rate, 2);
        $chinaShippingCostReal = (float)$this->china_shipping_cost_real;
        $orderCostOld = round((float)$this->order_cost / $this->exchange_rate, 2);
        $chinaShippingCostOld = (float)$this->china_shipping_cost;
        $bonus = (float)$this->bonus;

        return $orderCostOld + $chinaShippingCostOld  - ($orderCostReal + $chinaShippingCostReal) + $bonus;
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
                return $this->getColor((new OrderPackageService())->getStatusesDone($orderId) === $quantity);
            case 'complains':
                return $this->getColor((new ComplainService())->getStatusesDone($orderId) === $quantity);
            // Deliveries
            default:
                return $this->getColor((new DeliveryService())->getStatusesDone($orderId) === $quantity);
        }
    }

    /**
     * @param  bool  $isDone
     * @return string
     */
    private function getColor(bool $isDone): string
    {
        $red = ColorConstant::RED;
        $green = ColorConstant::GREEN;
        if ($isDone) {
            return $green;
        }
        return $red;
    }
}
