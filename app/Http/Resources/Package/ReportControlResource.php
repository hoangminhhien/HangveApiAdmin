<?php

namespace App\Http\Resources\Package;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Http\Resources\ReportStatusResource;
use App\Constants\PackageConstant;
use App\Models\Order;
use App\Models\OrderPackage;
use App\Constants\DeliveryConstant;

class ReportControlResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $delivery = $this->delivery;
        if($delivery) {
            $amountPackage = 0;
            $totalAmountPaid = 0;
            $customerBalanceOld = $delivery->customer_balance_old + $delivery->amount_import;
            if($delivery->amount > $customerBalanceOld) {
                $orderPackageAll = OrderPackage::where('delivery_id', $delivery->id)->withoutGlobalScopes()->get();
                foreach($orderPackageAll as $item) {
                    if($customerBalanceOld > 0) {
                        $amountItem = $item->amount;
                        $amountPaid = 0;
                        $orderItem = $item->orderPackageWithOrder;
                        if($item->order_type == Order::class && $orderItem) {
                            if(!empty($orderItem->packages)) {
                                $packageFirst = OrderPackage::where('order_id', $orderItem->id)->where('order_type', Order::class)
                                                ->withoutGlobalScopes()->orderBy('id')->first();
                                // Kiểm tra nếu là kiện đầu tiên thì cộng thêm tiền hàng còn thiếu vào
                                if($packageFirst->id == $item->id) {
                                    $amountPaid += $orderItem->delivery_pay_debt_cost;
                                    $amountItem += $orderItem->delivery_pay_debt_cost;
                                }
                            }
                        }
                        if($amountItem > $customerBalanceOld) {
                            $amountPaid += $customerBalanceOld;
                        }else {
                            $amountPaid += $amountItem;
                        }
                        if($item->id == $this->id) {
                            $totalAmountPaid += $amountPaid;
                            $amountPackage += $amountItem;
                        }
                        $customerBalanceOld -= $amountPaid;
                    }else {
                        $amountItem = $item->amount;
                        $amountPaid = 0;
                        $orderItem = $item->orderPackageWithOrder;
                        if($item->order_type == Order::class && $orderItem) {
                            if(!empty($orderItem->packages)) {
                                $packageFirst = OrderPackage::where('order_id', $orderItem->id)->where('order_type', Order::class)
                                                ->withoutGlobalScopes()->orderBy('id')->first();
                                // Kiểm tra nếu là kiện đầu tiên thì cộng thêm tiền hàng còn thiếu vào
                                if($packageFirst->id == $item->id) {
                                    $amountPaid += $orderItem->delivery_pay_debt_cost;
                                    $amountItem += $orderItem->delivery_pay_debt_cost;
                                }
                            }
                        }
                        if($item->id == $this->id) {
                            $totalAmountPaid += $amountPaid;
                            $amountPackage += $amountItem;
                        }
                        $customerBalanceOld -= $amountPaid;
                    }
                    
                }
            }else {
                $amountPackage = $totalAmountPaid = $this->amount;
                $order = $this->orderPackageWithOrder;
                if($this->order_type == Order::class && $order) {
                    if(!empty($order->packages)) {
                        $packageFirst = $order->packages->first();
                        // Kiểm tra nếu là kiện đầu tiên thì cộng thêm tiền hàng còn thiếu vào
                        if($packageFirst->id == $this->id) {
                            $amountPackage += $order->delivery_pay_debt_cost;
                            $totalAmountPaid += $order->delivery_pay_debt_cost;
                        }
                    }
                }
            }
            $method = data_get($delivery->transporter, 'name');
        }else {
            $amountPackage = $totalAmountPaid = $this->amount;
            if($this->status == PackageConstant::STATUS_RECEIVED_GOODS) {
                $amountPackage = $this->amount;
            }else {
                $amountPackage = $this->amount;
                $totalAmountPaid = 0;
                $order = $this->orderPackageWithOrder;
                if($this->order_type == Order::class && $order) {
                    if(!empty($order->packages)) {
                        $packageFirst = $order->packages->first();
                        // Kiểm tra nếu là kiện đầu tiên thì cộng thêm tiền hàng còn thiếu vào
                        if($packageFirst->id == $this->id) {
                            $amountPackage += $order->delivery_pay_debt_cost;
                            $totalAmountPaid += $order->delivery_pay_debt_cost;
                        }
                    }
                }
            }
            $method = 'Nhận tại kho';
        }
        return [
            'date_done' => $this->date_done ? Carbon::parse($this->date_done)->format('d/m/Y H:i:s') : '',
            'bill_code' => $this->bill_code,
            'customer_name' => data_get($this->customer, 'name'),
            'customer_code' => data_get($this->customer, 'code'),
            'customer_id' => data_get($this->customer, 'id'),
            'user_name' => data_get($this->staffGroup, 'name'),
            'delivery_id' => data_get($delivery, 'id'),
            'delivery_code' => data_get($delivery, 'code'),
            'total_amount' => $amountPackage,
            'total_amount_paid' => $totalAmountPaid,
            'total_amount_remain' => $amountPackage - $totalAmountPaid,
            'method' => $method,
            'status' => new ReportStatusResource(
                $this->status,
                PackageConstant::STATUSES,
                PackageConstant::STATUSES_COLOR
            ),
            'type' => $delivery ? 'Giao hàng' : 'Đến kho nhận hàng'
        ];
    }
}
