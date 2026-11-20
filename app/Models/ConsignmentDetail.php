<?php

namespace App\Models;

use App\Traits\OrderActivityLogs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentDetail extends BaseModel
{
    use HasFactory, SoftDeletes;
    use OrderActivityLogs;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function orderPackage(): BelongsTo
    {
        return $this->belongsTo(OrderPackage::class);
    }
}
