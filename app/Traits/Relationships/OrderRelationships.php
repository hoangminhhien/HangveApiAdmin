<?php

namespace App\Traits\Relationships;

use App\Models\OrderBuyder;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait OrderRelationships
{
    /**
     * Lấy ra bản ghi tương ứng trong 'order_buyders'
     *
     * @return HasMany
     */
    public function orderBuyders(): HasMany
    {
        return $this->hasMany(OrderBuyder::class);
    }

    /**
     * Lấy ra nhân viên phụ trách đơn hàng (users)
     *
     * @return BelongsTo
     */
    public function staffOrder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_order_id', 'id');
    }
}
