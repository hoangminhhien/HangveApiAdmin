<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetailHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_detail_id',
        'user_id',
        'value',
        'value_old',
        'object',
        'thuongdo_id',
        'order_status'
    ];

    const OBJECT_PRICE = 'unit_price_cny';  // Đơn giá
    const OBJECT_QUANTITY = 'quantity';     // Số lượng
    const OBJECT_NOTE_OTHER = 'note_other'; // Ghi chú khác
    const CLASSSIFICATION = 'classification'; // thuộc tính
    const LINK = 'link'; // link

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }
}
