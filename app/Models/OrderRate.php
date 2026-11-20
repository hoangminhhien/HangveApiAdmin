<?php

namespace App\Models;

use App\Scopes\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderRate extends BaseModel
{
    use HasFactory, Filterable, SoftDeletes;

    protected string $_tableNameFriendly = 'Đánh giá';

    protected $table = 'order_rates';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_code', 'code');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_code', 'code');
    }
}
