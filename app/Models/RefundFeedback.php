<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RefundFeedback extends BaseModel
{
    use HasFactory;

    protected string $_tableNameFriendly = 'Ý kiến hoàn tiền';

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }
    public function cause(): MorphTo
    {
        return $this->morphTo();
    }
}
