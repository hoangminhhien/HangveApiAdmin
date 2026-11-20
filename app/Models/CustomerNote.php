<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNote extends Model
{
    use HasFactory;

    protected $table = 'customer_notes';

    protected $fillable = [
        'customer_id',
        'user_id',
        'content',
        'thuongdo_id',
        'type',
        'created_at',
        'updated_at'
    ];

    // Logic ngày 27-12-24
    // public const CUSTOMER_NOTE_TYPE_0 = 0; // Dùng ở màn đơn hàng thì mới hiện
    // public const CUSTOMER_NOTE_TYPE_1 = 1; // Dùng ở màn khách hàng thì mới hiện


    // Logic ngày 30-12-24
    public const CUSTOMER_NOTE_TYPE_0 = 0; // Mặc định hiện ở cả 2 bên
    public const CUSTOMER_NOTE_TYPE_1 = 1; // Dùng ở màn khách hàng thì mới hiện
    public const CUSTOMER_NOTE_TYPE_2 = 2; // Dùng ở màn đơn hàng thì mới hiện

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'user_id', 'id');
    }
}
