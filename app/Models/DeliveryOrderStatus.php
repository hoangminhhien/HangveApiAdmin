<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryOrderStatus extends Model
{
    use HasFactory;

    protected $table = 'delivery_order_status';

    protected $fillable = ['delivery_id', 'carrier', 'desc', 'response', 'carrier_order_id', 'shop_id', 'status'];
}
