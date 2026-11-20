<?php

namespace App\Pivots;

class OrderBuyderOrder extends BasePivot
{
    protected $table = 'order_buyder_order';

    protected $fillable = [
        'order_id',
        'order_buyder_id'
    ];
}
