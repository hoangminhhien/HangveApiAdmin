<?php

namespace App\Http\Resources\Order;

use App\Constants\OrderConstant;
use App\Constants\TimeConstant;
use App\Helpers\AccountingHelper;
use App\Helpers\TimeHelper;
use App\Http\Resources\OnlyIdNameResource;
use App\Http\Resources\ReportStatusResource;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class OrderCustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $customer = $this->customer;
        $details  = $this->details;
        return [
            'order_id' => $this->id,
            'order_code' => $this->code,
            // 'total_amount' => AccountingHelper::getCosts((float)$this->total_amount),
            'total_amount'    => $this->getTotalAmount(),
            'deposit_cost' => $deposit = (float)$this->deposit_cost,
            'debt_cost' => (float) $this->debt_cost,
            'status' => new ReportStatusResource($this->status, OrderConstant::STATUSES, OrderConstant::STATUSES_COLOR),
            'date_ordered' => TimeHelper::format($this->date_ordered, TimeConstant::DATE_VI),
            'packages_number' => $this->packages_number,
            'exchange_rate' => (float)$this->exchange_rate,
            'customer' => [
                'id' => optional($customer)->id,
                'name' => optional($customer)->name,
                'phone_number' => optional($customer)->phone_number,
            ],
            'staff' => [
                'order' => new OnlyIdNameResource($this->staffOrder),
                //'sale' => new OnlyIdNameResource($this->staffSale),
                //'care' => new OnlyIdNameResource($this->staffCare),
                //'counselor' => new OnlyIdNameResource($this->staffCounselor),
                //'quotation' => new OnlyIdNameResource($this->staffQuotation),
            ],
            'total_quantity' => intval(optional($details)->sum('quantity')),

            // Thêm key đánh dấu đây là đơn đồng bộ
            'thuongdo_id' => $this->thuongdo_id,
            'thuongdo_tong_tien_tam_ung' => $this->thuongdo_tong_tien_tam_ung,
            'thuongdo_tong_tien_don_hang' => $this->thuongdo_tong_tien_don_hang,

            // Thêm phần của giao hàng hoàn thành
            'delivery_pay_debt_cost' => $this->delivery_pay_debt_cost,
            'delivery_pay_shipping_cost' => $this->delivery_pay_shipping_cost
        ];
    }

    public function getTotalAmount()
    {
        $totalAmount = 0;

        $orderSuppliers = $this->orderSuppliers;
        if ($orderSuppliers->isEmpty()) {
            return $totalAmount;
        }

        $totalWoodWorkingCost       = $orderSuppliers->sum('woodworking_cost');
        $totalChinaShippingCost     = $orderSuppliers->sum('china_shipping_cost');
        $totalOrderFee              = $orderSuppliers->sum('order_fee');
        $totalOrderCost             = $orderSuppliers->sum('order_cost');
        $totalInspectionCost        = $orderSuppliers->sum('inspection_cost');
        $totalDiscountCost          = $orderSuppliers->sum('discount_cost');
        $totalInternalShippingCost  = $orderSuppliers->sum('international_shipping_cost');

        $totalDiscountCostPacks = !empty($this->packages) ? $this->packages->sum('discount_cost') : 0;
        $totalAmount = (
            $totalWoodWorkingCost + ($totalChinaShippingCost * $this->exchange_rate) + $totalOrderFee + $totalOrderCost +
            $totalInspectionCost + $totalInternalShippingCost
        ) - ( $totalDiscountCost + $totalDiscountCostPacks );

        $totalAmount = roundMoney($totalAmount);

        return $totalAmount;
    }
}
