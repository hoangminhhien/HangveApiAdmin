<?php

namespace App\Http\Resources\Delivery;

use App\Helpers\AccountingHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class PrintOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'code' => $this['code'],
            'amount' => AccountingHelper::getCosts(data_get($this, 'total_amount', 0)),
            'deposit_cost' => data_get($this, 'deposit_cost', 0),
            'balance' => data_get($this, 'balance', 0)
        ];
    }
}
