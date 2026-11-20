<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOrder extends BaseModel
{
    use HasFactory, SoftDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function order(): MorphTo
    {
        return $this->morphTo();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
