<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportOrderConsignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fee_ship',
        'total_customer',
        'total_customer_order',
        'week',
        'month',
        'type',
        'year',
        'total_weight',
        'total_volume',
        'user_group_id',
    ];
}
