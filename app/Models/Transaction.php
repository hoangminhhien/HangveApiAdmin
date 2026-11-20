<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Constants\TransactionConstant;
use App\Models\Traits\Filters\StatusFilter;
use App\Scopes\Traits\Filterable;
use App\Scopes\Traits\HasSortByCode;
use App\Services\FilterService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use  App\Models\FundTransaction;

use Spatie\Activitylog\Traits\LogsActivity;

class Transaction extends BaseModel
{
    use HasFactory, Filterable, StatusFilter, SoftDeletes;

    use LogsActivity;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected string $_prefixRoute = 'ewallet';

    protected $dates = [
        'time'
    ];

    protected $casts = [
        'amount' => 'float',
        'balance' => 'float',
    ];

    protected $appends = ['is_positive'];

    protected static $logAttributes = [
        "customer_id",
        "organization_id",
        "code",
        "sourceable_type",
        "sourceable_id",
        "order_id",
        "order_type",
        "amount",
        "balance",
        "time",
        "status",
        "content",
        "thuongdo_id"
    ];
    protected static $logOnlyDirty = true;
    protected static $logName = 'transactions';


    protected static function booted()
    {
        static::created(function ($transaction) {
            $transaction->code = 'GD_' . date('ymd') . substr($transaction->id, -6);

            // update nhưng không trigger event lại
            $transaction->saveQuietly();
        });
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo('sourceable');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class, 'order_id', 'id');
    }

    public function scopeCreatedAt($query)
    {
        return (new FilterService())->rangeDateFilter($query, request()->query('created_at'), 'created_at');
    }

    public function scopeCode($query)
    {
        return $query->where('code', request()->query('code'));
    }

    public function scopeOrderCode($query)
    {
        return $query->whereHas('order', function ($q) {
            $q->where('orders.code', request()->query('order_code'));
        })->orWhereHas('consignment', function($q) {
            $q->where('consignments.code', request()->query('order_code'));
        });
    }

    public function getIsPositiveAttribute(): bool
    {
        return $this->status === TransactionConstant::STATUS_DEPOSIT || $this->status === TransactionConstant::STATUS_REFUND;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fundTransaction(): BelongsTo
    {
        return $this->belongsTo(FundTransaction::class, 'sourceable_id', 'id');
    }
}
