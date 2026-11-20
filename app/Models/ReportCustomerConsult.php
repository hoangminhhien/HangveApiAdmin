<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportCustomerConsult extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'customer_consulting_consignment',
        'total_consignment_customer_order',
        'customer_consulting_order',
        'total_customer_order',
        'new_customer_order',
        'new_order_value',
        'service_fee',
        'total_qty_new_customer_order',
        'total_value_new_customer_order',
        'total_service_fee_new_customer',
        'type',
        'week',
        'month',
        'year',
    ];
}
