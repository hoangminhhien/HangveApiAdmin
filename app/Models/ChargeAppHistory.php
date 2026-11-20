<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChargeAppHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'charge_content',
        'code',
        'content',
        //'user_id'
    ];
}
