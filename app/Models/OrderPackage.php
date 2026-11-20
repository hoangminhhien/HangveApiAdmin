<?php

namespace App\Models;

use App\Constants\TimeConstant;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Helpers\AccountingHelper;
use App\Models\Traits\CustomerDeliveryRelation;
use App\Models\Traits\CustomerRelation;
use App\Models\Traits\Filters\CreatedAtFilter;
use App\Models\Traits\Filters\StatusFilter;
use App\Models\Traits\OrderMorphRelation;
use App\Models\Traits\StaffServicesRelation;
use App\Models\Traits\WarehouseRelation;
use App\Scopes\Traits\Filterable;
use App\Scopes\Traits\HasSortDescByCreated;
use App\Services\FilterService;
use App\Traits\OrderActivityLogs;
use App\Traits\Relationships\OrderPackageRelationships;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class OrderPackage
 * @package App\Models
 *
 * @property string $id
 * @property int $quantity
 * @property float $unit_price_cny
 * @property float $amount_cny
 * @property float $order_cost
 * @property float $insurance_cost
 * @property float $china_shipping_cost
 * @property float $international_shipping_cost
 * @property float $shock_proof_cost
 * @property float $inspection_cost
 * @property float $woodworking_cost
 * @property float $exchange_rate
 * @property float $storage_cost
 * @property float $delivery_cost
 * @property float $discount_cost
 * @property float $amount
 * @property float $weight
 * @property float $volume
 * @property float $height
 * @property float $length
 * @property float $width
 * @property bool $is_order
 * @property bool $is_insurance
 * @property bool $is_inspection
 * @property bool $is_woodworking
 * @property bool $is_shock_proof
 * @property string $order_code
 * @property string $customer_id
 * @property string $delivery_id
 * @property string $customer_delivery_id
 * @property boolean $is_make_up
 * @property CustomerDelivery $customerDelivery
 * @property Warehouse $warehouse
 */
class OrderPackage extends BaseModel
{
    use HasFactory, WarehouseRelation, CustomerDeliveryRelation, StaffServicesRelation, CustomerRelation, OrderMorphRelation,
        HasSortDescByCreated, Filterable, StatusFilter, CreatedAtFilter, SoftDeletes;

    use OrderPackageRelationships;

    use LogsActivity;
    use OrderActivityLogs;

    public bool $skipObserver = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected static $logAttributes = [
        "organization_id",
        "buyder_no",
        "order_type",
        "order_id",
        "order_code",
        "code",
        "code_po",
        "bill_code",
        "code_warehouse",
        "delivery_id",
        "warehouse_id",
        "warehouse_cn",
        "product_name",
        "quantity",
        "package_number",
        "order_cost",
        "inspection_cost",
        "is_inspection",
        "insurance_cost",
        "is_insurance",
        "woodworking_cost",
        "is_woodworking",
        "height",
        "length",
        "width",
        "m3",
        "weight",
        "weight_real",
        "type_quantitative",
        "volume_enter",
        "is_delivery",
        "is_pack_first",
        "delivery_cost",
        "delivery_type",
        "in_transit",
        "transporter",
        "transporter_id",
        "exchange_rate",
        "international_shipping_cost",
        "type_international_shipping_cost",
        "unit_price",
        "china_shipping_cost",
        "is_shock_proof",
        "shock_proof_cost",
        "storage_cost",
        "discount_cost",
        "discount_percent",
        "amount",
        "sync_note",
        "note",
        "note_internal",
        "note_ordered",
        "description",
        "customer_id",
        "customer_delivery_id",
        "is_sms",
        "category_id",
        "category",
        "staff_care_id",
        "staff_quotation_id",
        "staff_order_id",
        "staff_group_id",
        "staff_counselor_id",
        "date_ordered",
        "bill_code_at",
        "date_done",
        "is_extension",
        "status",
        "is_waiting_cancel",
        "is_make_up",
        "thuongdo_id",
        "thuongdo_volumn",
        "configs",
        "is_lost",
        "is_confirm",
        'official_service_fee',
        'special_consumption_tax',
        'tax_xnk',
        'tax_vat',
        'environmental_tax',
        'safeguard_tax',
        'surcharge',
        'rate_percent',
        'cost_percent',
        'cn_bag_real',
        'diff_count_pack',
        "is_advanced_inspection",
        "advanced_inspection_cost",
        "is_update_offical_final",
        "share_fee_ship",
    ];
    protected static $logOnlyDirty = true;
    protected static $logName = 'order_package';

    protected $table = 'order_package';

    protected $_prefixRoute = 'package';

    protected $appends = ['amount', 'shipping_cost', 'order_kind_of', 'volume', 'is_order'];

    protected $casts = [
        'delivery_cost' => 'double',
        'international_shipping_cost' => 'double',
        'china_shipping_cost' => 'double',
        'inspection_cost' => 'double',
        'insurance_cost' => 'double',
        'storage_cost' => 'double',
        'shock_proof_cost' => 'double',
        'woodworking_cost' => 'double',
        'exchange_rate' => 'double',
        'discount_cost' => 'double',
        'discount_percent' => 'float',
        'amount' => 'double',
        'volume' => 'double',
        'weight' => 'double',
        'width' => 'double',
        'height' => 'double',
        'length' => 'double',
        'is_inspection' => 'bool',
        'is_insurance' => 'bool',
        'is_woodworking' => 'bool',
        'is_shock_proof' => 'bool',
        'is_delivery' => 'bool',
    ];

    const ORDER_TYPE_DEFAULT = "App\Models\Order";
    const ORDER_TYPE_CONSIGNMENT = "App\Models\Consignment";


    const OFFICIAL_CN = 2;  // Hàng chính ngạch
    const OFFICIAL_BCN = 1;  // Hàng bán chính ngạch

    const TYPE_OFFICIAL_UT = 1;  // Ủy Thác nhập khẩu
    const TYPE_OFFICIAL_TD = 2;  // Tự đứng tên tờ khai.
    public function order(): MorphTo
    {
        return $this->morphTo('order');
    }

    // Chỉ lấy theo bảng orders
    public function order1(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id')->where('order_type', Order::class);
    }

    public function orderPackageWithOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
    public function orderPackageWithConsignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class, 'order_id', 'id');
    }

    /**
     * @return float
     */
    public function getAmountAttribute(): float
    {
        return AccountingHelper::getCosts(
            $this->shipping_cost - $this->discount_cost
        );
    }

    public function getVolumeAttribute(): float
    {
        if (data_get($this, 'volume_enter')) {
            return roundXPrecision(data_get($this, 'volume_enter'), 3);
        }
        $packageNumber = 1;
        if ($this->package_number > 1) {
            $packageNumber = $this->package_number;
        }
        $volume = $this->width * $this->length * $this->height / 1000000;
        return roundXPrecision($volume, 3) * $packageNumber;
    }

    /**
     * @return float
     */
    public function getShippingCostAttribute(): float
    {
        return AccountingHelper::getCosts(
            $this->international_shipping_cost
            + ($this->china_shipping_cost * $this->exchange_rate)
            + $this->insurance_cost
            + $this->inspection_cost
            + $this->woodworking_cost
            + $this->shock_proof_cost
            //+ $this->storage_cost
            + $this->delivery_cost
        );
    }

    public function getIsOrderAttribute()
    {
        return $this->order_type === Order::class;
    }

    public function getChinaShippingCostCnyAttribute()
    {
        return AccountingHelper::getCosts(
            $this->china_shipping_cost / ($this->exchange_rate != 0 ? $this->exchange_rate : getExchangeRate())
        );
    }

    public function orderR(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function getOrderKindOfAttribute(): string
    {
        return !$this->order_type ? 'Chờ xác nhận' : ($this->order_type === Order::class ? 'Hàng Order' : 'Hàng ký gửi');
    }

    public function orderDetails(): BelongsToMany
    {
        return $this->belongsToMany(OrderDetail::class, 'order_detail_packages');
    }

    public function orderDetailsPackage(): HasMany
    {
        return $this->hasMany(OrderDetailPackage::class, 'order_package_id', 'id');
    }

    public function categoryRelation(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function transporterRelation(): BelongsTo
    {
        return $this->belongsTo(Transporter::class, 'transporter_id', 'id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(OrderPackageImage::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OrderPackageNote::class);
    }

    public function staffOrder(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_order_id', 'id');
    }

    public function staffSale(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_sale_id', 'id');
    }

    public function staffCare(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_care_id', 'id');
    }

    public function staffCounselor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_counselor_id', 'id');
    }
    public function staffGroup(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_group_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function warehouseCn(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_cn', 'id');
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class, 'order_id', 'id');
    }

    public function scopeDeliveryType($query)
    {
        return $query->where('delivery_type', request()->query('delivery_type'));
    }

    public function scopeOfficial($query)
    {
        if(request()->query('official') == 21) {
            return $query->where('official', OrderConstant::OFFICIAL)->where('type_official', OrderConstant::TYPE_OFFICIAL_1);
        }
        if(request()->query('official') == 22) {
            return $query->where('official', OrderConstant::OFFICIAL)->where('type_official', OrderConstant::TYPE_OFFICIAL_2);
        }
        if(request()->query('official') == 31) {
            return $query->where('official_is_partner', 1);
        }
        return $query->where('official', request()->query('official'));
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            OrderDetail::class,
            'order_detail_packages',
            'order_package_id',
            'order_detail_id'
        )->withPivot(['quantity']);
    }

    public function statusTimes(): HasMany
    {
        return $this->hasMany(OrderPackageStatusTime::class, 'order_package_id', 'id');
    }

    public function statusTimeVN(): HasOne
    {
        return $this->hasOne(OrderPackageStatusTime::class)
            ->where('key', PackageConstant::STATUS_WAREHOUSE_VN);
    }

    public function statusTimeTQ()
    {
        return $this->HasOne(OrderPackageStatusTime::class)->where('key', PackageConstant::STATUS_IN_CHINA);
    }

    public function scopeOrderCode($query)
    {
        return $query->where('order_code', request()->query('order_code'));
    }

    public function scopeCodePo($query)
    {
        return $query->where('code_po', request()->query('code_po'));
    }

    public function scopeCustomerId($query)
    {
        return $query->where('customer_id', request()->query('customer_id'));
    }

    public function scopeBillCode($query)
    {
        $billCodes = request()->query('bill_code');
        if (!$billCodes) {
            return $query;
        }
        $codes = collect(explode(',', $billCodes))
            ->map(fn($item) => trim($item))
            ->filter()
            ->unique()
            ->values();
        if ($codes->isEmpty()) {
            return $query;
        }
        return $query->where(function ($q) use ($codes) {
            foreach ($codes as $code) {
                $q->orWhere('bill_code', 'LIKE', '%' . $code . '%');
            }
        });
//        return $query->where('bill_code', 'LIKE', '%' . request()->query('bill_code') . '%');
    }

    public function scopeBillCodeAt($query)
    {
        return (new FilterService())->rangeDateFilter($query, request()->query('bill_code_at'), 'bill_code_at');
    }

    public function scopeDateDone($query)
    {
        return (new FilterService())->rangeDateFilter($query, request()->query('date_done'), 'order_package.date_done');
    }
    public function scopeDateDoneByMonth($query)
    {
        $dateDone = Carbon::now()->startOfMonth()->format('Y-m-d') . ',' . Carbon::now()->endOfMonth()->format('Y-m-d');
        return (new FilterService())->rangeDateFilter($query, $dateDone, 'order_package.date_done');
    }


    public function scopeEcommerce($query)
    {
        if (empty(request()->query('ecommerce'))) {
            return $query;
        }
        return $query->whereHas('order1', function($q){
            if(request()->query('ecommerce') == 'other'){
                $q->whereNull('ecommerce');
            }else{
                $q->where('ecommerce', request()->query('ecommerce'));
            }
            return $q;
        });
    }

    public function scopeCategoryId($query)
    {
        return $query->where('category_id', request()->query('category_id'));
    }

    public function scopeStaffOrderId($query)
    {
        // Logic mới
        if (request()->query('staff_order_id') == 0) {
            return $query->whereNull('order_package.staff_order_id');
        } else {
            return $query->where('order_package.staff_order_id', request()->query('staff_order_id'));
        }
        // Logic cũ
        return $query->where('order_package.staff_order_id', request()->query('staff_order_id'));
    }

    public function scopeStaffQuotationId($query)
    {
        // Logic mới
        if (request()->query('staff_quotation_id') == 0) {
            return $query->whereNull('staff_quotation_id');
        } else {
            return $query->where('staff_quotation_id', request()->query('staff_quotation_id'));
        }
        // Logic cũ
        return $query->where('staff_quotation_id', request()->query('staff_quotation_id'));
    }

    public function scopeStaffCareId($query)
    {
        // Logic mới
        if (request()->query('staff_care_id') == 0) {
            return $query->whereNull('staff_care_id');
        } else {
            return $query->where('staff_care_id', request()->query('staff_care_id'));
        }
        return $query->where('staff_care_id', request()->query('staff_care_id'));
    }

    //nhân viên tư vấn
    public function scopeStaffCounselorId($query)
    {
        // Logic mới
        if (request()->query('staff_counselor_id') == 0) {
            return $query->whereNull('staff_counselor_id');
        } else {
            return $query->where('staff_counselor_id', request()->query('staff_counselor_id'));
        }
        return $query->where('staff_counselor_id', request()->query('staff_counselor_id'));
    }

    //nhân viên quản lý
    public function scopeStaffGroupId($query)
    {
        $staffGroupIds = is_array(request()->query('staff_group_id')) ? request()->query('staff_group_id') : [request()->query('staff_group_id')];
        return $query->where(function($q) use ($staffGroupIds) {
            $q->whereIn('order_package.staff_group_id', $staffGroupIds);
            if (in_array(0, $staffGroupIds)) {
                $q->orWhereNull('order_package.staff_group_id');
            }
        });
    }

    public function scopeWarehouseId($query)
    {
        return $query->where('order_package.warehouse_id', request()->query('warehouse_id'));
    }

    public function scopeWarehouseCn($query)
    {
        return $query->where('warehouse_cn', request()->query('warehouse_cn'));
    }

    public function scopeStatusExtend($query)
    {
        switch ((int)request()->query('status_extend')) {
            case 0:
                return $query->where('status', '!=' ,PackageConstant::STATUS_WAREHOUSE_VN);
            case 1:
                return $query->where('status', '!=' ,PackageConstant::STATUS_RECEIVED_GOODS);
        }
        return $query;
    }

    public function scopeType($query)
    {
        return $query->when(request()->query('type') != 'unconfirmed', function($q) {
            $q->where('order_type', 'like', '%'.request()->query('type') .'%');
        })->when(request()->query('type') == 'unconfirmed',  function($q) {
            $q->whereNull('order_type');
        });
    }

    public function scopeModifyKey($query)
    {
        switch ((int)request()->query('modify_key'))
        {
            case 0:
                return $query->whereNull('bill_code');
            case 1:
                return $query->whereNull('code_po');
            case 2:
                return $query->where(function ($q) {
                    $q->whereNull('transporter');
                    $q->orWhereNull('transporter_id');
                });
            case 3:
                return $query->whereNull('customer_id');
            case 4:
                return $query->where('is_extension', 1);
            case 5:
                return $query->whereNotNull('bill_code')->whereNotNull('customer_id')->whereNotNull('code_po')->where(function($q){
                    $q->whereNotNull('transporter');
                    $q->orWhereNotNull('transporter');
                });
            default:
                return $query;
        }
    }

    public function scopeRequestKey($query)
    {
        switch ((int)request()->query('request_key'))
        {
            case 0:
                return $query->where('is_inspection', 1);
            case 1:
                return $query->where('is_woodworking', 1);
            case 2:
                return $query->where('is_insurance', 1);
            case 4:
                return $query->where('is_delivery', 1);
            case 5:
                return $query->where('delivery_type', OrderConstant::DELIVERY_NORMAL);
            case 3:
                return $query->where('is_shock_proof', 1);
            case 6:
                return $query->where('is_advanced_inspection', 2);
            default:
                return $query;
        }
    }

    public function scopeIsPackOverDay($query)
    {
        if (!empty(request()->query('is_pack_over_day'))) {
            return $query->whereRaw('DATE(created_at) < DATE_SUB(CURDATE(),INTERVAL 5 DAY) ')
                ->whereIn('status', [
                    PackageConstant::STATUS_PENDING,
                    PackageConstant::STATUS_RECONFIRM,
                    PackageConstant::STATUS_IN_GUANGZHOU,
                    PackageConstant::STATUS_ON_THE_WAY_PINGXIANG,
                    PackageConstant::STATUS_ON_THE_WAY_CHINA,
                ] );
        }
    }

    public function getStaffInOrder()
    {
        $listStaff = [
            'staff_order' => null,
            'staff_counselor' => null,
            'staff_care' => null,
            'staff_quotation' => null,
        ];

        $isOder = $this->getIsOrderAttribute();

        $relationDB = null;
        // Nếu là đơn kí gửi thì lấy từ khách hàng ra
        if ($isOder == false) {
            $customerDB = $this->customer;
            if ($customerDB) {
                $relationDB = $customerDB;
            }
        } else {
            $orderDB = $this->orderR;
            if ($orderDB) {
                $relationDB = $orderDB;
            }
        }

        if ($relationDB) {
            $staffOrder = $relationDB->staffOrder;
            if ($staffOrder) {
                $listStaff['staff_order'] = $staffOrder->name;
            }

            $staffCare = $relationDB->staffCare;
            if ($staffCare) {
                $listStaff['staff_care'] = $staffCare->name;
            }

            $staffCounselor = $relationDB->staffCounselor;
            if ($staffCounselor) {
                $listStaff['staff_counselor'] = $staffCounselor->name;
            }

            $staffQuotation = $relationDB->staffQuotation;
            if ($staffQuotation) {
                $listStaff['staff_quotation'] = $staffQuotation->name;
            }
        }
        return $listStaff;
    }

    public function scopeIsMakeUp($query)
    {
        return $query->where('is_make_up', request()->query('is_make_up'));
    }

    public function scopeTypeTime($query)
    {
        if (request()->query('type_time')) {
            switch (request()->query('type_time')) {
                case PackageConstant::ORDER_IN_DAY:
                    return $query->whereDate('created_at', date(TimeConstant::DATE));
                case PackageConstant::ORDER_IN_WEEK:
                    return $query->whereBetween(
                        'created_at',
                        [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]
                    );
                case PackageConstant::ORDER_IN_MONTH:
                    return $query->whereMonth('created_at', date('m'))
                        ->whereYear('created_at', date('Y'));
            }
        }
    }

    public function scopeCodeWarehouse($query)
    {
        $codes = request()->query('code_warehouse');
        if (!$codes) return $query;
        $arr = array_filter(array_map('trim', explode(',', $codes)));
        $arr = array_unique($arr);
        if (empty($arr)) return $query;
        return $query->where(function ($q) use ($arr) {
            foreach ($arr as $code) {
                $q->orWhere('code_warehouse', 'LIKE', '%' . $code . '%');
            }
        });
//        return $query->where('code_warehouse', 'LIKE', '%' . request()->query('code_warehouse') . '%');
    }

    public function statusTimeLast(): HasOne
    {
        return $this->hasOne(OrderPackageStatusTime::class)->orderBy('id', 'desc');
    }

    public function approveUnitPrices()
    {
        return $this->hasMany(ApproveUnitPrice::class);
    }
}
