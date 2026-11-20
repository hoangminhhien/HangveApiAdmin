<?php

namespace App\Http\Resources\Fund;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Fund;

class FundResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                    => (int)$this->id,
            'name'                  => $this->name,
            'type_common_default'   => $this->type_common == Fund::TYPE_COMMON_DEFAULT ? 1 : 0,
            'type_common_warehouse' => $this->type_common == Fund::TYPE_COMMON_WAREHOUSE ? 1 : 0,
            'warehouse_id'          => $this->warehouse_id == 0 ? "" : $this->warehouse_id,
        ];
    }
}
