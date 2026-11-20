<?php

namespace App\Models;

use App\Models\Traits\ImageAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplainDetail extends BaseModel
{
    use HasFactory, ImageAttribute;

    public function orderDetail(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'order_detail_id', 'id');
    }
}
