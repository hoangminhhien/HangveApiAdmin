<?php

namespace App\Http\Resources\Report;

use App\Constants\CustomerConstant;
use App\Constants\OrderConstant;
use App\Models\Customer;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerActiveResource extends JsonResource
{
    public function toArray($request): array
    {
        $orderLast = $this->getLastOrder(false);
        $orders = optional($this->orders);
        $orderDeposit = $orders ? $orders->where('status', OrderConstant::KEY_STATUS_WAITING_DEPOSIT) : '';
        $totalOrdersDeposit = $orderDeposit ? $orderDeposit->count() : 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone_number,
            'email' => $this->email,
            'source' => $this->source ?? '',
            'source_name' => Customer::LIST_SOURCE[$this->source] ?? '',
            'last_order' => $orderLast ? date('d/m/Y', strtotime($orderLast)) : '',
            'orders_deposit' => $totalOrdersDeposit,
            'categories' => $totalOrdersDeposit ? $this->getListCategories($orderDeposit) : '',
            'deposit_level' => $totalOrdersDeposit ? $this->getLevelDeposit($orderDeposit) : ''
        ];
    }

    public function getListCategories($orderDeposit)
    {
        $categories = [];

        $orderDeposits = $orderDeposit->all();

        foreach ($orderDeposits as $item) {
            $orderDetails = !empty($item->details) ? $item->details : [];
            foreach ($orderDetails as $orderDetail) {
                $categories[] = optional($orderDetail->category)->name;
            }
        }

        return implode(', ', array_unique($categories));
    }

    public function getLevelDeposit($orderDeposit)
    {
        $level = '';

        $orderDepositFirst = $orderDeposit->first();
        $orderStatusTime   = optional($orderDepositFirst->orderStatusTime)->{OrderConstant::KEY_STATUS_WAITING_DEPOSIT};

        if (!$orderStatusTime) {
            return $level;
        }

        $timeStart = strtotime($orderStatusTime) + 5 * 60;
        $timeEnd   = strtotime($orderStatusTime) + 60 * 60;
        if (time() <= $timeStart) {
            $level = CustomerConstant::WAIT_DEPOSIT_LV1;
        } else if (time() > $timeStart && time() <= $timeEnd) {
            $level = CustomerConstant::WAIT_DEPOSIT_LV2;
        } else { // over 60 minutes
            $level = CustomerConstant::WAIT_DEPOSIT_LV3;
        }

        return $level;
    }
}
