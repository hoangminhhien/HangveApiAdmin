<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Constants\CustomerLogEndowConstant;

class CustomerOfferEndowResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = $this->user;
        $is_percent = false;
        if (in_array($this->type, ['debt', 'deposit_rate', 'level_rate', 'offer_rate', 'consignment_rate'])) {
            $is_percent = true;
        }
        return [
            'created_at' => Carbon::parse($this->created_at)->format('d-m-Y H:i:s'),
            'user_name' => data_get($user, 'name'),
            'type' => $this->type,
            'is_percent' => $is_percent,
            'type_name' => data_get(CustomerLogEndowConstant::TYPES, $this->type),
            'value_old' => $this->type == 'debt' ? (data_get($this, 'value_old') == 1 ? 'Có' : 'Không') : data_get($this, 'value_old'),
            'value_new' => $this->type == 'debt' ? (data_get($this, 'value_new') == 1 ? 'Có' : 'Không') : data_get($this, 'value_new')
        ];
    }
}
