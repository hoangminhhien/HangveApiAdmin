<?php

namespace App\Http\Resources\FundTransaction;

use Illuminate\Http\Resources\Json\JsonResource;

class FundTransactionRmbResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                 => (int) $this->id,

            'fund_id'            => $this->fund_id,
            'fund_name'          => $this->fund_name,

            'customer_code'      => $this->customer_code,
            'customer_name'      => $this->customer_name,
            'customer_phone'     => $this->customer_phone,
            'customer_id'        => optional($this->customer)->id,

            'money'              => (int) ($this->money_update - $this->fee_system), // Tổng tệ đổi

            'fee_customer'       => $this->fee_customer, // Phí khách hàng
            'fee_customer_ratio' => $this->fee_customer_ratio, // Tỉ giá khách hàng
            'fee_system'         => $this->fee_system, // Phí hệ thống
            'fee_system_ratio'   => $this->fee_system_ratio, // Phí hệ thống

            'profit'             => $this->getProfit(), // Lợi nhuận

            'create_at'          => $this->created_at ? $this->created_at->format('Y-m-d H:i') : null
        ];
    }

    private function getCustomerId()
    {
        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('code', $this->customer_code)->first();

        return optional($customer)->id;
    }

    public function getProfit()
    {
        // - Lợi nhuận = (Tổng tệ đổi +Phí khách hàng ) * tỷ giá KH - (Tổng tệ đổi + phí hệ thống) * tỷ giá HT
        $a = ($this->money_update - $this->fee_system  + $this->fee_customer) * $this->fee_customer_ratio;
        $b = ($this->money_update - $this->fee_system + $this->fee_system) * $this->fee_system_ratio;
        return $a - $b;
    }
}
