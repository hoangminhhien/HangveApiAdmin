<?php

namespace App\Models;

use App\Builders\OrderBuilders;
use App\Constants\ConfigConstant;
use App\Constants\TimeConstant;
use App\Helpers\AccountingHelper;
use App\Models\Traits\CustomerDeliveryRelation;
use App\Models\Traits\CustomerRelation;
use App\Models\Traits\Filters\CodeFilter;
use App\Models\Traits\Filters\CreatedAtFilter;
use App\Models\Traits\Filters\StatusFilter;
use App\Models\Traits\Filters\WarehouseFilter;
use App\Models\Traits\StaffServicesRelation;
use App\Models\Traits\WarehouseRelation;
use App\Scopes\Traits\Filterable;
use App\Services\AccountingService;
use App\Services\ConfigService;
use App\Services\FilterService;
use App\Constants\OrderConstant;
use App\Traits\OrderActivityLogs;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use App\Models\OrderDetailNote;

use Spatie\Activitylog\Traits\LogsActivity;

class Order extends BaseModel
{
    use HasFactory, Filterable, StatusFilter, SoftDeletes;
    use StaffServicesRelation, CustomerDeliveryRelation, CustomerRelation;
    use WarehouseFilter, CodeFilter, CreatedAtFilter, WarehouseRelation;
    use LogsActivity;
    use OrderActivityLogs;

    public string $_colorLog = '#00BFC4';

    protected string $_tableNameFriendly = 'Đơn hàng';

    protected static $logAttributes = [
        "force_clear",
        "organization_id",
        "code",
        "code_parent",
        "code_po",
        "is_one_order",
        "is_order_now",
        "order_cost",
        "order_cost_real",
        "exchange_rate",
        "delivery_type",
        "delivery_cost",
        "inspection_cost",
        "woodworking_cost",
        "international_shipping_cost",
        "china_shipping_cost",
        "china_shipping_cost_real",
        "is_woodworking",
        "is_inspection",
        "is_shock_proof",
        "is_deposit",
        "deposit_percent",
        "deposit_cost",
        "tax_percent",
        "is_tax",
        "discount_percent",
        "discount_cost",
        "order_fee",
        "actual_payment",
        "order_percent",
        "customer_id",
        "warehouse_id",
        "customer_delivery_id",
        "delivery_id",
        "delivery_pay_id",
        "delivery_pay_debt_cost",
        "delivery_pay_shipping_cost",
        "delivery_pay_source",
        "date_ordered",
        "order_provisional_date",
        "date_purchased",
        "date_done",
        "date_quotation",
        "note_number",
        "packages_number",
        "complains_number",
        "deliveries_number",
        "weight",
        "volume",
        "supplier_id",
        "note",
        "staff_care_id",
        "staff_counselor_id",
        "staff_quotation_id",
        "staff_order_id",
        "staff_group_id",
        "reset_staff",
        "reset_position",
        "is_website",
        "ecommerce",
        "staff_cancel_id",
        "reason_cancel",
        "reason_confirm_order_id",
        "status",
        "is_quoting",
        "is_waiting_for_sale",
        "order_cost_old",
        "china_shipping_cost_old",
        "bonus",
        "error_logs",
        "thuongdo_id",
        "thuongdo_id_old",
        "has_edit",
        "thuongdo_created",
        "thuongdo_page",
        "thuongdo_tong_tien_tam_ung",
        "thuongdo_tong_tien_don_hang",
        "is_multiple_supplier",
        "remaining_amount",
        "is_up_order_cost",
        "is_advanced_inspection",
        "advanced_inspection_cost",
        "debt_cost_pending",
        "is_update_offical_final",
        'is_waiting_confirm',
    ];
    protected static $logOnlyDirty = true;
    protected static $logName = 'orders';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    protected $appends = [
        'total_amount',
        'order_cost_cny',
        'debt_cost',
        'shock_proof_cost',
    ];

    protected $casts = [
        'exchange_rate' => 'float',
        'order_cost' => 'float',
        'total_amount' => 'float',
        'inspection_cost' => 'float',
        'woodworking_cost' => 'float',
        'discount_cost' => 'float',
        'deposit_cost' => 'float',
        'china_shipping_cost' => 'float',
        'international_shipping_cost' => 'float',
        'order_fee' => 'float',
        'delivery_cost' => 'float',
        'date_ordered' => 'datetime',
        'actual_payment' => 'float',
    ];

    const ORDER_STATUS_WAITING_FOR_QUOTE = 'status_0'; /* chờ báo giá */
    const ORDER_STATUS_WAITING_FOR_DEPOSIT = 'status_1'; /* chờ đặt cọc */
    const ORDER_STATUS_DEPOSITED = 'status_2'; /* đã đặt cọc */
    const ORDER_STATUS_WAITING_FOR_PAYMENT = 'status_8'; /* chờ thanh toán */
    const ORDER_STATUS_ORDER = 'status_3'; /* đặt hàng */
    const ORDER_STATUS_COMPLETED = 'status_4'; /* hoàn thành */
    const ORDER_STATUS_NEED_TO_CONFIRM = 'status_5'; /* cần xác nhận lại */
    const ORDER_STATUS_CANCEL = 'status_6'; /* đã hủy */
    const LIST_STATUS = [
        self::ORDER_STATUS_WAITING_FOR_QUOTE => 'Chờ báo giá',
        self::ORDER_STATUS_WAITING_FOR_DEPOSIT => 'Chờ đặt cọc',
        self::ORDER_STATUS_DEPOSITED => 'Đã đặt cọc',
        self::ORDER_STATUS_WAITING_FOR_PAYMENT => 'Chờ thanh toán',
        self::ORDER_STATUS_ORDER => 'Đặt hàng',
        self::ORDER_STATUS_COMPLETED => 'Hoàn thành',
        self::ORDER_STATUS_NEED_TO_CONFIRM => 'Cần xác nhận lại',
        self::ORDER_STATUS_CANCEL => 'Đã hủy',
    ];

    public function newEloquentBuilder($query): OrderBuilders
    {
        return new OrderBuilders($query);
    }

    public function orderHistories(): HasMany
    {
        return $this->hasMany(OrderHistory::class, 'order_id', 'id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(OrderPackage::class)->where('order_type', Order::class);
    }

    public function packagesOrder(): HasMany
    {
        return $this->hasMany(OrderPackage::class)->where('order_type', 'App\Models\Order');
    }

    public function complains(): HasMany
    {
        return $this->hasMany(Complain::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function deliveriesPivot(): BelongsToMany
    {
        return $this->belongsToMany(
            Delivery::class, 'delivery_orders',
            'order_id', 'delivery_id'
        );
    }

    public function deliveriesOrder(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function notePublic(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function orderNotes(): HasMany
    {
        return $this->hasMany(OrderNote::class, 'order_id', 'id');
    }

    public function orderProvisionalDate(): HasMany
    {
        return $this->hasMany(OrderNote::class, 'order_id', 'id')->where('is_public', 2);
    }

    public function notePrivate(): HasMany
    {
        return $this->hasMany(OrderNote::class)->where('is_public', false);
    }

    public function details(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }


    public function rate(): HasOne
    {
        return $this->hasOne(OrderRate::class, 'order_code', 'code');
    }

    /** Hàm này vẫn còn chỗ dùng nên không xóa. Từ giờ dùng hết ở get getTotalAmount hoặc getTotalAmountNotShip */
    public function getTotalAmountAttribute(): float
    {
        return $this->order_cost
            + $this->order_fee
            - $this->discount_cost
            + $this->inspection_cost
            + $this->advanced_inspection_cost
            + $this->china_shipping_cost * $this->exchange_rate;
    }

    public function getActualPayment(): float
    {
        return $this->order_cost
            + $this->china_shipping_cost * $this->exchange_rate;
    }

    public function getOrderCostCnyAttribute(): float
    {
        return AccountingHelper::getCosts($this->order_cost / ($this->exchange_rate ?? (new ConfigService())->getExchangeRate()));
    }

    public function getDebtCostAttribute(): float
    {
        // Nếu là đơn TD thì trả luôn theo giá trị đồng bộ
        if ($this->thuongdo_id != null && $this->has_edit == 0) {
            return $this->thuongdo_tong_tien_don_hang - $this->thuongdo_tong_tien_tam_ung;
        }
        return $this->getTotalAmountNotShip() - $this->deposit_cost;
    }

    public function getShockProofCostAttribute(): float
    {
        return $this->orderSuppliers()->sum('shock_proof_cost');
    }

    public function scopeCustomerName($query)
    {
        return $query->whereHas('customer', function ($q) {
            $q->where('name', request()->query('customer_name'));
        });
    }

    public function scopeOfficial($query)
    {
        if(request()->query('official') == 1) {
            return $query->where('official', OrderConstant::OFFICIAL_SALE)->where('official_is_partner', 0);
        }else {
            if(request()->query('official') == 21) {
                return $query->where('official', OrderConstant::OFFICIAL)->where('type_official', OrderConstant::TYPE_OFFICIAL_1)->where('official_is_partner', 0);
            }elseif(request()->query('official') == 22) {
                return $query->where('official', OrderConstant::OFFICIAL)->where('type_official', OrderConstant::TYPE_OFFICIAL_2)->where('official_is_partner', 0);
            }else {
                return $query->where('official_is_partner', 1);
            }
        }
    }

    public function scopeCustomerLevel($query)
    {
        return $query->whereHas('customer', function ($q) {
            $q->where('level', request()->query('customer_level'));
        });
    }

    public function scopeCustomerPhone($query)
    {
        return $query->whereHas('customer', function ($q) {
            $q->where('phone_number', request()->query('customer_phone'));
        });
    }

    public function orderSupplier(): \Illuminate\Database\Eloquent\Relations\hasMany
    {
        return $this->hasMany(OrderSupplier::class, 'order_id', 'order_id');
    }

    public function scopeCustomerVia($query)
    {
        /*return $query->whereHas('customer', function ($q) {
            $q->where('via', request()->query('customer_via'));
        });*/

        if (is_array(request()->input('customer_via'))) {
            return $query->whereHas('customer', function ($q) {
                $q->whereIn('via', request()->query('customer_via'));
            });
        }
        return $query->whereHas('customer', function ($q) {
            $q->where('via', request()->query('customer_via'));
        });
    }

    public function scopeCustomerBusinessType($query)
    {
        return $query->whereHas('customer', function ($q) {
            $q->where('business_type', request()->query('customer_business_type'));
        });
    }

    public function scopeCustomerCreatedAt($query)
    {
        return $query->whereHas('customer', function ($q) {
            $dates = (new FilterService())->rangeDateFilterV2(request()->query('customer_created_at'));
            $q->whereBetween('created_at', [$dates[0] . ' 00:00:00', $dates[1] . ' 23:59:59']);
        });
    }

    public function scopeCustomerCode($query)
    {
        if (empty(request()->query('customer_code'))) {
            return $query;
        }

        return $query->whereHas('customer', function ($q) {
            $q->where('code', request()->query('customer_code'));
        });
    }

    public function scopeStaffOrderId($query)
    {
        $staffOrderIds = is_array(request()->query('staff_order_id')) ? request()->query('staff_order_id') : [request()->query('staff_order_id')];
        return $query->whereIn('staff_order_id', $staffOrderIds)
                    ->when(in_array("0", $staffOrderIds), function($q) {
                        return $q->orWhereNull('staff_order_id');
                    });
    }

    public function scopeStaffQuotationId($query)
    {
        $staffQuotationIds = is_array(request()->query('staff_quotation_id')) ? request()->query('staff_quotation_id') : [request()->query('staff_quotation_id')];
        return $query->whereIn('staff_quotation_id', $staffQuotationIds)
                    ->when(in_array("0", $staffQuotationIds), function($q) {
                        return $q->orWhereNull('staff_quotation_id');
                    });
    }

    public function scopeStaffCareId($query)
    {
        $staffCareIds = is_array(request()->query('staff_care_id')) ? request()->query('staff_care_id') : [request()->query('staff_care_id')];
        return $query->whereIn('staff_care_id', $staffCareIds)
                    ->when(in_array("0", $staffCareIds), function($q) {
                        return $q->orWhereNull('staff_care_id');
                    });
    }

    //nhân viên tư vấn
    public function scopeStaffCounselorId($query)
    {

        $staffCounselorId = is_array(request()->query('staff_counselor_id')) ? request()->query('staff_counselor_id') : [request()->query('staff_counselor_id')];
        return $query->whereIn('staff_counselor_id', $staffCareIds)
                    ->when(in_array("0", $staffCounselorId), function($q) {
                        return $q->orWhereNull('staff_counselor_id');
                    });
    }

    //
    public function scopeStaffGroupId($query)
    {
        $staffGroupIds = is_array(request()->query('staff_group_id')) ? request()->query('staff_group_id') : [request()->query('staff_group_id')];
        return $query->whereIn('staff_group_id', $staffGroupIds)
                    ->when(in_array("0", $staffGroupIds), function($q) {
                        return $q->orWhereNull('staff_group_id');
                    });
    }

    public function scopeIsWebsite($query)
    {
        return $query->where('is_website', (int)request()->query('is_website'));
    }

    public function scopeIsTax($query)
    {
        return $query->where('is_tax', request()->query('is_tax'));
    }

    public function scopeIsPurchase($query)
    {
        return $query->where('deposit_cost', (bool)request()->query('is_purchase') ? '>' : '=', 0);
    }

    public function scopeEcommerce($query)
    {
        return $query->where('ecommerce', request()->query('ecommerce'));
    }

    public function scopeCodePo($query)
    {
        return $query->whereHas('packages', function ($q) {
            $q->where('code_po', request()->query('code_po'));
        });
        return $query->where('code_po', request()->query('code_po'));
    }

    public function scopeCodeParent($query)
    {
        return $query->where('code_parent', request()->query('code_parent'));
    }

    public function scopeNotOrderId($query)
    {
        return $query->where('id', '!=', request()->query('not_order_id'))
                    ->orderBy('id', 'desc');
    }

    public function scopeCustomerId($query)
    {
        return $query->where('customer_id', request()->query('customer_id'));
    }

    public function scopeCode($query)
    {
        if (empty(request()->query('code'))) {
            return $query;
        }

//        return $query->where('code', 'LIKE', '%' . request()->query('code') . '%');
        return $query->where(function ($q) {
                $q->where('code', 'LIKE', '%' . request()->query('code') . '%')
                ->orWhere('code_parent', 'LIKE', '%' . request()->query('code') . '%');
        });
    }

    public function scopeDateDone($query)
    {
        if (empty(request()->query('date_done'))) {
            return $query;
        }
        $dates = (new FilterService())->rangeDateFilterV2(request()->query('date_done'));
        return $query->whereBetween('date_done', [$dates[0] . ' 00:00:00', $dates[1] . ' 23:59:59']);
    }

    public function scopeDatePurchased($query)
    {
        // Logic cũ
        // return (new FilterService())->rangeDateFilter($query, request()->query('date_purchased'), 'date_purchased');

        // Logic mới
        // $dates = (new FilterService())->rangeDateFilterV2(request()->query('date_purchased'));
        // $query = $query
        //                 ->whereDate('order_status_times.status_2', '>=', $dates[0])
        //                 ->whereDate('order_status_times.status_2', '<=', $dates[1]);

        // Logic mới hơn
        $orderStatusTimeModel = \App::make('App\Models\OrderStatusTime');
        $dates = (new FilterService())->rangeDateFilterV2(request()->query('date_purchased'));
        $orderStatusTimes = $orderStatusTimeModel
            ->whereDate('order_status_times.status_2', '>=', $dates[0])
            ->whereDate('order_status_times.status_2', '<=', $dates[1])
            ->get();
        $order_ids = [];
        if (count($orderStatusTimes)) {
            $order_ids = \Arr::pluck($orderStatusTimes, 'order_id');
        }
        $query = $query->whereIn('orders.id', $order_ids);
        return $query;
    }

    public function scopeDateOrdered($query)
    {
        // Logic cũ
        // return (new FilterService())->rangeDateFilter($query, request()->query('date_ordered'), 'date_ordered');

        // Logic mới
        // $dates = (new FilterService())->rangeDateFilterV2(request()->query('date_ordered'));
        // $query = $query
        //                 ->whereDate('order_status_times.status_3', '>=', $dates[0])
        //                 ->whereDate('order_status_times.status_3', '<=', $dates[1]);

        // Logic mới hơn
        $dates = (new FilterService())->rangeDateFilterV2(request()->query('date_ordered'));
        return $query = $query->whereIn('orders.id', function($query) use ($dates) {
            $query->select('order_status_times.order_id')
            ->from('order_status_times')
            ->whereBetween('order_status_times.status_3', [Carbon::parse($dates[0]), Carbon::parse($dates[1])->endOfDay()]);
        });
    }

    public function scopeDateQuotation($query)
    {
        // Logic cũ
        // return (new FilterService())->rangeDateFilter($query, request()->query('date_quotation'), 'date_quotation');

        // Logic mới
        // $dates = (new FilterService())->rangeDateFilterV2(request()->query('date_quotation'));
        // $query = $query
        //                 ->whereDate('order_status_times.status_1', '>=', $dates[0])
        //                 ->whereDate('order_status_times.status_1', '<=', $dates[1]);

        // Logic mới hơn
        $orderStatusTimeModel = \App::make('App\Models\OrderStatusTime');
        $dates = (new FilterService())->rangeDateFilterV2(request()->query('date_quotation'));
        $orderStatusTimes = $orderStatusTimeModel
            ->whereDate('order_status_times.status_1', '>=', $dates[0])
            ->whereDate('order_status_times.status_1', '<=', $dates[1])
            ->get();
        $order_ids = [];
        if (count($orderStatusTimes)) {
            $order_ids = \Arr::pluck($orderStatusTimes, 'order_id');
        }
        $query = $query->whereIn('orders.id', $order_ids);
        return $query;
    }

    public function scopeCreatedAtSort($query)
    {
        return $query->orderBy('orders.created_at', request()->query('created_at_sort'));
        return $query->orderBy('orders.id', request()->query('created_at_sort'));
    }

    public function scopeOrderCostSort($query)
    {
        return $query->orderBy('order_cost', request()->query('order_cost_sort'));
    }

    public function scopeNullStaffCounselorId($query)
    {
        if (request()->query('null_staff_counselor_id')) {
            return $query->whereNull('staff_counselor_id')->where('reset_staff', 'care')->where('status', '<>', 'status_4');
        }
    }

    public function scopeNullStaffCareId($query)
    {
        if (request()->query('null_staff_care_id')) {
            return $query->whereNull('staff_care_id')->where('reset_staff', 'counselor')->where('status', '<>', 'status_4');
        }
    }

    public function scopeProductName($query)
    {
        if (request()->query('product_name')) {
            return $query->whereHas('details', function ($query1) {
                $query1->where('order_details.name', 'like', '%' . request()->query('product_name') . '%');
            });
        }
    }

    public function scopeSupplierName($query)
    {
        if (request()->query('supplier_name')) {
            return $query->whereHas('details.supplier', function ($query1) {
                $query1->where('suppliers.name', 'like', '%' . request()->query('supplier_name') . '%');
            });
        }
    }

    public function scopeIsOutDate($query)
    {
        if (empty(request()->query('is_out_date'))) {
            return $query;
        }

        return $query->whereRaw('DATE(date_ordered) < DATE_SUB(CURDATE(),INTERVAL 15 DAY)')
            ->whereIn('status',[OrderConstant::KEY_STATUS_ORDERED]);
    }

    public function scopeIsOrderNow($query)
    {
        if (empty(request()->query('is_order_now'))) {
            return $query;
        }
        return $query->where('is_order_now', (int)request()->query('is_order_now'));
    }

    public function scopeIsWaitingForSale($query)
    {
        if (empty(request()->query('is_waiting_for_sale'))) {
            return $query;
        }

        return $query->where('is_waiting_for_sale', request()->query('is_waiting_for_sale'));
    }

    public function scopeIsWaitingConfirm($query)
    {
        $value = request()->query('is_waiting_confirm');

        if ($value === null) {
            return $query;
        }

        return $query->where('is_waiting_confirm', $value);
    }

    public function scopeTypeTime($query)
    {
        if (request()->query('type_time')) {
            switch (request()->query('type_time')) {
                case OrderConstant::ORDER_IN_DAY:
                    return $query->whereDate('orders.created_at', date(TimeConstant::DATE));
                case OrderConstant::ORDER_IN_WEEK:
                    return $query->whereBetween(
                        'orders.created_at',
                        [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]
                    );
                case OrderConstant::ORDER_IN_MONTH:
                    return $query->whereMonth('orders.created_at', date('m'))
                        ->whereYear('orders.created_at', date('Y'));
            }
        }
    }

    public function scopeChanelId($query)
    {
        return $query->whereHas('orderSuppliers', function ($q) {
            $q->where('source_type', request()->query('chanel_id'));
        });
    }

    public function staffCounselor()
    {
        return $this->belongsTo(Staff::class, 'staff_counselor_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function orderSuppliers(): HasMany
    {
        return $this->hasMany(OrderSupplier::class, 'order_id', 'id');
    }

    public function orderStatusTime(): HasOne
    {
        return $this->hasOne(OrderStatusTime::class, 'order_id', 'id');
    }

    public function getIndexOrderCompleteByStaff($staffCareId, $staffCounselorId)
    {
        $query = $this->newModelQuery()
            ->join('order_status_times', 'orders.id', '=', 'order_status_times.order_id')
            ->where('status', OrderConstant::KEY_STATUS_DONE)
            ->where('order_status_times.' . OrderConstant::KEY_STATUS_DONE, '<',
                optional($this->orderStatusTime)->{OrderConstant::KEY_STATUS_DONE});

        if ($staffCareId) {
            $query->where(function ($q) use ($staffCareId) {
                $q->where('staff_care_id', $staffCareId);
                $q->whereNull('staff_counselor_id');
            });
        }
        if ($staffCounselorId) {
            $query->where(function ($q1) use ($staffCounselorId) {
                $q1->where('staff_counselor_id', $staffCounselorId);
                $q1->whereNull('staff_care_id');
            });
        }

        $count = $query->count();

        return $count ? $count + 1 : 1;
    }

    public function orderParent(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'code_parent', 'code');
    }

    public function reportVN(): HasOne
    {
        return $this->hasOne(ReportOrderVN::class, 'order_id', 'id');
    }

    /**
     * Lấy ra bản ghi tương ứng trong 'order_buyders'
     *
     * @return HasMany
     */
    public function orderBuyders(): BelongsToMany
    {
        return $this->belongsToMany(
            OrderBuyder::class, 'order_buyder_order',
            'order_id', 'order_buyder_id'
        );
    }

    public function orderBuyerSub(): HasMany
    {
        return $this->hasMany(SubOrderBuyder::class, 'order_id', 'id');
    }

    public function orderDetail(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'order_id', 'id');
    }

    /**
     * Lấy ra nhân viên phụ trách đơn hàng (users)
     *
     * @return BelongsTo
     */
    public function staffOrder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_order_id', 'id');
    }

    /**
     * Danh sách các order_buyder_subs thuộc một order
     *
     * @return Collection
     */
    public function subOrderBuyders(): HasMany
    {
        return $this->hasMany(SubOrderBuyder::class);
    }

    public function orderContracts(): HasMany
    {
        return $this->hasMany(OrderContract::class);
    }


    // copy trong orderResource de su dung
    public function getTotalAmount($is_round = true)
    {
        $orderSuppliers = $this->orderSuppliers;
        $totalDiscountCostPacks = !empty($this->packagesOrder) ? $this->packagesOrder->sum('discount_cost') : 0;
        $totalAmountRound = 0;
        $totalShipPackNotRound = 0;
        foreach ($orderSuppliers as $sup) {
            $orderFee  = $sup->order_fee;
            $orderCost = $sup->order_cost;
            $discountCost = $sup->discount_cost;
            $temp_sub = 0;
            $temp_sub += $sup->china_shipping_cost * $this->exchange_rate;
            $temp_sub += $orderFee;
            $temp_sub += $orderCost;
            $temp_sub += $sup->inspection_cost;
            $temp_sub += $sup->advanced_inspection_cost;
            $temp_sub -= $discountCost;
            $totalAmountRound += $temp_sub;

            $totalShipPackNotRound = $sup->woodworking_cost + $sup->shock_proof_cost + $sup->international_shipping_cost - $totalDiscountCostPacks;
        }

        // dd($totalAmountRound);

        return $is_round ? roundMoney($totalAmountRound) + $totalShipPackNotRound
            : $totalAmountRound + $totalShipPackNotRound;
    }


    public function getTotalAmountNotShip()
    {
        $orderSuppliers = $this->orderSuppliers;
        $totalAmountRound = 0;
        foreach ($orderSuppliers as $sup) {
            $orderFee = $sup->order_fee;
            $orderCost = $sup->order_cost;
            $discountCost = $sup->discount_cost;
            $temp_sub = 0;
            $temp_sub += $sup->china_shipping_cost * $this->exchange_rate;
            $temp_sub += $orderFee;
            $temp_sub += $orderCost;
            $temp_sub += $sup->inspection_cost;
            $temp_sub += $sup->advanced_inspection_cost;
            $temp_sub -= $discountCost;
            $totalAmountRound += $temp_sub;
        }

        return roundMoney($totalAmountRound);
    }

    public function staffGroup(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_group_id', 'id');
    }

    public function staffQuotation(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_quotation_id', 'id');
    }

    public function orderHistory(): HasMany
    {
        return $this->hasMany(OrderHistory::class, 'order_id', 'id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'order_id', 'id');
    }

    public function orderRefunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class, 'order_id', 'id');
    }

    public function reasonConfirmOrderOrders()
    {
        return $this->hasMany(ReasonConfirmOrderOrder::class, 'order_id', 'id');
    }

    public function reasonConfirmOrders()
    {
        return $this->belongsToMany('App\Models\ReasonConfirmOrder', 'reason_confirm_order_order', 'order_id', 'reason_confirm_order_id')->withPivot('created_at');
    }

    public function reasonConfirmOrder()
    {
        return $this->belongsTo(ReasonConfirmOrder::class, 'reason_confirm_order_id', 'id');
    }

    protected static function booted()
    {
        static::updating(function ($order) {
            $order->version_edit = $order->version_edit + 1;
        });
    }

    public function orderDepositPercent()
    {
        return $this->hasOne(OrderDepositPercent::class);
    }
}
