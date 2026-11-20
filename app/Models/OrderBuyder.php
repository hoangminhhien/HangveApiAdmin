<?php

namespace App\Models;

use App\Traits\Relationships\OrderBuyderRelationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderBuyder extends BaseModel
{
    use HasFactory, OrderBuyderRelationships;

    protected $table = 'order_buyders';

    public function orderBuyderSub(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubOrderBuyder::class, 'order_buyder_id', 'id');
    }
}
