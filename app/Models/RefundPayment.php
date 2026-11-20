<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundPayment extends BaseModel
{
    use HasFactory;

    protected string $_tableNameFriendly = 'Thanh toán hoàn tiền';

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }
}
