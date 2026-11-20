<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportPerformanceStaffSale extends Model
{
    use HasFactory;

    protected $table = 'report_performance_staff_sale';

    protected $fillable = [
        'user_id', 'user_name', 'time_sale_fastest', 'time_sale_slowest', 'time_sale_average', 'total_order'
    ];
}
