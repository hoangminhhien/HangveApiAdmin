<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderCostReal extends BaseModel
{
    use Filterable, SoftDeletes;

    public $fillable = [
        'order_id',
        'order_code',
        'order_code_po',
        'staff_order_id',
        'staff_order_name',
        'cost_old',
        'cost_new',
        'file',
        'note',
        'status',
        'thuongdo_id',
        'files',
        'thuongdo_files'
    ];

    protected $table = 'order_cost_real';
    public $timestamps = true;

    public function scopeOrderCode($query)
    {
        return $query->where('order_code', request()->query('order_code'));
    }

    public function scopeOrderCodePo($query)
    {
        return $query->where('order_code_po', request()->query('order_code_po'));
    }

    public function scopeStatus($query)
    {
        return $query->where('status', request()->query('status'));
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_order_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
