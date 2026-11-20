<?php

namespace App\Models;

use App\Models\Traits\CustomerDeliveryRelation;
use App\Services\FilterService;
use App\Traits\OrderActivityLogs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\Traits\LogsActivity;

class Consignment extends BaseModel
{
    use HasFactory, CustomerDeliveryRelation, SoftDeletes;

    use LogsActivity;
    use OrderActivityLogs;

    const STATUS_PENDING = "status_0"; // Chờ xử lý
    const STATUS_RECEIVED_GOODS = "status_2"; // Đã nhận hàng

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected $dates = [
        'date_ordered'
    ];

    protected static $logAttributes = [
        "code",
        "warehouse_cn",
        "warehouse_vi",
        "customer_id",
        "customer_delivery_id",
        "delivery_id",
        "organization_id",
        "packages_number",
        "deliveries_number",
        "status",
        "date_done",
        "date_ordered"
    ];
    protected static $logOnlyDirty = true;
    protected static $logName = 'consignments';

    public function consignmentHistory(): HasMany
    {
        return $this->hasMany(ConsignmentHistory::class, 'consignment_id', 'id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_vi', 'id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ConsignmentDetail::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(OrderPackage::class, 'order_id', 'id')->where('order_type', Consignment::class);
    }

    public function orderPackage(): HasMany
    {
        return $this->hasMany(OrderPackage::class, "order_id", "id")->where("order_type",
            OrderPackage::ORDER_TYPE_CONSIGNMENT);
    }

    public function statusTime(): HasOne
    {
        return $this->hasOne(ConsignmentStatusTime::class, 'consignment_id', 'id');
    }

    public function orderStatusTime(): HasOne
    {
        return $this->hasOne(ConsignmentStatusTime::class, 'consignment_id', 'id')->where("key", "status_2");
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function warehouseCn(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_cn', 'id');
    }

    public function warehouseVi(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_vi', 'id');
    }

    public function scopeWarehouseId($query)
    {
        return $query->where('warehouse_vi', request()->input('warehouse_id'));
    }


}
