<?php

namespace App\Models;

use App\Models\Traits\OrderPackageRelation;
use App\Traits\OrderActivityLogs;
use App\Traits\Relationships\OrderDetailRelationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\belongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\Traits\LogsActivity;

class OrderDetail extends BaseModel
{
    use HasFactory, OrderPackageRelation, OrderDetailRelationships, SoftDeletes;
    use LogsActivity;
    use OrderActivityLogs;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected $casts = [
        'amount_cny' => 'float',
        'unit_price_cny' => 'float',
        'quantity' => 'integer',
    ];

    protected $fillable = [
        'source',
        'mp_id',
        'mp_sku_id',
        'order_id',
        'name',
        'link',
        'image',
        'classification',
        'note',
        'unit_price_cny',
        'quantity',
        'amount_cny',
        'supplier_id',
        'category_id',
        'order_package_id',
        'complain_id',
        'complain_note',
        'organization_id',
        'thuongdo_id',
        'thuongdo_kien_hang_id',
        'mp_sku_id_fast',
        'classification_zh_fast',
        'note_china',
        'type_order',
    ];

    protected $appends = ['amount', 'unit_price', 'total_quantity', 'total_number_placed_order'];

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;
    protected static $logName = 'order_details';

    public function orderHistory(): HasMany
    {
        return $this->hasMany(OrderHistory::class, 'order_id', 'order_id');
    }

    public function orderNote(): HasMany
    {
        return $this->hasMany(OrderNote::class, 'order_id', 'order_id');
    }

    public function orderDetailNote(): HasMany
    {
        return $this->hasMany(OrderDetailNote::class, 'order_detail_id', 'id')->where('subject_type', Staff::class);
    }
    public function orderDetailNoteCustomer(): HasMany
    {
        return $this->hasMany(OrderDetailNote::class, 'order_detail_id', 'id')->where('subject_type', Customer::class);
    }

    public function order(): belongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function orderSupplier(): belongsTo
    {
        return $this->belongsTo(OrderSupplier::class, 'order_id', 'order_id');
    }

    public function supplier(): belongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    public function orderDetailHistoriyByPrice(): HasMany
    {
        return $this->hasMany(OrderDetailHistory::class)->where('object', OrderDetailHistory::OBJECT_PRICE);
    }

    public function orderDetailHistoryByClassification(): HasMany
    {
        return $this->hasMany(OrderDetailHistory::class)->where('object', OrderDetailHistory::CLASSSIFICATION);
    }

    public function orderDetailHistoriyByQuantity(): HasMany
    {
        return $this->hasMany(OrderDetailHistory::class)->where('object', OrderDetailHistory::OBJECT_QUANTITY);
    }

    public function productItem(): HasOne
    {
        return $this->hasOne(Item::class, 'num_iid', 'mp_id')->whereNotNull('buyder_data_last_time_sync');
    }

    public function nameTran(): HasOne
    {
        return $this->hasOne(NameTran::class, 'order_detail_id', 'id');
    }
}
