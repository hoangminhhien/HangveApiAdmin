<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'old_value',
        'new_value',
        'content',
        'date',
        'history_type'
    ];

    const ORDER_HISTORY_NOTE_TYPE = 'history_note';
    const ORDER_HISTORY_NOTE_BUSINESS_TYPE = 'history_note_business'; // ghi chú nội bộ nv kinh  doanh

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
