<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDetailPackage extends Model
{
    use HasFactory, SoftDeletes;

    protected $dates = ['created_at'];

    protected $table = 'order_detail_packages';
    protected $fillable = ['order_detail_id', 'order_package_id', 'quantity', 'thuongdo_sync'];

    public function orderDetail(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class);
    }
}
