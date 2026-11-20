<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\Traits\LogsActivity;

class OrderSupplier extends BaseModel
{
    use HasFactory, SoftDeletes;
    use LogsActivity;

    protected $table = 'order_supplier';

    protected static $logAttributes = [
        "order_id",
        "supplier_id",
        'source_type',
        "order_cost",
        "order_cost_real",
        "order_fee",
        "discount_cost",
        "inspection_cost",
        "shock_proof_cost",
        "is_shock_proof",
        "is_woodworking",
        "is_inspection",
        "china_shipping_cost",
        "china_shipping_cost_real",
        "international_shipping_cost",
        "woodworking_cost",
        "delivery_type",
        "thuongdo_id",
        "thuongdo_sync",
        "thuongdo_tong_tien_thanh_toan",
        "is_advanced_inspection",
        "advanced_inspection_cost"
    ];
    protected static $logOnlyDirty = true;
    protected static $logName = 'order_supplier';

    const SOURCE_ADDON = 1; /* nguồn add on*/
    const SOURCE_APP = 2; /* nguồn APP*/
    const SOURCE_API_WEB = 3; /* nguồn api web*/
    const SOURCE_SEARCH_FORM = 4; /* nguồn search form*/

    const LIST_SOURCE_TYPE = [
        self::SOURCE_ADDON => 'Addon',
        self::SOURCE_APP => 'App',
        self::SOURCE_API_WEB => 'Api Website',
        self::SOURCE_SEARCH_FORM => 'Api Form Website'
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function getTotalAmountAttribute(): float
    {
        $exchange_rate = optional($this->order)->exchange_rate ?? 0;

        return roundMoney (
            (
                $this->order_cost +
                $this->order_fee +
                $this->total_internal_ship_all +
                $this->inspection_cost +
                ( $this->china_shipping_cost * $exchange_rate )
            ) - $this->total_discount_all
        );
    }

    public function getTotalDiscountAllAttribute(): float
    {
        $totalDiscountCostPacks = !empty($this->order->packages) ? $this->order->packages->sum('discount_cost') : 0;

        return $this->discount_cost + $totalDiscountCostPacks;
    }

    public function getTotalInternalShipAllAttribute(): float
    {
        return $this->international_shipping_cost + $this->woodworking_cost;
    }
}
