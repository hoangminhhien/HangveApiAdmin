<?php

namespace App\Http\Resources\Config;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class ListConfigLogResource extends JsonResource
{
    public function toArray($request)
    {
        $user = $this->user;
        return [
            'created_at' => Carbon::parse($this->created_at)->format('d-m-Y H:i:s'),
            'user_name' => data_get($user, 'name'),
            'type' => $this->type,
            'value_old' => data_get($this, 'value_old'),
            'value_new' => data_get($this, 'value_new')
        ];
    }
}
