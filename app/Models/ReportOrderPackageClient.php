<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportOrderPackageClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'customer_id',
        'time',
        'date',
        'month',
        'year',
        'total_package_warehouse_vn',
        'total_package_received_good',
    ];
}
