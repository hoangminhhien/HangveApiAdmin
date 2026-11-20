<?php

namespace App\Models;

use App\Traits\Relationships\SubOrderBuyderRelationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubOrderBuyder extends BaseModel
{
    use HasFactory, SubOrderBuyderRelationships;

    protected $table = 'order_buyder_subs';

    protected $casts = [
        'domestic_post_fee' => 'float',
    ];

    public function userStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function orderBuyderCategory(): BelongsTo
    {
        return $this->belongsTo(OrderBuyderCategory::class, 'order_buyder_id', 'order_buyder_id')->where('order_id', $this->order_id);
    }
}
