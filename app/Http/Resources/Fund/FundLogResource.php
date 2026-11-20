<?php

namespace App\Http\Resources\Fund;

use Illuminate\Http\Resources\Json\JsonResource;

class FundLogResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'fund_id'    => $this->fund_id,
            'fund_name'  => $this->fund_name,
            'user_id'    => $this->user_id,
            'user_name'  => $this->user_name,
            'created_at' => date('Y/m/d H:i', strtotime($this->created_at))
        ];
    }
}
