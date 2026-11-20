<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReconciliationWarehouse extends Model
{
    use HasFactory;

    protected $table = 'reconciliation_warehouses';

    protected $fillable = [
        'customer_id',
        'customer_code',
        'customer_name',
        'customer_phone',

        'user_id',
        'user_name',
        'user_code',

        'order_package_id',
        'order_package_type',
        'order_id',
        'order_code',
        'bill_code',

        'status',
        'type',
        'warehouse_code',
        'print_receipt_code',

        'transport_fee',
        'other_fee',
        'total_amount',
    ];
}
