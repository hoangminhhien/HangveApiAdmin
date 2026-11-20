<?php

namespace App\Models;

use App\Constants\TimeConstant;
use App\Constants\DeliveryConstant;
use App\Constants\NoteConstant;
use App\Models\Traits\CustomerDeliveryRelation;
use App\Models\Traits\CustomerRelation;
use App\Models\Traits\Filters\StatusFilter;
use App\Models\Traits\StaffServicesRelation;
use App\Models\Traits\WarehouseRelation;
use App\Scopes\Traits\Filterable;
use App\Scopes\Traits\HasOrganization;
use App\Scopes\Traits\HasSortDescByCreated;
use App\Models\Traits\OrderMorphRelation;
use App\Services\FilterService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class Delivery
 * @package App\Models
 *
 * @property string $id
 * @property string $status
 * @property DeliveryNote $notesStaff
 */
class Delivery extends BaseModel
{
    use HasFactory, HasSortDescByCreated, HasOrganization, CustomerRelation, CustomerDeliveryRelation, Filterable, StatusFilter, StaffServicesRelation, WarehouseRelation, OrderMorphRelation;

    use LogsActivity;

    protected        $appends            = ['custom_address', 'shipping_cost', 'amount', 'order_kind_of', 'no'];
    protected string $_tableNameFriendly = 'Giao hàng';

    protected $dates = [
        'date',
        'created_at'
    ];

    protected $casts = [
        'amount'                      => 'double',
        'shipping_cost'               => 'float',
        'debt_cost'                   => 'float',
        'china_shipping_cost'         => 'float',
        'international_shipping_cost' => 'float',
        'delivery_cost'               => 'float',
        'insurance_cost'              => 'float',
        'inspection_cost'             => 'float',
        'shock_proof_cost'            => 'float',
        'storage_cost'                => 'float',
        'woodworking_cost'            => 'float',
        'extend_cost'                 => 'float',
    ];

    protected static $logAttributes = [
        "organization_id",
        "order_type",
        "order_id",
        "customer_id",
        "code",
        "receiver",
        "phone_number",
        "address",
        "date",
        "transporter_id",
        "transporter_detail_id",
        "delivery_cost",
        "is_delivery_cost_paid",
        "debt_cost",
        "international_shipping_cost",
        "china_shipping_cost",
        "shock_proof_cost",
        "storage_cost",
        "woodworking_cost",
        "inspection_cost",
        "insurance_cost",
        "discount_cost",
        "refund",
        "type",
        "payment",
        "note",
        "note_customer",
        "note_warehouse",
        "customer_delivery_id",
        "postcode",
        "shipper_phone_number",
        "is_received",
        "carrier_code",
        "status",
        "staff_care_id",
        "staff_quotation_id",
        "staff_order_id",
        "staff_group_id",
        "staff_counselor_id",
        "thuongdo_id",
        "thuongdo_total_money",
        'fee_ship_out',
        'fee_missing',
        'note_print',
        'time_next_action',
    ];
    protected static $logOnlyDirty = true;
    protected static $logName = 'deliveries';

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class);
    }

    public function transporterDetail(): BelongsTo
    {
        return $this->belongsTo(TransporterDetail::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(OrderPackage::class);
    }

    public function warehouse()
    {
        return $this->hasOneThrough(
            Warehouse::class,
            OrderPackage::class,
            'delivery_id',   // FK on packages
            'id',            // PK on warehouses
            'id',            // PK on deliveries
            'warehouse_id'   // FK on packages
        )->orderBy('order_package.id');
    }

    public function getShippingCostAttribute(): float
    {
        return $this->international_shipping_cost
        + $this->china_shipping_cost
        + $this->insurance_cost
        + $this->inspection_cost
        + $this->woodworking_cost
        + $this->shock_proof_cost
        + $this->storage_cost;
    }

    public function getAmountAttribute(): float
    {
        return $this->shipping_cost + $this->debt_cost;
    }

    public function getAmountOutAttribute(): float
    {
        return $this->shipping_cost + $this->debt_cost - $this->discount_cost;
    }

    public function getCustomAddressAttribute(): string
    {
        return $this->receiver . ' - ' . $this->phone_number . ' - ' . $this->address;
    }

    public function orderPackages(): HasMany
    {
        return $this->hasMany(OrderPackage::class);
    }

    public function scopePhoneNumber($query)
    {
        return;
        return $query->where('phone_number', request()->query('phone_number'));
    }

    public function scopeReceiver($query)
    {
        return $query->where('receiver', 'like', '%' . request()->query('receiver') . '%');
    }

    public function scopeCarrier_code($query)
    {
        return $query->where('carrier_code', request()->query('carrier_code'));
    }

    public function scopeShipperPhoneNumber($query)
    {
        return $query->where('shipper_phone_number', request()->query('shipper_phone_number'));
    }

    public function scopeTransporterId($query)
    {
        return $query->where('transporter_id', request()->query('transporter_id'));
    }

    public function scopeWarehouseId($query)
    {
        if (!empty(request()->query('warehouse_id'))) {
            $warehouse_id = request()->query('warehouse_id');
            $query->join('order_package', 'deliveries.id', 'order_package.delivery_id')
                ->where(function ($q) use ($warehouse_id) {
                    $q->where('order_package.warehouse_id', $warehouse_id);
            });
        }
        // return $query->whereHas(
        //     'orderPackages',
        //     function ($q) {
        //         return $q->where('warehouse_id', request()->query('warehouse_id'));
        //     }
        // );
    }

    public function scopePayment($query)
    {
        return $query->where('payment', request()->query('payment'));
    }

    public function scopeDate($query)
    {
        return (new FilterService())->rangeDateFilter($query, request()->query('date'), 'date');
    }

    public function scopeBillCode($query)
    {

        if (!empty(request()->query('bill_code'))) {
            $bill_code = request()->query('bill_code');
            $query->join('order_package', 'deliveries.id', 'order_package.delivery_id')
                ->where(function ($q) use ($bill_code) {
                    $q->where('order_package.bill_code', $bill_code);
            });
        }

        // return $query->whereHas(
        //     'orderPackages',
        //     function ($q) {
        //         return $q->where('bill_code', request()->query('bill_code'));
        //     }
        // );
    }

    public function scopeTransporterDetailId($query)
    {
        return $query->where('transporter_detail_id', request()->query('transporter_detail_id'));
    }

    // public function scopeIsReceived($query)
    // {
    //     return $query->where('status', request()->query('is_received') ? '=' : '!=', DeliveryConstant::KEY_STATUS_DONE);
    // }

    public function notesStaff(): HasMany
    {
        return $this->hasMany(DeliveryNote::class)->where('type', NoteConstant::TYPE_PRIVATE);
    }

    public function getOrderKindOfAttribute(): string
    {
        return !$this->order_type ? 'Chờ xác nhận' : ($this->order_type === Order::class ? 'Hàng Order' : 'Hàng ký gửi');
    }

    public function getNoAttribute(): string
    {
        return str_replace('GH_', '00', $this->code);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'delivery_orders')->where('order_type', Order::class);
    }

    public function order(): MorphTo
    {
        return $this->morphTo('order');
    }

    public function consignments(): BelongsToMany
    {
        return $this->belongsToMany(Consignment::class, 'delivery_orders')->where('order_type', Consignment::class);
    }

    public function deliveryOrderStatus(): HasMany
    {
        return $this->hasMany(DeliveryOrderStatus::class);
    }

    public function scopeTypeTime($query)
    {
        if (request()->query('type_time')) {
            switch (request()->query('type_time')) {
                case DeliveryConstant::ORDER_IN_DAY:
                    return $query->whereDate('created_at', date(TimeConstant::DATE));
                case DeliveryConstant::ORDER_IN_WEEK:
                    return $query->whereBetween(
                        'created_at',
                        [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]
                    );
                case DeliveryConstant::ORDER_IN_MONTH:
                    return $query->whereMonth('created_at', date('m'))
                        ->whereYear('created_at', date('Y'));
            }
        }
    }

    public function scopeCreatedAtFrom($query)
    {
        return $query->where(
            'deliveries.created_at',
            '>=',
            Carbon::parse(request('created_at_from'))->startOfDay()
        );
    }

    public function scopeCreatedAtTo($query)
    {
        return $query->where(
            'deliveries.created_at',
            '<=',
            Carbon::parse(request('created_at_to'))->endOfDay()
        );
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'sourceable_id', 'id')->where('sourceable_type', Delivery::class);
    }

    public function scopeOrderCode($query)
    {
        if ($order_code = request()->query('order_code')) {

            $query->join('delivery_orders', 'deliveries.id', '=', 'delivery_orders.delivery_id')
                ->join('orders', 'delivery_orders.order_id', '=', 'orders.id')
                ->where('orders.code', $order_code)
                ->select('deliveries.*')
                ->distinct();
        }
    }
}
