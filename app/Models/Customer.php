<?php

namespace App\Models;

use App\Constants\CustomerConstant;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\TimeConstant;
use App\Models\Traits\Filters\CreatedAtFilter;
use App\Models\Traits\Filters\WarehouseFilter;
use App\Models\Traits\WarehouseRelation;
use App\Models\Traits\ProvinceRelation;
use App\Models\Traits\DistrictRelation;
use App\Scopes\Traits\Filterable;
use App\Scopes\Traits\HasOrganization;
use App\Scopes\Traits\HasSortDescByCreated;
use App\Services\FilterService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class Customer
 * @package App\Models
 *
 * @property string $id
 * @property string $organization_id
 * @property string $warehouse_id
 * @property string $code
 * // * @method Builder search
 */
class Customer extends BaseModel
{
    // use HasFactory, Filterable, SoftDeletes, HasSortDescByCreated, HasOrganization, WarehouseFilter, WarehouseRelation, CreatedAtFilter;
    use HasFactory, Filterable, HasSortDescByCreated, HasOrganization, WarehouseFilter, WarehouseRelation, CreatedAtFilter,
        ProvinceRelation, DistrictRelation, SoftDeletes;
    use Notifiable, HasPushSubscriptions;
    use LogsActivity;

    protected $dates = [
        'created_at',
        'updated_at'
    ];

    protected string $_tableNameFriendly = 'Khách hàng';

    protected static $logAttributes = [
        "creator_id",
        "code",
        "name",
        "email",
        "avatar",
        "password",
        "phone_number",
        "address",
        "district_id",
        "province_id",
        "bod",
        "gender",
        "via",
        "facebook_link",
        "business_type",
        "type",
        "delivery_type",
        "level",
        "staff_counselor_id",
        "staff_care_id",
        "staff_sale_id",
        "staff_order_id",
        "staff_group_id",
        "staff_group_time_assign",
        "reset_staff",
        "reset_staff_time",
        "warehouse_id",
        "warehouse_cn_id",
        "last_order_at",
        "label_id",
        "customer_introduce_id",
        "customer_introduce_time_add",
        "discount_by_kg",
        "discount_by_m3",
        "point_level",
        "point_way",
        "point_care",
        "organization_id",
        "thuongdo_id",
        "thuongdo_uuid",
        "thuongdo_page",
        "chatbiz_total_call",
        "service",
        "verify_code",
        "otp_code",
        "customer_reason_inactive_id",
        "total_orders",
        "status",
        "deleted_by",
        'init_order_complete',
        'total_order_complete',
        'total_chat_unread',
        'conversation_id_current',
        'chatbiz_source'
    ];
    protected static $ignoreChangedAttributes = ['total_chat_unread', 'conversation_id_current'];
    protected static $logOnlyDirty = true;
    protected static $logName = 'customers';

    const SOURCE_HV = 1;
    const SOURCE_TD = 2;
    const SOURCE_NH = 3;

    const LIST_SOURCE = [
        self::SOURCE_HV => 'Hàng Về',
        self::SOURCE_TD => 'Thương Đô',
        self::SOURCE_NH => 'Nguồn Hàng 247',
    ];
    const LIST_URL = [
        self::SOURCE_HV => 'https://hangve.com',
        self::SOURCE_TD => 'https://thuongdo.com',
        self::SOURCE_NH => 'https://nguonhang247.com',
    ];

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class);
    }

    public function report(): HasOne
    {
        return $this->HasOne(ReportCustomer::class);
    }
    public function reportPack(): HasOne
    {
        return $this->HasOne(ReportPackage::class);
    }

    public function customerOffers(): HasOne
    {
        return $this->HasOne(CustomerOffer::class);
    }
    public function delivery(): HasMany
    {
        return $this->HasMany(CustomerDelivery::class);
    }

    public function deliveryDefault(): HasOne
    {
        return $this->HasOne(CustomerDelivery::class)->where('is_default', 1);
    }
    public function offer(): HasOne
    {
        return $this->HasOne(CustomerOffer::class);
    }

    public function consignments(): HasMany
    {
        return $this->HasMany(Consignment::class);
    }

    public function transactions(): HasMany
    {
        return $this->HasMany(Transaction::class);
    }

    public function changeStaffRequests(): HasMany
    {
        return $this->HasMany(ChangeStaffRequest::class);
    }

    public function rate(): HasMany
    {
        return $this->HasMany(OrderRate::class, 'customer_code', 'code');
    }

    public function customerNotes(): HasMany
    {
        return $this->HasMany(CustomerNote::class, 'customer_id');
    }

    public function customerNotesType0(): HasMany
    {
        return $this->HasMany(CustomerNote::class, 'customer_id')->where('type', CustomerNote::CUSTOMER_NOTE_TYPE_0);
    }

    public function customerNotesType1(): HasMany
    {
        return $this->HasMany(CustomerNote::class, 'customer_id')->where('type', CustomerNote::CUSTOMER_NOTE_TYPE_1);
    }

    // Màn bên khách hàng
    public function customerNotesType1And0(): HasMany
    {
        return $this->HasMany(CustomerNote::class, 'customer_id')
                        ->where(function ($query) {
                            $query->orWhere('type',  CustomerNote::CUSTOMER_NOTE_TYPE_0)
                                  ->orWhere('type', CustomerNote::CUSTOMER_NOTE_TYPE_1);
                        });
    }

    // Màn bên đơn hàng
    public function customerNotesType2And0(): HasMany
    {
        return $this->HasMany(CustomerNote::class, 'customer_id')
                        ->where(function ($query) {
                            $query->orWhere('type',  CustomerNote::CUSTOMER_NOTE_TYPE_0)
                                  ->orWhere('type', CustomerNote::CUSTOMER_NOTE_TYPE_2);
                        });
    }

    public function scopeCode($query)
    {
        return $query->where('customers.code', request()->input('code'));
    }

    public function scopeName($query)
    {
        return $query->where('name', 'like', '%' . request()->input('name') . '%');
    }

    public function scopeCreatedAtV2($query)
    {
        $arrayDate = explode(',', request()->input('created_at_v2'));
        return $query
                    ->whereDate('customers.created_at', '>=', $arrayDate[0].' 00:00:00')
                    ->whereDate('customers.created_at', '<=', $arrayDate[1].' 23:59:59');
    }

    // search từ màn CEO
    public function scopeSearchAll($query) {
        return $query->where(function ($q) {
            $q->where('code', request()->input('search_all'));
            $q->orwhere('phone_number', request()->input('search_all'));
            $q->orwhere('email', request()->input('search_all'));
            $q->orwhere('name', 'like', '%' . request()->input('search_all') . '%');
        });
    }

    public function scopeEmail($query)
    {
        return $query->where('email', request()->input('email'));
    }

    public function scopePhoneNumber($query)
    {
        return $query->where('phone_number', request()->input('phone_number'));
    }

    public function scopePointWay($query)
    {
        if(request()->input('point_way') == true) {
            return $query->where('point_way', '>=', 40)->get();
        } else {
            return $query;
        }
    }

    public function order(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id', 'id');
    }

    public function orderPackage(): HasMany
    {
        return $this->hasMany(OrderPackage::class, 'customer_id', 'id');
    }

    public function orderCompleted(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id', 'id')
            ->where('status', Order::ORDER_STATUS_COMPLETED);
    }

    public function scopeProvinceId($query)
    {
        return $query->where('province_id', request()->input('province_id'));
    }

    public function scopeLevel($query)
    {
        return $query->where('level', request()->input('level'));
    }

    public function scopeLabelId($query)
    {
        $labelIds = is_array(request()->query('label_id')) ? request()->query('label_id') : [request()->query('label_id')];

        return $query->whereHas('labels', function ($q) use ($labelIds) {
            $q->whereIn('labels.id', $labelIds);
        });
    }

    public function scopeBusinessType($query)
    {
        if (is_array(request()->input('business_type'))) {
            return $query->whereIn('business_type', request()->input('business_type'));
        }
        return $query->where('business_type', request()->input('business_type'));
    }

    public function scopeStaffCareId($query)
    {
        return $query->where('staff_care_id', request()->input('staff_care_id'));
    }

    public function scopeStaffCounselorId($query)
    {
        return $query->where('staff_counselor_id', request()->input('staff_counselor_id'));
    }

    public function scopeStaffGroupId($query)
    {
        $staffGroupIds = is_array(request()->query('staff_group_id')) ? request()->query('staff_group_id') : [request()->query('staff_group_id')];
        return $query->whereIn('customers.staff_group_id', $staffGroupIds)
                    ->when(in_array("0", $staffGroupIds), function($q) {
                        return $q->orWhereNull('customers.staff_group_id');
                    });
    }

    public function scopeStaffOrderId($query)
    {
        return $query->where('staff_order_id', request()->input('staff_order_id'));
    }

    public function scopePointLevel($query)
    {
        return $query->where('point_level', request()->input('point_level'));
    }

    public function scopeVia($query)
    {
        if (is_array(request()->input('via'))) {
            return $query->whereIn('via', request()->input('via'));
        }
        return $query->where('via', request()->input('via'));
//        return $query->where('via', request()->input('via'));
    }

    public function scopeService($query)
    {
        return $query->where('service', request()->input('service'));
    }

    public function scopeCustomerReasonInactiveId($query)
    {
        return $query->where('customer_reason_inactive_id', request()->input('customer_reason_inactive_id'));
    }

    public function scopeNullStaffCounselorId($query)
    {
        if (request()->query('null_staff_counselor_id')) {
            return $query->whereNull('staff_counselor_id')->where('reset_staff', 'care');
        }
    }

    public function scopeNullStaffCareId($query)
    {
        if (request()->query('null_staff_care_id')) {
            return $query->whereNull('staff_care_id')->where('reset_staff', 'counselor');
        }
    }
    public function scopeSource($query)
    {
        if (request()->query('source')) {
            return $query->where('source', request()->query('source'));
        }
    }

    public function scopeOrdersNumber($query)
    {
        $reportCustomerModel = \App::make('App\Models\ReportCustomer');
        $reports = $reportCustomerModel->where('orders_number', request()->input('orders_number'))->get();

        $customer_ids = [];

        if (count($reports)) {
            $customer_ids = \Arr::pluck($reports, 'customer_id');
        }
        $customer_ids = array_unique($customer_ids);
        $query = $query->whereIn('id', $customer_ids);

        return $query;
    }
    public function scopeTotalOrderNumberDone($query)
    {
        if ($orderNumber = request()->query('total_order_number_done')) {
            $reportCustomerModel = \App::make('App\Models\ReportCustomer');
            $reports = $reportCustomerModel
                ->whereRaw("status_3 + status_4 = " . $orderNumber . "")
                ->get();

            $customer_ids = [];

            if (count($reports)) {
                $customer_ids = \Arr::pluck($reports, 'customer_id');
            }
            $customer_ids = array_unique($customer_ids);
            $query = $query->whereIn('id', $customer_ids);

            return $query;
        }
    }

    public function scopeLastOrder($query)
    {
        $end = Carbon::now();
        $start = Carbon::now()->subDays((int)request()->input('last_order'));
        return (new FilterService())->rangeDateFilter(
            $query,
            $start->format(TimeConstant::DATE).','.$end->format(
                TimeConstant::DATE
            ),
            'last_order_at'
        );
    }

    public function scopeLastOrderV2($query)
    {
        return (new FilterService())->rangeDateFilter($query, request()->query('last_order_v2'), 'last_order_at');
    }

    public function scopeWaitDepositLevel($query)
    {
        if (!empty(request()->query('wait_deposit_level'))) {
            $level = request()->query('wait_deposit_level');

//            return $query->whereHas('orders.orderStatusTime', function ($q) use ($level) {
//                $q->where('orders.status', OrderConstant::KEY_STATUS_WAITING_DEPOSIT );
//
//                switch ($level) {
//                    case CustomerConstant::WAIT_DEPOSIT_LV1:
//                        $q->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) <= UNIX_TIMESTAMP(DATE_ADD(status_1,INTERVAL 5 MINUTE))');
//                        break;
//                    case CustomerConstant::WAIT_DEPOSIT_LV2:
//                        $q->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) > UNIX_TIMESTAMP(DATE_ADD(status_1,INTERVAL 5 MINUTE)) AND
//                        UNIX_TIMESTAMP(CURDATE()) <= UNIX_TIMESTAMP(DATE_ADD(status_1,INTERVAL 60 MINUTE))');
//                        break;
//                    case CustomerConstant::WAIT_DEPOSIT_LV3:
//                        $q->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) > UNIX_TIMESTAMP(DATE_ADD(status_1,INTERVAL 60 MINUTE))');
//                        break;
//                }
//            });

            $query->join('orders', 'customers.id', '=', 'orders.customer_id');
            $query->join('order_status_times', 'orders.id', '=', 'order_status_times.order_id');
            $query->where(function ($q) use ($level) {
                $q->where('orders.status', OrderConstant::KEY_STATUS_WAITING_DEPOSIT );

                switch ($level) {
                    case CustomerConstant::WAIT_DEPOSIT_LV1:
                        $q->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) <= UNIX_TIMESTAMP(DATE_ADD(order_status_times.status_1,INTERVAL 5 MINUTE))');
                        break;
                    case CustomerConstant::WAIT_DEPOSIT_LV2:
                        $q->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) > UNIX_TIMESTAMP(DATE_ADD(order_status_times.status_1,INTERVAL 5 MINUTE)) AND
                        UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) <= UNIX_TIMESTAMP(DATE_ADD(order_status_times.status_1,INTERVAL 60 MINUTE))');
                        break;
                    case CustomerConstant::WAIT_DEPOSIT_LV3:
                        $q->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) > UNIX_TIMESTAMP(DATE_ADD(order_status_times.status_1,INTERVAL 60 MINUTE))');
                        break;
                }
            });
        }
    }

    public function scopeStatus($query)
    {
        return $query->where('status', request()->input('status'));
    }

    public function scopeStatusActive($query)
    {
        return $query->where('status', '!=', CustomerConstant::CUSTOMER_STATUS_INACTIVE_KEY);
    }

    public function scopeIsOrder($query)
    {
        // Bộ lọc loại khách hàng có đơn hay chưa có đơn
        if(request()->input('service') !== null){
            return $query->whereHas('report', function ($q) {
                if(request()->input('service') == 0){
                    if(request()->query('is_order') == 0){
                        return $q->where('orders_number', 0);
                    }
                    if(request()->query('is_order') == 1){
                        return $q->where('orders_number', '>', 0);
                    }
                }
                if(request()->input('service') == 1){
                    if(request()->query('is_order') == 0){
                        return $q->where('consignment_number', 0);
                    }
                    if(request()->query('is_order') == 1){
                        return $q->where('consignment_number', '>', 0);
                    }
                }
            });
        }

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

    public function staffQuotation(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_quotation_id', 'id');
    }

    public function staffGroup(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_group_id', 'id');
    }

    public function userStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_group_id', 'id');
    }

    public function customerIntroduce(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_introduce_id', 'id');
    }

    public function orderDeposit(): HasMany
    {
        return $this->HasMany(Order::class)
            ->where('deposit_cost', '>', 0)
            ->orderBy('created_at', 'desc');
    }

    public function orderDone(): HasMany
    {
        return $this->HasMany(Order::class)
            ->whereIn('status', [OrderConstant::KEY_STATUS_ORDERED, OrderConstant::KEY_STATUS_DONE])
            ->orderBy('created_at', 'desc');
    }

    public function lastOrder(): HasMany
    {
        return $this->HasMany(Order::class)
            ->orderBy('created_at', 'desc')
            ->latest();
    }

    public function getLastOrder($isCheckDeposit = true)
    {
         $query = $this->orders();

         if ($isCheckDeposit) {
             $query->where('deposit_cost', '>', 0);
         }

         $query->orderBy('created_at', 'desc');

         $orderLast = $query->first();
        if ($orderLast) {
            return $orderLast->created_at->format('d-m-Y');
        }
        return null;
    }

    public function customer_categories()
    {
        return $this->belongsToMany('App\Models\Category', 'customer_categories', 'customer_id', 'category_id')->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->HasMany(Order::class);
    }

    public function ordersLast()
    {
        return $this->HasOne(Order::class)->orderBy('orders.created_at', 'DESC');
    }

    public function getSumOrderCostAttribute() {
        return $this->orders()->where('deposit_cost', '>', 0)->sum('order_cost');
    }

    public function reportCustomerOrders(): HasMany
    {
        $customerOrders = $this->HasMany(Order::class);

        $orderStatus = OrderConstant::REPORT_CUSTOMER_ORDER_CARE;
        if (!empty(request()->get('order_status'))) {
            $orderStatus = [request()->get('order_status')];
        }
        $customerOrders->whereIn('status', $orderStatus);

        if (!empty(request()->get('created_at'))) {
            $dates = explode(',', request()->get('created_at'));

            if (!empty($dates[0])) {
                $customerOrders->whereDate('orders.created_at', '>=', $dates[0]);
            }
            if (!empty($dates[1])) {
                $customerOrders->whereDate('orders.created_at', '<=', $dates[1]);
            }
        }

        return $customerOrders;
    }

    public function packages(): hasMany
    {
        return $this->hasMany(OrderPackage::class, 'customer_id', 'id');
    }

    public function packagesVN(): HasMany
    {
        return $this->HasMany(OrderPackage::class)->where('order_package.status', PackageConstant::STATUS_WAREHOUSE_VN);
    }

    public function reportCustomerPackagesVN(): HasMany
    {
        $customerPackagesVN = $this->packagesVN();

        $customerPackagesVN->select('order_package.*', 'order_package_status_times.created_at as timeVN');

        if (!empty(request()->get('bill_code'))) {
            $customerPackagesVN->where('bill_code', 'LIKE', '%' . request()->get('bill_code') . '%');
        }

        if (!empty(request()->get('created_at'))) {
            $dates = explode(',', request()->get('created_at'));

            if (count($dates)) {
                //$customerPackagesVN->statusTimes();
                $customerPackagesVN->join('order_package_status_times', 'order_package.id', '=' ,
                    'order_package_status_times.order_package_id');
            }

            $customerPackagesVN->where('order_package_status_times.key', PackageConstant::STATUS_WAREHOUSE_VN);

            if (!empty($dates[0])) {
                $customerPackagesVN->whereDate('order_package_status_times.created_at', '>=', $dates[0]);
            }
            if (!empty($dates[1])) {
                $customerPackagesVN->whereDate('order_package_status_times.created_at', '<=', $dates[1]);
            }
        }

        if (!empty(request()->get('priority'))) {
            $level = request()->get('priority');

            //$customerPackagesVN->statusTimes();
            if (empty(request()->get('created_at'))) {
                $customerPackagesVN->join('order_package_status_times', 'order_package.id', '=' ,
                    'order_package_status_times.order_package_id');
            }

            $customerPackagesVN->where(function ($q) use ($level) {
                $q->where('order_package_status_times.key', PackageConstant::STATUS_WAREHOUSE_VN );

                switch ($level) {
                    case CustomerConstant::REPORT_PACKAGE_VN_LV1:
                        $q->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) <= UNIX_TIMESTAMP(DATE_ADD(
                            order_package_status_times.created_at,INTERVAL 24 HOUR))');
                        break;
                    case CustomerConstant::REPORT_PACKAGE_VN_LV2:
                        $q->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) > UNIX_TIMESTAMP(DATE_ADD(
                            order_package_status_times.created_at,INTERVAL 24 HOUR)) AND
                        UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) <= UNIX_TIMESTAMP(DATE_ADD(
                            order_package_status_times.created_at,INTERVAL 72 HOUR))');
                        break;
                    case CustomerConstant::REPORT_PACKAGE_VN_LV3:
                        $q->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) > UNIX_TIMESTAMP(DATE_ADD(
                            order_package_status_times.created_at,INTERVAL 72 HOUR))');
                        break;
                }
            });
        }
        //dd($customerPackagesVN->toSql());
        return $customerPackagesVN;
    }

    // Đã đồng bộ level_rate trong bảng customer_offers sang level trong bảng customers nên không cần hàm này nữa
    // public function getLevelAttribute()
    // {
    //     $offerDB = $this->offer;
    //     if ($offerDB && $offerDB->level_rate) {
    //         return $offerDB->level_rate;
    //     }

    //     return !empty($this->attributes['level']) ? $this->attributes['level'] : 0;
    // }

    public function reportOrderVN(): HasMany
    {
        return $this->HasMany(ReportOrderVN::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'deleted_by', 'id');
    }

    public function getAvatarOriginAttribute(?string $value): ?string
    {
        return $this->attributes['avatar'];
    }

    public function pointWay(): HasMany
    {
        return $this->HasMany(CustomerPointTime::class)->where('type', 1);
    }

    public function warehouseCN(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_cn_id', 'id')->withoutGlobalScopes();
    }
}
