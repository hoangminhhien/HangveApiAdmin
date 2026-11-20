<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;

class OrderCostRealLog extends BaseModel
{
    use Filterable;

    public $fillable = [
        'user_id',
        'user_name',
        'order_cost_real_id',
        'order_code',
        'cost_old',
        'cost_new',
        'note',
        'status'
    ];

    protected $table = 'order_cost_real_log';
    public $timestamps = true;

    public function scopeOrderCode($query)
    {
        return $query->where('order_code', request()->query('order_code'));
    }

    public function scopeUserName($query)
    {
        return $query->where('user_name', request()->query('user_name'));
    }

    public function scopeDate($query)
    {
        $date     = request()->query('date');
        $dates    = $date ? explode(',', $date) : [];

        return $query->where(function ($q) use ($dates) {
            return $q->whereDate('created_at', '>=', $dates[0])
                ->whereDate('created_at', '<=', $dates[1]);
        });
    }

    public function orderCostReal()
    {
        return $this->belongsTo(OrderCostReal::class, 'order_cost_real_id', 'id');
    }
}
