<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSupplierHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_supplier_id',
        'supplier_id',
        'object',
        'value',
        'value_old',
        'order_status',
        'user_id',
        'thuongdo_id',
    ];

    const OBJECT_CHINA_SHIPPING_COST = 'china_shipping_cost';
    const OBJECT_CHINA_ORDER_COST = 'china_order_cost';

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
