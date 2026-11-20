<?php


namespace App\Models;

use App\Models\Traits\CustomerRelation;
use App\Models\Traits\Filters\CreatedAtFilter;
use App\Models\Traits\Filters\StatusFilter;
use App\Scopes\Traits\Filterable;
use App\Scopes\Traits\HasSortDescByCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderAdvanceMoney extends BaseModel
{
    use HasFactory, CustomerRelation, HasSortDescByCreated, Filterable, StatusFilter, CreatedAtFilter, SoftDeletes;

    protected $table = 'order_advance_moneys';

    public function order(): MorphTo
    {
        return $this->morphTo('order');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'id');
    }

    public function manager()
    {
        return $this->belongsTo(Staff::class, 'manager_id', 'id');
    }

    public function scopeCustomerName($query)
    {
        return $query->whereHas('customer', function ($q) {
           $q->where('name', 'LIKE', '%' . request()->query('customer_name') . '%');
        });
    }

    public function scopeCustomerCode($query)
    {
        return $query->whereHas('customer', function ($q) {
           $q->where('code', request()->query('customer_code'));
        });
    }

    public function scopeCustomerPhone($query)
    {
        return $query->whereHas('customer', function ($q) {
           $q->where('phone_number', 'LIKE', '%' . request()->query('customer_phone') . '%');
        });
    }

    public function scopeOrderCode($query)
    {
        return $query->where('order_code', request()->query('order_code'));
//        return $query->whereHas('order', function ($q) {
//           $q->where('code', request()->query('order_code'));
//        });
    }

    public function scopeStatus($query)
    {
        return $query->where('status', request()->query('status'));
    }
}

