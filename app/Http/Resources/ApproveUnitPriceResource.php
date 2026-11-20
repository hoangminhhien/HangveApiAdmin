<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\TimeHelper;
use App\Constants\ApproveUnitPriceConstant;
use Carbon\Carbon;

class ApproveUnitPriceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $user = $this->user;
        $customer = $this->customer;
        $orderPackage = $this->orderPackage;
        $approveUser = $this->approveUser;
        return [
            'id' => $this->id,
            'user_name' => data_get($user, 'name'),
            'created_at' => Carbon::parse($this->created_at)->format('d-m-Y H:i:s'),
            'customer_name' => data_get($customer, 'name'),
            'customer_code' => data_get($customer, 'code'),
            'bill_code' => data_get($orderPackage, 'bill_code'),
            'unit' => $this->unit,
            'unit_price' => $this->unit_price,
            'request_reason' => $this->request_reason,
            'cancel_reason' => $this->cancel_reason,
            'status' => [
                'value' => $this->status,
                'value_original' => $this->status,
                'color' => ApproveUnitPriceConstant::STATUSES_COLOR[$this->status],
                'label' => ApproveUnitPriceConstant::STATUSES[$this->status],
            ],
            'approve_user_name' => data_get($approveUser, 'name'),
            'approve_date' => $this->approve_date ? Carbon::parse($this->approve_date)->format('d-m-Y H:i:s') : null,
        ];
    }
}
