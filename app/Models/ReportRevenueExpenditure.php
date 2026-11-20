<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportRevenueExpenditure extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'fund_id',
        'fund_name',
        'fund_type',
        'fund_price',
        'date',
        'week',
        'month',
        'quarter',
        'year',
        'type',
    ];
}
