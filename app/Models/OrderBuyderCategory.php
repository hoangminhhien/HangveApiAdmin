<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderBuyderCategory extends Model
{
    use HasFactory;

    protected $table = 'order_buyder_categories';

    protected $fillable = [
        'order_buyder_id',
        'order_id',
        'order_code',
        'category_id'
    ];
}
