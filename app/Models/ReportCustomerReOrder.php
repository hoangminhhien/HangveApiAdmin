<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportCustomerReOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'revenue',
        'total_customer',
        'week',
        'month',
        'year',
        'total_order',
        'date'
    ];
}
