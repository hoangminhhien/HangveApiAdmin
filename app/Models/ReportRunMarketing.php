<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportRunMarketing extends Model
{
    use HasFactory;

    protected $table = 'report_run_marketing';

    protected $guarded = ['id', 'created_at', 'updated_at'];
}
