<?php

namespace App\Models;

use App\Constants\OrderConstant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusTime extends BaseModel
{
    use HasFactory, SoftDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected $dates = OrderConstant::STATUSES_KEYS;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
