<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReasonConfirmOrderOrder extends BaseModel
{
    use HasFactory;

    protected $table = 'reason_confirm_order_order';
    public function orders()
    {
        return $this->belongsToMany('App\Models\Order', 'reason_confirm_order_order', 'reason_confirm_order_id', 'order_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function reasonConfirmOrder()
    {
        return $this->belongsTo(ReasonConfirmOrder::class);
    }
}
