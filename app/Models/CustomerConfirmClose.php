<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerConfirmClose extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_create_id',
        'user_confirm_id',
        'note_id',
        'status',
    ];

    const STATUS_CONFIRMED = 2; // đã duyệt
    const STATUS_CANCEL = 3; // đã hủy
    const STATUS_WAITING_CONFIRM = 1; // chờ duyệt

    const LIST_STATUS = [
        self::STATUS_WAITING_CONFIRM => [
            'name' => 'Chờ duyệt',
            'color' => '#F09920'
        ],
        self::STATUS_CONFIRMED => [
            'name' => 'Đã duyệt',
            'color' => '#26B01C'
        ],
        self::STATUS_CANCEL => [
            'name' => 'Đã hủy',
            'color' => '#ED1C24'
        ],
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function userCreate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_create_id', 'id');
    }

    public function userConfirm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_confirm_id', 'id');
    }

    public function noteReason(): BelongsTo
    {
        return $this->belongsTo(CustomerReasonInactive::class, 'note_id', 'id');
    }
}
