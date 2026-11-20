<?php

namespace App\Services;

use App\Actions\Order\ListAmountAction;
use App\Actions\Order\ListOrderAction;
use App\Constants\ActivityConstant;
use App\Constants\ConfigConstant;
use App\Constants\CustomerConstant;
use App\Constants\DepartmentConstant;
use App\Constants\NotifyConstant;
use App\Constants\OrderConstant;
use App\Constants\RoleConstant;
use App\Constants\PackageConstant;
use App\Constants\SupplierConstant;
use App\Constants\TransactionConstant;
use App\Constants\UserConstant;
use App\Helpers\AccountingHelper;
use App\Http\Resources\OnlyIdNameResource;
use App\Http\Resources\Order\OrderCustomerPaginateResource;
use App\Http\Resources\Order\OrderCustomerResource;
use App\Http\Resources\Order\OrderListRefundResource;
use App\Http\Resources\Order\OrderRelatedListResource;
use App\Http\Resources\Order\OrderResource;
use App\Http\Resources\Order\OrderListResource;
use App\Jobs\MakeLogOrderStatusDone;
use App\Jobs\Report\SyncCustomerLevelJob;
use App\Jobs\Report\SyncReportOrderConsignmentDateJob;
use App\Jobs\Report\SyncReportOrderProvinceJob;
use App\Jobs\SentSmsToCustomerJob;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\CustomerOffer;
use App\Models\Ecommerce;
use App\Models\Fund;
use App\Models\FundTypePay;
use App\Models\Order;
use App\Models\OrderAdvanceMoney;
use App\Models\OrderDetail;
use App\Models\OrderDetailPackage;
use App\Models\OrderHistory;
use App\Models\OrderNote;
use App\Models\OrderPackage;
use App\Models\OrderRefund;
use App\Models\Refund;
use App\Models\OrderStatusTime;
use App\Models\OrderSupplier;
use App\Models\RefundFromXadal;
use App\Models\ReportCustomer;
use App\Models\SmsLog;
use App\Models\Supplier;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Helpers\ConvertHelper;
use App\Http\Resources\Order\OrderListCancelResource;
use App\Services\FilterService;
use App\Constants\TimeConstant;
use App\Constants\CustomerPointTime;
use App\Helpers\RandomHelper;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

use GuzzleHttp\Client as Guzzle;
use App\Http\Requests\Package\PackageModifiesRequest;
use Illuminate\Support\Facades\Redis;

class OrderService extends BaseService
{
    protected string $_resource = OrderListResource::class;
    protected string $_orderCustomerPaginateResource = OrderCustomerPaginateResource::class;
    protected string $_orderCustomerResource = OrderCustomerResource::class;

    /**
     * Danh sách đơn hàng
     *
     * @param int $perPage
     * @return JsonResponse
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();
        $status = request()->input('status');
        $is_order_slow_quote = request()->input('is_order_slow_quote');
        $perService = app()->make(PerService::class);
        $query = $this->_model->newQuery()->select([
            'orders.id', 'orders.code', 'orders.code_parent', 'orders.created_at', 'orders.status', 'orders.deposit_cost',
            'orders.order_percent', 'orders.is_waiting_for_sale', 'orders.deposit_percent', 'orders.packages_number',
            'orders.exchange_rate', 'orders.ecommerce', 'orders.china_shipping_cost_real', 'orders.customer_id',
            'orders.staff_order_id', 'orders.staff_quotation_id', 'orders.staff_group_id', 'orders.is_order_now',
            'orders.discount_percent', 'orders.inspection_cost', 'orders.order_cost', 'orders.deposit_cost', 'orders.thuongdo_id',
            'orders.thuongdo_tong_tien_tam_ung', 'orders.thuongdo_tong_tien_don_hang', 'orders.delivery_pay_debt_cost',
            'orders.delivery_pay_shipping_cost', 'orders.organization_id', 'orders.order_provisional_date', 'orders.has_edit',
            'orders.china_shipping_cost', 'order_status_times.status_0', 'order_status_times.status_1', 'order_status_times.status_2',
            'order_status_times.status_3', 'order_status_times.status_4', 'order_status_times.status_5', 'order_status_times.status_6',
            'order_status_times.status_8', 'orders.is_sent_notification', 'orders.official', 'orders.type_official', 'orders.official_is_partner',
            'orders.is_inspection', 'orders.reason_confirm_order_id', 'orders.is_up_order_cost', 'orders.is_advanced_inspection', 'orders.advanced_inspection_cost',
            'orders.supplier_note_count', 'orders.is_waiting_confirm'
        ]);
        if ($is_order_slow_quote) {
            $query->where('status', OrderConstant::KEY_STATUS_WAITING_QUOTE)
                ->where('is_sent_notification', OrderConstant::IS_SENT_NOTIFICATION_TRUE);
        }
        $query = $query->join('order_status_times', 'order_status_times.order_id', '=', 'orders.id');
        $query->with([
            'customer' => function($q){
                return $q->select('id', 'name', 'phone_number', 'warehouse_id', 'source');
            },
            'customer.warehouse.province',
            'customer.offer',
            'customer.reportCustomerOrders',
            'customer.customerNotes',
            'orderStatusTime',
            'orderSuppliers'=> function($q){
                return $q->select('id', 'order_id','woodworking_cost', 'china_shipping_cost', 'order_fee', 'order_cost',
                    'inspection_cost', 'advanced_inspection_cost','discount_cost', 'international_shipping_cost', 'shock_proof_cost');
            },
            'details'=> function($q){
                return $q->whereNotNull('link');
            },
            'orderContracts' => function($q){
                return $q->select('id', 'order_id');
            },
            'packages' => function($q){
                return $q->select('id', 'order_id', 'discount_cost')->where('order_type', Order::class);
            },
            'notePublic',
            'orderProvisionalDate' => function($q){
                return $q->with(['supplier' => function ($supplier) {
                    return $supplier->select('id', 'name');
                }]);
            },
            'reasonConfirmOrder',
            'staffOrder' => function($q){
                return $q->select('id', 'name');
            },
            'staffQuotation' => function($q){
                return $q->select('id', 'name');
            },
            'staffGroup' => function($q){
                return $q->select('id', 'name');
            },
        ]);

        $isSearch = request()->input('isSearchOrder');

        if(!$isSearch || $isSearch == 'false') {
            $query = $perService->getConditionByUserLogin($query);
        }
        if (!empty(request()->query('ecommerces'))) {
            if (request()->query('ecommerces') == 'empty') {
                $query->whereNull('orders.ecommerce');
            } else {
                $query->where('orders.ecommerce', request()->query('ecommerces'));
            }
        }
        if ($status == 'status_1' && request()->query('time')) {
            $dateRange = $this->dateRange(request()->input('time'));
            $query = $query->withoutGlobalScopes()
                ->whereHas('orderStatusTime', function ($q) use ($dateRange) {
                    $q->whereBetween('order_status_times.status_1', [$dateRange['startDate'], $dateRange['endDate']]);
                });
        }
        if ($status == 'exeption_done') {
            $query = $query->withoutGlobalScopes()
                ->whereNotIn('orders.status', [OrderConstant::KEY_STATUS_DONE, OrderConstant::KEY_STATUS_CANCEL]);
        }
        if (request()->query('time') && $status != 'status_1') {
            $dateRange = $this->dateRange(request()->input('time'));
            if ($status == 'outdate_not_done') {
                $startDate = date('Y-m-d H:i:s', strtotime('-7 day', strtotime($dateRange['startDate'])));
                $endDate = date('Y-m-d H:i:s', strtotime('-7 day', strtotime($dateRange['endDate'])));
                $query->whereBetween('orders.created_at', [$startDate, $endDate]);
            } else if ($status == '') {
                $query->whereBetween('orders.created_at', [$dateRange['startDate'], $dateRange['endDate']]);
            } else {
                $query->whereBetween('orders.updated_at', [$dateRange['startDate'], $dateRange['endDate']]);
            }
        }

        $params_status = request()->query('status');
        if($params_status) {
            if(request()->query('created_at_sort') == 'asc') {
                $query = $query->orderBy("order_status_times.$params_status", 'asc');
            } else {
                $query = $query->orderBy("order_status_times.$params_status", 'desc');
            }

        }
        $params_status_arr = array(
            'status_4' => 'orders.date_done',
            'status_3' => 'orders.date_ordered'
        );
        if (!empty(request()->query('label_id'))) {
            $labelIds = is_array(request()->query('label_id')) ? request()->query('label_id') : [request()->query('label_id')];
            $query->whereIn('orders.customer_id', function($q) use ($labelIds) {
                $q->select('customer_label.customer_id')
                ->from('customer_label')
                ->whereIn('customer_label.label_id', $labelIds);
            });
        }
        if (!empty(request()->query('reason_confirm'))) {
            $query->where('status', OrderConstant::KEY_STATUS_RECONFIRM)
                ->where('reason_confirm_order_id', request()->query('reason_confirm'));
        }
        if (request()->input('is_up_order_cost') == 1) {
            $query->where("is_up_order_cost", 1)
                    ->whereIn('status', [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY, OrderConstant::KEY_STATUS_ORDERED]);
        }
        if (!is_null(request()->input('source'))) {
            $query->whereHas('customer', function($q){
                $q->where("source", request()->input('source'));
            });
        }
        if(isset($params_status_arr[$params_status])) {
            $data = $query->orderBy($params_status_arr[$params_status], 'desc')->paginate($perPage);
        } else {
            $data = $query->paginate($perPage);
        }

        // $data = $query->paginate($perPage);
        if ($status == 'outdate_not_done') {
            $orderOutDate = $query->whereNotIn(
                'orders.status',
                [OrderConstant::KEY_STATUS_DONE, OrderConstant::KEY_STATUS_CANCEL]
            )->withoutGlobalScopes()->orderBy('orders.id', 'desc')->get();
            $data = $this->ArrToCollection($orderOutDate, $perPage);
        }
        /*list($amount, $amountCny) = $this->getTotalAmount($query);*/
        $extra = [
            'amount' => 0,
            'amount_cny' => 0
        ];

        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource, $extra));
    }

    private function getOrderSlowQuote(): array
    {
        $key = Ecommerce::CACHE_KEY_ECOMMERCE;
        $lists = Cache::get($key, []);
        if (empty($lists)) {
            $lists = Ecommerce::query()->get()->toArray();
            Cache::put($key, $lists);
        }
        $orderQuery = Order::query()->withoutGlobalScopes();
        $orderQuery = $orderQuery->select(
            'id', 'status', 'code',
            'is_sent_notification',
            'staff_quotation_id', 'staff_group_id', 'created_at', 'ecommerce'
        );
        $orderQuery = $orderQuery->whereHas('orderStatusTime');
        $orderQuery = $orderQuery->where('status', OrderConstant::KEY_STATUS_WAITING_QUOTE);
        $orderQuery = $orderQuery->get();
        $orderIds = [];
        foreach ($orderQuery as $order) {
            $orderSlowQuote = checkTimeOrderSlowQuote($order, $lists);
            if ($orderSlowQuote) {
                $orderIds[] = $order->id;
            }
        }
        return $orderIds;
    }

    public function totalAmountPayable(): array
    {
        $orders = $this->_model->query()
            ->select(
                DB::raw('SUM(order_cost) as total_order_cost_vn, SUM(order_cost / exchange_rate) as total_order_cost_cny')
            )
            ->whereIn('status', [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY])
            ->first();
        return [
            'total_order_cost_vn' => $orders->total_order_cost_vn ?? 0,
            'total_order_cost_cny' => $orders->total_order_cost_cny ?? 0
        ];
    }

    public function relatedOrder($params)
    {
        if (!empty($params['customer_id'])) {
            $query = $this->_model->query();
            $query->select(
                'id', 'code',
                'order_cost', 'exchange_rate', 'delivery_cost',
                'inspection_cost', 'discount_cost', 'order_fee',
                'china_shipping_cost', 'deposit_cost', 'status', 'note', 'created_at'
            );
            $query->with(['orderSuppliers' => function ($q) {
                $q->select('id', 'order_id','woodworking_cost', 'china_shipping_cost', 'order_fee', 'order_cost',
                    'inspection_cost', 'discount_cost', 'international_shipping_cost');
            }]);
            $query->with(['packages' => function ($q) {
                $q->select('id', 'order_id', 'discount_cost')->where('order_type', Order::class);
            }]);
            $query->with(['notePublic' => function ($notePublic) {
                $notePublic->select('id', 'order_id', 'user_id', 'content', 'created_at')
                    ->with(['staff' => function ($staff) {
                        $staff->select('id', 'name');
                }]);
            }]);
            if (!empty($params['not_order_id'])) {
                $query->where('id', '!=', $params['not_order_id']);
            }
            $query->where('customer_id', $params['customer_id']);
            return new $this->_paginateResource(
                $query->paginate($params['per_page'] ?? 15),
                OrderRelatedListResource::class
            );
        }
        return false;
    }

    public function getSaleStaff($params){
        $query = $this->_model->query();
        if (!empty($params['order_code'])) {
            $query = $query
                ->select('id', 'staff_group_id', 'code')
                ->where('code', $params['order_code'])
                ->with(['staffGroup' => function ($staffGroup) {
                    $staffGroup->select('id', 'name');
                }]);
            $item = $query->first();
            if ($item) {
                return $item;
            }
        }
        return false;
    }

    /**
     * Lấy tổng tiền đơn hằng bằng tệ và vnđ
     *
     * @return JsonResponse
     */
    public function getListAmount(): JsonResponse
    {
        $action = app()->make(ListAmountAction::class);
        return $action->handle([]);
    }

    public function detail(string $id): JsonResponse
    {
        $order = $this->_model->newQuery()
            ->with([
                'customer', 'packages', 'complains', 'deliveriesPivot',
                'orderContracts', 'warehouse', 'orderSuppliers', 'orderRefunds',
                'customer.offer', 'customerDelivery', 'customer.delivery',
                'complains.orderDetails', 'complains.complainDetails', 'complains.complainType',
                'deliveriesPivot.transporter', 'deliveriesPivot.packages',
                'staffGroup',
                'orderDepositPercent' => function ($q) {
                    $q->where('status', 0);
                }
            ])->findOrFail($id);
        return resSuccessWithinData(new OrderResource($order));
    }

    public function getTotalAmount($query)
    {
        $amount = $query->where('status', OrderConstant::KEY_STATUS_DEPOSITED)
            ->sum('deposit_cost');
        $amount = AccountingHelper::getCosts($amount);
        $amountCny = AccountingHelper::getCosts($amount / (new ConfigService())->getExchangeRate());

        return [$amount, $amountCny];
    }

    public function getDataExportList($params, $auth)
    {
        $query = $this->_model->newQuery()->select([
            'orders.id',
            'orders.code',
            'orders.date_quotation',
            'orders.date_purchased',
            'orders.date_ordered',
            'orders.date_done',
            'orders.customer_id',
            'orders.staff_quotation_id',
            'orders.staff_order_id',
            'orders.staff_group_id',
            'orders.order_fee',
            'orders.status',
            'orders.deposit_cost',
            'orders.thuongdo_tong_tien_don_hang',
            'orders.thuongdo_tong_tien_tam_ung',
            'orders.delivery_pay_debt_cost',
            'orders.delivery_pay_shipping_cost',
            'orders.thuongdo_id',
            'orders.exchange_rate',
            'orders.order_cost_real',
            'orders.china_shipping_cost_real',
            'orders.order_cost',
            'orders.china_shipping_cost',
            'orders.bonus',
            'orders.created_at',
            'orders.official',
            'orders.type_official',
            'orders.official_is_partner',
            'orders.official_order_cost',
            'orders.official_service_fee',
        ])->with([
            'customer' => function ($query) {
                $query->select('id', 'name', 'code', 'phone_number', 'level', 'business_type');
            },
            'staffQuotation' => function ($query) {
                $query->select('id', 'name');
            },
            'staffOrder' => function ($query) {
                $query->select('id', 'name');
            },
            'staffGroup' => function ($query) {
                $query->select('id', 'name');
            },
            'orderSuppliers' => function ($query) {
                $query->select('id', 'order_id', 'supplier_id','order_fee', 'order_cost', 'discount_cost', 'china_shipping_cost', 'inspection_cost', 'woodworking_cost', 'international_shipping_cost');
            },
            'packages' => function ($query) {
                $query->select('id', 'order_id', 'order_type', 'discount_cost');
            }
        ]);
        if(!empty($params['customer_name']) ||
            !empty($params['customer_code']) ||
            !empty($params['customer_phone']) ||
            !empty($params['customer_via']) ||
            !empty($params['customer_business_type']) ||
            !empty($params['customer_level']) ||
            !empty($params['customer_created_at'])) {
            $query->join('customers', 'customers.id', '=', 'orders.customer_id');
            if(!empty($params['customer_name'])) {
                $query->where('customers.name', $params['customer_name']);
            }
            if(!empty($params['customer_code'])) {
                $query->where('customers.code', $params['customer_code']);
            }
            if(!empty($params['customer_phone'])) {
                $query->where('customers.phone_number', $params['customer_phone']);
            }
            if(!empty($params['customer_level'])) {
                $query->where('customers.level', $params['customer_level']);
            }
            if(!empty($params['customer_via'])) {
                $query->where('customers.via', $params['customer_via']);
            }
            if(!empty($params['customer_business_type'])) {
                $query->where('customers.business_type', $params['customer_business_type']);
            }
            if(!empty($params['customer_created_at'])) {
                $dates = (new FilterService())->rangeDateFilterV2($params['customer_created_at']);
                $start = Carbon::parse($dates[0])->startOfDay();
                $end = Carbon::parse($dates[1])->endOfDay();
                $query->whereBetween('customers.created_at', [$start, $end]);
            }

        }
        if(!empty($params['chanel_id'])) {
            $query->join('order_supplier', 'order_supplier.order_id', '=', 'orders.id')
                ->where('order_supplier.source_type', $params['chanel_id']);
        }
        if(!empty($params['code'])) {
            $query->where('orders.code', $params['code']);
        }
        if(!empty($params['code_po'])) {
            $query->join('order_package', 'order_package.order_id', '=', 'orders.id')
                ->where('order_package.order_type', Order::class)
                ->where('order_package.code_po', $params['code_po']);
        }
        if(!empty($params['staff_order_id'])) {
            $staffOrderIds = is_array($params['staff_order_id']) ? $params['staff_order_id'] : [$params['staff_order_id']];
            $query->whereIn('orders.staff_order_id', $staffOrderIds);
        }
        if(!empty($params['staff_quotation_id'])) {
            $staffQuotationIds = is_array($params['staff_quotation_id']) ? $params['staff_quotation_id'] : [$params['staff_quotation_id']];
            $query->whereIn('orders.staff_quotation_id', $staffQuotationIds);
        }
        if(!empty($params['staff_group_id'])) {
            $staffGroupIds = is_array($params['staff_group_id']) ? $params['staff_group_id'] : [$params['staff_group_id']];
            $query->whereIn('orders.staff_group_id', $staffGroupIds);
        }
        if(!empty($params['is_website'])) {
            $query->where('orders.is_website', $params['is_website']);
        }
        if(!empty($params['warehouse_id'])) {
            $query->where('orders.warehouse_id', $params['warehouse_id']);
        }
        if (!empty($params['ecommerces'])) {
            if (request()->query('ecommerces') == 'empty') {
                $query->whereNull('ecommerce');
            } else {
                $query->where('ecommerce', empty($params['ecommerces']));
            }
        }
        if(!empty($params['status'])) {
            $query->where('orders.status', $params['status']);
        }
        if(!empty($params['created_at'])) {
            $dates = (new FilterService())->rangeDateFilterV2($params['created_at']);
            $start = Carbon::parse($dates[0])->startOfDay();
            $end = Carbon::parse($dates[1])->endOfDay();
            $query->whereBetween('orders.created_at', [$start, $end]);
        }
        if(!empty($params['date_quotation'])) {
            $dates = (new FilterService())->rangeDateFilterV2($params['date_quotation']);
            $start = Carbon::parse($dates[0])->startOfDay();
            $end = Carbon::parse($dates[1])->endOfDay();
            $query->join('order_status_times', 'order_status_times.order_id', 'orders.id')
                        ->whereBetween('order_status_times.status_1', [$start, $end]);
            // $query->whereBetween('orders.date_quotation', [$start, $end]);
        }
        if(!empty($params['date_ordered'])) {
            $dates = (new FilterService())->rangeDateFilterV2($params['date_ordered']);
            $start = Carbon::parse($dates[0])->startOfDay();
            $end = Carbon::parse($dates[1])->endOfDay();
            $query->join('order_status_times', 'order_status_times.order_id', 'orders.id')
                    ->whereBetween('order_status_times.status_3', [$start, $end]);
            // logic cũ
            // $query->whereBetween('orders.date_ordered', [$start, $end]);
        }
        if(!empty($params['date_purchased'])) {
            $dates = (new FilterService())->rangeDateFilterV2($params['date_purchased']);
            $start = Carbon::parse($dates[0])->startOfDay();
            $end = Carbon::parse($dates[1])->endOfDay();
            $query->join('order_status_times', 'order_status_times.order_id', 'orders.id')
                        ->whereBetween('order_status_times.status_2', [$start, $end]);
            // $query->whereBetween('orders.date_purchased', [$start, $end]);
        }
        if(!empty($params['label_id'])) {
            $labelIds = is_array($params['label_id']) ? $params['label_id'] : [$params['label_id']];
            $query->whereIn('orders.customer_id', function($q) use ($labelIds) {
                $q->select('customer_label.customer_id')
                ->from('customer_label')
                ->whereIn('customer_label.label_id', $labelIds);
            });
        }
        if(!empty($params['date_done'])) {
            $dates = (new FilterService())->rangeDateFilterV2($params['date_done']);
            $start = Carbon::parse($dates[0])->startOfDay();
            $end = Carbon::parse($dates[1])->endOfDay();
            $query->whereBetween('orders.date_done', [$start, $end]);
        }
        if(!empty($params['type_time'])) {
            if($params['type_time'] == OrderConstant::ORDER_IN_DAY) {
                $query->whereDate('orders.created_at', date(TimeConstant::DATE));
            }
            if($params['type_time'] == OrderConstant::ORDER_IN_WEEK) {
                $query->whereBetween(
                    'orders.created_at',
                    [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]
                );
            }
            if($params['type_time'] == OrderConstant::ORDER_IN_MONTH) {
                $query->whereMonth('orders.created_at', date('m'))
                        ->whereYear('orders.created_at', date('Y'));
            }
        }
        if (!empty($params['official'])) {
            if($params['official'] == 1) {
                $query->where('official', OrderConstant::OFFICIAL_SALE)->where('official_is_partner', 0);
            }else {
                if($params['official'] == 21) {
                    $query->where('official', OrderConstant::OFFICIAL)->where('type_official', OrderConstant::TYPE_OFFICIAL_1)->where('official_is_partner', 0);
                }elseif($params['official'] == 22) {
                    $query->where('official', OrderConstant::OFFICIAL)->where('type_official', OrderConstant::TYPE_OFFICIAL_2)->where('official_is_partner', 0);
                }else {
                    $query->where('official_is_partner', 1);
                }
            }
        }
        if (!empty($params['reason_confirm'])) {
            $query->where('status', OrderConstant::KEY_STATUS_RECONFIRM)
                    ->where('reason_confirm_order_id', $params['reason_confirm']);
        }
        if (!empty($params['source'])) {
            $query->whereHas('customer', function($q){
                $q->where("source", $params['source']);
            });
        }

        // $timeStart = Carbon::now()->subMonths(1)->startOfMonth();
        // // Chỉ cho xuất từ 2 tháng đổ lại
        if(empty($params['customer_phone'])) {
            // Nếu không truyền SDT khách thì sẽ chặn khoảng thời xuất
            $query->where('orders.created_at', '>=', '2024-01-01 00:00:00');
        }

        $perService = \App::make('App\Services\PerService');
        $query = $perService->getConditionByUserLogin($query, Order::query(), $auth);
        return $query;
    }

    public function export(int $perPage)
    {
        $this->throwModel();
        $perService = \App::make('App\Services\PerService');

        $query = $this->_model->newQuery()->with(['customer', 'staffOrder', 'staffQuotation', 'staffCare', 'staffCounselor',
            'staffGroup']);
        $query = $perService->getConditionByUserLogin($query);
        if (!empty(request()->query('ecommerces'))) {
            if (request()->query('ecommerces') == 'empty') {
                $query->whereNull('ecommerce');
            } else {
                $query->where('ecommerce', request()->query('ecommerces'));
            }
        }
        return $query->get();
    }

    public function exportV2(int $perPage)
    {
        //DB::enableQueryLog();
        $this->throwModel();
        $perService = \App::make('App\Services\PerService');

        $query = $this->_model->newQuery()->with([
            'customer' => function ($customer) {
                return $customer->select('id', 'name', 'phone_number');
            },
            'staffOrder' => function ($staffOrder) {
                return $staffOrder->select('id', 'name');
            },
            'staffQuotation' => function ($staffQuotation) {
                return $staffQuotation->select('id', 'name');
            },
            //'staffCare',
            //'staffCounselor',
            'staffGroup' => function ($staffGroup) {
                return $staffGroup->select('id', 'name');
            }
        ]);

        $query->select('id', 'code', 'created_at', 'date_quotation', 'date_purchased', 'date_ordered',
            'date_done', 'order_fee', 'deposit_cost', DB::raw('((order_cost + order_fee +
            inspection_cost + (china_shipping_cost * exchange_rate)) - discount_cost) as totalAmount'));

        //$query = $perService->getConditionByUserLogin($query);
        if (!empty(request()->query('ecommerces'))) {
            if (request()->query('ecommerces') == 'empty') {
                $query->whereNull('ecommerce');
            } else {
                $query->where('ecommerce', request()->query('ecommerces'));
            }
        }

        $startDate = Carbon::today()->subMonths(6)->startOfMonth()->toDateString();
        $endDate   = Carbon::today()->endOfMonth()->toDateString();
        $query->whereDate('created_at', '>=', $startDate);
        $query->whereDate('created_at', '<=', $endDate);

        return $query->get();
    }

    public function getRawDataExport($data)
    {
        $result = [];
        foreach ($data as $key => $item) {
            $depositAmount = $item->deposit_cost;
            $purchaseAmount = AccountingHelper::getCosts($item->total_amount - $depositAmount);
            $purchase = $purchaseAmount > 0 ? -1 * $purchaseAmount : 0;
            $customer = $item->customer;

            $quotation = new OnlyIdNameResource($item->staffQuotation);
            $order = new OnlyIdNameResource($item->staffOrder);
            $care = new OnlyIdNameResource($item->staffCare);
            $counselor = new OnlyIdNameResource($item->staffCounselor);
            $newItem = [
                $key + 1, // STT
                $item->code, // Mã đơn hàng
                $item->total_amount,
                $depositAmount ?? 0, // Tạm ứng
                $purchase, // Thanh toán
                $item->exchange_rate, // Tỉ giá
                $item->packages_number, // Số kiện,
                !empty(OrderConstant::STATUSES[$item->status]) ? OrderConstant::STATUSES[$item->status] : '', // Trạng thái
                optional($customer)->name ?? "", // Khách hàng
                optional($quotation)->name ?? "", // Nhân viên báo giá
                optional($order)->name ?? "", // Nhân viên đặt hàng
                optional($care)->name ?? "", // Nhân viên chăm sóc
                optional($counselor)->name ?? "", // Nhân viên tư vấn
            ];
            $result[] = $newItem;
        }
        return $result;
    }

    /**
     * Tính khoản tạm ứng, tiền chi phi vận chuyển
     * @param string $orderId
     * @param string $key
     * @param float $value
     * @param string|null $customerId
     */
    public function reportOrderToVN(string $orderId, string $key, float $value, ?string $customerId = null): void
    {
        $customer = $customerId ?? Auth::user()->id;
        $report = ReportCustomer::query()->where(['customer_id' => $customer, 'order_id', $orderId])->first();
        $report->{$key} += $value;
        $report->save();
    }

    /**
     * @param string $orderId
     * @param string $status
     */
    public function setStatusTime(string $orderId, string $status)
    {
        $order = OrderStatusTime::query()->firstOrCreate(['order_id' => $orderId]);
        $order->{$status} = now();
        $order->save();
    }

    /**
     * @param string $id
     */
    public function deleteByOrderId(string $id): void
    {
        foreach (OrderPackage::query()->where('order_id', $id)->cursor() as $package) {
            $package->delete();
        }
        OrderDetail::query()->where('order_id', $id)->delete();
        OrderSupplier::query()->where('order_id', $id)->delete();
    }

    /**
     * @param string $id
     * @param array $data
     * @return JsonResponse
     * @throws Exception
     */
    public function update(string $id, array $data)
    {
        DB::beginTransaction();
        try {
            $this->updateOrderRemoves($id, $data['removes'] ?? []);
            $isUserQuote = $this->checkUserQuote();
            $order = Order::query()->with('details')->find($id);
            $auth = getCurrentUser();
            $canBypassStatus = $this->canEditQuantityUnitPrice($auth, $order);
            // Nhân viên báo giá + nhân viên kinh doanh chỉ được sửa đơn ở trạng thái chờ báo giá + cần xác nhận lại
            if(!$canBypassStatus && ($isUserQuote || isBusiness())
                && !in_array($order->status, [Order::ORDER_STATUS_WAITING_FOR_QUOTE, Order::ORDER_STATUS_NEED_TO_CONFIRM]))
            {
                return resError('Bạn không thể chỉnh sửa đơn ở trạng thái này');
            }
            if (!$isUserQuote && !isAdmin() && $order->is_quoting) {
                return resError('Bạn không thể chỉnh sửa đơn vì đang trong quá trình báo giá');
            }
            if($order->version_edit != request()->get('version_edit')) {
                return resError('Đơn đã được thay đổi, vui lòng tải lại trang để cập nhật thông tin đơn');
            }
            $totalQuantityOld = $order->details()->sum('quantity');
            $unitInspectionCost = 0;
            if($totalQuantityOld > 0) {
                $unitInspectionCost = $order->inspection_cost / $totalQuantityOld;
            }
            $isDraft = data_get($data, 'is_draft') && data_get($data, 'is_draft') == true ? true : false;
            if (in_array($order->status, [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY])) {
                if ($isDraft) {
                    $order->has_edit = 2;
                } else {
                    $order->has_edit = 1;
                }
            }
            // trường hợp sửa đơn ở trạng thái chờ đặt hàng hoặc đặt hàng
            if(
                in_array($order->status,
                [
                    Order::ORDER_STATUS_ORDER,
                    Order::ORDER_STATUS_WAITING_FOR_PAYMENT,
                    Order::ORDER_STATUS_DEPOSITED
                ]
                ) && $isDraft == false
            ) {
                $this->handleRefundMoney($order, $order->deposit_cost - $order->getTotalAmountNotShip() * $order->deposit_percent / 100);
            }

            if ($order->status == OrderConstant::KEY_STATUS_WAITING_QUOTE && !isset($data['is_change_status'])) {
                // Đơn đang ở chờ báo giá Nếu là nhân viên báo giá thì update status thành chờ đặt cọc
                if ($isUserQuote) {
                    $data['status'] = OrderConstant::KEY_STATUS_WAITING_DEPOSIT;
                    // Thêm noti
                    $order->status = OrderConstant::KEY_STATUS_WAITING_DEPOSIT;
                    $this->updateOrderNotiLogic($order);
                }
            }
            if ($order->status == OrderConstant::KEY_STATUS_WAITING_DEPOSIT && !isset($data['is_change_status'])) {
                // Đơn đang ở chờ đặt cọc Nếu không phải là nhân viên báo giá thì update status thành chờ báo giá
                if (!$isUserQuote) {
                    $data['status'] = OrderConstant::KEY_STATUS_WAITING_QUOTE;
                    // Thêm noti
                    $order->status = OrderConstant::KEY_STATUS_WAITING_QUOTE;
                    $this->updateOrderNotiLogic($order);
                }
            }
            // Cập nhật lịch sử tiền phí ship, tiền hàng khi ở trạng thái chờ báo giá -> đặt cọc
            $this->updateSupplierOrderDetails($order);
            if (isset($data['status'])) {
                $order->status = $data['status'];
                $order->save();
                $_POST['status_in_order_service'] = $data['status'];
            }
            // Nếu là đơn tách + không thay đổi giá thì không update gì cả.
            // Chỉ thực hiện hoàn nếu rơi vào case giảm phí vc TQ
            if(!in_array($order->status,
                    [
                        Order::ORDER_STATUS_WAITING_FOR_QUOTE, Order::ORDER_STATUS_WAITING_FOR_DEPOSIT,
                        Order::ORDER_STATUS_NEED_TO_CONFIRM
                    ]
                )
                && $this->checkSplitOrder($order)
            ) {
                $totalQuantity = 0;
                $totalPriceCny = 0;
                foreach($data['products'] as $product) {
                    if(isset($product['modifies']['quantity'])) {
                        $totalQuantity += $product['modifies']['quantity'];
                        $totalPriceCny += $product['modifies']['quantity'] * $product['modifies']['unit_price_cny'];
                    }
                }
                if(
                    $totalQuantity > 0 && $totalQuantity == $totalQuantityOld
                    && $totalPriceCny * $order->exchange_rate == $order->order_cost
                ) {
                    DB::commit();
                    return true;
                }
            }

            $this->updateOrderModifies($id, $data); // Lôi từ trên xuống
            $this->updateOrderSupplier($order); // Lôi từ trên xuống
            $this->updateOrderCost($id); // Lôi tiếp xuống dưới này

            // Gán đơn cho nhân viên đặt hàng
            // $this->assignStaffOrder($order); // Tạm bỏ logic này ở admin
            if (!empty($data['warehouse_id']) && $data['warehouse_id'] != $order->warehouse_id) {
                $paramsUpdate['warehouse_id'] = $data['warehouse_id'];
                $this->updateOrderPackage($order->id, $paramsUpdate);
            }
            $data = parent::update($id, $data);
            $this->updateServicePackageByOrder($order);
            if($order->is_inspection) {
                $this->updateInspectionCost($order, $unitInspectionCost);
            }
            // Xử lý check lại supplier có chính ngạch hay không thì update lại đơn
            $supplierOfficial = OrderSupplier::query()
                ->where('order_id', $order->id)
                ->where('official', OrderConstant::OFFICIAL)
                ->first();
            if($supplierOfficial) {
                $order->update(['official' => OrderConstant::OFFICIAL, 'type_official' => $supplierOfficial->type_official]);
            } else {
                $order->update(['official' => OrderConstant::OFFICIAL_SALE]);
            }
            // Check kiểm tra nếu trong đơn có sản phẩm dc thêm thủ công thì cập nhật thành đơn tạo thủ công luôn
            $countOrderDetailManual = OrderDetail::where('order_id', $order->id)->where('type_order', OrderConstant::TYPE_ORDER_MANUAL)->count();
            if($countOrderDetailManual) {
                OrderDetail::where('order_id', $order->id)->update(['type_order' => OrderConstant::TYPE_ORDER_MANUAL]);
                $order->update(['type_order' => OrderConstant::TYPE_ORDER_MANUAL]);
            }

            DB::commit();
            return $data;
        } catch(Exception $e) {
            Log::info($e->getMessage());
            DB::rollBack();
            return resError($e->getMessage());
        }
    }


    private function canEditQuantityUnitPrice($auth, $order): bool
    {
        if (
            $order->status == Order::ORDER_STATUS_ORDER &&
            $auth->hasPermissionName('order.edit_quantity_unit_price_status_3')
        ) {
            return true;
        }

        if (
            in_array($order->status, [
                Order::ORDER_STATUS_WAITING_FOR_PAYMENT,
                Order::ORDER_STATUS_DEPOSITED,
            ]) &&
            $auth->hasPermissionName('order.edit_quantity_unit_price_status_8')
        ) {
            return true;
        }

        return false;
    }

    public function handleRefundMoney($order, $totalPriceRefund)
    {
        if($totalPriceRefund < 100) {
            return true;
        }
        $totalPriceRefund = round($totalPriceRefund);
        $order->deposit_cost = $order->deposit_cost - $totalPriceRefund;
        // Cập nhật lại tiền cọc
        $order->save();
        // Tạo giao dịch + tiền vào ví khách
        $msg = trans(
                'transaction.order_purchase_final',
                [
                    'order_code' => $order->code,
                    'amount' => ConvertHelper::numericToVND($totalPriceRefund),
                ]
            );
        (new TransactionService())->setTransaction(
            $totalPriceRefund,
            // $order->deposit_cost - $depositcostAfter,
            TransactionConstant::STATUS_REFUND,
            $msg,
            true,
            $order->customer_id,
            $order,
            true,
            $order,
        );
        $dataNotificationCustomer = [
            'order_code' =>$order->code,
            'amount' => ConvertHelper::numericToVND($totalPriceRefund)
        ];
        (new NotificationService())->sendNotificationCustomer(8, 8014, $order->customer_id, $dataNotificationCustomer);
    }

    public function updateOrderPackage($order_id, $params){
        OrderPackage::query()->where('order_id', $order_id)
            ->where('order_type', Order::class)
            ->update(['warehouse_id' => $params['warehouse_id']]);
    }

    private function createOrderHistory($old_value, $new_value, $order_id){
        $data = [
            'old_value' => $old_value,
            'new_value' => $new_value,
            'order_id' => $order_id,
            'history_type' => OrderHistory::ORDER_HISTORY_NOTE_TYPE,
            'user_id' => getCurrentUser()->id ?? 0,
            'date' => date('Y-m-d H:i:s'),
            'content' => 'đã thay đổi Ghi chú nội bộ từ [ ' .  $old_value . ' ] thành [ ' .  $new_value . ' ] '
        ];
        OrderHistory::query()->create($data);
    }

    // check là nhân viên báo giá hay không ?
    private function checkUserQuote()
    {
        $staffService = \App::make('App\Services\StaffService');
//        $check = false;
//        $userQuotes = $staffService->getStaffHasPermission(RoleConstant::PERMISSION_QUOTE_CUSTOMER)->pluck('id')->toArray();
        $userLogin = Auth::user();
//
//        if (in_array($userLogin->id, $userQuotes)) {
//            $check = true;
//        }
//
//        return $check;
        return $staffService->checkStaffByDepartmentTypes($userLogin->id, [DepartmentConstant::TYPE_QUOTE]);
    }

    public function updateOrderNotiLogic($order)
    {
        // Chuyển trạng thái -> Chờ báo giá    Báo cho nhân viên báo giá status_0 .. Trường hợp này cho vào case 2006.
        // Chuyển trạng thái -> Chờ đặt cọc    Báo cho nhân viên tư vấn/chăm sóc status_1
        // Chuyển trạng thái -> Đã hủy     Báo cho nhân viên tư vấn/chăm sóc status_6
        // Chuyển trạng thái -> Đã đặt cọc     Báo cho nhân viên Đặt hàng , nhân viên tư vấn/chăm sóc status_2
        // Chuyển trạng thái -> các trạng thái còn lại     Báo cho nhân viên Đặt hàng , nhân viên tư vấn/chăm sóc
        $status = $order->status;

        $staff_care_id = $order->staff_care_id;
        $staff_counselor_id = $order->staff_counselor_id;
        $staff_quotation_id = $order->staff_quotation_id;
        $staff_order_id = null;

        if ($status != 'status_1' && $status != 'status_6') {
            $staff_order_id = $order->staff_order_id;
        }

        $notificationService = \App::make('App\Services\NotificationService');

        $data_order = [
            'order_code' => $order->code,
            'order_id' => $order->id,
            'order_status' => $order->status,
            'order_status_txt' => \App\Constants\OrderConstant::STATUSES[$status],
        ];

        $name = 2006;
        if ($status == 'status_1') {
            $name = 2003;
        }
        if ($status == 'status_6') {
            $name = 2004;
        }
        if ($status == 'status_2') {
            $name = 2005;
        }
        $data = [
            'group' => 2,
            'name' => $name,
            'data' => json_encode($data_order),
            'time_create' => time(),
            'status' => 1
        ];

        if ($staff_care_id) {
            $notificationService->storeNoti($staff_care_id, $data);
        }
        if ($staff_counselor_id) {
            $notificationService->storeNoti($staff_counselor_id, $data);
        }
        if ($staff_order_id) {
            $notificationService->storeNoti($staff_order_id, $data);
        }
        if ($staff_quotation_id) {
            $notificationService->storeNoti($staff_quotation_id, $data);
        }
    }

    public function updateSupplierOrderDetails($order)
    {
        $arr_status = [
            // OrderConstant::KEY_STATUS_WAITING_QUOTE, // Chờ báo giá
            // OrderConstant::KEY_STATUS_WAITING_DEPOSIT, // Chờ đặt cọc
            OrderConstant::KEY_STATUS_DEPOSITED, //Đã đặt cọc
        ];
        if (in_array($order->status, $arr_status)) {
            $order->order_cost_old = $order->order_cost;
            $order->china_shipping_cost_old = $order->china_shipping_cost;
            $order->save();
        }
    }

    public function assignStaffOrder($order)
    {
        if ($order->status == OrderConstant::KEY_STATUS_DEPOSITED) {
            $this->getUserOrder($order);
        }
    }

    public function getUserOrder($order, $isChange = false)
    {
        // Logic mới
        $departmentRepo = \App::make('App\Models\Department');
        $userRepo = \App::make('App\Models\User');
        $organization_id = getOrganization();

        $departmentOrder = $departmentRepo->where('type', DepartmentConstant::TYPE_ORDER)->first();

        $users = $userRepo
            ->select('id', 'is_circle_order', 'reset_staff_allow')
            ->where('department_id', $departmentOrder->id)
            ->where('status', 1)
            ->where('reset_staff_allow', 1)
            ->where('organization_id', $organization_id)
            ->orderBy('id')
            ->get();

        $userIdCurrent = '';
        foreach($users as $user) {
            if($user->is_circle_order) {
                $userIdCurrent = $user->id;
                break;
            }
        }
        // Lấy user tiếp theo sẽ được gán
        $userOrder = '';
        if($userIdCurrent) {
            $userOrder = $users->filter(function($item) use ($userIdCurrent) {
                return $item->id > $userIdCurrent;
            })->first();
        }

        if(empty($userOrder)) {
            $userOrder = $users->first();
        }

        if ($userOrder) {
            if(!$order->staff_order_id || $order->staff_order_id == $userOrder->id || $isChange) {
                $userRepo->where('is_circle_order', 1)
                    ->where('department_id', $departmentOrder->id)
                    ->where('organization_id', $organization_id)
                    ->update(['is_circle_order' => 0]);
                $userRepo->where('id', $userOrder->id)->update(['is_circle_order' => 1]);
                $order->staff_order_id = $userOrder->id;
                $order->save();
            }
            return $userOrder->id;
        }
        return null;
    }

    public function getUserOrderStaff($order, $isChange = false)
    {
        // Logic mới
        $departmentRepo = \App::make('App\Models\Department');
        $userRepo = \App::make('App\Models\User');
        $organization_id = getOrganization();

        $departmentOrder = $departmentRepo->where('type', DepartmentConstant::TYPE_ORDER)->first();

        $users = $userRepo
            ->select('id', 'is_circle_order_assign_staff', 'reset_staff_allow')
            ->where('department_id', $departmentOrder->id)
            ->where('status', 1)
            ->where('reset_staff_allow', 1)
            ->where('organization_id', $organization_id)
            ->orderBy('id')
            ->get();

        $userIdCurrent = '';
        foreach($users as $user) {
            if($user->is_circle_order_assign_staff) {
                $userIdCurrent = $user->id;
                break;
            }
        }
        // Lấy user tiếp theo sẽ được gán
        $userOrder = '';
        if($userIdCurrent) {
            $userOrder = $users->filter(function($item) use ($userIdCurrent) {
                return $item->id > $userIdCurrent;
            })->first();
        }

        if(empty($userOrder)) {
            $userOrder = $users->first();
        }

        if ($userOrder) {
            if(!$order->staff_order_id || $order->staff_order_id == $userOrder->id || $isChange) {
                $userRepo->where('is_circle_order_assign_staff', 1)
                    ->where('department_id', $departmentOrder->id)
                    ->where('organization_id', $organization_id)
                    ->update(['is_circle_order_assign_staff' => 0]);
                $userRepo->where('id', $userOrder->id)->update(['is_circle_order_assign_staff' => 1]);
                //set Log
                $logService = app()->make(ActivityService::class);
                $content = 'Cập nhật nhân viên đặt hàng';
                $logService->setChangeStaffOrderLog($order, $content, $order->staff_order_id, $userOrder->id);
                $order->staff_order_id = $userOrder->id;
                $order->save();
            }
            return $userOrder->id;
        }
        return null;
    }

    public function getStaffRandom($permission)
    {
        $userRepo = \App::make('App\Models\User');
        $user = $userRepo->whereHas('permissions', function ($role) use ($permission) {
            $role->where('name', '=', $permission);
        })
            ->where('status', 1)
            ->inRandomOrder()
            ->first();
        if ($user) {
            return $user->id;
        }
        return null;
    }

    /**
     * @param string $orderId
     * @param array $removes
     */
    private function updateOrderRemoves(string $orderId, array $removes)
    {
        $this->prepareOrderRemoves($orderId, $removes, 'suppliers');
        $this->prepareOrderRemoves($orderId, $removes, 'products');
    }

    /**
     * @param array $data
     */
    private function updateOrderModifies(string $orderId, array $data): void
    {
        $this->prepareUpdateOrderModifies($orderId, OrderSupplier::class, $data, 'suppliers');
        $this->prepareUpdateOrderModifies($orderId, OrderDetail::class, $data, 'products');
    }

    /**
     * @param string $orderId
     * @param string $model
     * @param array $data
     * @param string $key
     */
    private function prepareUpdateOrderModifies(string $orderId, string $model, array $data, string $key): void
    {
        $idsLinkEdit = [];
        if (isset($data[$key]) && is_array($data[$key])) {
            foreach ($data[$key] as $item) {
                $query = (new $model)::query();
                $modifies = $item['modifies'];
                $supplier_id = null;
                if ($model == OrderSupplier::class) {
                    $condition = ['order_id' => $orderId, 'supplier_id' => $item['id']];
                    $supplier_id = $item['id'];
                    $detail = $query->where($condition)->first();
                    unset($modifies['is_tax']);
                    $orderDetails = OrderDetail::query()->where($condition)->pluck('id')->all();
                    $packageIds = OrderDetailPackage::query()->where('order_detail_id', $orderDetails)->pluck(
                        'order_package_id'
                    )->all();
                    OrderPackage::query()->whereIn('id', $packageIds)->where(
                        'status',
                        PackageConstant::STATUS_PENDING
                    )->get()->each(
                        function ($package) use ($modifies) {
                            $modifies['inspection_cost'] = (bool)$modifies['is_inspection'] ? $package->inspection_cost : 0;
                            $modifies['shock_proof_cost'] = (bool)$modifies['is_shock_proof'] ? $package->shock_proof_cost : 0;
                            $modifies['woodworking_cost'] = (bool)$modifies['is_woodworking'] ? $package->woodworking_cost : 0;
                            $package->update($modifies);
                        }
                    );
                    // $modifies['china_shipping_cost'] = $item['china_shipping_cost'];
                    // $modifies['china_shipping_cost_real'] = $item['china_shipping_cost_real'];
                    // $modifies['order_cost_real'] = $item['order_cost_real'];
                    if (isset($item['note']['private'])) {
                        $modifies['note_private'] = $item['note']['private'];
                    }
                    $content = 'order_supplier_update';
                    $object = optional(Supplier::query()->find($item['id']))->name;
                    // $this->unsetProperty($modifies, 'china_shipping_cost', $detail->china_shipping_cost);
                    $this->unsetProperty($modifies, 'is_inspection', $detail->is_inspection);
                    $this->unsetProperty($modifies, 'is_advanced_inspection', $detail->is_advanced_inspection);
                    $this->unsetProperty($modifies, 'is_shock_proof', $detail->is_shock_proof);
                    $this->unsetProperty($modifies, 'is_woodworking', $detail->is_woodworking);
                    $this->unsetProperty($modifies, 'delivery_type', $detail->delivery_type);
                }
                if ($model == OrderDetail::class) {
                    $link = $modifies['link'] ?? '';
                    if (isset($item['id'])) {
                        $detail = $query->find($item['id']);
                    } else if (isset($item['create'])) {
                        $condition = ['order_id' => $orderId];
                        $getDetail = $query->where($condition)->first();
                        $detail = (new OrderDetailService())->insertFromOrder($orderId, (array)$item['create'], $getDetail);
                        // Lưu log khi thêm sản phẩm
                        if(isset($item['create']['mp_sku_id'])) {
                            $content = 'order_products_adds_with_classification';

                            (new ActivityService())->setOrderLog(
                                Order::query()->find($orderId),
                                __(
                                    "activity.$content",
                                    [
                                        'name' => getCurrentUser()->name,
                                        'product' => $detail->name,
                                        'supplier' => optional($getDetail->supplier)->name,
                                        'classification' => $detail->classification
                                    ]
                                ),
                                $orderId
                            );
                        } else {
                            $content = 'order_products_adds';

                            (new ActivityService())->setOrderLog(
                                Order::query()->find($orderId),
                                __(
                                    "activity.$content",
                                    [
                                        'name' => getCurrentUser()->name,
                                        'product' => $detail->name,
                                        'supplier' => optional($getDetail->supplier)->name
                                    ]
                                ),
                                $orderId
                            );

                        }
                    }
                    if($detail->link != $link) {
                        $isEditLink = true;
                        array_push($idsLinkEdit, $detail->id);
                    }
                    $this->unsetProperty($modifies, 'quantity', $detail->quantity);
                    $this->unsetProperty($modifies, 'link', $detail->link);
                    $this->unsetProperty($modifies, 'unit_price_cny', $detail->unit_price_cny);
                    $content = 'order_products_update';
                    $object = optional($detail)->name;
                    if ($link) {
                        $source = getSourceFromProductLink($link);
                        if (in_array($source, ['1688', 'taobao'])) {
                            $detail->source = $source;
                            $detail->save();
                        }
                    }
                }
                (new ActivityService())->productActivity(
                    $modifies,
                    $orderId,
                    $object,
                    $content,
                    $detail,
                    null,
                    0,
                    $supplier_id);
                $detail->update($modifies);
                $this->storeNote($orderId, $item);
            }
        }
        if(count($idsLinkEdit) > 0) {
            $job = new \App\Jobs\Order\SyncMpidSkuIdOrderManualJob($idsLinkEdit);
            dispatch($job)->delay(now()->addSeconds(2))->onQueue('sync-data-report');
        }
    }

    private function unsetProperty(array &$modifies, string $property, $value)
    {
        if ($modifies[$property] == $value) {
            unset($modifies[$property]);
        }
    }

    /**
     * @param array $removes
     * @param string $key
     * @param string $orderId
     */
    private function prepareOrderRemoves(string $orderId, array $removes, string $key): void
    {
        if (isset($removes[$key])) {
            $column = 'id';
            $values = [];
            $content = 'order_products_removes';
            if ($isSupplier = $key === 'suppliers') {
                $column = 'supplier_id';
                $values = Supplier::query()->findMany($removes[$key])->pluck('name')->all();
                $content = 'order_supplier_removes';
                OrderSupplier::query()->where(['order_id' => $orderId])->whereIn($column, $removes[$key])->delete();
            }
            OrderDetail::query()->whereIn($column, $removes[$key])->where('order_id', $orderId)->each(
                function ($item) use (&$values, $isSupplier) {
                    if (!$isSupplier) {
                        $values[] = $item->name;
                    }
                    $item->delete();
                }
            );
            if (count($values) > 0)
                (new ActivityService())->setOrderLog(
                    Order::query()->find($orderId),
                    __(
                        "activity.$content",
                        [
                            'name' => getCurrentUser()->name,
                            'values' => implode(',', $values)
                        ]
                    ),
                    $orderId
                );
            $this->orderCancelIfNotExistSupplier($orderId);
        }
    }

    /**
     * @param string $orderId
     */
    public function orderCancelIfNotExistSupplier(string $orderId): void
    {
        if (OrderSupplier::query()->where(['order_id' => $orderId])->count() === 0) {
            Order::query()->find($orderId)->update(['status' => OrderConstant::KEY_STATUS_CANCEL]);
        }
    }

    /**
     * @param string $orderId
     * @param array $item
     */
    private function storeNote(string $orderId, array $item)
    {
        if (isset($item['notes'])) {
            $model = OrderNote::class;
            foreach ($item['notes'] as $key => $value) {
                if (!$value || $key == 'order_provisional_date') {
                    continue;
                } else {
                    $public = 0;
                    if ($key == 'public') {
                        $public = 1;
                    }
                    $orderNoteItem = OrderNote::query()
                        ->where('order_id', $orderId)
                        ->where('supplier_id', $item['id'])
                        ->where('is_public', $public)
                        ->first();
                    if (!$orderNoteItem) {
                        (new NoteService())->store(
                            $model,
                            [
                                'id' => $orderId,
                                'column' => 'order_id',
                                'content' => $value,
                                'supplier_id' => $item['id'],
                                'is_public' => $public,
                                'order_provisional_date' => null
                            ]
                        );
                        if ($key == 'private') {
                            $data = [
                                'old_value' => '',
                                'new_value' => $value,
                                'order_id' => $orderId,
                                'history_type' => OrderHistory::ORDER_HISTORY_NOTE_TYPE,
                                'user_id' => getCurrentUser()->id ?? 0,
                                'date' => date('Y-m-d H:i:s'),
                                'content' => 'đã thêm mới Ghi chú nội bộ [ ' .  $value . ' ] '
                            ];
                            OrderHistory::query()->create($data);
                        }
                    } else {
                        if ($key == 'private') {
                            if ($orderNoteItem->content != $value) {
                                $this->createOrderHistory($orderNoteItem->content, $value, $orderId);
                            }
                        }
                        // Nếu là note của khách thì k update k lại sai
                        if($orderNoteItem->subject_type == Customer::class){
                            continue;
                        }
                        $orderNoteItem->content = $value;
                        $orderNoteItem->subject_id = getCurrentUser()->id;
                        $orderNoteItem->update();
                    }
                }

            }
        }
    }

    /**
     * @param string $orderId
     */
    public function updateOrderCost(string $orderId): void
    {
        $order = Order::query()->findOrFail($orderId);

        $data = optional(OrderSupplier::query()->where('order_id', $orderId)->selectRaw(
            'sum(order_cost) as order_cost, sum(order_fee) as order_fee,' .
            'sum(inspection_cost) as inspection_cost, sum(discount_cost) as discount_cost,' .
            'sum(china_shipping_cost) as china_shipping_cost, sum(china_shipping_cost_real) as china_shipping_cost_real, '.
            'sum(order_cost_real) as order_cost_real'
        )->groupBy(
            'order_id'
        )->first())->toArray();

        if (!$data) {
            $data = ['order_cost' => 0, 'inspection_cost' => 0, 'china_shipping_cost' => 0, 'order_fee' => 0];
        }
        $data['china_shipping_cost_old'] = $data['china_shipping_cost'];
        $data['order_cost_real'] = floatval($data['order_cost_real']) * $order->exchange_rate;
        //unset($data['china_shipping_cost']);

        $order->update($data);
    }

    /**
     * @param string $orderId
     * @param string $column
     */
    public function incrementByColumn(string $orderId, string $column): void
    {
        optional(Order::query()->find($orderId))->increment($column);
    }

    public function getDebtCost(array $ids): float
    {
        return Order::query()->findMany($ids)->sum('debt_cost');
    }

    /**
     * @param Order $order
     * @return float
     */
    public function updateOrderSupplier(Order $order): float
    {
        // $orderClone = clone $order;
        // $totalAmountBegin = $orderClone->getTotalAmount();
        $orderPercent = $order->order_percent;
        $customerOffer = CustomerOffer::query()->where('customer_id', $order->customer_id)->first();
        // Cờ tính lại phí đặt hàng, khi tăng số lượng thì phí đặt hàng thay đổi
        $isFlagFee = true;

        if($customerOffer && $customerOffer->offer_rate > 0) {
            $isFlagFee = false;
            $orderPercent = $customerOffer->offer_rate;
        }
        if($order->is_fix_order_percent == 1) {
            $orderPercent = $order->order_percent;
        }
        $totalPriceOrder = 0;
        $inspectionCost = $this->updateOrderSupplierAndOrder($order, $orderPercent, $totalPriceOrder);
        // Nếu là đơn fix % phí đặt hàng thì k cần check
        if(!$order->is_fix_order_percent) {
            $orderPercentCheck = (new AccountingService())->getDepositPercent($totalPriceOrder);
            if(
                ($isFlagFee
                && $orderPercentCheck != $orderPercent
                && in_array($order->status,
                    [
                        Order::ORDER_STATUS_WAITING_FOR_QUOTE, Order::ORDER_STATUS_WAITING_FOR_DEPOSIT,
                        Order::ORDER_STATUS_NEED_TO_CONFIRM
                    ]
                ))
                || (!$this->checkSplitOrder($order))
            )
            {
                $inspectionCost = $this->updateOrderSupplierAndOrder($order, $orderPercentCheck, $totalPriceOrder);
            }

        }
        if($order->deposit_percent != 100 && !in_array($order->status, [
            Order::ORDER_STATUS_WAITING_FOR_QUOTE, Order::ORDER_STATUS_WAITING_FOR_DEPOSIT,
            Order::ORDER_STATUS_NEED_TO_CONFIRM
        ])) {
            // Chỉ tính lại tiền còn lại khi cọc khác 100%
            // tiền còn lại so với tiền cọc luôn bỏ ship
            $remainingAmount = $order->getTotalAmountNotShip() - $order->deposit_cost;
            // Sẽ có case làm tròn nên phải -1 để check
            $order->remaining_amount = $remainingAmount - 1 < 0 ? 0 : $remainingAmount;
            $order->save();
        }
        // Comment lại để dùng hàm của a Vương
        // $depositCostBegin = $order->deposit_cost;
        // $totalAmountAfter = $order->getTotalAmount();
        // $depositcostAfter = roundMoney(($totalAmountAfter * $order->deposit_percent) / 100);
        // Kiểm tra nếu đơn đang ở trạng thái đã đặt cọc và thay đổi tổng giá trị đơn hàng
        // if ($order->status == OrderConstant::KEY_STATUS_DEPOSITED && $totalAmountBegin != $totalAmountAfter) {
        //     // Nếu số tiền sau nhỏ hơn số tiền trước thì hoàn lại tiền đặt cọc cho khách
        //     if ($depositcostAfter < $depositCostBegin) {
        //         if (data_get($order, 'customer.report')) {
        //             $diffAmount = $depositCostBegin - $depositcostAfter;
        //             $order->customer->report()->decrement('purchase_amount', $diffAmount);
        //             $order->customer->report()->decrement('order_amount', $diffAmount);
        //             $order->decrement('deposit_cost', $diffAmount);
        //             // Ghi lại log hoàn tiền cho khách hàng
        //             (new TransactionService())->setTransaction(
        //                 $diffAmount,
        //                 // $order->deposit_cost - $depositcostAfter,
        //                 TransactionConstant::STATUS_REFUND,
        //                 trans(
        //                     'transaction.order_purchase',
        //                     [
        //                         'order_code' => $order->code,
        //                         'amount' => ConvertHelper::numericToVND($diffAmount),
        //                     ]
        //                 ),
        //                 true,
        //                 $order->customer_id,
        //                 $order,
        //                 true,
        //                 $order,
        //             );
        //             $dataNotificationCustomer = [
        //                 'order_code' =>$order->code,
        //                 'amount' => ConvertHelper::numericToVND($diffAmount)
        //             ];
        //             (new NotificationService())->sendNotificationCustomer(8, 8014, $order->customer_id, $dataNotificationCustomer);
        //         }
        //     }
        //     if($depositcostAfter > $depositCostBegin){
        //         $dataNotificationCustomer = [
        //             'order_code' => $order->code,
        //             'order_id' => $order->id,
        //         ];
        //         (new NotificationService())->sendNotificationCustomer(2, 2012, $order->customer_id, $dataNotificationCustomer);
        //     }
        // }


        return AccountingHelper::getCosts($inspectionCost);
    }

    public function updateOrderSupplierAndOrder($order, $orderPercent, &$totalPriceOrder, $isChangeOrder = false)
    {
        $orderId = $order->id;
        /** @var OrderSupplier $orderSupplier */
        $orderDetails = OrderDetail::query()->where(
            ['order_id' => $orderId]
        )->groupBy('supplier_id')->selectRaw(
            "sum(unit_price_cny * quantity) as amount_cny,
            sum(quantity) as quantity,
            supplier_id, sum(unit_price_cny * quantity) as unit_price_cny"
        )->get();
        $countOrderDetails = $orderDetails->count();
        $inspectionCost = 0;
        $advancedInspectionCost = 0;
        $orderCost = 0;
        $orderCostOld = 0;
        $orderFee = 0;
        // Khi thanh toán tất cả
        $checkIsPaymentAll = $order->code_parent || Order::query()->where('code_parent', $order->code)->exists();
        // Nếu đơn ở trạng thái này thì cũng k update % cọc
        $checkStatusOrder = in_array($order->status,
            [Order::ORDER_STATUS_ORDER, Order::ORDER_STATUS_WAITING_FOR_PAYMENT, Order::ORDER_STATUS_DEPOSITED]);

        foreach ($orderDetails as $detail) {
            $supplierId = $detail->supplier_id;
            $orderSupplier = OrderSupplier::query()->where(
                ['order_id' => $orderId, 'supplier_id' => $supplierId]
            )->first();
            if (!$orderSupplier) {
                $orderSupplier = OrderSupplier::query()->create(
                    [
                        'order_id' => $orderId,
                        'supplier_id' => $supplierId,
                        'is_inspection' => $order->is_inspection,
                        'is_advanced_inspection' => $order->is_advanced_inspection,
                        'is_woodworking' => $order->is_woodworking,
                        'is_shock_proof' => $order->is_shock_proof
                    ]
                );
            }

            $data = [];
            $data['order_cost'] = $orderCostTmp = AccountingHelper::getCosts(
                $detail->amount_cny * $order->exchange_rate
            );
            $orderCostOldTmp = AccountingHelper::getCosts(
                $detail->unit_price_cny * $order->exchange_rate
            );
            // luôn ưu tiên sửa ở đơn
            if($isChangeOrder || $order->is_fix_order_percent == 1) {
                $data['order_fee'] = $orderFeeTmp = (new AccountingService())
                ->getOrderFee($orderCostTmp, $order->customer_id, $orderPercent, 'isChangeOrder');
            } else {
                // Nếu countOrderDetails > 1 tức là đơn có nhiều nhà cung cấp.
                // Phải check thêm nó có được tách ra từ đơn gốc không. Nếu có thì vẫn tính theo giá của đơn gốc
                $data['order_fee'] = $orderFeeTmp =
                ($countOrderDetails > 1 || $checkIsPaymentAll || $checkStatusOrder) ? (new AccountingService())
                    ->getOrderFee($orderCostTmp, $order->customer_id, $orderPercent, 'isMultipleSupplier')
                    : (new AccountingService())
                    ->getOrderFee($orderCostTmp, $order->customer_id, $orderPercent);
            }
            $data['discount_cost'] = (new AccountingService())->getDiscountCost($orderFeeTmp, $order->customer_id);
            $data['inspection_cost'] = $orderSupplier->is_inspection
                ? (new AccountingService())->getInspectionCost($detail->quantity) : 0;
            $data['advanced_inspection_cost'] = $orderSupplier->is_advanced_inspection == 2
                ? (new AccountingService())->getAdvancedInspectionCost($detail->quantity) : 0;
            $advancedInspectionCost += $data['advanced_inspection_cost'];
            $inspectionCost += $orderSupplier->inspection_cost;
            $orderSupplier->update($data);
            $orderCost += $orderCostTmp;
            $orderCostOld += $orderCostOldTmp;
            $orderFee += $orderFeeTmp;
        }
        $default = json_decode((new ConfigService())->getValueByKey(ConfigConstant::SERVICE_ORDER_FEE), true)[0]['default'];
        // Nếu phí nhỏ hơn phí mặc định thì luôn lấy theo mặc định
        if($orderFee > 0 && $orderFee < $default && !$checkIsPaymentAll) {
            foreach ($orderDetails as $detail) {
                $supplierId = $detail->supplier_id;
                $orderSupplier = OrderSupplier::query()->where(
                    ['order_id' => $orderId, 'supplier_id' => $supplierId]
                )->first();

                $data['order_cost'] = AccountingHelper::getCosts(
                    $detail->unit_price_cny * $order->exchange_rate
                );
                $data['order_fee'] = $orderFeeTmp = $orderSupplier->order_fee / $orderFee * $default;
                $data['discount_cost'] = (new AccountingService())->getDiscountCost($orderFeeTmp, $order->customer_id);

                $orderSupplier->update($data);
            }
            $orderFee = $default;
        }
        if($order->order_cost < $orderCost &&
            in_array($order->status, [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY, OrderConstant::KEY_STATUS_ORDERED])) {
            $order->is_up_order_cost = 1; // Đánh dấu là đã từng tăng tiền hàng làm chênh lệnh cọc
        }
        $order->order_cost = $orderCost;
        $totalPriceOrder = $orderCost;
        $order->order_cost_old = $orderCostOld; // Phần này mới thêm để tính lại giá chỗ kế toán
        $order->order_percent = $orderPercent;
        $order->order_fee = $orderFee;
        $order->is_inspection = $inspectionCost > 0 ? 1 : 0;
        $order->inspection_cost = $inspectionCost;
        $order->is_advanced_inspection = $advancedInspectionCost > 0 ? 2 : 1;
        $order->advanced_inspection_cost = $advancedInspectionCost;
        $order->save();
        return $inspectionCost;
    }

    /**
     * get order pagination by customer
     * @param array $params
     */
    public function getOrderByCustomer($params)
    {
        //DB::enableQueryLog();
        $data = $this->_model->newQuery()
            ->with([
                'customer' => function($q) {
                    $q->select('id', 'name', 'phone_number');
                },
                'details' => function($q) {
                    $q->select('id', 'quantity', 'order_id');
                },
                'staffOrder' => function($q) {
                    $q->select('id', 'name');
                },
                'orderSuppliers' => function($q) {
                    $q->select('id', 'order_id', 'woodworking_cost', 'china_shipping_cost', 'order_fee', 'order_cost',
                        'inspection_cost', 'discount_cost', 'international_shipping_cost');
                },
                'packages' => function($q) {
                    $q->select('id', 'order_id', 'discount_cost');
                }
            ])
            ->where('customer_id', $params['customer_id'])
            ->orderBy('created_at', 'DESC')
            ->paginate($params['per_page']);
        //dd(DB::getQueryLog());
        return resSuccessWithinData(new $this->_orderCustomerPaginateResource($data));
    }

    public function updateInternalShippingCostOrderPackageById($orderPackage)
    {
        $orderId = $orderPackage->order_id;
        $order = Order::query()->findOrFail($orderId);

        $totalCostPackages = optional($order->packages)->sum('international_shipping_cost');
        $totalCostPackages = $totalCostPackages ?? 0;
        $totalWoodWorkingCostPackages = optional($order->packages)->sum('woodworking_cost');
        $totalWoodWorkingCostPackages = $totalWoodWorkingCostPackages ?? 0;

        $order->update(['international_shipping_cost' => $totalCostPackages]);
        //$order->international_shipping_cost = $totalCostPackages;
        //$order->save();

        // update internal shipping cost for order supplier first
        $orderSupplierFirst = optional($order->orderSuppliers)->first();
        if ($orderSupplierFirst) {
            $orderSupplierFirst->international_shipping_cost = $totalCostPackages;
            $orderSupplierFirst->woodworking_cost = $totalWoodWorkingCostPackages;
            $orderSupplierFirst->save();
        }
    }

    public function updateFeeForOrderWaitingPurchase($customerId)
    {
        $orders = Order::query()
            ->with('orderSuppliers')
            ->select('id', 'order_cost', 'status', 'customer_id', 'exchange_rate')
            ->where('customer_id', $customerId)
            ->whereIn('status', OrderConstant::STATUSES_CALCULATOR_FEE)
            ->get();

        foreach ($orders as $order) {
            $orderFee = (new AccountingService())->getOrderFee($order->order_cost, $customerId);
            $discountCost = (new AccountingService())->getDiscountCost($orderFee, $customerId);

            $order->order_fee = $orderFee;
            $order->discount_cost = $discountCost;
            $order->save();

            // update order fee, discount cost for order supplier
            $this->updateOrderSupplierWhenChangeCustomerOfferRate($order);
        }
    }

    private function updateOrderSupplierWhenChangeCustomerOfferRate($order)
    {
        $accountService = new AccountingService();

        $suppliers = !empty($order->orderSuppliers) ? $order->orderSuppliers : [];
        foreach ($suppliers as $supplier) {
            $orderFee = $accountService->getOrderFee($supplier->order_cost, $order->customer_id);
            $discountCost = $accountService->getDiscountCost($orderFee, $order->customer_id);
            $orderSupplier = [
                'order_fee' => $orderFee,
                'discount_cost' => $discountCost,
                'updated_at' => now()
            ];

            OrderSupplier::query()
                ->where([
                    ['order_id', $order->id], ['supplier_id', $supplier]
                ])
                ->update($orderSupplier);
        }
    }

    /**
     * change order deposit
     * @param Order $order
     * @param array $params
     */
    public function changeDeposit(Order $order, $data)
    {
        if (
            !isset($data['is_only_deposit_percent']) &&
            !in_array($order->status, [OrderConstant::KEY_STATUS_WAITING_QUOTE, OrderConstant::KEY_STATUS_WAITING_DEPOSIT])
        ) {
            return false;
        }
        $oldDepositPercent = $order->deposit_percent;
        $oldOrderPercent = $order->order_percent;
        // save change
        if(isset($data['deposit_percent']) && $data['deposit_percent'] > 0) {
            $order->deposit_percent = $data['deposit_percent'];
        }

        if(isset($data['order_percent']) && $data['order_percent'] > 0 && $data['order_percent'] != $oldOrderPercent) {
            $order->order_percent = $data['order_percent'];
            $totalPriceOrder = 0;
            // Tính lại phí đặt hàng
            $isChangeOrder = true;
            $this->updateOrderSupplierAndOrder($order, $data['order_percent'], $totalPriceOrder, $isChangeOrder);
            if ($order->is_inspection) {
                $this->updateInspectionCost($order);
            }
            $order->is_fix_order_percent = 1;
        }
        $dataPayment = '';
        if(isset($data['is_only_deposit_percent'])) {
            // Tạo giao dịch hoàn tiền
            if($data['deposit_percent'] > 0 && $oldDepositPercent > $data['deposit_percent']) {
                $amountDeposit = $order->deposit_cost;
                $amountDepositNew = ($data['deposit_percent'] * $order->getTotalAmountNotShip()) / 100;
                $moneyRefund = $amountDeposit - $amountDepositNew;
                if($moneyRefund > 0) {
                    $order->deposit_cost = $amountDepositNew;
                    $this->refundOrderChangeDeposit($order, $moneyRefund, $oldDepositPercent, $data['deposit_percent']);
                } else {
                    $moneyPayment = $amountDepositNew - $amountDeposit;
                    if($moneyPayment != 0) {
                        $dataPayment = $this->paymentOrderChangeDeposit($order, $moneyPayment, $oldDepositPercent, $data['deposit_percent']);
                        if(!$dataPayment) {
                            $order->deposit_cost = $amountDepositNew;
                        } else {
                            return $dataPayment;
                        }
                    }
                }
            }
            // Tạo giao dịch thanh toán
            if($data['deposit_percent'] > 0 && $oldDepositPercent < $data['deposit_percent']) {
                $amountDeposit = $order->deposit_cost;
                $amountDepositNew = ($data['deposit_percent'] * $order->getTotalAmountNotShip()) / 100;
                $moneyPayment = $amountDepositNew - $amountDeposit;
                if($moneyPayment > 0) {
                    $dataPayment = $this->paymentOrderChangeDeposit($order, $moneyPayment, $oldDepositPercent, $data['deposit_percent']);
                    if(!$dataPayment) {
                        $order->deposit_cost = $amountDepositNew;
                    } else {
                        return $dataPayment;
                    }
                }
            }
        }
        $order->save();
        // ghi log change
        $logService = \App::make('App\Services\ActivityService');
        $userNameLogin = Auth::user()->name;
        if (isset($data['deposit_percent']) && $data['deposit_percent'] > 0 && $data['deposit_percent'] != $oldDepositPercent) {
            $content = $userNameLogin . ' đã thay đổi phần trăm đặt cọc từ ' . $oldDepositPercent . ' thành ' . $order->deposit_percent;
            $logService->setOrderLog($order, $content, $order->id);
        }
        if (isset($data['order_percent']) && $data['order_percent'] > 0 && $data['order_percent'] != $oldOrderPercent) {
            $content = $userNameLogin . ' đã thay đổi phần trăm phí đặt hàng từ ' . $oldOrderPercent . ' thành ' . $order->order_percent;
            $logService->setOrderLog($order, $content, $order->id);
        }
        return $dataPayment;
    }

    public function refundOrderChangeDeposit(Order $order, $amount, $oldDepositPercent, $depositPercent)
    {
        try {
            $customerId = $order->customer_id;
            $amountFormat = number_format($amount);
            $code = $order->code;
            $content = "Hoàn $amountFormat đ do thay đổi % đặt cọc của đơn hàng $code từ $oldDepositPercent thành $depositPercent";

            (new \App\Services\TransactionService())->setTransactionIncrement(
                $amount,
                \App\Constants\TransactionConstant::STATUS_REFUND,
                $content,
                $customerId,
                $order,
                $order
            );
            $this->updateNotiLogic($customerId, $content);
        } catch (Exception $e) {
            Log::info('Refund change deposit # '. $order->code . 'errors: ' .$e->getMessage());
        }
    }

    public function paymentOrderChangeDeposit(Order $order, $amount, $oldDepositPercent, $depositPercent)
    {
        try {
            $customerId = $order->customer_id;
            $amountFormat = number_format($amount);
            $code = $order->code;
            $content = "Thanh toán $amountFormat đ do thay đổi % đặt cọc của đơn hàng $code  từ $oldDepositPercent thành $depositPercent";
            $customerService = \App::make('App\Services\CustomerService');;
            $customerBalance = (int)$customerService->getBalanceAmount($customerId);
            if($customerBalance < $amount) {
                return 'Thay đổi % đặt cọc không thành công. Vui lòng liên hệ khách hàng thanh toán thêm tiền cọc còn thiếu !';
            } else {
                (new \App\Services\TransactionService())->setTransactionDecrement(
                    $amount,
                    \App\Constants\TransactionConstant::STATUS_PURCHASE,
                    $content,
                    $customerId,
                    $order,
                    $order
                );
                $this->updateNotiLogic($customerId, $content);
            }
            return '';
        } catch (Exception $e) {
            Log::info('payment order change deposit # '. $order->code . 'errors: ' .$e->getMessage());
        }
    }
    /**
     * store order advance money
     * @param Order $order
     * @param array $data
     */
    public function storeAdvanceMoney(Order $order, $data)
    {
        $data['order_type'] = get_class($order);
        $data['order_id'] = $order->id;
        $data['order_code'] = $order->code;
        $data['customer_id'] = $order->customer_id;
        $data['staff_id'] = Auth::id();

        $customerDB = $order->customer;
        if ($customerDB && $customerDB->organization_id) {
             $data['organization_id'] = $customerDB->organization_id;
        } else {
            $data['organization_id'] = getOrganization();
        }

        return OrderAdvanceMoney::create($data);
    }

    /**
     * @throws BindingResolutionException
     */
    public function approveAdvanceMoney(array $ids): array
    {
        $orderAdvanceMoneys = OrderAdvanceMoney::query()->findMany($ids);
        $notificationService = app()->make(NotificationService::class);
        $idFails = [];
        foreach ($orderAdvanceMoneys as $orderAdvanceMoney) {
            if (in_array($orderAdvanceMoney->status, [OrderConstant::KEY_ORDER_ADVANCE_STATUS_APPROVE,
                OrderConstant::KEY_ORDER_ADVANCE_STATUS_CANCEL])) {
                $idFails[] = $orderAdvanceMoney->id;
                continue;
            }
            $updateOrder = $this->updateOrderDepositCost($orderAdvanceMoney->order_id, $orderAdvanceMoney->amount);
            if (!$updateOrder) {
                $idFails[] = $orderAdvanceMoney->id;
                continue;
            }
            DB::transaction(function () use ($orderAdvanceMoney, $notificationService) {
                // update status
                $orderAdvanceMoney->manager_id = Auth::id();
                $orderAdvanceMoney->status = OrderConstant::KEY_ORDER_ADVANCE_STATUS_APPROVE;
                $orderAdvanceMoney->save();
                // decrement customer balance
                (new TransactionService())->setTransaction(
                    $orderAdvanceMoney->amount,
                    TransactionConstant::STATUS_ADVANCE_MONEY,
                    'Trừ tiền tạm ứng của đơn #' . $orderAdvanceMoney->order_code,
                    false,
                    $orderAdvanceMoney->customer_id,
                    $orderAdvanceMoney->order,
                    true,
                    $orderAdvanceMoney->order
                );

                // update report customer
                $reportCustomerService = new ReportCustomerService();
                $reportCustomerService->incrementByKey(CustomerConstant::KEY_REPORT_ORDER_COST, $orderAdvanceMoney->amount);
                //$reportCustomerService->updateLevel();
                // push notification cho nv kinh doanh
                $dataNotification = [
                    'group' => 2,
                    'name' => 2014,
                    'data' => json_encode([
                        'order_code' => $orderAdvanceMoney->order_code,
                        'amount' => $orderAdvanceMoney->amount,
                        'order_id' => $orderAdvanceMoney->order_id
                    ]),
                    'time_create' => time(),
                    'status' => 1
                ];
                $notificationService->storeNoti($orderAdvanceMoney->staff_id, $dataNotification);
            });
        }
        return $idFails;
    }

    private function updateOrderDepositCost($order_id, $amount): bool
    {
        $order = Order::query()->find($order_id);
        if ($order) {
            \Log::info('updateOrderDepositCost:amount: ' . $amount);
            $totalAmount = $order->getTotalAmount();
            $deposit_cost = $order->deposit_cost ?? 0;
            $deposit_cost += $amount;
            \Log::info('updateOrderDepositCost:deposit_cost: ' . $deposit_cost);
            \Log::info('updateOrderDepositCost:totalAmount: ' . $totalAmount);
            if ($deposit_cost > $totalAmount) {
                return false;
            }
            $order->deposit_cost = $deposit_cost;
            $order->save();
            return true;
        }
        return false;
    }

    /**
     * cancel order advance money
     * @param array $ids
     */
    public function cancelAdvanceMoney($ids)
    {
        return OrderAdvanceMoney::query()
            ->whereIn('id', $ids)
            ->where('status', OrderConstant::KEY_ORDER_ADVANCE_STATUS_WAITING)
            ->update(['status' => OrderConstant::KEY_ORDER_ADVANCE_STATUS_CANCEL, 'manager_id' => Auth::id()]);
    }

    /**
     * list order advance money
     * @param array $params
     */
    public function listAdvanceMoney($params)
    {
        $perPage = !empty($params['per_page']) ? $params['per_page'] : 25;

        $query = OrderAdvanceMoney::query();
        $query->with('staff', 'manager', 'customer', 'order');

        if (isset($params['customer_name'])) {
            $query->customerName();
        }
        if (isset($params['customer_code'])) {
            $query->customerCode();
        }
        if (isset($params['customer_phone'])) {
            $query->customerPhone();
        }
        if (isset($params['order_code'])) {
            $query->orderCode();
        }
        if (!empty($params['status'])) {
            $query->status();
        }

        return $query->paginate($perPage);
    }

    public function getTotalCustomersByStaffId($userId)
    {
        $query = Order::query();
        $query->selectRaw('COUNT(DISTINCT(customer_id)) as totalCustomers');
        $query->where(function ($q) use ($userId) {
            $q->where('staff_care_id', $userId);
            $q->orWhere('staff_counselor_id', $userId);
        });

        $data = $query->first();
        return (int)optional($data)->totalCustomers;
    }

    /**
     * force delete order
     * @return mixed
     */
    public function forceDeleteOrder()
    {
        $list = Order::query()
            ->withTrashed(true)
            ->whereNotNull('deleted_at')
            ->whereRaw('UNIX_TIMESTAMP(CURRENT_TIMESTAMP()) >= UNIX_TIMESTAMP(DATE_ADD(deleted_at,INTERVAL 60 MINUTE))')
            ->get();

        foreach ($list as $item) {
            DB::transaction(function () use ($item) {
                $item->forceDelete();
            });
        }
    }

    /**
     * restore order by customer
     * @param array $customerIds
     * @return mixed
     */
    public function restoreByCustomer(array $customerIds)
    {
        $list = Order::query()
            ->withTrashed(true)
            ->whereNotNull('deleted_at')
            ->whereIn('customer_id', $customerIds)
            ->get();

        foreach ($list as $item) {
            DB::transaction(function () use ($item) {
                $item->restore();
            });
        }
    }

    public function getListStatusByRole()
    {
        $statues = [];

        $nameRole = getNameRole();

        if (!$nameRole) {
            return $statues;
        }

        switch ($nameRole) {
            case UserConstant::ROLE_STAFF_ORDER:
                $statues = array_keys(OrderConstant::STAFF_ORDER_STATUSES);
                break;
            case UserConstant::ROLE_STAFF_QUOTATION:
                $statues = array_keys(OrderConstant::STAFF_QUOTE_STATUSES);
                break;
            case UserConstant::ROLE_STAFF_GROUP:
                $statues = array_keys(OrderConstant::STAFF_BUSINESS_STATUSES);
                break;
            default:
                $statues = array_keys(OrderConstant::STATUSES);
                break;
        }

        return $statues;
    }

    public function getListDeposited()
    {
        $perService = \App::make('App\Services\PerService');

        $query = $this->_model->newQuery();
        $query->where('status', OrderConstant::KEY_STATUS_DEPOSITED)->with('details');

        $query = $perService->getConditionByUserLogin($query);
        $data = $query->orderBy('id', 'desc')->paginate(5);
        return $data;
    }

    protected function ArrToCollection($items, $perPage = 10, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, $options);
    }

    public function getOrderDoneByDate($startDate = null, $endDate = null)
    {
        $result = $this->_model->newQuery()
            ->where('status', OrderConstant::KEY_STATUS_DONE)
            ->whereNotNull("staff_group_id");
        if ($startDate) {
            $result = $result->whereDate("date_done", ">=", $startDate);
        }
        if ($endDate) {
            $result = $result->whereDate("date_done", "<=", $endDate);
        }
        return $result->get();
    }

    public function getOrderByDate($startDate = null, $endDate = null, $provinceId = null)
    {
        return $this->_model->newQuery()
            ->with(["customerDelivery.province", "orderStatusTime", "packages"])
            ->where(function ($query) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $query->where(function ($subQuery) use ($startDate, $endDate) {
                        $subQuery->whereDate("created_at", ">=", $startDate)->whereDate("created_at", "<=", $endDate);
                    })->orWhereHas('orderStatusTime', function ($subQueryTime) use ($startDate, $endDate) {
                        $subQueryTime->where(function ($subQueryStatusTime) use ($startDate, $endDate) {
                            $subQueryStatusTime->whereDate("status_1", ">=", $startDate)->whereDate("status_1",
                                "<=",
                                $endDate);
                        })->orWhere(function ($subQueryStatusTime) use ($startDate, $endDate) {
                            $subQueryStatusTime->whereDate("status_2", ">=", $startDate)->whereDate("status_2",
                                "<=",
                                $endDate);
                        })->orWhere(function ($subQueryStatusTime) use ($startDate, $endDate) {
                            $subQueryStatusTime->whereDate("status_3", ">=", $startDate)->whereDate("status_3",
                                "<=", $endDate);
                        })->orWhere(function ($subQueryStatusTime) use ($startDate, $endDate) {
                            $subQueryStatusTime->whereDate("status_4", ">=", $startDate)->whereDate("status_4",
                                "<=", $endDate);
                        })->orWhere(function ($subQueryStatusTime) use ($startDate, $endDate) {
                            $subQueryStatusTime->whereDate("status_6", ">=", $startDate)->whereDate("status_6",
                                "<=", $endDate);
                        })->orWhere(function ($subQueryStatusTime) use ($startDate, $endDate) {
                            $subQueryStatusTime->whereDate("status_8", ">=", $startDate)->whereDate("status_8",
                                "<=", $endDate);
                        });
                    });
                }
            })
            ->whereHas('customerDelivery.province', function ($subQuery) use ($provinceId) {
                if ($provinceId) {
                    $subQuery->where('id', $provinceId);
                }
            })
            ->get();
    }

    /**
     * Note: Chuyển logic từ code cũ trong OrderController vào OrderService
     * Function update status orders
     * @param Order $order
     * @param string $status
     * @return mixed
     */
    public function changeStatus(Order $order, string $status): JsonResponse
    {
        try {
            // Log::info('is admin ' . isAdmin());
            if(
                in_array($order->status, OrderConstant::STATUS_PAID)
                && in_array($status, OrderConstant::STATUS_NO_PAID)
                && !isAdmin()
            ) {
                return resError('Đơn đã thay đổi trạng thái, vui lòng tải lại trang để cập nhật thông tin đơn', 422);
            }
            if(
                $this->canChangeStatus($order->status, $status)
                && !isAdmin()
            ) {
                return resError('Đơn không thể thay đổi trạng thái. Vui lòng liên hệ Admin!', 422);
            }
            $subNowDone = false;
            if ($order && $order->delivery_pay_id == 0) {
                if ($status == OrderConstant::KEY_STATUS_DONE) {
                    $packs = $order->packagesOrder->where('status', '<>', PackageConstant::STATUS_CANCEL);
                    if (count($packs)) {
                        return resError('Bạn vui lòng trả hàng kiện trước khi chuyển trạng thái đơn hàng sang Hoàn thành!', 422);
                    }
                    $subNowDone = true;
                }
            }

            DB::beginTransaction();
            $now = Carbon::now();
            if ($status == OrderConstant::KEY_STATUS_DONE) {
                $order->date_done = $now;
            }
            if ($status == OrderConstant::KEY_STATUS_DEPOSITED) {
                $order->date_purchased = $now;
            }
            if ($status == OrderConstant::KEY_STATUS_WAITING_DEPOSIT) {
                $order->date_quotation = $now;
                $order->is_quoting = 0;
            }
            if ($status == OrderConstant::KEY_STATUS_ORDERED) {
                $order->date_ordered = $now;
            }
            $statusOld = $order->status;
            // Nếu chuyển trạng thái từ cần xác nhận lại hoặc đã hủy thì lấy theo tỉ giá mới
            if($statusOld != $status && $status == OrderConstant::KEY_STATUS_WAITING_QUOTE) {
                if(in_array($statusOld, [OrderConstant::KEY_STATUS_RECONFIRM, OrderConstant::KEY_STATUS_CANCEL])) {
                    $order->exchange_rate = getExchangeRate();
                    $this->updateOrderSupplier($order);
                    $this->updateOrderCost($order->id);

                    if($order->is_inspection) {
                        $this->updateInspectionCost($order);
                    }
                }
            }
            if(in_array($status, [OrderConstant::KEY_STATUS_WAIT_TO_PAY])) {
                // Chuyển trạng thái tự động cập nhật tiền hàng thực tế bằng tiền hàng
                if($order->order_cost_real == 0) {
                    $order->order_cost_real = $order->order_cost;
                }
                if($order->china_shipping_cost_real == 0) {
                    $order->china_shipping_cost_real = $order->china_shipping_cost;
                }
                // order_cost_real chia cho tỉ giá vì ở orders lưu vnđ còn ở order_supplier lưu tệ
                OrderSupplier::where('order_id', $order->id)->update([
                    'order_cost_real' => $order->order_cost_real/$order->exchange_rate,
                    'china_shipping_cost_real' => $order->china_shipping_cost
                ]);
            }
            if($status == OrderConstant::KEY_STATUS_RECONFIRM && $reasonId = request()->input('reason_id')) {
                $order->is_quoting = 0;
                $reasonModel = \App::make('App\Models\ReasonConfirmOrder');
                $reason = $reasonModel->find($reasonId);
                if($reason && $reason->time) {
                    $order->reasonConfirmOrders()->attach($reasonId, [
                            'created_at' => now(),
                            'updated_at' => now()
                    ]);
                    $order->reason_confirm_order_id = $reasonId;
                    $times = json_decode($reason->time);
                    if($reason->time_quote) {
                        // Job tự động chuyển về chờ báo giá
                        $decimalHours = $reason->time_quote;
                        $hours = floor($decimalHours);
                        $mins = round(($decimalHours - $hours) * 60);
                        $itemResult = $hours * 60 + $mins;
                        $job = new \App\Jobs\Order\AutoChangeStatusJob($order->id, OrderConstant::KEY_STATUS_WAITING_QUOTE, $itemResult);
                        dispatch($job)->delay(now()->addMinutes($itemResult))->onQueue('sent-noti-app');
                    }
                    if(is_array($times)) {
                        foreach($times as $time) {
                            $decimalHours = $time;
                            $hours = floor($decimalHours);
                            $mins = round(($decimalHours - $hours) * 60);
                            $itemResult = $hours * 60 + $mins;
                            $job = new \App\Jobs\Order\PushNotiWarningConfirmOrderJob($order->id, $time);
                            dispatch($job)->delay(now()->addMinutes($itemResult))->onQueue('sent-noti-app');
                        }
                    }
                }
            }
            $order->status = $status;
            $order->save();
            $config = env('SEND_SMS_ORDER_PACKAGE', '');
            /* && $config == 'PRODUCT'*/
            if ($order->status == OrderConstant::KEY_STATUS_WAITING_DEPOSIT && $config == 'PRODUCT') {
                $phone_number = $order->customer->phone_number ?? '';
                $phone_number = convertPhoneNumber($phone_number);
                $order_code = $order->code ?? '';
                $params = [
                    "phone_number" => $phone_number,
                    "template_id" => SmsLog::ZALO_TEMPLATE_ID_BAO_GIA_DON_HANG,
                    "message" => "Đơn hàng mã $order_code đã được báo giá Quý khách hàng vui lòng truy cập vào hệ thống để kiểm tra và xác nhận đặt cọc. Cảm ơn quý khách đã gửi yêu cầu báo giá đơn hàng đến chúng tôi",
                    "template_data" => [
                        "order_code" => $order_code,
                        "customer_name" => $order->customer->name ?? '',
                        "payment_status" => OrderConstant::STATUSES[OrderConstant::KEY_STATUS_WAITING_DEPOSIT] ?? '',
                    ]
                ];
                $jobs = new SentSmsToCustomerJob($params, $order);
                dispatch($jobs)->onQueue('sent-sms-customer');
                /*$response = (new SmsZaLoService($params))
                    ->getAccessToken()
                    ->validate()
                    ->sent()
                    ->getResult();
                if ($response['code'] == 500) {
                    Log::info('SMS ZaLo Error Bao Gia Don Hang ' . $response['message']);
                    $array = [
                        'user_code' => $order->customer->code ?? '',
                        'log_type' => SmsLog::LOG_TYPE_SMS_CHANGE_STATUS_ORDER,
                        'phone_number' => $order->customer->phone_number ?? '',
                        'message' => 'Don hang ma ' . $order_code . ' da duoc bao gia. Quy khach truy cap https://hangve.com thanh toan de chung toi co the dat hang cho ban som nhat. Xin cam on!'
                    ];
                    $jobs = new SentSmsToCustomerJob($array, $order);
                    dispatch($jobs)->onQueue('sent-sms-customer');
                }
                else
                {
                    Log::info('SMS ZaLo Success Bao Gia Don Hang!');
                    SmsLog::query()->create([
                        'phone_number' => $phone_number,
                        'content' => $params['message'],
                        'user_code' => $order->customer->code ?? '',
                        'sms_source' => SmsLog::SMS_SOURCE_ZALO,
                        'log_type' => SmsLog::LOG_TYPE_SMS_CHANGE_STATUS_PACKAGE,
                        'response_status' => 0
                    ]);
                }*/
            }

            // Cập nhật snapshot
            $this->updateSupplierOrderDetails($order);
            // Gán đơn cho nhân viên đặt hàng (5 đơn)
            $this->assignStaffOrder($order);

            // Lưu noti
            $this->updateOrderNotificationChangeStatus($order);

            // Cộng tiền cho người giới thiệu
            if ($order->status == OrderConstant::KEY_STATUS_DONE) {
                $this->bankForUserIntroduce($order);
            }

            // Xử lý điểm point level
            $this->checkPointLevel($order);

            // xử lý điểm đường đi của Khách hàng
            $this->checkPointWay($order);

            // Tính tổng tiền cho nhà cung cấp
            if ($order->status == OrderConstant::KEY_STATUS_DEPOSITED) {
                (new SupplierService())->updateTotalOrderAmountOrder($order->id);
            }

            // Trừ tiền luôn với trường hợp toàn bộ các kiện đã hủy
            if ($order->status == OrderConstant::KEY_STATUS_DONE && $subNowDone) {
                $packService = \App::make('App\Services\OrderPackageService');
                // Đánh dấu đã trừ tiền hàng còn thiếu
                $order->delivery_pay_id        = (int) $order->id;
                $order->delivery_pay_debt_cost = $order->debt_cost;
                $order->delivery_pay_source    = OrderConstant::DELIVERY_PAY_SOURCE_ORDER;

                // Tạo giao dịch trừ tiền hàng còn thiếu
                $packService->makeTransactionDebtCost($order, $order);
                // Khi thanh toán kiện thì tiền hàng còn thiếu cũng đã được thanh toán. Nên update field này về 0
                $order->remaining_amount =  0;
                $order->save();
            }

            // Đồng bộ doanh số lên report khi đơn hàng hoàn thành
            // if ($order->date_done) {
            //     $dateDone = date("Y-m-d", strtotime($order->date_done));
            //     dispatch(new SyncReportOrderConsignmentDateJob($dateDone, $dateDone));
            //     dispatch(new SyncCustomerLevelJob($order->customer_id, null));
            // }
            // dispatch(new SyncReportOrderProvinceJob());

            DB::commit();
            // Log::info('update status ' . $order->status);
            return resSuccess();
        } catch (Exception $exception) {
            DB::rollback();
            Log::info('order change status error ' . json_encode($exception->getMessage()) . ' LINE : ' . $exception->getLine() . ' FILE ' . $exception->getFile());
            return resError($exception->getMessage());
        }
    }

    /**
     * Note: Chuyển logic từ code cũ trong OrderController vào OrderService
     * Function update status orders
     * @param Order $order
     * @throws Exception
     */
    public function updateOrderNotificationChangeStatus(Order $order)
    {
        // Chuyển trạng thái -> Chờ đặt cọc    Báo cho nhân viên tư vấn/chăm sóc status_1
        // Chuyển trạng thái -> Đã hủy     Báo cho nhân viên tư vấn/chăm sóc status_6
        // Chuyển trạng thái -> Đã đặt cọc     Báo cho nhân viên Đặt hàng , nhân viên tư vấn/chăm sóc status_2
        // Chuyển trạng thái -> các trạng thái còn lại     Báo cho nhân viên Đặt hàng , nhân viên tư vấn/chăm sóc
        $status = $order->status;
        $staff_group_id = $order->staff_group_id;
        $staff_order_id = null;
        $staff_quote_id = null;

        if ($status != 'status_1' && $status != 'status_6') {
            $staff_order_id = $order->staff_order_id;
        }
        if ($status == OrderConstant::KEY_STATUS_WAITING_QUOTE) {
            $staff_quote_id = $order->staff_quotation_id;
        }

        $data_order = [
            'order_code' => $order->code,
            'order_id' => $order->id,
            'order_status' => $order->status,
            'order_status_txt' => OrderConstant::STATUSES[$status],
        ];
        $content = 'Đơn hàng '. $order->code .' được chuyển sang trạng thái ' . OrderConstant::STATUSES[$order->status] ?? '';
        $name = 2006;
        if ($status == 'status_1') {
            $name = 2003;
        }
        if ($status == 'status_6') {
            $name = 2004;
        }
        if ($status == 'status_2') {
            $name = 2005;
        }
        if ($status == OrderConstant::KEY_STATUS_RECONFIRM) {
            $name = 2016;
            $reason = $order->reasonConfirmOrders()->orderBy('pivot_created_at', 'desc')->first();
            $content = __('noti.order_change_status_confirm', ['order_code' => $order->code, 'reason' => data_get($reason, 'name')]);
        }
        $data_order['content'] = $content;
        $group = 2;
        if ($status == OrderConstant::KEY_STATUS_RECONFIRM) {
            $group = 14;
        }
        $data = [
            'group' => $group,
            'name' => $name,
            'data' => json_encode($data_order),
            'time_create' => time(),
            'status' => 1
        ];
        if ($staff_group_id) {
            (new NotificationService())->storeNoti($staff_group_id, $data);
        }
        if ($staff_order_id) {
            (new NotificationService())->storeNoti($staff_order_id, $data);
        }
        if ($staff_quote_id) {
            (new NotificationService())->storeNoti($staff_quote_id, $data);
        }

        $dataCustomer = [
            'group' => 2,
            'name' => 8001,
            'data' => json_encode($data_order),
            'time_create' => time(),
            'customer_id' => $order->customer_id ?? 0,
            'status' => 1
        ];
        (new NotificationService())->store($dataCustomer);
    }

    protected function canChangeStatus(string $current, string $next): bool
    {
        // Nếu current có trong danh sách cấm và next nằm trong mảng bị cấm → false
        if (isset(OrderConstant::INVALID_TRANSITIONS[$current]) &&
            in_array($next, OrderConstant::INVALID_TRANSITIONS[$current], true)) {
            return true;
        }

        return false;
    }

    public function getDataNotiByStatus($order, $status)
    {
        $title = '';
        $body = '';
        $data = [
            'screen' => 'OrderDetail',
            'params' => [
                'orderId' => $order->id
            ]
        ];

        switch ($status) {
            case OrderConstant::KEY_STATUS_WAITING_DEPOSIT:
                $title = 'Đơn hàng';
                $body = 'Đơn ' . $order->code . ' đã chuyển sang chờ đặt cọc';
                break;
            case OrderConstant::KEY_STATUS_DEPOSITED:
                $title = 'Đơn hàng';
                $body = 'Đơn ' . $order->code . ' đã chuyển sang đã đặt cọc';
                break;
            case OrderConstant::KEY_STATUS_DONE:
                $title = 'Đơn hàng';
                $body = 'Đơn ' . $order->code . ' đã chuyển sang hoàn thành';
                break;
        }

        return [
            'title' => $title,
            'body' => $body,
            'data' => $data
        ];
    }

    public function checkPointLevel($order)
    {
        $customerDB = $order->customer;

        // Nếu trạng thái đơn là đã đặt cọc
        if ($order->status == OrderConstant::KEY_STATUS_DEPOSITED) {
            if ($customerDB->point_level < 75) {
                $customerDB->point_level = 75;
                $customerDB->save();
            }
        }

        // Nếu trạng thái đơn là hoàn thành
        if ($order->status == OrderConstant::KEY_STATUS_DONE) {
            if ($customerDB->point_level < 100) {
                $customerDB->point_level = 100;
                $customerDB->save();
            }
        }
    }

    public function checkPointWay($order)
    {
        $customerDB = $order->customer;
        // Nếu trạng thái đơn là chờ đặt cọc
        if ($order->status == OrderConstant::KEY_STATUS_WAITING_DEPOSIT) {
            if ($customerDB->point_way < 80) {
                $customerDB->point_way = 80;
                $customerDB->save();

                // Lưu thêm thời gian gán point_way
                $data_point_way_time = [
                    'customer_id' => (int) $customerDB->id,
                    'type' => CustomerPointTime::CUSTOMER_POINT_WAY,
                    'case' => CustomerPointTime::CUSTOMER_POINT_WAY_MAKE_DEPOSIT
                ];
                $customerPointTimeModel = \App::make('App\Models\CustomerPointTime');
                $customerPointTimeModel->create($data_point_way_time);

            }
        }
    }

    public function bankForUserIntroduce($order)
    {
        $customerDB = $order->customer;
        $timeOrder = $order->created_at->timestamp;
        $userOnline = Auth::user();
        // Nếu tạo đơn trước lúc gán người giới thiệu
        if ($customerDB->customer_introduce_time_add > $timeOrder) {
            return;
        }

        $customerIntroduce = Customer::query()->where('code', $customerDB->customer_introduce_id)
            ->first();

        if ($customerIntroduce) {
            $total_kg = 0;
            $total_m3 = 0;
            $orderPackages = $order->packages;
            if (count($orderPackages)) {
                foreach ($orderPackages as $key => $item) {
                    if ($item->weight) {
                        $total_kg += $item->weight;
                    } else {
                        $total_m3 += ($item->height * $item->length * $item->width);
                    }
                }
            }

            if ($total_kg || $total_m3) {
                $totalMoney = $total_kg * $customerDB->discount_by_kg + $total_m3 * $customerDB->discount_by_m3;
                $fund = Fund::query()->where('current_balance', '>=', $totalMoney)->first();

                // Nếu không tìm dc ai thì ghi log vào đơn hàng
                if (is_null($fund)) {
                    // Thanh toán số tiền hoa hồng $số tiền  cho khách $ABC thành công / thất bại
                    $contentLog = 'Thanh toán số tiền hoa hồng ' . number_format($totalMoney, 0, ".",
                            ".") . 'đ cho khách hàng ' . $customerIntroduce->code . ' thất bại';

                    dispatch(new MakeLogOrderStatusDone($userOnline, $contentLog, $order))
                        ->delay(now()->addSeconds(2))
                        ->onQueue('sent-noti-admin');
                }

                if ($fund) {
                    // Ghi log vào đơn
                    $contentLog = 'Thanh toán số tiền hoa hồng ' . number_format($totalMoney, 0, ".",
                            ".") . 'đ cho khách hàng ' . $customerIntroduce->code . ' thành công';

                    dispatch(new MakeLogOrderStatusDone($userOnline, $contentLog, $order))
                        ->delay(now()->addSeconds(2))
                        ->onQueue('sent-noti-admin');

                    // Trừ tiền từ quỹ
                    $fundTypePay8 = FundTypePay::query()->where('code', 8)->first();
                    if ($fundTypePay8) {
                        // Trừ tiền vào quỹ
                        $contentLog = 'Thanh toán số tiền hoa hồng ' . number_format($totalMoney, 0, ".",
                                ".") . 'đ từ đơn hàng ' . $order->code . ' cho khách hàng ' . $customerIntroduce->code . ' thành công';
                        $data_fund = [
                            'type_object' => 2,
                            'customer_code' => $customerIntroduce->code,
                            'type_pay' => 2,

                            'fund_id' => $fund->id,
                            'fund_type_pay_id' => $fundTypePay8->id,
                            'fund_type_pay_code' => $fundTypePay8->code,

                            'money' => $totalMoney,
                            'note' => $contentLog,
                            'lock' => 1
                        ];
                        (new FundTransactionService())->store($data_fund);
                    }

                    // Nạp tiền vào ví khách
                    $amount = $totalMoney;
                    $content = $contentLog;
                    $customer_id = $customerIntroduce->id;
                    $soure = $order;

                    (new TransactionService())->setTransactionIncrement(
                        $amount,
                        TransactionConstant::STATUS_REFUND,
                        $content,
                        $customer_id,
                        $soure,
                        $fund ? $order : null
                    );
                }
            }
        }
    }

    public function processDataUpdateLevel($customerId, $customer = null): bool
    {
        try {
            $customerInfo = $customer ?? Customer::with(["customerOffers"])->where("id", $customerId)->first();
            // Level 0: Khách hàng tiềm năng
            $levelUpdate = CustomerConstant::LEVEL_VALUE_MAX_0;
            $hasLevelRate = isset($customerInfo->customerOffers) && $customerInfo->customerOffers->level_rate > 0;
            $checkOrderCost = Order::query()
                ->where("customer_id", $customerId)
                ->where("status", OrderConstant::KEY_STATUS_DONE)
                ->sum('order_cost');

            if ($checkOrderCost > CustomerConstant::LEVEL_VALUE_MAX_0 && $checkOrderCost <= CustomerConstant::LEVEL_VALUE_MAX_1) {
                // Update lên level 1
                $levelUpdate = CustomerConstant::CUSTOMER_LEVEL_1;
            } elseif ($checkOrderCost > CustomerConstant::LEVEL_VALUE_MAX_1 && $checkOrderCost <= CustomerConstant::LEVEL_VALUE_MAX_2) {
                // Update lên level 2
                $levelUpdate = CustomerConstant::CUSTOMER_LEVEL_2;
            } elseif ($checkOrderCost > CustomerConstant::LEVEL_VALUE_MAX_2 && $checkOrderCost <= CustomerConstant::LEVEL_VALUE_MAX_3) {
                // Update lên level 3
                $levelUpdate = CustomerConstant::CUSTOMER_LEVEL_3;
            } elseif ($checkOrderCost > CustomerConstant::LEVEL_VALUE_MAX_3 && $checkOrderCost <= CustomerConstant::LEVEL_VALUE_MAX_4) {
                // Update lên level 4
                $levelUpdate = CustomerConstant::CUSTOMER_LEVEL_4;
            } elseif ($checkOrderCost > CustomerConstant::LEVEL_VALUE_MAX_4) {
                // Update lên level 5
                $levelUpdate = CustomerConstant::CUSTOMER_LEVEL_5;
            }

            if ($levelUpdate != $customerInfo->level) {
                $customerInfo->level = $levelUpdate;
                $customerInfo->save();
            }

            if ($hasLevelRate && $customerInfo->customerOffers->level_rate < $levelUpdate) {
                CustomerOffer::query()->where("customer_id", $customerId)->update([
                    "level_rate" => $levelUpdate
                ]);
            }
            return true;
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return false;
        }
    }

    public function getUserInOrder($orderId)
    {
        $arrayUser = [];

        if (!$orderId) {
            return $arrayUser;
        }
        $order = Order::query()->with(['staffOrder', 'staffQuotation', 'staffGroup'])->find($orderId);
        if ($order) {
            if ($order->staffOrder) {
                $arrayUser[] = [
                    'id' => $order->staffOrder->id,
                    'name' => $order->staffOrder->name
                ];
            }
            if ($order->staffQuotation) {
                $arrayUser[] = [
                    'id' => $order->staffQuotation->id,
                    'name' => $order->staffQuotation->name
                ];
            }
            if ($order->staffGroup) {
                $arrayUser[] = [
                    'id' => $order->staffGroup->id,
                    'name' => $order->staffGroup->name
                ];
            }
        }

        return $arrayUser;
    }

    public function refundOrderCancel(Order $order)
    {
        DB::beginTransaction();

        try {
            $inspectioned = request()->input('inspectioned');
            $customerId = $order->customer_id;

            $amount = $order->deposit_cost;
            $orderRefund = $order->orderRefunds()
                            ->where('status', OrderRefund::STATUS_PROCESSING)
                            ->where('type_refund', OrderConstant::IS_CREATE_CANCEL)
                            ->first();
            if(($orderRefund && $orderRefund->inspectioned == 1) || $inspectioned == 1) {
                // Nếu có yêu hủy đơn và tích đã sử dụng dịch vụ kiểm đếm thì bỏ qua phí kiểm đếm hoàn cho khách
                if($order->inspection_cost > 0) {
                    $amount -= $order->inspection_cost;
                }
                if($order->advanced_inspection_cost > 0) {
                    $amount -= $order->advanced_inspection_cost;
                }
                (new ActivityService())->setOrderLog(
                    $order,
                    __(
                        "activity.order_inspectioned",
                    ),
                    $order->id ?? 0
                );
            }

            $content = 'Hoàn tiền ' . number_format($amount) . 'vnđ' . ' Cho đơn hàng mã: ' . $order->code . '. Do đơn bị huỷ';

            (new \App\Services\TransactionService())->setTransactionIncrement(
                $amount,
                \App\Constants\TransactionConstant::STATUS_REFUND,
                $content,
                $customerId,
                $order,
                $order
            );

            ReportCustomer::query()->where('customer_id', $customerId)->increment('discount_amount', $amount);

            $this->updateNotiLogic($customerId, $content);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::info('Refund order cancel # '. $order->code . 'errors: ' .$e->getMessage());
        }
    }

    private function updateNotiLogic($customerId, $content)
    {
        $notificationService = \App::make('App\Services\NotificationService');

        $data_customer = [
            'content'  => $content
        ];
        $data = [
            'group'           => 8,
            'name'            => 8010,
            'data'            => json_encode($data_customer),
            'time_create'     => time(),
            'customer_id'     => $customerId,
            'status'          => 1
        ];

        $notificationService->store($data);
    }

    public function listCancel()
    {
        $orders = $this->_model->select([
            'orders.id as order_id',
            'orders.code as order_code',
            'order_status_times.status_6 as time',
            'orders.reason_cancel',
            'users.name as user_name'
            ])->join('order_status_times', 'order_status_times.order_id', 'orders.id')
            ->leftJoin('users', 'users.id', 'orders.staff_cancel_id')
            ->when(request()->input('order_code'), function($q){
                $q->where('orders.code', request()->input('order_code'));
            })
                        ->where('orders.status', OrderConstant::KEY_STATUS_CANCEL)->orderBy('time', 'desc')->paginate();
        return resSuccessWithinData(new $this->_paginateResource($orders, OrderListCancelResource::class));
    }

    /**
     * @throws Exception
     */
    public function changeSupplier(array $data)
    {
        $orderId = $data['order_id'];
        $order = Order::query()->findOrFail($orderId);
        // Check permission
        $this->checkPermissionChangeSupplier($order);
        DB::transaction(function () use ($order, $data) {
            $supplierTo = $data['supplier_id_to'];
            $supplier_id_from = $data['supplier_id_from'] ?? 0;
            $customerId = $data['customer_id'];
            $user = Auth::user();
            // check new supplier
            if(!empty($data['is_new'])) {
                $supplierTo = $this->getSupplierTo($customerId, $user, $data);
            }
            $orderSupplierCheck = OrderSupplier::query()
                ->where('supplier_id', $supplier_id_from)
                ->where('order_id', $order->id)->first();
            // change Supplier
            $this->saveChangeSupplier($order, $supplierTo, $data);
            $order->update(['has_edit' => 1, 'thuongdo_id' => null]);
            $totalPriceOrder = 0;

            // Nếu đơn là chính ngạch. Thì cái đơn gốc phải update lại supplier là đơn chính ngạch

            if($orderSupplierCheck && $orderSupplierCheck->official == OrderConstant::OFFICIAL) {
                OrderSupplier::query()
                    ->where('order_id', $order->id)
                    ->whereIn('supplier_id',[$supplierTo, $supplier_id_from])
                    ->update(['official' => $orderSupplierCheck->official, 'type_official' => $orderSupplierCheck->type_official]);
            }
            $this->updateOrderSupplierAndOrder($order, $order->order_percent, $totalPriceOrder);
            if ($order->is_inspection) {
                $this->updateInspectionCost($order);
            }
            if ($supplierTo != $supplier_id_from) {
                $content = 'order_supplier_merge';
                if (
                    OrderDetail::query()->where('order_id', $order->id ?? 0)
                        ->where('supplier_id', $supplier_id_from)
                        ->first()
                ) {
                    $content = 'order_supplier_split';
                }
                $value = Supplier::query()->where('id', $supplier_id_from)->pluck('name')->all();
                $values = Supplier::query()->where('id', $supplierTo)->pluck('name')->all();
                if (count($values) > 0)
                    (new ActivityService())->setOrderLog(
                        $order,
                        __(
                            "activity.$content",
                            [
                                'name' => getCurrentUser()->name,
                                'value' => implode(',', $values),
                                'values' => implode(',', $value)
                            ]
                        ),
                        $order->id ?? 0
                    );
            }
        });
    }

    public function getSupplierTo(int $customerId, $user, array $data)
    {
        $supplier = Supplier::query()->firstOrCreate([
            'customer_id' => $customerId,
            'organization_id' => $user->organization_id ?? 0,
            'name' => $data['supplier_name'],
            'code' => $data['supplier_code'],
            'type' => SupplierConstant::TYPE_ONLINE,
        ]);

        return $supplier->id ?? 0;
    }

    public function waitingDeposit($params): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $per_page = !empty($params['per_page']) ? $params['per_page'] : 15;
        $select = [
            'users.id as user_id',
            'users.code as user_code',
            'users.name as user_name',
            DB::raw('count(orders.id) AS count_order'),
            DB::raw('SUM(orders.order_cost) AS total_order_cost'),
            DB::raw('SUM(orders.order_fee) AS total_order_fee'),
            DB::raw('SUM((orders.order_cost - orders.order_cost_real) +
                ((orders.china_shipping_cost - orders.china_shipping_cost_real) * orders.exchange_rate) +
                (orders.bonus * orders.exchange_rate)) AS total_negotiate'
            )
        ];
        $query = $this->sqlWaitingDeposit($params, $select)->groupBy(['users.id']);
        return $query->paginate($per_page);
    }

    public function totalWaitingDeposit($params)
    {
        $select = [
            DB::raw('count(orders.id) AS count_order'),
            DB::raw('SUM(orders.order_cost) AS total_order_cost'),
            DB::raw('SUM(orders.order_fee) AS total_order_fee'),
            DB::raw('SUM((orders.order_cost - orders.order_cost_real) +
                ((orders.china_shipping_cost - orders.china_shipping_cost_real) * orders.exchange_rate) +
                (orders.bonus * orders.exchange_rate)) AS total_negotiate'
            )
        ];
        return $this->sqlWaitingDeposit($params, $select)->first();
    }

    private function sqlWaitingDeposit($params, $select): Builder
    {
        $time = data_get($params, 'time');
        [$startDate, $endDate] = getStartDateEndDateByType($time);
        if (!empty($params['month']) && $time == 'month') {
            $month = $params['month'];
            $array = explode('-', $month);
            $year = $array[0];
            $month = $array[1];
            $data = $this->getStartDateEndDateOfMonth($month, $year);
            $startDate = $data['start_date'] . ' 00:00:00';
            $endDate = $data['end_date'] . ' 23:59:59';
        }
        $query = $this->_model->query()->select($select)
                        ->join('users', 'users.id', 'orders.staff_quotation_id')
                        ->where('orders.status', OrderConstant::KEY_STATUS_DONE)
                        ->whereBetween('orders.date_done', [$startDate, $endDate]);
        $auth = getCurrentUser();
        if($auth->hasPermissionName('report.view-only-quote-by-self')) {
            $query->where('users.id', $auth->id);
        }
        return $query;
    }
    private function getStartDateEndDateOfMonth($month, $year): array
    {
        $fistDayOfMonth = date("Y-m-d", strtotime("first day of ".$year . "-" . $month));
        $lastDayOfMonth = date("Y-m-d", strtotime("last day of ".$year . "-" . $month));
        return [
            'start_date' => $fistDayOfMonth,
            'end_date' => $lastDayOfMonth
        ];
    }

    private function sqlOrdered($params, $select): Builder
    {
        $time = data_get($params, 'time');
        [$startDate, $endDate] = getStartDateEndDateByType($time);
        if (!empty($params['month']) && $time == 'month') {
            $month = $params['month'];
            $array = explode('-', $month);
            $year = $array[0];
            $month = $array[1];
            $data = $this->getStartDateEndDateOfMonth($month, $year);
            $startDate = $data['start_date'] . ' 00:00:00';
            $endDate = $data['end_date'] . ' 23:59:59';
        }
        $query = $this->_model->query()->select($select)
                        ->join('users', 'users.id', 'orders.staff_order_id')
                        ->where('orders.status', OrderConstant::KEY_STATUS_DONE)
                        ->whereBetween('orders.date_done', [$startDate, $endDate]);
        $auth = getCurrentUser();
        if($auth->hasPermissionName('report.view-only-quote-by-self')) {
            $query->where('users.id', $auth->id);
        }
        return $query;
    }

    public function ordered($params): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $per_page = !empty($params['per_page']) ? $params['per_page'] : 15;
        $select = [
            'users.id as user_id',
            'users.code as user_code',
            'users.name as user_name',
            DB::raw('count(orders.id) AS count_order'),
            DB::raw('SUM(orders.order_cost) AS total_order_cost'),
            DB::raw('SUM(orders.order_fee) AS total_order_fee'),
            DB::raw('SUM((orders.order_cost - orders.order_cost_real) +
                ((orders.china_shipping_cost - orders.china_shipping_cost_real) * orders.exchange_rate) +
                (orders.bonus * orders.exchange_rate)) AS total_negotiate'
            )
        ];
        $query = $this->sqlOrdered($params, $select)->groupBy(['users.id']);
        return $query->paginate($per_page);
    }

    public function totalOrdered($params)
    {
        $select = [
            DB::raw('count(orders.id) AS count_order'),
            DB::raw('SUM(orders.order_cost) AS total_order_cost'),
            DB::raw('SUM(orders.order_fee) AS total_order_fee'),
            DB::raw('SUM((orders.order_cost - orders.order_cost_real) +
                ((orders.china_shipping_cost - orders.china_shipping_cost_real) * orders.exchange_rate) +
                (orders.bonus * orders.exchange_rate)) AS total_negotiate'
            )
        ];
        return $this->sqlOrdered($params, $select)->first();
    }

    public function checkPermissionChangeSupplier($order)
    {
        // Check trạng thái đơn
        $arr_status = [
            OrderConstant::KEY_STATUS_WAITING_QUOTE,
            OrderConstant::KEY_STATUS_WAITING_DEPOSIT,
            OrderConstant::KEY_STATUS_RECONFIRM
        ];

        if (!in_array($order->status ?? 0, $arr_status)) {
            throw new Exception('Đơn không được phép thay đổi nhà cung cấp!');
        }
    }

    public function saveChangeSupplier($order, int $supplierTo, array $data): bool
    {
        $supplierFrom = $data['supplier_id_from'];
        if ($supplierFrom == $supplierTo) {
            return true;
        }

        $products = collect($data['products']);
        // update details change
        foreach ($products as $product) {
            $product['supplier_id'] = $supplierTo;
            $product = $this->removeAttributeProduct($product);
            OrderDetail::query()->where('id', $product['id'])->update($product);
        }
        // giữ lại order supplier
        $oldSuppliers = OrderSupplier::query()
            ->where('order_id', $order->id)
            ->get([
                'order_id',
                'is_china_shipping',
                'supplier_id',
                'china_shipping_cost',
                'is_inspection',
                'is_advanced_inspection',
                'is_woodworking',
                'is_shock_proof'
            ])
            ->keyBy('supplier_id');
        /** update order suppliers */
        // delete records old of order suppliers
        OrderSupplier::query()->where('order_id', $order->id)->delete();
        // save order supplier
        $orderDetails = OrderDetail::query()->where('order_id', $order->id)->get()->toArray();
        $this->insertOrderSupplier($order, $orderDetails, $data, $oldSuppliers);
        return true;
    }

    public function removeAttributeProduct(array $product): array
    {
        unset($product['unit_price_old']);
        unset($product['order_detail_id']);
        unset($product['quantity_old']);
        unset($product['exchange_rate']);
        unset($product['category']);
        unset($product['total_quantity_package']);
        unset($product['text_note_last']);
        unset($product['bill_code']);
        unset($product['unit_price']);
        unset($product['amount']);
        unset($product['classification_all']);
        unset($product['classification_new']);
        unset($product['classification_1']);
        unset($product['classification_2']);
        unset($product['image']); // lỗi ảnh
        unset($product['note_by_admin']);
        unset($product['is_customer_note']);
        unset($product['text_note_priority']);
        unset($product['note_china']);
        unset($product['stt']);
        unset($product['mp_sku_id']);
        unset($product['mp_sku_id_fast']);
        unset($product['is_edit_note_classification']);

        return $product;
    }

    private function insertOrderSupplier(Order $order, array $orderDetail, array $data, $oldSuppliers): void
    {
        $suppliers = collect($orderDetail)->groupBy('supplier_id');
        $orderSuppliers = [];
        $countSuppliers = count($suppliers);
        $accountService = new AccountingService();

        $isInspection  = $data['is_inspection'] ?? false;
        $isWoodworking = $data['is_woodworking'] ?? false;
        $isAdvancedInspection  = $data['is_advanced_inspection'] ?? 1;
        $isChinaShipping  = $data['is_china_shipping'] ?? 0;

        foreach ($suppliers as $supplier => $value) {
            $oldData = $oldSuppliers[$supplier] ?? null;
            $orderCost = AccountingHelper::getCosts(
                $value->sum('amount_cny') * $order->exchange_rate
            );
            $productsCount = array_sum(Arr::pluck($value, 'quantity'));
            $orderSuppliers[] = [
                'china_shipping_cost' => $oldData->china_shipping_cost ?? 0,
                'order_id' => $order->id,
                'is_china_shipping' => $isChinaShipping ?? 0,
                'supplier_id' => $supplier,
                'order_cost' => $orderCost,
                'is_shock_proof' => $oldData->is_shock_proof ?? $order->is_shock_proof,
                'order_fee' => $orderFee = $accountService->getOrderFee($orderCost, $order->customer_id),
                // 'order_fee' => $orderFee = $accountService->getOrderFee($orderCost, $orderPercent), // Logic mới
                // 'order_fee' => $orderCost * $orderPercent / 100, // Logic mới chỉ lấy theo % với các Suppiler
                'is_inspection' =>  $oldData->is_inspection ?? $isInspection,
                'is_advanced_inspection' =>  $oldData->is_advanced_inspection ?? $isAdvancedInspection,
                'is_woodworking' => $oldData->is_woodworking ?? $isWoodworking,
                'delivery_type' => $order->delivery_type,
                'inspection_cost' => (bool)$isInspection ? $accountService->getInspectionCost($productsCount) : 0,
                'advanced_inspection_cost' => $isAdvancedInspection == 2 ? $accountService->getAdvancedInspectionCost($productsCount) : 0,
                'international_shipping_cost' => $internationCost = AccountingHelper::getCosts(
                    $order->international_shipping_cost / $countSuppliers
                ),
                'discount_cost' => $accountService
                    ->getDiscountCost($orderFee + $internationCost, $order->customer_id),
                'created_at' => now(),
            ];
        }

        OrderSupplier::query()->insert($orderSuppliers);
    }

    public function updateInspectionCost(Order $order, $unitInspectionCost = 0)
    {
        // Phí kiểm đếm logic đang theo supplier. Nên update lại theo supplier
        $orderDetails = OrderDetail::query()->where('order_id', $order->id)->get();
        $suppliers = OrderSupplier::query()->where('order_id', $order->id)->get();

        $arraySupplier = [];
        foreach ($suppliers as $supplier) {
            if($supplier->is_inspection) {
                $arraySupplier[] = $supplier->supplier_id;
            }
        }

        $totalQuantity = 0;
        foreach ($orderDetails as $detail) {
            if(in_array($detail->supplier_id, $arraySupplier)){
                $totalQuantity += $detail->quantity;
            }
        }
        // nếu k có sản phẩm được kiểm đếm
        if($totalQuantity == 0) {
            $order->update(['is_inspection' => 0, 'inspection_cost' => $totalQuantity]);
            return true;
        }
        $accountingService = app()->make(AccountingService::class);
        $totalInspectionCost = $accountingService->getInspectionCost($totalQuantity);

        $order->update(['inspection_cost' => $totalInspectionCost]);
        if(!$this->checkSplitOrder($order) || $unitInspectionCost == 0) {
            $unitInspectionCost = $totalInspectionCost/ $totalQuantity;
        } else {
            $order->update(['inspection_cost' => $unitInspectionCost * $totalQuantity]);
        }
        $totalInspectionCost = [];
        foreach ($orderDetails as $key => $detail) {
            $totalInspectionCost[$detail->supplier_id][$key] = $unitInspectionCost * $detail->quantity;
        }

        foreach ($suppliers as $supplier) {
            foreach ($totalInspectionCost as $supplierId => $value) {
                if($supplier->supplier_id == $supplierId && $supplier->is_inspection) {
                    OrderSupplier::query()
                        ->where('id', $supplier->id)
                        ->update(['inspection_cost' => array_sum($value)]);
                }
                if(!$supplier->is_inspection) {
                    OrderSupplier::query()
                        ->where('id', $supplier->id)
                        ->update(['inspection_cost' => 0]);
                }
            }
        }
    }

    public function updateAdvancedInspectionCost(Order $order): bool
    {
        $orderDetails = OrderDetail::query()->where('order_id', $order->id)->get();
        $suppliers = OrderSupplier::query()->where('order_id', $order->id)->get();

        $arraySupplier = [];
        foreach ($suppliers as $supplier) {
            if($supplier->is_advanced_inspection == 2) {
                $arraySupplier[] = $supplier->supplier_id;
            }
        }

        $totalQuantity = 0;
        foreach ($orderDetails as $detail) {
            if(in_array($detail->supplier_id, $arraySupplier)){
                $totalQuantity += $detail->quantity;
            }
        }
        // nếu k có sản phẩm được kiểm đếm
        if($totalQuantity == 0) {
            $order->update(['is_advanced_inspection' => 1, 'advanced_inspection_cost' => $totalQuantity]);
            return true;
        }
        $accountingService = app()->make(AccountingService::class);
        $totalInspectionCost = $accountingService->getAdvancedInspectionCost($totalQuantity);

        $order->update(['advanced_inspection_cost' => $totalInspectionCost]);
        $totalInspectionCost = [];
        foreach ($orderDetails as $key => $detail) {
            $totalInspectionCost[$detail->supplier_id][$key] = $accountingService->getAdvancedInspectionCost($detail->quantity);
        }

        foreach ($suppliers as $supplier) {
            foreach ($totalInspectionCost as $supplierId => $value) {
                if($supplier->supplier_id == $supplierId && $supplier->is_advanced_inspection == 2) {
                    OrderSupplier::query()
                        ->where('id', $supplier->id)
                        ->update(['advanced_inspection_cost' => array_sum($value)]);
                }
                if($supplier->is_advanced_inspection == 1) {
                    OrderSupplier::query()
                        ->where('id', $supplier->id)
                        ->update(['advanced_inspection_cost' => 0]);
                }
            }
        }
        return true;
    }

    // Hàm tính là đơn tách
    public function checkSplitOrder($order)
    {
        if($order->code_parent != null) {
            return true;
        }

        if($order->code_parent == null && Order::query()->where('code_parent', $order->code)->exists()) {
            return true;
        }

        return false;
    }

    public function getOrderPercent($order)
    {
        $orderPercent = $order->order_percent;

        $customer     = $order->customer;
        if (in_array($order->status, [OrderConstant::KEY_STATUS_WAITING_QUOTE, OrderConstant::KEY_STATUS_WAITING_DEPOSIT])) {
            $orderPercent = !empty($customer) ? data_get($customer, 'offer.offer_rate') : '';
        }
        $orderPercent = $orderPercent ? $orderPercent :
            (new ConfigService())->getResultFromValueByBetweenMinMax(
                ConfigConstant::SERVICE_ORDER_FEE,
                $order->order_cost
            );

        return $orderPercent > 100 ? OrderConstant::ORDER_PERCENT_DEFAULT : $orderPercent;
    }

    public function updateOrderSupplierWhenChangeExchangeRate($order)
    {
        $orderId = $order->id;
        $orderDetails = OrderDetail::query()->where(
            ['order_id' => $orderId]
        )->groupBy('supplier_id')->selectRaw(
            "sum(unit_price_cny * quantity) as amount_cny, sum(quantity) as quantity, supplier_id, sum(unit_price_cny * quantity) as unit_price_cny"
        )->get();

        $countOrderDetails = $orderDetails->count();
        $inspectionCost = 0;
        $orderCost = 0;
        $orderCostOld = 0;
        $orderFee = 0;
        $orderPercent = $order->order_percent;

        foreach ($orderDetails as $detail) {
            $supplierId = $detail->supplier_id;
            $orderSupplier = OrderSupplier::query()->where(
                ['order_id' => $orderId, 'supplier_id' => $supplierId]
            )->first();

            if (!$orderSupplier) {
                $orderSupplier = OrderSupplier::query()->create(
                    [
                        'order_id' => $orderId,
                        'supplier_id' => $supplierId,
                        'is_inspection' => $order->is_inspection,
                        'is_woodworking' => $order->is_woodworking,
                        'is_shock_proof' => $order->is_shock_proof
                    ]
                );
            }

            $data = [];
            $data['order_cost'] = $orderCostTmp = AccountingHelper::getCosts(
                $detail->amount_cny * $order->exchange_rate
            );
            $orderCostOldTmp = AccountingHelper::getCosts(
                $detail->unit_price_cny * $order->exchange_rate
            );
            $data['order_fee'] = $orderFeeTmp =
                $countOrderDetails > 1 ? (new AccountingService())
                    ->getOrderFee($orderCostTmp, $order->customer_id, $orderPercent, 'isMultipleSupplier')
                    : (new AccountingService())
                    ->getOrderFee($orderCostTmp, $order->customer_id, $orderPercent);
            $data['discount_cost'] = (new AccountingService())->getDiscountCost($orderFeeTmp, $order->customer_id);
            $data['is_inspection'] = $orderSupplier->is_inspection;

            $orderSupplier->update($data);
            $orderCost += $orderCostTmp;
            $orderCostOld += $orderCostOldTmp;
            $orderFee += $orderFeeTmp;
        }
        $default = json_decode((new ConfigService())->getValueByKey(ConfigConstant::SERVICE_ORDER_FEE), true)[0]['default'];

        if($orderFee > 0 && $orderFee < $default) {
            foreach ($orderDetails as $detail) {
                $supplierId = $detail->supplier_id;
                $orderSupplier = OrderSupplier::query()->where(
                    ['order_id' => $orderId, 'supplier_id' => $supplierId]
                )->first();

                $data['order_cost'] = AccountingHelper::getCosts(
                    $detail->unit_price_cny * $order->exchange_rate
                );
                $data['order_fee'] = $orderFeeTmp = $orderSupplier->order_fee / $orderFee * $default;
                $data['discount_cost'] = (new AccountingService())->getDiscountCost($orderFeeTmp, $order->customer_id);

                $orderSupplier->update($data);
            }
            $orderFee = $default;
        }
        $order->order_cost = $orderCost;
        $order->order_cost_old = $orderCostOld;
        $order->order_percent = $orderPercent;
        $order->order_fee = $orderFee;
        $order->save();
    }

    public function updateOrderSupplierWhenChangeDiscountCost($order, $discountPercent)
    {
        $orderId = $order->id;
        $orderDetails = OrderDetail::query()->where(
            ['order_id' => $orderId]
        )->groupBy('supplier_id')->selectRaw(
            "sum(unit_price_cny * quantity) as amount_cny, sum(quantity) as quantity, supplier_id, sum(unit_price_cny * quantity) as unit_price_cny"
        )->get();

        $countOrderDetails = $orderDetails->count();
        $orderCost = 0;
        $orderCostOld = 0;
        $orderFee = 0;
        $orderPercent = $order->order_percent;

        foreach ($orderDetails as $detail) {
            $supplierId = $detail->supplier_id;
            $orderSupplier = OrderSupplier::query()->where(
                ['order_id' => $orderId, 'supplier_id' => $supplierId]
            )->first();
            $data = [];
            $data['order_cost'] = $orderCostTmp = AccountingHelper::getCosts(
                $detail->amount_cny * $order->exchange_rate
            );
            $orderCostOldTmp = AccountingHelper::getCosts(
                $detail->unit_price_cny * $order->exchange_rate
            );
            $data['order_fee'] = $orderFeeTmp =
                $countOrderDetails > 1 ? (new AccountingService())
                    ->getOrderFee($orderCostTmp, $order->customer_id, $orderPercent, 'isMultipleSupplier')
                    : (new AccountingService())
                    ->getOrderFee($orderCostTmp, $order->customer_id, $orderPercent);
            $data['discount_cost'] = $discountPercent * $orderFeeTmp / 100;
            $data['is_inspection'] = $orderSupplier->is_inspection;

            $orderSupplier->update($data);
            $orderCost += $orderCostTmp;
            $orderCostOld += $orderCostOldTmp;
            $orderFee += $orderFeeTmp;
        }
        $default = json_decode((new ConfigService())->getValueByKey(ConfigConstant::SERVICE_ORDER_FEE), true)[0]['default'];

        if($orderFee > 0 && $orderFee < $default) {
            foreach ($orderDetails as $detail) {
                $supplierId = $detail->supplier_id;
                $orderSupplier = OrderSupplier::query()->where(
                    ['order_id' => $orderId, 'supplier_id' => $supplierId]
                )->first();

                $data['order_cost'] = AccountingHelper::getCosts(
                    $detail->unit_price_cny * $order->exchange_rate
                );
                $data['order_fee'] = $orderFeeTmp = $orderSupplier->order_fee / $orderFee * $default;
                $data['discount_cost'] =  $discountPercent * $orderFeeTmp / 100;

                $orderSupplier->update($data);
            }
            $orderFee = $default;
        }
        $order->order_cost = $orderCost;
        $order->order_cost_old = $orderCostOld;
        $order->order_percent = $orderPercent;
        $order->order_fee = $orderFee;
        $order->save();
    }

    public function splitOrder($supplierId, $orderId)
    {
        /** @var Order $order */
        // Đơn gốc
        $order = Order::query()->findOrFail($orderId);
        $condition = ['supplier_id' => $supplierId, 'order_id' => $orderId];
        // supplier được tách ra
        $orderSupplier = OrderSupplier::query()->where($condition)->first();
        $order->china_shipping_cost = $order->china_shipping_cost - optional($orderSupplier)->china_shipping_cost;
        $order->save();
        // Order này là order được tách ra để gán vào cái ncc được tách
        $newOrder = $order->replicate();
        $newOrder->is_china_shipping = $orderSupplier->is_china_shipping;
        $newOrder->code = RandomHelper::checkDuplicateOrderCode($order->customer_id);
        $newOrder->created_at = Carbon::now()->format('Y-m-d H:i:s');
        // Check supplier khác xem các supplier khác có chính ngạch không
        /** NCC được tách là chính ngạch thì cần check xem các NCC còn lại có chính ngạch không
         * Nếu các NCC khác có chính ngạch thì phải check xem NCC đấy là loại chính ngạch nào để update lại vào đơn*/
        // case này là case tách đơn chính ngạch
        if($orderSupplier->official == OrderConstant::OFFICIAL) {
            // check các ncc còn lại
            $orderSupplierCheck = OrderSupplier::query()
                ->where('order_id', $orderId)
                ->where('official', OrderConstant::OFFICIAL)
                ->where('supplier_id', '!=', $supplierId)
                ->first();

            if(empty($orderSupplierCheck)) {
                // Check lại để update cho đơn cũ
                $order->official = OrderConstant::OFFICIAL_SALE;
                $order->type_official = 0;
                $order->save();
            }
            // tách 2-1 mà đơn gốc là 2-1 thì đơn gốc phải thành 22
            // tách 2-2 mà đơn gốc là 2-1 thì đơn gốc giữ nguyên
            $orderSupplierCheckNew = OrderSupplier::query()
                ->where('order_id', $orderId)
                ->where('type_official','!=', $order->type_official)
                ->where('official', OrderConstant::OFFICIAL)
                ->where('supplier_id', '!=', $supplierId)
                ->first();

            if(!empty($orderSupplierCheckNew)) {
                $order->official = $orderSupplierCheckNew->official;
                $order->type_official = $orderSupplierCheckNew->type_official;
                $order->save();
            }
        }
        $newOrder->official = $orderSupplier->official;
        $newOrder->type_official = $orderSupplier->type_official;
        // Nếu order supplier bán chính ngạch thì đơn mới cũng phải bán chính ngạch
        if($orderSupplier->official == OrderConstant::OFFICIAL_SALE) {
            $newOrder->official = OrderConstant::OFFICIAL_SALE;
            $newOrder->type_official = 0;
        }

        $newOrder->china_shipping_cost = optional($orderSupplier)->china_shipping_cost;
        $newOrder->save();
        $this->setStatusTime($newOrder->id, $order->status);
        OrderNote::query()->where($condition)->update(['order_id' => $newOrder->id]);

        OrderSupplier::query()->where($condition)->update(['order_id' => $newOrder->id]);

        OrderDetail::query()->where($condition)->update(['order_id' => $newOrder->id]);

        //update giá của đơn mới
        $totalPriceOrder = 0;
        $this->updateOrderSupplierAndOrder($newOrder, $newOrder->order_percent, $totalPriceOrder);
        //update giá của đơn cũ
        $this->updateOrderSupplier($order);
        $reportCustomerService = app()->make(ReportCustomerService::class);
        $reportCustomerService->incrementByKey(CustomerConstant::KEY_REPORT_ORDER, $order->customer_id);
        $reportCustomerService->incrementByKey(CustomerConstant::KEY_REPORT_ORDER_INCREMENT, $order->customer_id);
        if ($order->is_inspection) {
            $this->updateInspectionCost($order);
            $this->updateInspectionCost($newOrder);
        }
        // lưu lịch sử đơn mới và cũ
        (new ActivityService())->setOrderLog(
            Order::query()->find($order->id),
            __(
                "activity.order_split_old",
                [
                    'order_code' => $newOrder->code
                ]
            ),
            $orderId
        );

        (new ActivityService())->setOrderLog(
            Order::query()->find($newOrder->id),
            __(
                "activity.order_split_new",
                [
                    'order_code' => $order->code
                ]
            ),
            $newOrder->id
        );

        return true;
    }

    public function updateFeeOther($orderId, $params)
    {
        if (isset($params['suppliers'])) {
            foreach ($params['suppliers'] as  $supplier) {
                $orderSupplier = OrderSupplier::query()
                    ->where('order_id', $orderId)
                    ->where('supplier_id', $supplier['id'])
                    ->first();
                if(!$orderSupplier) {
                    continue;
                }
                $china_shipping_cost_old = $orderSupplier->china_shipping_cost;
                $china_shipping_cost_real_old = $orderSupplier->china_shipping_cost_real;
                $order_cost_real_old = $orderSupplier->order_cost_real;

                $orderSupplier->update([
                        'china_shipping_cost' => $supplier['china_shipping_cost'],
                        'china_shipping_cost_real' => $supplier['china_shipping_cost_real'],
                        'order_cost_real' => $supplier['order_cost_real'],
                    ]
                );

                $modifies = [];
                if($china_shipping_cost_old != $supplier['china_shipping_cost']) {
                    $modifies['china_shipping_cost'] = $supplier['china_shipping_cost'];
                }
                if($china_shipping_cost_real_old != $supplier['china_shipping_cost_real']) {
                    $modifies['china_shipping_cost_real'] = $supplier['china_shipping_cost_real'];
                }
                if($order_cost_real_old != $supplier['order_cost_real']) {
                    $modifies['order_cost_real'] = $supplier['order_cost_real'];
                }
                $object = $orderSupplier->supplier ? $orderSupplier->supplier->name : '';
                $content = 'order_supplier_update';
                (new ActivityService())->productActivity($modifies, $orderId, $object, $content, $orderSupplier);
            }
        }
        $orderSupplier = OrderSupplier::query()
                    ->select([
                        DB::raw('SUM(china_shipping_cost_real) AS total_china_shipping_cost_real'),
                        DB::raw('SUM(order_cost_real) AS total_order_cost_real'),
                        DB::raw('SUM(china_shipping_cost) AS total_china_shipping_cost')
                    ])
                    ->where('order_id', $orderId)
                    ->first();
        // Sau khi cập nhật vào order_supplier thì capah nhật vào order
        $order = Order::where('id', $orderId)->first();
        if($order) {
            $order->order_cost_real = ($orderSupplier->total_order_cost_real ? $orderSupplier->total_order_cost_real : 0) * $order->exchange_rate;
            $order->china_shipping_cost_real = $orderSupplier->total_china_shipping_cost_real ? $orderSupplier->total_china_shipping_cost_real : 0;
            $order->china_shipping_cost = $orderSupplier->total_china_shipping_cost ? $orderSupplier->total_china_shipping_cost : 0;
            $order->save();
        }
    }

    public function destroy(string $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $order = $this->_model->newQuery()->with('packagesOrder')->findOrFail($id);
            $statuses = [
                OrderConstant::KEY_STATUS_WAITING_QUOTE,
                OrderConstant::KEY_STATUS_WAITING_DEPOSIT,
                OrderConstant::KEY_STATUS_RECONFIRM,
            ];
            if(!in_array($order->status, $statuses)) {
                $result = ['err' => 'Trạng thái đơn không hợp lệ!'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            $packagesOrder = $order->packagesOrder;
            if (!$packagesOrder->isEmpty()) {
                $result = ['err' => 'Không được xóa đơn hàng đã có kiện!'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            $this->_model->newQuery()->findOrFail($id)->delete();
            DB::commit();
            return resSuccess(trans('system.deleted'));

        } catch (Exception $e) {
            DB::rollBack();
            \Log::info($e);
            return resError();
        }
    }

    public function getDataExportActualPayment($params)
    {
        $query = $this->_model->newQuery()->select([
            'orders.id',
            'orders.created_at',
            'orders.code',
            'orders.order_cost',
            'orders.order_cost_real',
            'orders.china_shipping_cost',
            'orders.china_shipping_cost_real',
            'orders.exchange_rate',
            'orders.bonus',
            'orders.customer_id',
            'orders.staff_order_id',
            ])->with([
            'staffOrder' => function ($query) {
                $query->select('id', 'name');
            },
            'packagesOrder' => function ($query) {
                $query->select('id', 'order_id', 'code_po')->whereNotNull('code_po');
            }
        ]);
        if(!empty($params['customer_code'])) {
            $query->join('customers', 'customers.id', '=', 'orders.customer_id')
                    ->where(function($q) use ($params) {
                        return $q->where('customers.code', $params['customer_code'])
                                    ->orWhere('customers.phone_number', $params['customer_code']);
                    });
        }
        if(!empty($params['code'])) {
            $query->where('orders.code', $params['code']);
        }
        if(!empty($params['staff_order_id'])) {
            $query->where('orders.staff_order_id', $params['staff_order_id']);
        }
        if(!empty($params['ecommerce'])) {
            $query->where('orders.ecommerce', $params['ecommerce']);
        }
        if(!empty($params['status'])) {
            $query->where('orders.status', $params['status']);
        }
        if(!empty($params['code_po'])) {
            $arrCodePo = explode(';', $params['code_po']);
            $query->join('order_package', 'order_package.order_id', '=', 'orders.id')
                ->where(function($q) use ($arrCodePo){
                    return $q->where('order_package.order_type', Order::class)
                        ->whereIn('order_package.code_po', $arrCodePo);
                });
        }
        $dateDone = data_get($params, 'date_done');
        $dateOrdered = data_get($params, 'date_ordered');
        $dateWaitToPay = data_get($params, 'date_wait_to_pay');
        if (!is_null($dateDone) && is_array($dateDone)) {
            $start = $dateDone[0]. ' 00:00:00';
            $end = $dateDone[1]. ' 23:59:59';
            $query = $query->whereBetween('orders.date_done', [$start, $end]);
        }
        if (!is_null($dateOrdered) && is_array($dateOrdered)) {
            $start = $dateOrdered[0]. ' 00:00:00';
            $end = $dateOrdered[1]. ' 23:59:59';
            $query = $query->whereBetween('orders.date_ordered', [$start, $end]);
        }
        if (!is_null($dateWaitToPay) && is_array($dateWaitToPay)) {
            $start = $dateWaitToPay[0]. ' 00:00:00';
            $end = $dateWaitToPay[1]. ' 23:59:59';
            $query = $query->whereHas('orderStatusTime', function ($subQuery) use ($start, $end){
                $subQuery->whereBetween('status_8', [$start, $end]);
            });
        }
        // $timeStart = Carbon::now()->subMonths(1)->startOfMonth();
        // // Chỉ cho xuất từ 2 tháng đổ lại
        $query->where('orders.created_at', '>=', '2024-01-01 00:00:00');

        return $query;
    }

    public function updateQuickInspection($orderId, $params)
    {
        if (isset($params['suppliers'])) {
            $order = Order::findOrFail($orderId);
            $isInspection = $params['suppliers']['services']['is_inspection'];
            foreach ($params['suppliers'] as  $supplier) {
                $orderSupplier = OrderSupplier::query()
                    ->where('order_id', $orderId)
                    ->where('supplier_id', $supplier)
                    ->first();
                if(!$orderSupplier) {
                    continue;
                }
                $is_inspection = $orderSupplier->is_inspection;
                $orderSupplier->is_inspection = $isInspection;
                if(!$isInspection) {
                    $orderSupplier->inspection_cost = 0;
                }
                $orderSupplier->save();
                $modifies = [];
                $checkExits = OrderSupplier::where('order_id', $orderId)->where('is_inspection', 1)->first();
                if($checkExits) {
                    $order->is_inspection = 1;
                }else {
                    $order->is_inspection = 0;
                }
                $order->save();
                $this->updateServicePackageByOrder($order);
                if($is_inspection != $isInspection) {
                    $modifies['is_inspection'] = $isInspection;
                }
                $content = 'is_inspection';
                $object = '';
                (new ActivityService())->productActivity($modifies, $orderId, $object, $content, $orderSupplier);
            }
            if (in_array($order->status, [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY])) {
                $order->has_edit = 2;
            }
            if($order->is_inspection) {
                $this->updateInspectionCost($order);
            } else {
                $order->inspection_cost = 0;
                $order->save();
            }
        }
    }
    public function updateQuickAdvancedInspection($orderId, $params)
    {
        if (isset($params['suppliers'])) {
            $order = Order::findOrFail($orderId);
            $depositAmount = $order->deposit_cost ?? 0;
            $isAdvancedInspectionRequest = $params['suppliers']['services']['is_advanced_inspection'] ?? 1;
            foreach ($params['suppliers'] as  $supplier) {
                $orderSupplier = OrderSupplier::query()
                    ->where('order_id', $orderId)
                    ->where('supplier_id', $supplier)
                    ->first();
                if(!$orderSupplier) {
                    continue;
                }
                $is_advanced_inspection = $orderSupplier->is_advanced_inspection;
                $orderSupplier->is_advanced_inspection = $isAdvancedInspectionRequest;
                if($isAdvancedInspectionRequest == 1) {
                    $orderSupplier->advanced_inspection_cost = 0;
                }
                $orderSupplier->save();
                $modifies = [];
                $checkExits = OrderSupplier::query()
                    ->where('order_id', $orderId)
                    ->where('is_advanced_inspection', 2)
                    ->first();
                if($checkExits) {
                    $order->is_advanced_inspection = 2;
                }else {
                    $order->is_advanced_inspection = 1;
                }
                $order->save();
                $this->updateServicePackageByOrder($order);
                if($is_advanced_inspection != $isAdvancedInspectionRequest) {
                    $modifies['is_advanced_inspection'] = $isAdvancedInspectionRequest;
                }
                $content = 'is_advanced_inspection';
                $object = '';
                (new ActivityService())->productActivity(
                    $modifies,
                    $orderId,
                    $object,
                    $content,
                    $orderSupplier,
                    null,
                    0,
                    $orderSupplier->supplier_id
                );
            }
            $this->updateAdvancedInspectionCost($order);
            $orderAfter = Order::findOrFail($orderId);
            $totalAmountNotShip = $orderAfter->getTotalAmountNotShip();
            $deposit_amount = roundMoney($depositAmount);
            $deposit_cost_reality = roundMoney(((float) $totalAmountNotShip * (int) $orderAfter->deposit_percent) / 100);
            if ($deposit_cost_reality - 100 > $deposit_amount) {
                $order->is_up_order_cost = 1;
            } else {
                $order->is_up_order_cost = 0;
            }
            if (in_array($order->status, [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY])) {
                $order->has_edit = 2;
            }
            $order->save();
        }
    }
    public function updateQuickWoodworking($orderId, $params)
    {
        if (isset($params['suppliers'])) {
            $order = Order::findOrFail($orderId);
            $isWoodworking = $params['suppliers']['services']['is_woodworking'];
            foreach ($params['suppliers'] as  $supplier) {
                $orderSupplier = OrderSupplier::query()
                    ->where('order_id', $orderId)
                    ->where('supplier_id', $supplier)
                    ->first();
                if(!$orderSupplier) {
                    continue;
                }
                $is_woodworking = $orderSupplier->is_woodworking;
                $orderSupplier->is_woodworking = $isWoodworking;
                if(!$isWoodworking) {
                    $orderSupplier->woodworking_cost = 0;
                }
                $orderSupplier->save();
                $modifies = [];
                $checkExits = OrderSupplier::where('order_id', $orderId)->where('is_woodworking', 1)->first();
                if($checkExits) {
                    $order->is_woodworking = 1;
                }else {
                    $order->is_woodworking = 0;
                }
                $order->save();
                $this->updateServicePackageByOrder($order);
                if($is_woodworking != $isWoodworking) {
                    $modifies['is_woodworking'] = $isWoodworking;
                }
                $content = 'is_woodworking';
                $object = '';
                (new ActivityService())->productActivity($modifies, $orderId, $object, $content, $orderSupplier);
            }

            if(!$order->is_woodworking) {
                $order->woodworking_cost = 0;
                $order->save();
            }
        }
        if (isset($params['suppliers'])) {
            $this->updateFeeWithOutEnter($order);
        }
    }

    public function updateFeeWithOutEnter($order)
    {
        $orderPackges = $order->packages;
        $packController = app()->make(\App\Http\Controllers\PackageController::class);
        foreach ($orderPackges as $key => $orderPackge) {
            if ($orderPackge->weight || $orderPackge->weight_real || $orderPackge->volume_enter || $orderPackge->m3) {
                \Log::info('zzzz');
                $data_pack = [
                    'weight' => $orderPackge->weight,
                    'type' => 'weight'
                ];
                $request = PackageModifiesRequest::create('/', 'PATCH', $data_pack);
                $packController->modifies($request, $orderPackge);
            }
        }
    }

    public function updateShockProof($orderId, $params)
    {
        $order = Order::findOrFail($orderId);
        $orderSupplier = OrderSupplier::where([
            'order_id' => $orderId,
            'supplier_id' => data_get($params, 'supplier_id')
        ])->first();
        if($orderSupplier) {
            $orderSupplier->is_shock_proof = data_get($params, 'is_shock_proof') ? 1 : 0;
            $orderSupplier->save();
            // Kiểm tra nếu có một order_suppliers nào có dịch vụ chống sốc thì cập nhật đơn hàng đó có dịch vụ chống sốc
            $checkExits = OrderSupplier::where('order_id', $orderId)->where('is_shock_proof', 1)->first();
            if($checkExits) {
                $order->is_shock_proof = 1;
            }else {
                $order->is_shock_proof = 0;
            }
            $order->save();
            $this->updateServicePackageByOrder($order);
            $modifies = [];
            $modifies['is_shock_proof'] = data_get($params, 'is_shock_proof') == true;
            $content = 'is_shock_proof';
            $object = '';
            (new ActivityService())->productActivity($modifies, $orderId, $object, $content, $orderSupplier);
        }
        if ($orderSupplier) {
            $this->updateFeeWithOutEnter($order);
        }
    }
    public function updateWarehouse($orderId, $params)
    {
        $order = Order::findOrFail($orderId);

        $oldWarehouseId   = $order->warehouse_id;
        $oldWarehouseName = optional($order->warehouse)->custom_name ?? '';
        $newWarehouseId = data_get($params, 'warehouse_id');

        // update Kiện ở Chờ xử lý, Đang về kho TQ
        OrderPackage::query()
            ->where('order_id', $orderId)
            ->whereIn('status', [PackageConstant::STATUS_PENDING, PackageConstant::STATUS_ON_THE_WAY_CHINA])
            ->update(['warehouse_id' => $params['warehouse_id']]);
        $order->warehouse_id = $params['warehouse_id'];

        $this->logWarehouseChange(
            $order,
            $oldWarehouseId,
            $oldWarehouseName,
            $newWarehouseId
        );

        return $order->save();
    }
    protected function logWarehouseChange(
        Order $order,
        $oldWarehouseId,
        $oldWarehouseName,
        $newWarehouseId
    ) {
        if ($oldWarehouseId == $newWarehouseId) {
            return;
        }
        $order->load('warehouse');
        $newWarehouseName = optional($order->warehouse)->custom_name ?? '';
        $content = __(
            'activity.change_order_field',
            [
                'name'  => getCurrentUser()->name,
                'field' => 'địa chỉ kho nhận',
                'from'  => $oldWarehouseName,
                'to'    => $newWarehouseName,
            ]
        );
        app(ActivityService::class)
            ->setOrderLog(getCurrentUser(), $content, $order->id);
    }
    public function updateCustomerDelivery($orderId, $params)
    {
        $order = Order::with('customerDelivery')->findOrFail($orderId);
        $oldId   = $order->customer_delivery_id;
        $oldName = optional($order->customerDelivery)->custom_name ?? '';
        $newId = data_get($params, 'customer_delivery_id');
        if ($oldId == $newId) {
            return true;
        }
        $order->customer_delivery_id = $newId;
        $order->save();
        $order->load('customerDelivery');
        $newName = optional($order->customerDelivery)->custom_name ?? '';
        $content = __(
            'activity.change_order_field',
            [
                'name'  => getCurrentUser()->name,
                'field' => 'Thông tin mua hàng',
                'from'  => $oldName,
                'to'    => $newName,
            ]
        );
        app(ActivityService::class)
            ->setOrderLog(getCurrentUser(), $content, $order->id);

        return true;
    }

    public function updateOfficial($orderId, $params)
    {
        $order = Order::findOrFail($orderId);
        $orderSupplier = OrderSupplier::query()
            ->where('id', $params['id'])
            ->first();
        if($orderSupplier->official != $params['official'] || $orderSupplier->type_official != $params['type_official']) {
            OrderPackage::query()
                ->where('order_id', $order->id)
                ->update(['official' => $params['official'], 'type_official' => $params['type_official']]);

            $supplier = Supplier::query()->where('id', $params['supplier_id'])->first();
            $beginOfficial = '';
            if($orderSupplier->official == OrderConstant::OFFICIAL) {
                $beginOfficial = OrderConstant::OFFICIAL_TEXT;
            }
            if($orderSupplier->official == OrderConstant::OFFICIAL_SALE) {
                $beginOfficial = OrderConstant::OFFICIAL_SALE_TEXT;
            }
            if($orderSupplier->type_official == OrderConstant::TYPE_OFFICIAL_1) {
                $beginOfficial = $beginOfficial . ' - ' . OrderConstant::TYPE_OFFICIAL_1_TEXT;
            }
            if($orderSupplier->type_official == OrderConstant::TYPE_OFFICIAL_2) {
                $beginOfficial = $beginOfficial . ' - ' . OrderConstant::TYPE_OFFICIAL_2_TEXT;
            }
            $startOfficial = '';
            if($params['official'] == OrderConstant::OFFICIAL_SALE) {
                $startOfficial = OrderConstant::OFFICIAL_SALE_TEXT;
            }
            if($params['official'] == OrderConstant::OFFICIAL) {
                $startOfficial = OrderConstant::OFFICIAL_TEXT;
                if($params['type_official'] == OrderConstant::TYPE_OFFICIAL_1) {
                    $startOfficial = $startOfficial . ' - ' . OrderConstant::TYPE_OFFICIAL_1_TEXT;
                }
                if($params['type_official'] == OrderConstant::TYPE_OFFICIAL_2) {
                    $startOfficial = $startOfficial . ' - ' . OrderConstant::TYPE_OFFICIAL_2_TEXT;
                }
            }
            $userName = getCurrentUser()->name;
            $contentLog = __(
                'activity.official',
                [
                    'name' => $userName,
                    'value' => $startOfficial,
                    'begin' => $beginOfficial,
                    'supplier' => $supplier->name
                ]
            );

            (new ActivityService())->setOrderLog(
                Order::query()->find($orderId),
                $contentLog,
                $orderId
            );
        }
        $orderSupplier->official = $params['official'];
        $orderSupplier->type_official = $params['type_official'];
        $orderSupplier->save();

        if($order->official == 2 && $params['official'] == 2) {
            $order->official = 2;
            $order->type_official = $params['type_official'];
            $order->save();
        }
        if($order->official == 1 && $params['official'] == 2) {
            $order->official = 2;
            $order->type_official = $params['type_official'];
            $order->save();
        }
        if($order->official == 2 && $params['official'] == 1) {
            // Nếu kiện khác kiện này tất cả là bán chính ngạch thì phải update lại đơn thành bán chính ngạch
            $orderSupplierCheck = OrderSupplier::query()
                ->where('order_id', $orderId)
                ->where('id', '!=', $params['id'])
                ->where('official', 2)
                ->exists();

            if(!$orderSupplierCheck) {
                $order->official = 1;
                $order->type_official = 0;
                $order->save();
            }
        }
        // Khi chuyển sang bán chính ngạch thì tất cả NCC + Kiện đều phải bỏ tích đi bên đối tác
        if($order->official == 1) {
            $order->orderSuppliers()->update(['official_is_partner' => 0]);
            $order->packages()->update(['official_is_partner' => 0]);
            $order->update(['official_is_partner' => 0]);
        }
    }

    private function updateServicePackageByOrder($order)
    {
        $data = [
            'is_woodworking' => $order->is_woodworking,
            'is_inspection' => $order->is_inspection,
            'is_advanced_inspection' => $order->is_advanced_inspection,
            'is_shock_proof' => $order->is_shock_proof,
        ];
        $order->packages()->update($data);
        $packageIds = $order->packages->pluck('id')->toArray();
        if(count($packageIds) > 0) {
            $oneWarehouseService = \App::make('App\Services\OneWarehouseService');
            $data['is_advanced_inspection'] = $order->is_advanced_inspection == 2 ? 1 : 0;
            $syncServiceOWJob = new \App\Jobs\OneWarehouse\SyncServicePackJob($packageIds, $data);
            dispatch($syncServiceOWJob)->onQueue('export-1800');
            $config = env('SEND_SMS_ORDER_PACKAGE', '');
            if ($config == 'PRODUCT') {
                // Đồng bộ lại dịch vụ sang bên báo giá
                $this->syncServiceToQuote($packageIds, $data, $order);
            }
        }
    }

    public function syncServiceToQuote($packageIds, $data, $order)
    {
        // Lấy dữ liệu
        $guzzle = new Guzzle;
        $url = env('URL_QUOTE') . 'webhook/hangve-m2329k1947165msh';
        $options = [
            'json' => [
                'event_name' => 'hangve_update_pack_service',
                'data' => [
                    'hangve_pack_ids'       => $packageIds,
                    'hangve_is_inspection'  => $data['is_inspection'],
                    'hangve_is_woodworking' => $data['is_woodworking'],
                    'hangve_is_shock_proof' => $data['is_shock_proof'],
                ],
                'token2000a'         => env('TOKEN_SYNC_DD_HV')
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        // \Log::info($options['json']['data']);

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);


        if ($result['code'] == 200) {
        } else {
            \Log::info("syncServiceToQuote err");
            \Log::info($packageIds);
        }
    }

    public function syncPackNumberToQuote($packageId, $pack_number)
    {
        // Lấy dữ liệu
        $guzzle = new Guzzle;
        $url = env('URL_QUOTE') . 'webhook/hangve-m2329k1947165msh';
        $options = [
            'json' => [
                'event_name' => 'hangve_update_pack_number',
                'data' => [
                    'hangve_pack_id'     => $packageId,
                    'hangve_pack_number' => $pack_number,
                ],
                'token2000a'         => env('TOKEN_SYNC_DD_HV')
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        \Log::info($options['json']['data']);

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);


        if ($result['code'] == 200) {
        } else {
            \Log::info("syncPackNumberToQuote err");
            \Log::info($packageId);
        }
    }

    public function sendNotiOrder(Order $order, $onlyStaffGroup = false)
    {
        $notiService = \App::make('App\Services\NotificationService');

        $keyGroup = NotifyConstant::KEY_GROUP_ORDER;
        $keyName  = NotifyConstant::KEY_NAME_ORDER_CHANGE_STAFF;
        $data     = [
            'order_code' => $order->code,
            'order_id'   => $order->id,
        ];
        if ($onlyStaffGroup && $order->staff_group_id) {
            $notiService->sendNoti($keyGroup, $keyName, $order->staff_group_id, $data);
        } else {
            if ($order->staff_care_id) {
                $notiService->sendNoti($keyGroup, $keyName, $order->staff_care_id, $data);
            }

            if ($order->staff_counselor_id) {
                $notiService->sendNoti($keyGroup, $keyName, $order->staff_counselor_id, $data);
            }
        }
    }

    public function refundFromXadal($id, $type = '')
    {
        $refundFromXadal = RefundFromXadal::query()
            ->where('order_id', $id)
            ->orderBy('id', 'desc')
            ->first();
        Log::info('$refundFromXadal ' . json_encode($refundFromXadal));
        if(!empty($refundFromXadal)) {
            $totalMoney = RefundFromXadal::query()->where('order_id', $id)->sum('money');
            if(!$type) {
                OrderRefund::query()->where('order_id', $id)
                    ->increment('money_refund_from_xadal', $totalMoney, [
                        'order_code_from_xadal' => $refundFromXadal->order_code,
                    ]);
            } else {
                Refund::query()->where('order_id', $id)
                    ->increment('money_refund_from_xadal', $totalMoney, [
                        'order_code_from_xadal' => $refundFromXadal->order_code,
                    ]);
            }
        }
    }

    public function createCancel($order, $reason, $images, $inspectioned = OrderRefund::UN_INSPECTION)
    {
        $userName = Auth::user()->name;
        $contentLog = "Nhân viên  $userName đã tạo yêu cầu hủy đơn";
        $depositCost = $order->deposit_cost;
        if($inspectioned == OrderRefund::INSPECTIONED) {
            if($order->inspection_cost > 0) {
                $depositCost -= $order->inspection_cost;
            }
            if($order->advanced_inspection_cost > 0) {
                $depositCost -= $order->advanced_inspection_cost;
            }
        }
        $moneyRefund = $depositCost;
        $moneyCnyRefund = $depositCost / $order->exchange_rate;
        OrderRefund::query()->create([
            'created_user_id' => Auth::user()->id,
            'money_refund' => $moneyRefund,
            'money_cny_refund' => $moneyCnyRefund,
            'order_id' => $order->id,
            'status' => 0,
            'type_refund' => OrderConstant::IS_CREATE_CANCEL,
            'date_create_refund' => Carbon::now()->format('Y-m-d H:i:s'),
            'reason_create_cancel' => $reason,
            'images_create_cancel' => json_encode($images),
            'inspectioned' => $inspectioned
        ]);
        $orderId = $order->id;
        (new ActivityService())->setOrderLog(
            Order::query()->find($orderId),
            $contentLog,
            $orderId,
            ActivityConstant::IS_VIEW_ADMIN
        );
    }

    public function listRefund($param): JsonResponse
    {
        try {
            $query = OrderRefund::query();
            $query->with([
                'order',
                'order.details',
                'order.orderSuppliers',
                'order.packagesOrder',
                'order.customer' => function ($q) {
                    return $q->select('id', 'name', 'phone_number');
                },
                'order.staffGroup'
            ]);

            if(!empty($param['code'])) {
                $code = $param['code'];
                $query->whereHas('order', function ($q) use ($code){
                    $q->where('code', $code);
                });
            }
            if(!empty($param['order_status'])) {
                $orderStatus = $param['order_status'];
                $query->whereHas('order', function ($q) use ($orderStatus){
                    $q->where('status', $orderStatus);
                });
            }
            if(!empty($param['staff_order_id'])) {
                $staff_order_id = $param['staff_order_id'];
                $query->whereHas('order', function ($q) use ($staff_order_id){
                    $q->where('staff_order_id', $staff_order_id);
                });
            }
            if(isset($param['status_refund'])
                && in_array($param['status_refund'],
                    [OrderRefund::STATUS_PROCESSING, OrderRefund::STATUS_PROCESSED, OrderRefund::STATUS_CANCEL])
            ) {
                $query->where('status', $param['status_refund']);
            }

            if(!empty($param['type_refund'])) {
                $query->where('type_refund', $param['type_refund']);
            }

            if(!empty($param['created_at_refund'])) {
                if(!empty($params['created_at_refund'])) {
                    $dates = (new FilterService())->rangeDateFilterV2($params['created_at_refund']);
                    $start = Carbon::parse($dates[0])->startOfDay();
                    $end = Carbon::parse($dates[1])->endOfDay();
                    $query->whereBetween('created_at', [$start, $end]);
                }
            }
            $query->orderBy('status')->orderBy('id', 'desc');
            $orders = $query->paginate($param['per_page_2'] ?? 15);
            return resSuccessWithinData(new $this->_paginateResource($orders, OrderListRefundResource::class));
        } catch (Exception $e) {
            return resError($e);
        }
    }

    public function validateAfterRefund($order, $data)
    {
        $products = $data['products'] ?? [];
        $orderCost = 0;
        foreach ($products as $product) {
            $orderCost += $product['unit_price_cny'] * $product['quantity'];
        }
        $orderCostNew = $order->exchange_rate * $orderCost + $data['china_shipping_cost'] * $order->exchange_rate;
        if($orderCostNew >= ($order->order_cost + $data['china_shipping_cost'] * $order->exchange_rate)) {
            return false;
        }
        return true;
    }

    public function createRefund($order, $data)
    {
        $products = $data['products'] ?? [];
        $reason = $data['reason'] ?? '';
        $orderCostReal = $data['order_cost_real'] ?? '';
        $chinaShippingCostReal = $data['china_shipping_cost_real'] ?? '';
        $images = $data['images'] ?? [];
        if(empty($products)) {
            return false;
        }
        $dataProduct = [];
        $amount = 0;
        $totalQuantity = 0;
        $inspectionCost = $order->inspection_cost;
        foreach ($products as $product) {
            $dataProduct[] = [
                'id' => $product['id'],
                'unit_price_cny' => $product['unit_price_cny'],
                'quantity' => $product['quantity'],
            ];
            $amount += $product['unit_price_cny'] * $product['quantity'];
            $totalQuantity += $product['quantity'];
        }
        $orderDetails = $order->details;
        $totalPriceOld = 0;
        foreach ($orderDetails as $orderDetail) {
            $totalPriceOld += $orderDetail['unit_price_cny'] * $orderDetail['quantity'];
        }

        $moneyCnyRefund = $totalPriceOld - $amount;
        $chinaShippingCostRefund = $order->china_shipping_cost - $data['china_shipping_cost'];
        $orderCost = $amount * $order->exchange_rate;
        $type = '';
        // Tính lại inspection cost. Nếu là đơn tách thì giữ nguyên. còn là đơn lẻ thì tính theo giá mới
        $totalQuantityOld = $order->details->sum('quantity');
        $orderPercent = $order->order_percent;
        if($this->checkSplitOrder($order)) {
            $type = 'isMultipleSupplier';
            if($order->is_inspection) {
                $unitInspectionCost = $order->inspection_cost / $totalQuantityOld;
                $inspectionCost = $unitInspectionCost * $totalQuantity;
            }
        } else {
            if($order->is_inspection) {
                $inspectionCost = (new AccountingService())->getInspectionCost($totalQuantity) ?? 0;
            }
        }
        if ($order->is_advanced_inspection == 2) {
            $unitAdvancedInspection = $order->advanced_inspection_cost / $totalQuantityOld;
            // Vì sẽ chỉ có kiểm đêm thường hoặc kiểm đếm nâng cao, nên dùng chung biến luôn
            $inspectionCost = $unitAdvancedInspection * $totalQuantity;
            Log::info('Kdnc ' . $inspectionCost);
        }
        $orderFee = (new AccountingService())
            ->getOrderFee($orderCost, $order->customer_id, $orderPercent, $type);
        if(!$type) {
            $orderFee = (new AccountingService())
                ->getOrderFee($orderCost, $order->customer_id, $orderPercent, $type);
        }
        $discountCost = (new AccountingService())->getDiscountCost($orderFee, $order->customer_id);
        $chinaShippingCost = $data['china_shipping_cost'] ?? $order->china_shipping_cost;
        $totalAmountTemp = $orderCost + $orderFee - $discountCost + $chinaShippingCost * $order->exchange_rate
            + $inspectionCost;
        if ($order->delivery_pay_id > 0) {
            $moneyRefund = $order->deposit_cost
                + $order->delivery_pay_debt_cost
                - $totalAmountTemp;
        } else {
            $moneyRefund = $order->deposit_cost - $totalAmountTemp * $order->deposit_percent / 100;
        }
        if($moneyRefund <= 0) {
            return false;
        }
        $userName = Auth::user()->name;
        $contentLog = "Nhân viên  $userName đã tạo yêu cầu hoàn tiền thành công";
        OrderRefund::query()->create(
            [
                'order_id' => $order->id,
                'created_user_id' => Auth::user()->id,
                'money_refund' => $moneyRefund,
                'money_cny_refund' => $moneyRefund / $order->exchange_rate ?? 0,
                'money_refund_cn_origin' => $moneyCnyRefund + $chinaShippingCostRefund,
                'status' => 0,
                'type_refund' => OrderConstant::IS_CREATE_REFUND,
                'reason_create_refund' => $reason,
                'china_shipping_cost_refund' => $data['china_shipping_cost'],
                'images_create_refund' => json_encode($images),
                'price_quantity_order_details' => json_encode($dataProduct),
                'order_cost_real' => $orderCostReal,
                'china_shipping_cost_real' => $chinaShippingCostReal,
            ]);
        $orderId = $order->id;
        (new ActivityService())->setOrderLog(
            Order::query()->find($orderId),
            $contentLog,
            $orderId,
            ActivityConstant::IS_VIEW_ADMIN
        );

        return true;
    }

    public function refundCancel($params)
    {
        $idOrderRefunds = $params['ids'];
        $orderRefunds = OrderRefund::query()->whereIn('id', $idOrderRefunds)->get();
        foreach ($orderRefunds as $orderRefund) {
            $userName = Auth::user()->name;
            $msgCancel = $params['reason'] ?? '';
            if($orderRefund->type_refund == OrderConstant::IS_CREATE_REFUND) {
                $orderRefund->reason_cancel = $msgCancel;
            } else {
                $orderRefund->reason_cancel = $msgCancel;
            }
            $contentLog = "$userName đã hủy yêu cầu hoàn tiền với lý do $msgCancel";
            $order = Order::query()->where('id', $orderRefund->order_id)->first();
            $orderRefund->status = OrderRefund::STATUS_CANCEL;
            $orderRefund->save();

            $orderId = $order->id;
            (new ActivityService())->setOrderLog(
                Order::query()->find($orderId),
                $contentLog,
                $orderId,
                ActivityConstant::IS_VIEW_ADMIN
            );
            $notiService = \App::make('App\Services\NotificationService');
            $keyGroup = NotifyConstant::KEY_GROUP_ORDER;
            $keyName  = NotifyConstant::KEY_GROUP_REFUND;

            $data     = [
                'order_code' => $order->code,
                'order_id'   => $order->id,
                'content'    => $contentLog
            ];
            $notiService->sendNoti($keyGroup, $keyName, $order->staff_order_id, $data);
        }
        return true;
    }

    public function refundMultiple($params)
    {
        $idOrderRefunds = $params['ids'];
        $orderRefunds = OrderRefund::query()->whereIn('id', $idOrderRefunds)->get();
        $arrayOrderId = [];
        foreach ($orderRefunds as $orderRefund) {
            if($orderRefund->status != OrderRefund::STATUS_PROCESSING) {
                continue;
            }
            $order = Order::query()->where('id', $orderRefund->order_id)->first();
            $totalQuantityOld = $order->details->sum('quantity');
            $moneyRefund = 0;
            if($orderRefund->type_refund == OrderConstant::IS_CREATE_CANCEL) {
                $orderPackage = OrderPackage::query()
                    ->where('order_id', $orderRefund->order_id)
                    ->where('order_type', Order::class)
                    ->whereNotIn('status', [PackageConstant::STATUS_PENDING, PackageConstant::STATUS_RECONFIRM, PackageConstant::STATUS_CANCEL])
                    ->exists();
                if($orderPackage) {
                    $arrayOrderId[] = $order->code;
                    continue;
                }
                $order->status = OrderConstant::KEY_STATUS_CANCEL;
                $order->reason_cancel = $orderRefund->reason_create_cancel;
                $order->staff_cancel_id = getCurrentUserId();
                $order->save();
                OrderPackage::query()
                    ->where('order_id', $orderRefund->order_id)
                    ->where('order_type', Order::class)
                    ->whereIn('status', [PackageConstant::STATUS_PENDING, PackageConstant::STATUS_RECONFIRM])
                    ->update([
                        'status' => PackageConstant::STATUS_CANCEL
                    ]);
            }
            if($orderRefund->type_refund == OrderConstant::IS_CREATE_REFUND) {
                $products = json_decode($orderRefund->price_quantity_order_details, true);
                $chinaShippingCost = $orderRefund->china_shipping_cost_refund;
                $content = 'order_products_update';
                $userName = $orderRefund->user->name ?? '';
                foreach ($products as $product) {
                    $orderDetail = OrderDetail::query()->where('id', $product['id'])->first();
                    $quantityOld = $orderDetail->quantity;
                    $unitPriceCnyOld = $orderDetail->unit_price_cny;
                    $object = optional($orderDetail)->name;
                    $unitPriceCny = [];
                    $quantity = [];
                    if($quantityOld != $product['quantity']) {
                        $quantity = [
                            'quantity' => $product['quantity'],
                        ];
                    }
                    if($unitPriceCnyOld != $product['unit_price_cny']) {
                        $unitPriceCny = [
                            'unit_price_cny' => $product['unit_price_cny'],
                        ];
                    }
                    $modifies = array_merge($unitPriceCny, $quantity);
                    $orderId = $order->id;
                    (new ActivityService())
                        ->productActivity($modifies, $orderId, $object, $content, $orderDetail, $userName);

                    $orderDetail->quantity = $product['quantity'];
                    $orderDetail->unit_price_cny = $product['unit_price_cny'];
                    $orderDetail->save();
                }
                $order->china_shipping_cost = $chinaShippingCost;
                $orderSupplier = OrderSupplier::query()->where('order_id', $order->id)->first();
                if($orderSupplier->china_shipping_cost != $chinaShippingCost) {
                    $modifies = [
                        'china_shipping_cost' => $chinaShippingCost
                    ];
                    $orderId = $orderRefund->order_id;
                    $object = optional(Supplier::query()->find($orderSupplier->id))->name ?? ' ';
                    (new ActivityService())
                        ->productActivity($modifies, $orderId, $object, $content, $orderSupplier, $userName);
                }

                $order->orderSuppliers()->update(
                    [
                        'china_shipping_cost_real' => $orderRefund->china_shipping_cost_real ?? 0,
                        'order_cost_real' => $orderRefund->order_cost_real ?? 0,
                        'china_shipping_cost' => $chinaShippingCost
                    ]
                );
                $order->china_shipping_cost_real = $orderRefund->china_shipping_cost_real * $order->exchange_rate;
                $order->order_cost_real = $orderRefund->order_cost_real * $order->exchange_rate;
                $order->save();
                // check phí đặt hàng
                $this->updateOrderSupplier($order);
                $this->updateOrderCost($order->id);

                if($order->is_inspection) {
                    $unitInspectionCost = 0;
                    if($this->checkSplitOrder($order)) {
                        $unitInspectionCost = $order->inspection_cost / $totalQuantityOld;
                    }
                    $this->updateInspectionCost($order, $unitInspectionCost);
                }
                $moneyRefund = $orderRefund->money_refund;
            }

            $orderRefund->status = OrderRefund::STATUS_PROCESSED;
            $orderRefund->save();
            if($moneyRefund > 0) {
                $this->handleRefundMoney($order, $moneyRefund);
            }
            if($orderRefund->type_refund != OrderConstant::IS_CREATE_CANCEL) {
                $userName = Auth::user()->name;
                $contentLog = "$userName đã chấp nhận yêu cầu hoàn tiền";
                $orderId = $order->id;
                (new ActivityService())->setOrderLog(
                    Order::query()->find($orderId),
                    $contentLog,
                    $orderId,
                    ActivityConstant::IS_VIEW_ADMIN
                );
            }

        }
        if(!empty($arrayOrderId)) {
            return $arrayOrderId;
        }
        return true;
    }

    public function circleStaffOrder($data)
    {
        if(empty($data['order_ids'])) {
            return true;
        }

        foreach ($data['order_ids'] as $orderId) {
            $order = Order::query()->find($orderId);
            if(empty($order)) {
                continue;
            }
            if(!in_array($order->status,
                [
                    OrderConstant::KEY_STATUS_WAITING_QUOTE,
                    OrderConstant::KEY_STATUS_WAITING_DEPOSIT
                ]
            )) {
                continue;
            }
            $this->getUserOrderStaff($order, true);
        }
    }

    public function checkPrice($orderCodes)
    {
        if($orderCodes) {
            $data = $orderCodes['order_codes'];
            $orderCodes = explode(',', $data);
        }
        $query = OrderPackage::query()
            ->select('id', 'order_type', 'code_po', 'order_id')
            ->where('order_type', Order::class)
            ->whereIn('code_po', $orderCodes);

        $orderPackages = $query->get();
        $data = [];
        foreach ($orderPackages as $orderPackage) {
            $order = Order::query()->select('order_cost_real', 'china_shipping_cost_real', 'exchange_rate')
                ->where('id', $orderPackage->order_id)
                ->first();
            $totalPrice = 0;
            if($order) {
                $totalPrice = $order->order_cost_real / $order->exchange_rate + $order->china_shipping_cost_real;
            }
            $data[$orderPackage->code_po] = number_format($totalPrice, 2);
        }

        return $data;
    }

    public function updateOfficialIsPartner($orderId, $params)
    {
        $supplierId = data_get($params, 'supplier_id');
        $officialIsPartner = data_get($params, 'official_is_partner');
        $order = Order::findOrFail($orderId);
        if($order) {
            OrderSupplier::query()
                            ->where('order_id', $orderId)
                            ->where('supplier_id', $supplierId)
                            ->update(['official_is_partner' => $officialIsPartner]);
            $checkExits = $order->orderSuppliers()->where('official_is_partner', 1)->first();
            if($checkExits) {
                $order->official_is_partner = 1;
                $order->packages()->update(['official_is_partner' => 1]);
            }else {
                $order->official_is_partner = 0;
                $order->packages()->update(['official_is_partner' => 0]);
            }
            $order->save();
            $modifies['official_is_partner'] = $officialIsPartner;
            $content = 'official_is_partner';
            $object = '';
            (new ActivityService())->productActivity($modifies, $orderId, $object, $content, $checkExits, '', ActivityConstant::IS_VIEW_ADMIN);
        }

    }

    public function cacheRedisForSearch()
    {
        // Lưu vào redis
        $userId = getCurrentUserId() ?? 0;
        $endpoint = request()->path();
        $date = now()->toDateString();

        $key = "api_usage_admin:$date:$userId:$endpoint";

        Redis::incr($key);
        Redis::expire($key, 86400 * 2);
    }
}
