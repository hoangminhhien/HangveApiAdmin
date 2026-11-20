<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportCustomerOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_group_id',
        'revenue',
        'revenue_official',
        'revenue_official_partner',
        'service_charge',
        'service_charge_official',
        'service_charge_official_partner',
        'total_customer',
        'total_customer_order',
        'total_order',
        'start_date',
        'end_date',
        'source',
    ];
}
