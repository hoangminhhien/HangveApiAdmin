<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPerformanceHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id', 'date', 'week', 'month', 'year', 'total_order'
    ];
}
