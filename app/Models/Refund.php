<?php

namespace App\Models;

use App\Models\Traits\CustomerRelation;
use App\Models\Traits\Filters\CreatedAtFilter;
use App\Models\Traits\Filters\StatusFilter;
use App\Models\Traits\OrderRelation;
use App\Models\Traits\StaffServicesRelation;
use App\Scopes\Traits\Filterable;
use App\Scopes\Traits\HasSortDescByCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Spatie\Activitylog\Traits\LogsActivity;

class Refund extends BaseModel
{
    use HasFactory, OrderRelation, CustomerRelation, Filterable, StatusFilter, CreatedAtFilter, StaffServicesRelation, HasSortDescByCreated;
    use LogsActivity;

    protected static $logAttributes = [
       "id",
       "organization_id",
       "staff_id",
       "order_id",
       "order_code",
       "order_status",
       "amount_cn",
       "total_money_item",
       "total_fee_service",
       "total_fee_inspection",
       "china_shipping_cost",
       "exchange_rate",
       "amount_vn",
       "order_detail_change",
       "file",
       "note",
       "status",
       "type",
       "order_percent",
       "files",
       "total_refund_cny",
       "thuongdo_id",
       "thuongdo_files", 
       "order_cost_real_old", 
       "order_cost_real" 
    ];

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;
    protected static $logName = 'refunds';

    protected string $_tableNameFriendly = 'Hoàn tiền';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function refund_payment(): HasMany
    {
        return $this->hasMany(RefundPayment::class, 'refund_id');
    }

    public function refund_feedback(): HasMany
    {
        return $this->hasMany(RefundFeedback::class, 'refund_id');
    }

    public function scopeOrderCode($query)
    {
        return $query->whereHas(
            'order',
            function ($q) {
                return $q->where('code', request()->query('order_code'));
            }
        );
    }

    public function scopeCustomerName($query)
    {
        return $query->whereHas(
            'order',
            function ($q) {
                return $q->whereHas(
                    'customer',
                    function ($subQ) {
                        return $subQ->where('name', 'like', '%'.request()->query('customer_name').'%');
                    }
                );
            }
        );
    }

    public function scopeCustomerPhone($query)
    {

        return $query->whereHas(
            'order',
            function ($q) {
                return $q->whereHas(
                    'customer',
                    function ($subQ) {
                        return $subQ->where('phone_number',request()->query('customer_phone'));
                    }
                );
            }
        );
    }

    public function scopeStaffId($query)
    {
        return $query->where('staff_id', request()->query('staff_id'));
    }

    public function getTotalAmountCnyAttribute()
    {
        if($this->total_refund_cny) {
            $amount = $this->total_refund_cny;
        }elseif($this->total_refund_vn) {
            $amount = $this->total_refund_vn/$this->exchange_rate;
        }else {
            $amount = $this->amount_cn;
        }
        return round($amount, 2);
    }

    public function getTotalAmountAttribute()
    {
        if($this->total_refund_cny) {
            $amount = $this->total_refund_cny * $this->exchange_rate;
        }elseif($this->total_refund_vn) {
            $amount = $this->total_refund_vn;
        }else {
            $amount = $this->amount_vn;
        }
        return $amount;
    }

    public function getIsMultiAttribute()
    {
        $isMulti = self::where('order_id', $this->order_id)->withoutGlobalScopes();
        return $isMulti->count();
    }
}
