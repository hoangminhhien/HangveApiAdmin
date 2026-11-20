<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Helpers\TimeHelper;
use App\Constants\TimeConstant;

class OrderContract extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'original_name', 'path', 'creator_id', 'signing_time', 'created_at'];

    protected $appends = [
        'created_at_format',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getCreatedAtFormatAttribute()
    {
        return TimeHelper::format($this->created_at, TimeConstant::DATETIME_BY_DAY_HI);

    }
}
