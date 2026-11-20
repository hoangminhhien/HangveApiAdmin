<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportCustomerVipOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'staff_group_ids',
        'staff_group_id',
        'name',
        'start_date',
        'end_date',
        'total_customer_order',
        'total_customer_ordered',
        'total_customer_order_new',
        'total_customer_ordered_new',
        'total_order',
        'total_order_new',
        'total_price',
        'total_price_new',
        'total_fee',
        'total_fee_new',
        'source'
    ];
}
