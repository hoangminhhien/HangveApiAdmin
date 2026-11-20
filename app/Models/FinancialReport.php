<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'customer_id',
        'time',
        'date',
        'month',
        'year',
        'total_deposit',
        'total_payment',
        'total_withdrawal'
    ];
}
