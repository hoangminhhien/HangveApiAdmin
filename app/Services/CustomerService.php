<?php

namespace App\Services;

use App\Constants\CustomerConstant;
use App\Constants\FundConstant;
use App\Constants\NotifyConstant;
use App\Constants\PackageConstant;
use App\Constants\TimeConstant;
use App\Constants\ViaConstant;
use App\Helpers\RandomHelper;
use App\Helpers\StatusHelper;
use App\Jobs\UpdateAssignStaff;
use App\Jobs\UpdateStaffLogic;
use App\Models\Bank;
use App\Models\ChargeAppHistory;
use App\Models\Customer;
use App\Models\CustomerLabel;
use App\Models\CustomerNote;
use App\Models\Label;
use App\Models\Order;
use App\Models\CustomerOffer;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\FundTypePay;
use App\Models\LogFundTransaction;
use App\Models\OrderPackage;
use App\Models\ReportCustomer;
use App\Models\ReportUserCounselingCustomer;
use App\Http\Resources\Customer\CustomerListResource;
use App\Http\Resources\Customer\CustomerPaginateResource;
use App\Http\Resources\Customer\CustomerResource;
use App\Models\SmsLog;
use App\Models\Supplier;
use App\Models\User;
use App\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Constants\OrderConstant;
use App\Constants\TransactionConstant;
use App\Constants\CustomerPointTime;
use App\Models\Transaction;
use App\Models\CustomerLogEndow;
use App\Models\Consignment;
use App\Models\LogAutoPay;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\Customer\CustomerLevelResource;
use App\Http\Resources\Customer\CustomerWithTrashedResource;
class CustomerService extends BaseService
{
    protected string $_paginateResource = CustomerPaginateResource::class;
    protected string $_listResource = CustomerListResource::class;
    protected string $_resourceLevel = CustomerLevelResource::class;
    protected string $_resource = CustomerResource::class;

    public function pagination(int $perPage): JsonResponse
    {
        //DB::enableQueryLog();
        $categoryId = request()->input('category_id');
        $this->throwModel();

        $perService = new PerService();


        $query = $this->_model->newQuery();

        $query->select('customers.id', 'customers.code', 'customers.name', 'customers.email', 'customers.source', 'customers.gender', 'customers.warehouse_id', 'customers.phone_number', 'customers.created_at', 'customers.via',
            'customers.level', 'customers.warehouse_cn_id', 'customers.service', 'customers.staff_care_id', 'customers.staff_counselor_id', 'customers.staff_group_id',
            'customers.business_type', 'customers.status', 'customers.label_id', 'customers.customer_introduce_id', 'customers.discount_by_kg', 'customers.discount_by_m3',
            'customers.point_level', 'customers.last_order_at', 'customers.organization_id');

        $query->with([
            'report' => function($a){
                return $a->select("orders_number", "consignment_number", 'packages_number', 'order_amount',
                    'deposited_amount', 'balance_amount', 'customer_id');
            },
            'warehouse.province', 'offer',
            'packages' => function($a){
                return $a->select("international_shipping_cost", "customer_id");
            }, 'labels', 'customerIntroduce', 'province', 'orderDeposit:id,customer_id,created_at,order_cost',
            'orderDone:id,customer_id,created_at,order_cost,status', 'customerNotes' => function($q) {
                $q->select('created_at', 'customer_id', 'user_id', 'content')
                  ->orderBy('created_at', 'desc');
            },
            'customer_categories',
            'customerNotes.user' => function($a){
                return $a->select("id", "name");
            },
        ]);

        $resetStaff = request()->query('reset_staff');
        if ($resetStaff == 'total_waiting_for_advice') {
            $query->withoutGlobalScopes()->whereNull('staff_group_id');
        }
        if($dateOrderLast = request()->get('date_order_last')) {
            $query->whereDate('last_order_at', '=', $dateOrderLast);
        }
        if($resetStaff == 'total_consignment') {
            $query->withoutGlobalScopes()->where('service', 1);
        }
        if($resetStaff == 'total_order') {
            $query->withoutGlobalScopes()->where('service', 0);
        }
        if($resetStaff == 'total_place_ordered_mkt') {
            $query = $query->withoutGlobalScopes()
                ->join('orders','orders.customer_id', '=', 'customers.id')
                ->join('order_status_times', function ($q) {
                    $q->on('orders.id', '=', 'order_status_times.order_id')
                        ->whereNotNull('order_status_times.status_3');
                })
                ->groupBy('customers.id');
        }
        if($resetStaff == 'total_place_order_mkt') {
            $query = $query->withoutGlobalScopes()
                ->join('orders','orders.customer_id', '=', 'customers.id')->groupBy('customers.id');
        }
        if (request()->query('time')) {
            $dateRange = $this->dateRange(request()->input('time'));
            if ($resetStaff == 'total_place_order_mkt' || $resetStaff == 'total_place_ordered_mkt') {
                $query->whereBetween('orders.created_at', [$dateRange['startDate'], $dateRange['endDate']]);
            } else {
                $query->whereBetween('created_at', [$dateRange['startDate'], $dateRange['endDate']]);
            }
        }

        $isOfferRate   = request()->query('is_offer_rate');
        $isDepositRate = request()->query('is_deposit_rate');
        $isTransFee = request()->query('is_trans_fee');
        if (isset($isOfferRate) || isset($isDepositRate) || isset($isTransFee)) {
            $query->leftJoin('customer_offers', 'customers.id', '=', 'customer_offers.customer_id');
        }
        if (isset($isOfferRate)) {
            $cusHasOfferRate = $this->customerHasRate('offer_rate');
            if ($isOfferRate) {
                $query->whereIn('customers.id', $cusHasOfferRate );
            } else {
                $query->where(function ($query) use ($cusHasOfferRate) {
                    $query->where('customer_offers.offer_rate', '=', 0)
                        ->orWhereNotIn('customers.id', $cusHasOfferRate );
                });
            }
        }
        if (isset($isDepositRate)) {
            $cusHasDepositRate = $this->customerHasRate('deposit_rate');
            if ($isDepositRate) {
                $query->whereIn('customers.id', $cusHasDepositRate );
            } else {
                $query->where(function ($query) use ($cusHasDepositRate) {
                    $query->where('customer_offers.deposit_rate', '=', 0)
                        ->orWhereNotIn('customers.id', $cusHasDepositRate );
                });
            }
        }
        if (isset($isTransFee)) {
            $cusHasTransFee = $this->customerHasFee();
            if ($isTransFee) {
                $query->whereIn('customers.id', $cusHasTransFee );
            } else {
                $query->where(function ($query) use ($cusHasTransFee) {
                    $query->where(function ($q) {
                        $q->where('customer_offers.trans_weight_hn_fee', 0)
                            ->where('customer_offers.trans_weight_hcm_fee', 0)
                            ->where('customer_offers.trans_volumn_hn_fee', 0)
                            ->where('customer_offers.trans_volumn_hcm_fee', 0);
                    })
                    ->orWhereNotIn('customers.id', $cusHasTransFee );
                });
            }
        }

        if(request()->query('last_month') || request()->query('re_order')) {
            $query->where('customers.status', 1);
            $numberLastMonth = request()->query('last_month') ? request()->query('last_month') : 1;
            $now = Carbon::now();
            $lastMonth = $now->copy()->subMonths($numberLastMonth);
            $startOfWeek = $now->copy()->startOfWeek();
            $endOfWeek = $now->copy()->endOfWeek();
            if(request()->query('re_order')) {
                $customerIds = [];
                $orders = Customer::query()
                    ->select(
                        DB::raw('customers.id AS customer_id'),
                    )
                    ->join('orders', function($q) use ($startOfWeek, $endOfWeek){
                        $q->on('orders.customer_id', '=', 'customers.id')
                            ->whereBetween('orders.created_at', [$startOfWeek, $endOfWeek]);
                        }
                    )
                    ->whereNotNull('customers.last_order_at')
                    ->whereDoesntHave('order', function ($order) use ($numberLastMonth, $startOfWeek, $endOfWeek){
                        $order->whereRaw(DB::raw('orders.created_at BETWEEN DATE_ADD(customers.last_order_at, INTERVAL -'.$numberLastMonth.' MONTH) AND DATE_ADD(customers.last_order_at, INTERVAL -1 MINUTE)'))
                            ->whereNotBetween('orders.created_at', [$startOfWeek, $endOfWeek]);
                    })
                    ->where('customers.created_at', '<', Carbon::now()->subMonths($numberLastMonth))
                    ->where('customers.status', 1)
                    ->groupBy('customers.id')
                    ->get();
                $consignments = Customer::query()
                    ->select(
                        DB::raw('customers.id AS customer_id')
                    )
                    ->join('consignments', function($q) use ($startOfWeek, $endOfWeek){
                        $q->on('consignments.customer_id', '=', 'customers.id')
                            ->whereBetween('consignments.created_at', [$startOfWeek, $endOfWeek]);
                        }
                    )
                    ->whereNotNull('customers.last_order_at')
                    ->whereDoesntHave('consignments', function ($order) use ($numberLastMonth, $startOfWeek, $endOfWeek){
                        $order->whereRaw(DB::raw('consignments.created_at BETWEEN DATE_ADD(customers.last_order_at, INTERVAL -'.$numberLastMonth.' MONTH) AND DATE_ADD(customers.last_order_at, INTERVAL -1 MINUTE)'))
                            ->whereNotBetween('consignments.created_at', [$startOfWeek, $endOfWeek]);
                    })
                    ->where('customers.created_at', '<', Carbon::now()->subMonths($numberLastMonth))
                    ->where('customers.status', 1)
                    ->groupBy('customers.id')
                    ->get();
                $orderPackage = Customer::query()
                    ->select(
                        DB::raw('customers.id AS customer_id'),
                        DB::raw('COUNT(DISTINCT order_package.id) AS total_order'),
                    )
                    ->join('order_package', function($q) use ($startOfWeek, $endOfWeek){
                        $q->on('order_package.customer_id', '=', 'customers.id')
                            ->whereBetween('order_package.created_at', [$startOfWeek, $endOfWeek])
                            ->whereNull('order_package.order_id')
                            ->where('order_package.order_type', Consignment::class);
                        }
                    )
                    ->whereNotNull('customers.last_order_at')
                    ->whereDoesntHave('orderPackage', function ($order) use ($numberLastMonth, $startOfWeek, $endOfWeek){
                        $order->whereRaw(DB::raw('order_package.created_at BETWEEN DATE_ADD(customers.last_order_at, INTERVAL -'.$numberLastMonth.' MONTH) AND DATE_ADD(customers.last_order_at, INTERVAL -1 MINUTE)'))
                            ->whereNotBetween('order_package.created_at', [$startOfWeek, $endOfWeek])
                            ->whereNull('order_package.order_id')
                            ->where('order_package.order_type', Consignment::class);
                    })
                    ->where('customers.created_at', '<', Carbon::now()->subMonths($numberLastMonth))
                    ->where('customers.status', 1)
                    ->groupBy('customers.id')
                    ->get();
                foreach($orders as $order) {
                    array_push($customerIds, $order->customer_id);
                }
                foreach($consignments as $consignment) {
                    array_push($customerIds, $consignment->customer_id);
                }
                foreach($orderPackage as $item) {
                    array_push($customerIds, $item->customer_id);
                }
                $query->whereIn('customers.id', array_unique($customerIds))
                ->where('customers.created_at', '<', Carbon::now()->subMonths($numberLastMonth));
            }else {
                $query->where(DB::raw("IF(customers.last_order_at IS NOT NULL, customers.last_order_at, customers.created_at)"), '<', $lastMonth)
                    ->whereDoesntHave('packages', function ($q) use ($lastMonth){
                        $q->whereBetween('created_at', [$lastMonth, Carbon::now()])
                            ->where('order_type', Consignment::class);
                    });
            }
            if (request()->query('group_id_report')) {
                $user_group_ids = $this->getUserGroup(request()->query('group_id_report'));
                $query->whereIn('customers.staff_group_id', $user_group_ids);
            }
        }
        if(request()->query('staff_group_id')) {
            $staffGroupIds = is_array(request()->query('staff_group_id')) ? request()->query('staff_group_id') : [request()->query('staff_group_id')];
            $query->whereIn('customers.staff_group_id', $staffGroupIds)
                    ->when(in_array("0", $staffGroupIds), function($q) {
                        return $q->orWhereNull('customers.staff_group_id');
                    });
        }
        if(is_array(request()->query('label_id'))) {
            if(in_array(999, request()->query('label_id'))) {
                $query->withoutGlobalScopes()
                    ->leftJoin('customer_label', 'customers.id', '=', 'customer_label.customer_id')
                    ->whereNull('customer_label.label_id')
                    ->whereNull('customers.deleted_at')
                ;
                $query->orderBy('id', 'DESC');
            } else {
                $query = $query->join('customer_label','customer_label.customer_id', '=', 'customers.id');
                $query->whereIn('customer_label.label_id', request()->query('label_id'));
                $query->groupBy('customers.id');
                if (!empty(request()->query('created_label'))) {
                    $createdLabel = request()->query('created_label');
                    $startDateLabel = Carbon::createFromFormat('Y-m-d', data_get($createdLabel, 0))->startOfDay();
                    $endDateLabel = Carbon::createFromFormat('Y-m-d', data_get($createdLabel, 1))->endOfDay();
                    $query->whereBetween('customer_label.created_at', [$startDateLabel, $endDateLabel]);
                }
            }
        }
        if(request()->input('isSearchCustomer') != "true") {
            if(request()->query('waiting_for_care') != 1) {
                // Nếu k phải màn chờ chăm sóc mới phân quyền xem
                $query = $perService->getConditionByUserLogin($query);
            }
            if(request()->query('past')) {
                $longTimeOrder2020 = Carbon::createFromFormat('Y-m', '2022-12')->startOfmonth();
                if(request()->query('past') == 'long_order') {
                    $query->where(DB::raw("IF(customers.last_order_at IS NULL OR customers.last_order_at < customers.created_at, customers.created_at, customers.last_order_at)"), '<', $longTimeOrder2020);
                }
                if(request()->query('past') == 'long_interact') {
                    $query->where('customers.created_at', '<', $longTimeOrder2020)
                    ->whereHas('labels', function ($q){
                        $q->where('labels.id', 1);
                    });
                }
            }else {
                if(request()->query('waiting_for_care') && request()->query('waiting_for_care') == 1) {
                    // Màn chờ chăm sóc
                    $query = $this->getCustomerWaitingForCare($query);
                } else if(request()->query('close_account') && request()->query('close_account') == 1) {
                    // Màn đóng khoản
                    $query = $query->where('status', CustomerConstant::STATUS_INACTIVE);
                }
                else {
                    $timeLabel = Carbon::createFromFormat('Y-m', '2024-12')->endOfmonth();
                    $timeNoLabel =  [
                        Carbon::createFromFormat('Y', '2020')->startOfYear(),
                        Carbon::createFromFormat('Y', '2024')->endOfYear()
                    ];
                    $labelIds = Label::whereIn('code', [
                        'DA_DAT_HANG',
                        'DA_LEN_DON',
                        'KI_GUI',
                        'KI_GUI_DA_LEN_DON',
                        'KHONG_CO_NHU_CAU',
                        'MAT_HANG_KHONG_NHAP_DUOC',
                        'KHONG_TUONG_TAC_DUOC'
                    ])->pluck('id')->toArray();
                    $query->where('status', CustomerConstant::STATUS_ACTIVE)
                            ->where(function($subQuery) use ($timeNoLabel, $timeLabel, $labelIds) {
                                $subQuery->where(function($q) use ($timeLabel) {
                                    $q->whereDoesntHave('labels')
                                        ->where(DB::raw("IF(customers.last_order_at IS NOT NULL, customers.last_order_at, customers.created_at)"), '>', $timeLabel);
                                    })
                                    ->orWhere(function($q1) use ($timeLabel, $labelIds) {
                                        $q1->where(DB::raw("IF(customers.last_order_at IS NOT NULL, customers.last_order_at, customers.created_at)"), '>', $timeLabel)
                                            ->whereHas('labels', function($q2) use ($labelIds) {
                                                $q2->whereIn('label_id', $labelIds);
                                            });
                                            ;
                                    })
                                    ->orWhere(function($q1) use ($timeLabel, $labelIds) {
                                        $q1->whereHas('labels', function($q2) use ($labelIds) {
                                            $q2->whereNotIn('label_id', $labelIds);
                                        });
                                    });
                            });
                }
            }
        }
        $query->when($categoryId, function($subQuery) use ($categoryId) {
            return $subQuery->whereHas('customer_categories', function($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        });
        if(request()->query('assign_time_sort')) {
            $query->orderBy('staff_group_time_assign', request()->query('assign_time_sort'));
        }
        if(request()->query('last_order_at_sort')) {
            $query->orderBy('last_order_at', request()->query('last_order_at_sort'));
        }
        if(request()->query('year')) {
            $year = request()->query('year');
            $yearBetween = [
                Carbon::createFromFormat('Y', $year)->startOfYear(),
                Carbon::createFromFormat('Y', $year)->endOfYear()
            ];
            $sortBy = request()->query('sort_by') ?? 1;
            if($sortBy == 1) {
                $query->whereBetween('customers.created_at', $yearBetween);
            }else {
                $query->whereBetween('customers.last_order_at', $yearBetween);
            }
        }
        $data = $query->groupBy('customers.id')->paginate($perPage);

        $fromPage = request()->get('from_page');
        if ($fromPage == 'customer-levels') {
            return resSuccessWithinData(new $this->_paginateResource($data, $this->_resourceLevel));
        }
        //dd(DB::getQueryLog());
        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }
    private function getCustomerOrderTime($user_group_ids, $params = []): array
    {
        $label_ids = Label::query()->select('id')
            ->whereIn('code', ['DA_LEN_DON', 'THAM_KHAO', 'DA_DAT_HANG'])
            ->pluck('id')
            ->toArray();
        $list = CalculatorWeekReport::getWeekMonthYear([]);
        $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($list->year, $list->month, $list->week);
        $start_date = $calculatorDatetime['week_start_date'] . ' 00:00:00';
        $sub_day = date('Y-m-d', strtotime($start_date. ' -30 days'));
        $sub_day = $sub_day . ' 00:00:00';
        $sql = Customer::query();
        $select = 'customers.id';
        $sql->select(
            $select
        );
        $sql->join('customer_label','customer_label.customer_id','=','customers.id');
        $sql->where('customers.last_order_at', '<', $sub_day);
        $sql->whereNotNull('customers.last_order_at');
        if (is_array(request()->input('business_type')) || isset($params['business_type'])) {
            $business_type = isset($params['business_type']) ? $params['business_type'] : request()->input('business_type');
            $sql->whereIn('customers.business_type', $business_type);
        } else {
            $sql->where('customers.business_type', request()->input('business_type'));
        }
        $sql->whereIn('customer_label.label_id', $label_ids);
        $sql->where('customers.status', 1);
        $sql->whereIn('customers.staff_group_id', $user_group_ids);
        $ids = $sql->pluck('id')->toArray();
        return array_unique($ids);
    }

    private function getCustomerOrderTimeHasOrder($user_group_ids, $params = []): array
    {
        $label_ids = Label::query()->select('id')
            ->whereIn('code', ['DA_LEN_DON', 'THAM_KHAO', 'DA_DAT_HANG'])
            ->pluck('id')
            ->toArray();
        $list = CalculatorWeekReport::getWeekMonthYear([]);
        $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($list->year, $list->month, $list->week);
        $start_date = $calculatorDatetime['week_start_date'] . ' 00:00:00';
        $end_date = $calculatorDatetime['week_end_date'] . ' 23:59:59';
        $sub_day = date('Y-m-d', strtotime($start_date. ' -30 days'));
        $sub_day = $sub_day . ' 00:00:00';
        $sub_day_business_end_date = date('Y-m-d', strtotime($start_date. ' -1 days'));
        $sub_day_business_end_date = $sub_day_business_end_date . ' 23:59:59';
        $query = Customer::query()
            ->select(DB::raw('DISTINCT customers.id as id'))
            ->whereHas('order', function ($order) use ($start_date, $end_date) {
                $order->whereBetween('orders.created_at',[$start_date, $end_date])
                    ->whereNull('orders.deleted_at');
            })
            ->whereDoesntHave('order', function ($order) use ($sub_day, $sub_day_business_end_date) {
                $order->whereBetween('orders.created_at',[$sub_day, $sub_day_business_end_date])
                    ->whereNull('orders.deleted_at');
            })
            ->with(['order' => function ($order) use ($start_date, $end_date) {
                $order->select(
                    'id',
                    'customer_id',
                    'order_cost',
                    'inspection_cost',
                    'international_shipping_cost',
                    'order_fee',
                    'china_shipping_cost',
                    'exchange_rate',
                    'discount_cost'
                )->whereBetween('orders.created_at',[$start_date, $end_date])
                    ->whereNull('orders.deleted_at');
            }])
            ->join('users_group','users_group.user_id', 'customers.staff_group_id')
            ->join('customer_label','customer_label.customer_id','=','customers.id')
            ->whereIn('customer_label.label_id', $label_ids);
            if (is_array(request()->input('business_type')) || isset($params['business_type'])) {
                $business_type = isset($params['business_type']) ? $params['business_type'] : request()->input('business_type');
                $query->whereIn('customers.business_type', $business_type);
            } else {
                $query->where('customers.business_type', request()->input('business_type'));
            }
        $query->whereIn('users_group.user_id', $user_group_ids);
        $ids = $query->pluck('id')->toArray();
        return array_unique($ids);
    }

    private function getUserGroup($group_id = false): array
    {
        $query = User::query();
        /*$query = $query->where('id', 286);*/
        $query = $query->whereExists(function ($query) use ($group_id){
            $query->select("users_group.id")
                ->from('users_group')
                ->whereRaw('users_group.user_id = users.id');
            if ($group_id) {
                $query->where('users_group.group_id', $group_id);
            }
        })
            ->where('status', 1)
            ->select('id');
        return $query->pluck('id')
            ->toArray();
    }

    public function customerHasRate(string $column)
    {
        return CustomerOffer::query()->where($column, '>', 0)->pluck('customer_id')->toArray();
    }

    public function customerHasFee()
    {
        return CustomerOffer::query()
            ->where(function ($query) {
                $query->where('trans_weight_hn_fee', '>', 0)
                    ->orWhere('trans_weight_hcm_fee', '>', 0)
                    ->orWhere('trans_volumn_hn_fee', '>', 0)
                    ->orWhere('trans_volumn_hcm_fee', '>', 0);
            })
            ->pluck('customer_id')->toArray();
    }

    public function checkPhoneNumberSearch($phoneNumber): array
    {
        $customer = $this->_model->newQuery()
            ->with('staffGroup')
            ->where('phone_number', $phoneNumber)
            ->first();

        if (!$customer) {
            return [];
        }

        $userLogin = auth()->user();
        if (isBusiness() && !isSalesManager() && !isDeputySalesManager()) {
            if ($customer->staff_group_id != $userLogin->id && $customer->staffGroup) {
                return [
                    'name_staff' => $customer->staffGroup->name,
                    'customer_code' => $customer->code
                ];
            }
        }
        return [];
    }


    /**
     *  Lấy số lượng khách hàng theo tháng đã đăng ký
     * @return int
     */
    public function getCustomerNumberByYearAndMonth(): int
    {
        $code = optional(Customer::query()->whereMonth('created_at', date('m'))->whereYear(
            'created_at',
            date('Y')
        )->orderByDesc('code')->first())->code;
        return (int) ($code ? substr(filter_var($code, FILTER_SANITIZE_NUMBER_INT), 6) : 1);
    }

    public function updateCode(Customer $customer)
    {
        if ($customer->code) {
            return;
        }
        $customer->code = RandomHelper::customerCode();
        $customer->save();
    }

    /**
     * @param string $email
     * @return Builder|Model
     */
    public function getCustomerByEmail(string $email)
    {
        return Customer::query()->withoutGlobalScope(OrganizationScope::class)->where('email', $email)->select(
            'id',
            'code',
            'name',
            'email',
            'password',
            'organization_id',
            'status'
        )->firstOrFail();
    }

    /**
     * @return Builder|Model
     */
    public function getCustomerTest()
    {
        return $this->getCustomerByEmail(CustomerConstant::CUSTOMER_TEST);
    }

    /**
     * @return array
     */
    public function getVia(): array
    {
        $via = ViaConstant::STATUSES;
        $data = [];
        foreach (ViaConstant::STATUSES as $key => $via) {
            $data[] = StatusHelper::getInfo($key, ViaConstant::class);
        }
        return $data;
    }

    /**
     * @return array
     */
    public function reports(): array
    {
        $column = 'created_at';
        $query = Customer::query();
        return [
            'today' => (clone $query)->whereDate($column, date(TimeConstant::DATE))->count(),
            'week' => (clone $query)->whereBetween(
                $column,
                [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]
            )->count(),
            'month' => (clone $query)->whereMonth($column, date('m'))->whereYear($column, date('Y'))->count()
        ];
    }

    // Hàm test
    public function makeCode()
    {
        // https://stackoverflow.com/questions/1643431/how-to-get-random-value-out-of-an-array
        // Lấy số
        $number = rand(0, 9999);
        $numberText = strval($number);
        if ($number < 1000) {
            $numberText = '0' . $number;
        }
        if ($number < 100) {
            $numberText = '00' . $number;
        }
        if ($number < 10) {
            $numberText = '000' . $number;
        }

        // Lấy chữ
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = $characters[rand(0, strlen($characters) - 1)];

        // Mã code
        $codeCustomer = $numberText . $randomString;
        $array = str_split($codeCustomer);
        shuffle($array);
        $codeCustomer = implode("",$array);

        // Trả về
        return $codeCustomer;
    }

    // Hàm test
    public function checkInArr($arr)
    {
        $codeCustomer = $this->makeCode();
        if (in_array($codeCustomer, $arr)) {
            dump($codeCustomer);
            $this->checkInArr($arr);
        }
        return $codeCustomer;
    }

    public function store(array $data): JsonResponse
    {
        $record = DB::transaction(function () use ($data) {
            $data['level'] = 1; // Logic ngày 24-02-25: set = 1 luôn
            $data['password'] = Hash::make($data['password']);
            $data['staff_counselor_id'] = $data['staff_counselor_id'] ?? $this->getStaff(
                    ReportUserCounselingCustomer::class
                );

            $data['organization_id'] = getCurrentUser()->organization_id;
            $data['point_level'] = 25;
            $data['point_way'] = 20;
            $data['staff_group_id'] = data_get($data, 'staff_group_id');
            if($data['staff_group_id'] == null) {
                // Nếu không chọn nv quản lý thì lấy random nhân viên kinh để gán
                $staffService = \App::make('App\Services\StaffService');
                $userGroups = $staffService->getUserGroup();
                if(in_array(getCurrentUserId(), $userGroups)) {
                    $data['staff_group_id'] = getCurrentUserId();
                }else {
                    if(count($userGroups)) {
                        $data['staff_group_id'] = $userGroups[0];
                    }
                }

            }
            $data['staff_group_time_assign'] = date('Y-m-d H:i:s');
            $data['creator_id'] = getCurrentUserId();
            $customer = Customer::query()->create($data);

            // Lưu thêm thời gian gán point_way
            $data_point_way_time = [
                'customer_id' => (int) $customer->id,
                'type' => CustomerPointTime::CUSTOMER_POINT_WAY,
                'case' => CustomerPointTime::CUSTOMER_POINT_WAY_MAKE_ACCOUNT
            ];
            $customerPointTimeModel = \App::make('App\Models\CustomerPointTime');
            $customerPointTimeModel->create($data_point_way_time);

            if (isset($data['customer_delivery']) && count($data['customer_delivery']) > 0) {
                (new CustomerDeliveryService())
                    ->storeMultiRecord(optional($customer)->id, $data['customer_delivery']);
            }
            if (isset($data['customer_offer'])) {
                $data['customer_offer']['customer_id'] = optional($customer)->id;
                CustomerOffer::query()->create($data['customer_offer']);
            }
            ReportCustomer::query()->create(['customer_id' => optional($customer)->id]);
            $label = Label::query()->where('code', Label::KI_GUI)->first();
            if(isset($data['service']) && $data['service'] == 1 && $label) {
                CustomerLabel::query()->create(
                    [
                        'customer_id' => $customer->id,
                        'label_id' => $label->id
                    ]
                );
                $params['label_id'] = $label->id;
                $customerUpdate = Customer::query()->where('id', $customer->id)->first();
                $customerUpdate->update($params);
            }
            // Thêm noti
            $this->updateOrderNotiChangeStatus($customer);

            return $customer;
        });
        return resSuccessWithinData(new $this->_resource($record));
    }


    public function getStaffRandom($permission)
    {
        $userRepo = \App::make('App\Models\User');
        $user = $userRepo->whereHas('permissions', function($role) use($permission) {
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

    public function updateOrderNotiChangeStatus($customer)
    {
        $staff_care_id      = $customer->staff_care_id;
        $staff_counselor_id = $customer->staff_counselor_id;
        $staff_group_id     = $customer->staff_group_id;

        $notificationService = \App::make('App\Services\NotificationService');

        $data_customer = [
            'customer_name'  => $customer->name,
            'customer_email' => $customer->email,
            'customer_id'    => $customer->id,
            'from'           => 'Admin'
        ];
        $data = [
            'group'           => 1,
            'name'            => 1001,
            'data'            => json_encode($data_customer),
            'time_create'     => time(),
            'status'          => 1
        ];

//        if ($staff_care_id) {
//            $notificationService->storeNoti($staff_care_id, $data);
//        }
//        if ($staff_counselor_id) {
//            $notificationService->storeNoti($staff_counselor_id, $data);
//        }
        if ($staff_group_id) {
            $notificationService->storeNoti($staff_group_id, $data);
        }
    }

    public function update($id, array $data)
    {
        // Thêm phần gán time lưu người giới thiệu nếu có
        $record = $this->_model->newQuery()->findOrFail($id);
        if (isset($data['customer_introduce_id']) && $record->customer_introduce_id != $data['customer_introduce_id']) {
            $data['customer_introduce_time_add'] = time();
        }
        $data = (new UserService())->updatePassword($data);

        // update logic customer only one staff (care or counselor)
        $staffCare         = $data['staff_care_id'];
        $oldStaffCare      = $record->staff_care_id;
        $staffCounselor    = $data['staff_counselor_id'];
        $oldStaffCounselor = $record->staff_counselor_id;
        if ($staffCare && ($oldStaffCounselor === $staffCounselor)) {
            $data['staff_counselor_id'] = null;
        }
        if ($staffCounselor && ($oldStaffCare === $staffCare)) {
            $data['staff_care_id'] = null;
        }
        $auth = getCurrentUser();
        if($auth->hasPermissionName('customer.change_staff_group', $auth) == false) {
            // Nếu không có quyền thay đổi nhân viên quản lý thì unset key staff_group_id
            unset($data['staff_group_id']);
        }

        return parent::update($id, $data);
    }

    /**
     * Lấy số tiền trong ví
     * @param  string|null  $customerId
     * @return float
     */
    public function getBalanceAmount(?string $customerId = null): float
    {
        return optional(
                (new ReportCustomerService())->getReportCustomerCurrent($customerId)
            )->balance_amount ?? 0;
    }

    public function getStaff(string $model): ?string
    {
        $report = (new $model)::query()->where('status', 1)->orderBy('quantity')->first();
        if(!$report) return null;
        $report->increment('quantity');
        return $report->user_id;
    }

    /**
     * @param  string  $id
     * @return Builder|Builder[]|\Illuminate\Database\Eloquent\Collection|Model|null
     */
    public function getCustomerById(string $id)
    {
        return Customer::query()->find($id);
    }

    /**
     * @param  string  $id
     * @return int
     */
    public function getCustomerLevelById(string $id): int
    {
        // Logic mới
        $customerDB = $this->getCustomerById($id);

        if ($customerDB) {
            $offerDB = $customerDB->offer;

            if ($offerDB && $offerDB->level_rate) {
                return $offerDB->level_rate;
            }

            return $customerDB->level;
        }

        return 0;

        // Logic cũ
        return optional($this->getCustomerById($id))->level ?? 0;
    }

    /**
     * force delete customer
     * @return mixed
     */
    public function forceDeleteCustomer() {
        $list = Customer::query()
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
     * restore customer trash
     * @param array $customerIds
     * @return mixed
     */
    public function restoreCustomer($customerIds) {
        $list = Customer::query()
            ->withTrashed(true)
            ->whereNotNull('deleted_at')
            ->whereIn('id', $customerIds)
            ->get();

        foreach ($list as $item) {
            DB::transaction(function () use ($item) {
                $item->restore();
            });
        }
    }
    public function getDataExportList($params, $auth)
    {
        $query = Customer::select([
            'id',
            'code',
            'name',
            'phone_number',
            'level',
            'business_type',
            'email',
            'created_at',
            'total_orders',
            'status',
            'staff_group_id',
            'province_id',
            'last_order_at'
        ])->with(['report', 'offer', 'deliveryDefault', 'province', 'deliveryDefault.district', 'deliveryDefault.ward', 'staffGroup']);
        if(!empty($params['search_all'])) {
            $query->where(function ($q) use ($params) {
                $q->where('code', $params['search_all']);
                $q->orwhere('phone_number', $params['search_all']);
                $q->orwhere('email', $params['search_all']);
                $q->orwhere('name', 'like', '%' . $params['search_all'] . '%');
            });
        }
        if(!empty($params['level'])) {
            $query->where('level', $params['level']);
        }
        if(!empty($params['code'])) {
            $query->where('code', $params['code']);
        }
        if(!empty($params['name'])) {
            $query->where('name', 'like', '%' . $params['name'] . '%');
        }
        if(!empty($params['email'])) {
            $query->where('email', $params['email']);
        }
        if(!empty($params['phone_number'])) {
            $query->where('phone_number', $params['phone_number']);
        }
        if(!empty($params['province_id'])) {
            $query->where('province_id', $params['province_id']);
        }
        if(isset($params['status'])) {
            $query->where('status', $params['status']);
        }
        if(!empty($params['service'])) {
            $query->where('service', $params['service']);
        }
        if(!empty($params['business_type'])) {
//            $query->where('business_type', $params['business_type']);
            if (is_array($params['business_type'])) {
                $query->whereIn('business_type', $params['business_type']);
            } else {
                $query->where('business_type', $params['business_type']);
            }
        }
        if(!empty($params['staff_group_id'])) {
            $staffGroupIds = is_array($params['staff_group_id']) ? $params['staff_group_id'] : [$params['staff_group_id']];
            $query->whereIn('customers.staff_group_id', $staffGroupIds)
                    ->when(in_array("0", $staffGroupIds), function($q) {
                        return $q->orWhereNull('customers.staff_group_id');
                    });
        }
        if(!empty($params['via'])) {
            if (is_array($params['via'])) {
                $query->whereIn('via', $params['via']);
            } else {
                $query->where('via', $params['via']);
            }
        }
        if(!empty($params['customer_reason_inactive_id'])) {
            $query->where('customer_reason_inactive_id', $params['customer_reason_inactive_id']);
        }
        if(!empty($params['point_level'])) {
            $query->where('point_level', $params['point_level']);
        }
        if(!empty($params['label_id'])) {
            $labelIds = is_array($params['label_id']) ? $params['label_id'] : [$params['label_id']];
            $query->whereHas('labels', function ($q) use ($labelIds) {
                $q->whereIn('labels.id', $labelIds);
            });
            if(!empty($params['created_label'])) {
                $createdLabel = $params['created_label'];
                $startDateLabel = Carbon::createFromFormat('Y-m-d', data_get($createdLabel, 0))->startOfDay();
                $endDateLabel = Carbon::createFromFormat('Y-m-d', data_get($createdLabel, 1))->endOfDay();
                $query->whereIn('customers.id', function($query) use ($startDateLabel, $endDateLabel) {
                    $query->select('customer_label.customer_id')
                    ->from('customer_label')
                    ->whereBetween('customer_label.created_at',[$startDateLabel, $endDateLabel]);
                });
            }
        }
        if(!empty($params['last_order'])) {
            $last_order =  (int) $params['last_order'];
            $time = Carbon::now()->subDays($last_order)->startOfDay();
            $query->whereDate('last_order_at', '>=', $time);
        }
        if(!empty($params['created_at'])) {
            $rangeDate = explode(",", $params['created_at']);
            $fromDate = data_get($rangeDate, 0) ? Carbon::parse(data_get($rangeDate, 0))->startOfDay() : null;
            $toDate = data_get($rangeDate, 1) ? Carbon::parse(data_get($rangeDate, 1))->endOfDay() : null;
            if($fromDate && $toDate){
                $query->whereBetween('created_at', [$fromDate, $toDate]);
            }elseif($fromDate){
                $query->whereDate('created_at', '>=', $fromDate);
            }else{
                $query->whereDate('created_at', '<=', $toDate);
            }
        }
        if(!empty($params['last_month']) || !empty($params['re_order'])) {
            $query->where('customers.status', 1);
            $numberLastMonth = !empty($params['last_month']) ? $params['last_month'] : 1;
            $now = Carbon::now();
            $lastMonth = $now->copy()->subMonths($numberLastMonth)->startOfDay();
            $startOfWeek = $now->copy()->startOfWeek();
            $endOfWeek = $now->copy()->endOfWeek();
            if(!empty($params['re_order'])) {
                $customerIds = [];
                $orders = Customer::query()
                    ->select(
                        DB::raw('customers.id AS customer_id')
                    )
                    ->join('orders', function($q) use ($startOfWeek, $endOfWeek){
                        $q->on('orders.customer_id', '=', 'customers.id')
                            ->whereBetween('orders.created_at', [$startOfWeek, $endOfWeek]);
                        }
                    )
                    ->whereNotNull('customers.last_order_at')
                    ->whereDoesntHave('order', function ($order) use ($numberLastMonth, $startOfWeek, $endOfWeek){
                        $order->whereRaw(DB::raw('orders.created_at BETWEEN DATE_ADD(customers.last_order_at, INTERVAL -'.$numberLastMonth.' MONTH) AND DATE_ADD(customers.last_order_at, INTERVAL -1 MINUTE)'))
                            ->whereNotBetween('orders.created_at', [$startOfWeek, $endOfWeek]);
                    })
                    ->where('customers.created_at', '<', Carbon::now()->subMonths($numberLastMonth))
                    ->where('customers.status', 1)
                    ->groupBy('customers.id')
                    ->get();
                $consignments = Customer::query()
                    ->select(
                        DB::raw('customers.id AS customer_id')
                    )
                    ->join('consignments', function($q) use ($startOfWeek, $endOfWeek){
                        $q->on('consignments.customer_id', '=', 'customers.id')
                            ->whereBetween('consignments.created_at', [$startOfWeek, $endOfWeek]);
                        }
                    )
                    ->whereNotNull('customers.last_order_at')
                    ->whereDoesntHave('consignments', function ($order) use ($numberLastMonth, $startOfWeek, $endOfWeek){
                        $order->whereRaw(DB::raw('consignments.created_at BETWEEN DATE_ADD(customers.last_order_at, INTERVAL -'.$numberLastMonth.' MONTH) AND DATE_ADD(customers.last_order_at, INTERVAL -1 MINUTE)'))
                            ->whereNotBetween('consignments.created_at', [$startOfWeek, $endOfWeek]);
                    })
                    ->where('customers.created_at', '<', Carbon::now()->subMonths($numberLastMonth))
                    ->where('customers.status', 1)
                    ->groupBy('customers.id')
                    ->get();
                $orderPackage = Customer::query()
                    ->select(
                        DB::raw('customers.id AS customer_id'),
                        DB::raw('COUNT(DISTINCT order_package.id) AS total_order'),
                    )
                    ->join('order_package', function($q) use ($startOfWeek, $endOfWeek){
                        $q->on('order_package.customer_id', '=', 'customers.id')
                            ->whereBetween('order_package.created_at', [$startOfWeek, $endOfWeek])
                            ->whereNull('order_package.order_id')
                            ->where('order_package.order_type', Consignment::class);
                        }
                    )
                    ->whereNotNull('customers.last_order_at')
                    ->whereDoesntHave('orderPackage', function ($order) use ($numberLastMonth, $startOfWeek, $endOfWeek){
                        $order->whereRaw(DB::raw('order_package.created_at BETWEEN DATE_ADD(customers.last_order_at, INTERVAL -'.$numberLastMonth.' MONTH) AND DATE_ADD(customers.last_order_at, INTERVAL -1 MINUTE)'))
                            ->whereNotBetween('order_package.created_at', [$startOfWeek, $endOfWeek])
                            ->whereNull('order_package.order_id')
                            ->where('order_package.order_type', Consignment::class);
                    })
                    ->where('customers.created_at', '<', Carbon::now()->subMonths($numberLastMonth))
                    ->where('customers.status', 1)
                    ->groupBy('customers.id')
                    ->get();
                foreach($orders as $order) {
                    array_push($customerIds, $order->customer_id);
                }
                foreach($consignments as $consignment) {
                    array_push($customerIds, $consignment->customer_id);
                }
                foreach($orderPackage as $item) {
                    array_push($customerIds, $item->customer_id);
                }
                $query->whereIn('customers.id', array_unique($customerIds))
                    ->where('customers.created_at', '<', Carbon::now()->subMonths($numberLastMonth));
            }else {
                $query->where(DB::raw("IF(customers.last_order_at IS NOT NULL, customers.last_order_at, customers.created_at)"), '<', $lastMonth)
                    ->whereDoesntHave('packages', function ($q) use ($lastMonth){
                        $q->whereBetween('created_at', [$lastMonth, Carbon::now()])
                            ->where('order_type', Consignment::class);
                    });
            }
            if (request()->query('group_id_report')) {
                $user_group_ids = $this->getUserGroup(request()->query('group_id_report'));
                $query->whereIn('customers.staff_group_id', $user_group_ids);
            }
        }
        $perService = \App::make('App\Services\PerService');
        if(data_get($params, 'isSearchCustomer') != "true") {
            if(data_get($params, 'waiting_for_care') != 1) {
                // Nếu k phải màn chờ chăm sóc mới phân quyền xem
                $query = $perService->getConditionByUserLogin($query, Customer::query(), $auth);
            }
            if(data_get($params, 'past')) {
                $longTimeOrder2020 = Carbon::createFromFormat('Y-m', '2022-12')->startOfmonth();
                if(data_get($params, 'past') == 'long_order') {
                    $query->where(DB::raw("IF(customers.last_order_at IS NULL OR customers.last_order_at < customers.created_at, customers.created_at, customers.last_order_at)"), '<', $longTimeOrder2020);
                }
                if(data_get($params, 'past') == 'long_interact') {
                    $query->where('customers.created_at', '<', $longTimeOrder2020)
                    ->whereHas('labels', function ($q){
                        $q->where('labels.id', 1);
                    });
                }
            }else {
                if(data_get($params, 'waiting_for_care') == 1) {
                    // Màn chờ chăm sóc
                    $query = $this->getCustomerWaitingForCare($query);
                } else if(data_get($params, 'close_account') == 1) {
                    // Màn đóng khoản
                    $query = $query->where('status', CustomerConstant::STATUS_INACTIVE);
                }
                else {
                    $timeLabel = Carbon::createFromFormat('Y-m', '2024-12')->endOfmonth();
                    $timeNoLabel =  [
                        Carbon::createFromFormat('Y', '2020')->startOfYear(),
                        Carbon::createFromFormat('Y', '2024')->endOfYear()
                    ];
                    $labelIds = Label::whereIn('code', [
                        'DA_DAT_HANG',
                        'DA_LEN_DON',
                        'KI_GUI',
                        'KI_GUI_DA_LEN_DON',
                        'KHONG_CO_NHU_CAU',
                        'MAT_HANG_KHONG_NHAP_DUOC',
                        'KHONG_TUONG_TAC_DUOC'
                    ])->pluck('id')->toArray();
                    $query->where('status', CustomerConstant::STATUS_ACTIVE)
                            ->where(function($subQuery) use ($timeNoLabel, $timeLabel, $labelIds) {
                                $subQuery->where(function($q) use ($timeLabel) {
                                    $q->whereDoesntHave('labels')
                                        ->where(DB::raw("IF(customers.last_order_at IS NOT NULL, customers.last_order_at, customers.created_at)"), '>', $timeLabel);
                                    })
                                    ->orWhere(function($q1) use ($timeLabel, $labelIds) {
                                        $q1->where(DB::raw("IF(customers.last_order_at IS NOT NULL, customers.last_order_at, customers.created_at)"), '>', $timeLabel)
                                            ->whereHas('labels', function($q2) use ($labelIds) {
                                                $q2->whereIn('label_id', $labelIds);
                                            });
                                            ;
                                    })
                                    ->orWhere(function($q1) use ($timeLabel, $labelIds) {
                                        $q1->whereHas('labels', function($q2) use ($labelIds) {
                                            $q2->whereNotIn('label_id', $labelIds);
                                        });
                                    });
                            });
                }
            }
        }
        // $timeStart = Carbon::now()->subMonths(1)->startOfMonth();
        // // Chỉ cho xuất từ 2 tháng đổ lại
        // $query->where('created_at', '>=', $timeStart);
        if(!empty($params['assign_time_sort'])) {
            $query->orderBy('staff_group_time_assign', $params['assign_time_sort']);
        }
        if(!empty($params['last_order_at_sort'])) {
            $query->orderBy('last_order_at', $params['last_order_at_sort']);
        }
        if(!empty($params['year'])) {
            $year = $params['year'];
            $yearBetween = [
                Carbon::createFromFormat('Y', $year)->startOfYear(),
                Carbon::createFromFormat('Y', $year)->endOfYear()
            ];
            $sortBy = $params['sort_by'] ?? 1;
            if($sortBy == 1) {
                $query->whereBetween('customers.created_at', $yearBetween);
            }else {
                $query->whereBetween('customers.last_order_at', $yearBetween);
            }
        }
        $query->groupBy('customers.id');
        return $query;
    }

    public function getDataExportListActive($params)
    {
        return Customer::where('customers.status', CustomerConstant::STATUS_ACTIVE)
                        ->with([
                            'orders' => function($q){
                                $q->where('orders.status', OrderConstant::KEY_STATUS_WAITING_DEPOSIT);
                            },
                            'orders.details',
                            'orders.details.category',
                            'orders.orderStatusTime'
                        ])
                        ->when(!empty($params['name']), function($q) use ($params){
                        $q->where('customers.name', 'like', '%'.$params['name'].'%');
                        })
                        ->when(!empty($params['code']), function($q) use ($params){
                            $q->where('customers.code', $params['code']);
                        })
                        ->when(!empty($params['phone_number']), function($q) use ($params){
                            $q->where('customers.phone_number', $params['phone_number']);
                        })
                        ->when(!empty($params['wait_deposit_level']), function($q) use ($params){
                            $q->whereHas('orders.orderStatusTime', function($query) use ($params){
                                $level = $params['wait_deposit_level'];
                                $now = Carbon::now();
                                $max = $now->copy()->addMinutes(-5);
                                $min = $now->copy()->addMinutes(-60);
                                if($level == CustomerConstant::WAIT_DEPOSIT_LV1){
                                    $query->where('order_status_times.status_1', '<=', $now)->where('order_status_times.status_1', '>', $max);
                                }elseif($level == CustomerConstant::WAIT_DEPOSIT_LV2){
                                    $query->whereBetween('order_status_times.status_1', [$min, $max]);
                                }else{
                                    $query->where('order_status_times.status_1', '<', $min);
                                }
                            });
                        });
    }

    public function exportTransaction($customerId, $params)
    {
        $where = [
            ['customer_id', $customerId]
        ];
        if(!empty(data_get($params, 'code'))){
            $where[] = ['code', data_get($params, 'code')];
        }
        if(!empty(data_get($params, 'status'))){
            $where[] = ['status', data_get($params, 'status')];
        }
        $transactions = Transaction::where($where)
                                    ->when(!empty(data_get($params, 'created_at')), function($q) use ($params){
                                        $rangeDate = explode(',', data_get($params, 'created_at'));
                                        $q->whereBetween('created_at', $rangeDate);
                                    })
                                    ->when(!empty(data_get($params, 'order_code')), function($q) use ($params){
                                        $q->whereHas('order', function (Builder $query) use ($params) {
                                            $query->where('code', data_get($params, 'order_code'));
                                        });
                                    })
                                    ->with(['order'])->orderBy('created_at', 'desc')->get();
        $result = [];
        foreach($transactions as $key => $transaction){
            $newItem = [
                $key + 1, // STT
                Carbon::parse($transaction->created_at)->format('d-m-Y H:i:s'),
                TransactionConstant::STATUSES[$transaction->status],
                $transaction->code,
                $transaction->order ? $transaction->order->code : '',
                $transaction->content,
                number_format($transaction->amount, 0, '', ','),
                number_format($transaction->balance, 0, '', ','),
            ];
            $result[] = $newItem;
        }
        return $result;
    }

    /**
     * Lấy thông tin customer qua code
     * @param  string  $code
     * @return Builder|Model|object|null
     */
    public function getUserByCode(string $code)
    {
        return Customer::query()->withoutGlobalScope(OrganizationScope::class)
            ->where([
                ['code', $code], ['status', CustomerConstant::STATUS_ACTIVE]
            ])->select(
                'id',
                'name',
                'code',
                'email',
                'status',
                'phone_number',
                'organization_id'
            )->first();
    }

    public function chargeFromApp($amount, Customer $customer, Bank $bank, string $content)
    {
        $amount = floatval($amount);

        DB::beginTransaction();
        try {
            $customerId = $customer->id;
            // update balance and transaction

            // Không tạo ở đây, tạo cuối cùng để lấy liên kết vs Fund_transaction
            // Không tạo ở đây, tạo cuối cùng để lấy liên kết vs Fund_transaction

            // (new TransactionService())->setTransactionIncrement(
            //     $amount,
            //     TransactionConstant::STATUS_DEPOSIT,
            //     'Nạp tiền từ app',
            //     $customerId,
            //     $customer
            // );
            // Cộng vào số tiền khách đã nạp
            // ReportCustomer::query()->where('customer_id', $customerId)->increment('deposited_amount', $amount);
            // update bank customer
            //$bank = Bank::query()->where('charge_code', $bankChargeCode)->firstOrFail();
//            $dataCustomerBankPayment = [
//                'customer_id' => $customer->id,
//                'bank_id'     => $bank->id,
//                'amount'      => $amount
//            ];
//            CustomerBankPayment::create($dataCustomerBankPayment);
            // update fund
            $admin = User::query()->where('email', User::ADMIN_EMAIL)->firstOrFail();

            $fund = Fund::query()->where('bank_id', $bank->id)->first();
            $fundService = \App::make('App\Services\FundService');
            $bank_number = $fundService->getBankNumberInContent($content);
            if ($bank_number) {
                $fund = Fund::query()->where('bank_number', $bank_number)->first();
            }
            if (!$fund) { // create new fund
                Log::info('chargeFromApp Logic ngày 17/03/2025 yêu cầu đá ra luôn');
                return; // Logic ngày 17/03/2025 yêu cầu đá ra luôn

                $dataMoneyFund = [
                    'name'            => $bank->name,
                    'type_currency'   => Fund::BANK_TYPE_CURRENCY,
                    'unit_currency'   => Fund::VND_UNIT_CURRENCY,
                    'organization_id' => $admin->organization_id,
                    'bank_id'         => $bank->id,
                    'initial_balance' => $amount,
                    'total_money_in'  => $amount,
                    'total_money_out' => 0,
                    'current_balance' => $amount,
                    'note'            => 'Nạp tiền từ app',
                ];
                $fund      = Fund::create($dataMoneyFund);
                //$oldAmount = $amount;
            } else {
                //$oldAmount  = $fund->current_balance;
                //$oldMoneyIn = $fund->total_money_in;

                $fund->total_money_in  += $amount;
                $fund->current_balance += $amount;
                $fund->save();
            }
            // update fund transaction
            $fundTypePay = FundTypePay::query()->where([
                ['type', FundConstant::FUND_TYPE_PAY_CHARGE], ['code', FundConstant::FUND_TYPE_PAY_CHARGE_CODE]
            ])->firstOrFail();

            $temp_log = [
                'action'             => 'Tạo mới',
                'user_request_name'  => $admin->name,
                'user_request_email' => $admin->email,
                'user_request_id'    => $admin->id,
                'column'             => "",
                'value_old'          => "",
                'value_new'          => "",
                'time'               => date('Y-m-d H:i:s', time()),
            ];

            $dataFundTransaction = [
                'organization_id'    => $admin->organization_id,
                'user_create_id'     => $admin->id,
                'code'               => $this->generateRandomCode(),
                'type_object'        => FundTransaction::CUSTOMER_TYPE_OBJECT,
                'type_pay'           => FundTransaction::REVENUE_TYPE_PAY,
                'fund_type_pay_id'   => $fundTypePay->id,
                'fund_type_pay_code' => $fundTypePay->code,
                'fund_id'            => $fund->id,
                'fund_name'          => $fund->name,
                'fund_type_currency' => $fund->type_currency,
                'fund_unit_currency' => $fund->unit_currency,
                'customer_id'        => $customer->id,
                'customer_code'      => $customer->code,
                'customer_name'      => $customer->name,
                'customer_phone'     => $customer->phone_number,
                'money'              => $amount,
                'money_update'       => $amount,
                'balance'            => $fund->current_balance,
                'note'               => 'Chuyển tiền mua hàng',
                'log'                => json_encode([$temp_log]),
                'status'             => 1
            ];
            $fundTransaction = FundTransaction::create($dataFundTransaction);
            // update fund transaction log
            $dataFundTransaction['id'] = $fundTransaction->id;
            $dataFundLog = [
                'fund_transactions' => json_encode($dataFundTransaction),
                'name'              => 'Nạp tiền từ app',
                'user_id'           => $admin->id,
                'user_name'         => $admin->name,
                'user_email'        => $admin->email
            ];
            LogFundTransaction::create($dataFundLog);

            // Tạo giao dịch cho khách
            (new TransactionService())->setTransactionIncrement(
                $amount,
                TransactionConstant::STATUS_DEPOSIT,
                'Chuyển tiền mua hàng',
                $customerId,
                $fundTransaction
            );
            // Cộng vào số tiền khách đã nạp
            ReportCustomer::query()->where('customer_id', $customerId)->increment('deposited_amount', $amount);

            DB::commit();
            return resSuccess();
        } catch (\Illuminate\Database\QueryException $e) {
            Log::info('Lỗi khi nạp tiền từ App :' . $e->getMessage());
            DB::rollBack();

            //dd($e->getMessage());
            throw new \Exception('Nạp tiền thất bại: '.$e->getMessage());
        }
    }

    public function getBankTypeFromMsg($msg)
    {
        $type = CustomerConstant::FROM_OTHER_BANK; // techcombank, pvcom bank

        if (substr_count($msg, 'Agribank:')) {
            return CustomerConstant::FROM_AGRIBANK;
        }
        if (substr_count($msg, 'VietinBank:')) {
            return CustomerConstant::FROM_VIETINBANK;
        }
        if (substr_count($msg, 'tai BIDV')) {
            return CustomerConstant::FROM_BIDV;
        }
        if (substr_count($msg, 'TK VCB')) {
            return CustomerConstant::FROM_VIETCOMBANK;
        }
        if (substr_count($msg, 'tai VPB')) {
            return CustomerConstant::FROM_VPBANK;
        }
        if (substr_count($msg, 'NamABank:')) {
            return CustomerConstant::FROM_NAMABANK;
        }

        return $type;
    }

    public function getAmountCharge(string $msg)
    {
        $amount = 0;

        $isCharge = substr_count($msg, "+") || substr_count($msg, "nop");
        if (!$isCharge) {
            return $amount;
        }

        if (substr_count($msg, "+")) {
            $startIndex = strpos($msg, "+") + 1;
        } else if (substr_count($msg, "nop")) { // NamA bank
            $startIndex = strpos($msg, "nop") + 3;
        } else {
            $startIndex = "";
        }

        if (!$startIndex) {
            return $amount;
        }

        $newContent = substr($msg, $startIndex);

        $endIndex = strpos($newContent, "VND") > 0 ? strpos($newContent, "VND") :
            (strpos($newContent, ".") > 0 ? strpos($newContent, ".") :
                (strpos($newContent, "So du") ? strpos($newContent, "So du") : '')
            );

        if ($endIndex) {
            $amount = substr($newContent, 0, $endIndex);
        } else {
            $amount = substr($newContent, 0);
        }

        $amount = str_replace(",","", trim($amount));
        $amount = str_replace(".","", trim($amount));

        return floatval($amount);
    }

    public function getListChargePrefix()
    {
        return collect(CustomerConstant::CHARGE_PREFIX);
        //return Bank::query()->pluck('charge_code');
    }
    public function getChargeContent(string $msg, $chargePrefix)
    {
        $chargeFormat = "";

        $isCharge     = false;
        $startIndex   = "";

        foreach ($chargePrefix as $prefix) {
            if (substr_count($msg, $prefix) || substr_count($msg, strtolower($prefix))) {
                $isCharge   = true;
                $startIndex = strpos($msg, $prefix) ? strpos($msg, $prefix) :
                    strpos($msg, strtolower($prefix));
                break;
            }
        }

        if (!$isCharge || !$startIndex) {
            return $chargeFormat;
        }

        $newContent = substr($msg, $startIndex);
        $endIndex   = strpos($newContent, "-") > 0 ? strpos($newContent, "-") : (
        strpos($newContent, ".") ? strpos($newContent, ".") : ""); // BIDV, Pvcom bank

        if ($endIndex) {
            $chargeFormat = substr($newContent, 0, $endIndex);
        } else {
            $chargeFormat = substr($newContent, 0);
        }

        // cut again if exist ".", "-"
        $chargeFormat = $this->getContentChargeAgain($chargeFormat);
        $chargeFormat = explode(" ", $chargeFormat);
        if (count($chargeFormat) < 2) {
            return is_array($chargeFormat) ? implode($chargeFormat) : $chargeFormat;
        }

        $prefixResult = $chargeFormat[0];
        $userCode     = str_replace(".", "", trim($chargeFormat[1]));

        return $prefixResult . ' ' . $userCode;
    }

    public function getContentChargeAgain($text)
    {
        $specialCharacters = [ ".", "-", ")", "(", "CT ", "C T", ";" ];

        $endIndex = $this->getIndexBySpecialCharacter($text, $specialCharacters);
        if (strpos($text, "-") > 0) {
            $endIndex = strpos($text, "-");
        }

        if (!$endIndex) {
            return $text;
        }

        $contentCharge = substr($text, 0, $endIndex);
        foreach ($specialCharacters as $character) {
            if (substr_count($contentCharge, $character)){
                $contentCharge = $this->getContentChargeAgain($contentCharge);
            }
        }

        return $contentCharge;
    }

    public function getIndexBySpecialCharacter(string $text, array $specialCharacters) {
        $index = "";

        foreach ($specialCharacters as $character) {
            if (strpos($text, $character) > 0) {
                $index = strpos($text, $character);
                break;
            }
        }

        return $index;
    }

    public function getTransactionCode(string $content)
    {
        $bankType = $this->getBankTypeFromMsg($content);
        if ($bankType == CustomerConstant::FROM_BIDV) {
            return $this->getTransactionCodeBIDV($content);
        }
        if (substr_count($content, "FT") || substr_count($content, "ft")) {
            return $this->getCodeWithPrefix1($content);
        }
        if (substr_count($content, "TRACE") || substr_count($content, "trace")) {
            return $this->getCodeWithPrefix2($content);
        }
        // get code with prefix 3
        $prefix       = ["MB(", "trac e:"];
        $checkPrefix3 = false;
        foreach ($prefix as $item) {
            if (substr_count($content, $item)) {
                $checkPrefix3 = true;
                break;
            }
        }

        if ($checkPrefix3) {
            return $this->getCodeWithPrefix3($content);
        }
        // MBVCB
        if (substr_count($content, 'MBVCB')) {
            $sIndex       = strpos($content, "MBVCB");
            $newContent   = substr($content, $sIndex);
            $transCodeArr = explode('.', $newContent);
            return !empty($transCodeArr[2]) ? $transCodeArr[2] : '';
        }
        // prefix 4
        $transactionCode = $this->getCodeWithPrefix4($content);

        return $transactionCode;
    }

    public function getCodeWithPrefix1($content)
    {
        $code = "";

        $sIndex     = strpos($content, "FT") ? strpos($content, "FT") :
            (strpos($content, "ft") ? strpos($content, "ft") : "");
        if (!$sIndex) {
            return $code;
        }

        $newContent = substr($content, $sIndex);

        $nextPrefix = [" ", ".", ":", ")", "-"];
        $eIndex     = "";
        foreach ($nextPrefix as $item) {
            if (strpos($newContent, $item)) {
                $eIndex = strpos($newContent, $item);
            }
        }

        if ($eIndex) {
            $code = substr($newContent, 0, $eIndex);
        } else {
            $code = substr($newContent, 0);
        }

        return $code;
    }

    public function getCodeWithPrefix2($content)
    {
        $sIndex = strpos($content, "TRACE") ? strpos($content, "TRACE") :
            (strpos($content, "trace") ? strpos($content, "trace") : "");
        $code   = $sIndex ? substr($content, ($sIndex + 6), 6) : "";

        if ($code && !is_numeric($code)) {
            $code = $this->getCodeWithPrefix4($content);
        }

        return $code;
    }

    public function getCodeWithPrefix3($content)
    {
        $prefix = ["MB(", "trac e:"];
        $sIndex = "";

        $lengPrefix = 0;
        foreach ($prefix as $item) {
            if (substr_count($content, $item)) {
                $sIndex = strpos($content, $item);
                $lengPrefix = strlen($item);
            }
        }

        $code = $sIndex ? substr($content, ($sIndex + $lengPrefix), 6) : "";
        return $code;
    }

    public function getCodeWithPrefix4($content)
    {
        $code = "";

        $prefix = CustomerConstant::CHARGE_PREFIX;
        $sIndex = "";
        foreach ($prefix as $item) {
            if (substr_count($content, $item)) {
                $sIndex = strpos($content, $item);
            }
        }

        if (!$sIndex) {
            return $code;
        }

        $newContent = substr($content, $sIndex);
        $nextPrefix = [".", ":", "(", "-"];
        $nIndex     = "";
        foreach ($nextPrefix as $item) {
            if (strpos($newContent, $item)) {
                $nIndex = strpos($newContent, $item);
            }
        }

        if (!$nIndex) {
            return $code;
        }

        $code = substr($newContent, ($nIndex + 1), 6);

        $date = date('dmy');
        if ($code == $date) {
            $code = $this->getCodeByDate($content, $date);
        }

        return $code;
    }

    public function getCodeByDate(string $content, $date)
    {
        $transCode = '';

        if (substr_count($content, $date)) {
            $sIndex       = strpos($content, $date);
            $newContent   = substr($content, $sIndex);

            $eIndex = strpos($newContent, '.');
            if (!$eIndex) {
                return $transCode;
            }

            $resultContent = substr($newContent, 0, $eIndex);

            return substr($resultContent, -6, 6);
        }

        return $transCode;
    }

    public function getTransactionCodeBIDV(string $content)
    {
        if (substr_count($content, 'MBVCB')) {
            $sIndex       = strpos($content, "MBVCB");
            $newContent   = substr($content, $sIndex);
            $transCodeArr = explode('.', $newContent);
            return !empty($transCodeArr[2]) ? $transCodeArr[2] : '';
        }

        $prefix = CustomerConstant::CHARGE_PREFIX;
        $sIndex = "";
        foreach ($prefix as $item) {
            if (substr_count($content, $item)) {
                $sIndex = strpos($content, $item);
            }
        }
        if (!$sIndex) {
            return '';
        }

        $newContent = substr($content, $sIndex);

        $checkExistCode = false;
        $prefixCodes    = ['-'];
        foreach ($prefixCodes as $pre) {
            if (substr_count($newContent, $pre)) {
                $checkExistCode = true;
                break;
            }
        }

        $transCode  = $checkExistCode ? substr($newContent, -6, 6) : time().rand(0, 1000);
        return $transCode ?? '';
    }

    public function checkTransactionCodeExist(string $code)
    {
        if (!$code) { // chưa lấy được mã giao dịch
            return false;
        }

        return ChargeAppHistory::query()->where('code', $code)->exists();
    }

    public function saveHistory($transactionCode = null, $chargeContent, $content)
    {
        ChargeAppHistory::create([
            'charge_content' => $chargeContent,
            'code'           => $transactionCode,
            'content'        => $content
        ]);
    }

    public function chargeMoney(array $data, Customer $customer)
    {
        DB::beginTransaction();
        try {
            $customerId = $customer->id;
            // update balance and transaction
            (new TransactionService())->setTransactionIncrement(
                $data['amount'],
                TransactionConstant::STATUS_DEPOSIT,
                $data['content'],
                $customerId,
                $customer
            );

            DB::commit();
            return resSuccess();
        } catch (\Illuminate\Database\QueryException $e) {
            Log::info('Lỗi khi nạp tiền từ web :' . $e->getMessage());
            DB::rollBack();
            return resError($e->getMessage());
        }
    }

    public function initTotalOrders()
    {
        $customers = Customer::query()
            ->select(DB::raw('COUNT(orders.id) as totalOrder, customers.id, customers.total_orders'))
            ->join('orders','customers.id','=','orders.customer_id')
            ->groupBy('customers.id')
            ->get();

        foreach ($customers->chunk(1000) as $chunk) {
            foreach ($chunk as $customer) {
                $customer->total_orders = $customer->totalOrder;
                $customer->save();
            }
        }
    }

    // Đếm lại đơn của khách đồng bộ
    public function initTotalOrdersV2()
    {
        $customers = Customer::query()
            ->select(DB::raw('COUNT(orders.id) as totalOrder, customers.id, customers.total_orders'))
            ->join('orders','customers.id','=','orders.customer_id')
            ->groupBy('customers.id')
            ->get();

        foreach ($customers->chunk(1000) as $chunk) {
            \Log::info('hhehehe');
            $this->updateOrderReportCustomerQueue($chunk);
        }

        \Log::info('initTotalOrdersV2: done');
    }

    public function updateOrderReportCustomerQueue($chunk)
    {
        foreach ($chunk as $customer) {
            $reportDB = $customer->report;
            if ($reportDB) {
                $reportDB->orders_number = $customer->totalOrder;
                $reportDB->orders_number_increment = $customer->totalOrder;
                $reportDB->save();
            }
        }
    }

    // Đếm lại kiện của khách đồng bộ
    public function initTotalPackagesV2()
    {
        $customers = Customer::query()
            ->select(DB::raw('COUNT(order_package.id) as totalPackage, customers.id'))
            ->join('order_package','customers.id','=','order_package.customer_id')
            ->groupBy('customers.id')
            ->get();

        foreach ($customers->chunk(1000) as $chunk) {
            \Log::info('kkkakk');
            $this->updatePackageReportCustomerQueue($chunk);
        }

        \Log::info('initTotalPackagesV2: done');
    }

    public function updatePackageReportCustomerQueue($chunk)
    {
        foreach ($chunk as $customer) {
            $reportDB = $customer->report;
            if ($reportDB) {
                $reportDB->packages_number = $customer->totalPackage;
                $reportDB->save();
            }
        }
    }

    // Đếm lại kiện của đơn
    public function initTotalPackagesOfOrderV2()
    {
        $countOrder =  Order::query()->count();
        $step = 10000;
        for ($i=0; $i < $countOrder; $i+=$step) {
            // $this->updatePackageOrderQueue($step, $i);
            $job = new \App\Jobs\ReportCustomer\UpdateCountPackageOrder($step, $i);
            dispatch($job)->delay(now()->addSeconds(0))->onQueue('cron-data-2023');
        }
    }

    public function updatePackageOrderQueue($step, $i)
    {
        $orders = Order::query()
            ->select(DB::raw('COUNT(order_package.id) as totalPackage, orders.id'))
            ->join('order_package','orders.id','=','order_package.order_id')
            ->groupBy('orders.id')
            ->limit($step)
            ->offset($i)
            ->get();

        foreach ($orders->chunk(1000) as $chunk) {
            foreach ($chunk as $order) {
                $user = Order::withoutEvents(function () use ($order) {
                    $order->packages_number = $order->totalPackage;
                    $order->save();
                    return 1;
                });
            }
        }
    }

    public function withTrashed()
    {
        $customers = $this->_model->onlyTrashed()->with(['staffGroup', 'deletedBy'])->paginate();
        return resSuccessWithinData(new $this->_paginateResource($customers, CustomerWithTrashedResource::class));
    }

    public function saveFundTransaction($amount, $customer, $bank, string $content)
    {
        if (!$bank) {
            \Log::info('saveFundTransaction không bank');
            return;
        }

        $amount = floatval($amount);

        DB::beginTransaction();
        try {
            // update fund
            $admin = User::query()->where('email', User::ADMIN_EMAIL)->firstOrFail();

            $fund = Fund::query()->where('bank_id', $bank->id)->first();
            if (!$fund) { // create new fund
                Log::info('saveFundTransaction Logic ngày 17/03/2025 yêu cầu đá ra luôn');
                return; // Logic ngày 17/03/2025 yêu cầu đá ra luôn

                $dataMoneyFund = [
                    'name'            => $bank->name,
                    'type_currency'   => Fund::BANK_TYPE_CURRENCY,
                    'unit_currency'   => Fund::VND_UNIT_CURRENCY,
                    'organization_id' => $admin->organization_id,
                    'bank_id'         => $bank->id,
                    'initial_balance' => $amount,
                    'total_money_in'  => $amount,
                    'total_money_out' => 0,
                    'current_balance' => $amount,
                    'note'            => 'Nạp tiền từ app',
                ];
                $fund = Fund::create($dataMoneyFund);
            } else {
                $fund->total_money_in  += $amount;
                $fund->current_balance += $amount;
                $fund->save();
            }

            // update fund transaction
            $fundTypePay = FundTypePay::query()->where([
                ['type', FundConstant::FUND_TYPE_PAY_CHARGE], ['code', FundConstant::FUND_TYPE_PAY_CHARGE_CONFIRM_CODE]
            ])->firstOrFail();

            $temp_log = [
                'action'             => 'Tạo mới',
                'user_request_name'  => $admin->name,
                'user_request_email' => $admin->email,
                'user_request_id'    => $admin->id,
                'column'             => "",
                'value_old'          => "",
                'value_new'          => "",
                'time'               => date('Y-m-d H:i:s', time()),
            ];

            $dataFundTransaction = [
                'organization_id'    => $admin->organization_id,
                'user_create_id'     => $admin->id,
                'code'               => $this->generateRandomCode(),
                'type_object'        => FundTransaction::CUSTOMER_TYPE_OBJECT,
                'type_pay'           => FundTransaction::REVENUE_TYPE_PAY,
                'fund_type_pay_id'   => $fundTypePay->id,
                'fund_type_pay_code' => $fundTypePay->code,
                'fund_id'            => $fund->id ?? null,
                'fund_name'          => $fund->name ?? null,
                'fund_type_currency' => 2, // 1 - tiền mặt, 2- tiền ngân hàng, 3- tiền ảo
                'fund_unit_currency' => 1, // 1- VND, 2- ¥
                'customer_code'      => $customer->code ?? null,
                'customer_name'      => $customer->name ?? null,
                'customer_phone'     => $customer->phone_number ?? null,
                'money'              => $amount,
                'money_update'       => $amount,
                'balance'            => $fund->current_balance ?? null,
                'note'               => 'Nạp tiền từ app: '. $content,
                'log'                => json_encode([$temp_log]),
                'status'             => 1
            ];
            $fundTransaction = FundTransaction::create($dataFundTransaction);
            // update fund transaction log
            $dataFundTransaction['id'] = $fundTransaction->id;
            $dataFundLog = [
                'fund_transactions' => json_encode($dataFundTransaction),
                'name'              => 'Nạp tiền từ app',
                'user_id'           => $admin->id,
                'user_name'         => $admin->name,
                'user_email'        => $admin->email
            ];
            LogFundTransaction::create($dataFundLog);

            DB::commit();
            //return resSuccess();
        } catch (\Exception $e) {
            Log::info('Lỗi khi lưu quỹ giao dịch (nạp tiền từ App) :' . $e->getMessage());
            DB::rollBack();

            //dd($e->getMessage());
            //throw new \Exception('Nạp tiền thất bại: '.$e->getMessage());
        }
    }

    public function createLogEndow($customerId, $valueOld, $valueNew, $type)
    {
        CustomerLogEndow::create([
            'customer_id' => $customerId,
            'user_id' => getCurrentUserId(),
            'type' => $type,
            'value_old' => $valueOld,
            'value_new' => $valueNew ?? 0
        ]);
    }

    public function getNoteByCustomer(int $customerId)
    {
        return CustomerNote::query()->with('staff')
            ->where('customer_id', $customerId)
            // ->where('type', CustomerNote::CUSTOMER_NOTE_TYPE_0)
            ->where(function ($query) {
                $query->orWhere('type',  CustomerNote::CUSTOMER_NOTE_TYPE_0)
                      ->orWhere('type', CustomerNote::CUSTOMER_NOTE_TYPE_2);
            })
            ->get();
    }

    public function deleteCustomerNote($customer_note_id): bool
    {
        $customerNote = CustomerNote::query()->where('id', $customer_note_id)->first();
        if ($customerNote) {
            $customerNote->delete();
        }
        return true;
    }

    public function getSupplierByCustomer(int $customerId)
    {
        return Supplier::query()
            ->where('customer_id', $customerId)
            ->get();
    }

    public function getBankByShortName(string $shortName)
    {
        return Bank::query()->where('short_name', 'LIKE', strtolower($shortName))->first();
    }

    public function updateLevelCustomer($customerId, $isAlwaysUpdateLevel = false)
    {
        $customer = Customer::query()->find($customerId);
        $orderCostFromTD = $customer->init_order_complete ?? 0;
        $totalAmount = (float)Order::query()->where(
            'deposit_cost',
            '>',
            0
        )
            ->where('customer_id', $customerId)
            ->where('status', OrderConstant::KEY_STATUS_DONE)
            ->whereNull('thuongdo_id')
            ->sum('order_cost');
        $totalAmount = $totalAmount + $orderCostFromTD;

        $level = (new AccountingService())->getLevelCustomer($totalAmount);

        if($level > $customer->level || ($level < $customer->level && $isAlwaysUpdateLevel)) {
            $customer->update(['level' => $level]);
        }
        if($isAlwaysUpdateLevel) {
            return $level;
        }

        return $customer->level;
    }

    public function assignStaff($params)
    {
        $phoneNumber = $params['phone_number'] ?? null;
        $staffGroupId = $params['staff_group_id'] ?? null;

        $arrayPhone = explode(',', $phoneNumber);
        $arrayPhoneNew = [];
        foreach ($arrayPhone as $phone) {
            $arrayPhoneNew[] = trim($phone);
        }
        $customers = Customer::query()->whereIn('phone_number', $arrayPhoneNew)->get();

        $countPhone = count($arrayPhoneNew);
        $countCustomer = count($customers);
        // update customer all
        $auth = Auth::user()->id;
        foreach ($customers as $customer) {
            $params['staff_group_id_old'] = $customer->staff_group_id; // Phục vụ cho việc chuyển chat
            $customer->update([
                'staff_group_id' => $staffGroupId,
                'staff_group_time_assign' => date('Y-m-d H:i:s')
            ]);
            $this->updateNotiLogic($customer, $params);
            $this->updateStaffLogic($customer, $params, $auth);
        }

        return "Đang chuyển đổi cho $countCustomer / $countPhone khách";
    }

    public function assignAllStaff($params)
    {
        $staffGroupIdOld = $params['staff_group_id_old'] ?? null;
        $staffGroupIdNew = $params['staff_group_id_new'] ?? null;
        $userId = Auth::user()->id;
        return dispatch(new UpdateAssignStaff($staffGroupIdOld, $staffGroupIdNew, $userId))
            ->onQueue('sent-noti-admin');
    }

    public function updateStaffLogic($customer, $params, $auth = null)
    {
        dispatch(new UpdateStaffLogic($customer,$params, $auth))
            ->onQueue('sent-noti-admin');
    }

    public function checkPerStaffGroupId()
    {
        $userOnline = auth()->user();
        $permissionDBs = $userOnline->getAllPermissions();
        $perOfRole = \Arr::pluck($permissionDBs, 'name');
        $roleChangeStaff = 'customer.change_staff_group';

        if (in_array($roleChangeStaff, $perOfRole)) {
            return false;
        }
        return true;
    }

    public function smsZaLoKienHangVeVietNam($params = [])
    {
        $query = Customer::query();
        if (!empty($params['customer_id'])) {
            $query->where('customers.id', $params['customer_id']);
        }
        $query->select('customers.id', 'customers.phone_number', 'customers.code', 'customers.name')
            ->whereHas('orderPackage', function ($has) {
                $has->where('status', PackageConstant::STATUS_WAREHOUSE_VN)
                    ->where('is_sms', 0);
            })
            ->with(['orderPackage' => function ($orderPackage) {
                $orderPackage->select(
                    'order_package.id', 'order_package.customer_id',
                    'order_package.status', 'order_package.is_sms'
                )->where('status', PackageConstant::STATUS_WAREHOUSE_VN)
                    ->where('is_sms', 0);
            }])->chunk(50, function ($items) {
                $packages = $items->toArray();
                foreach ($packages as $package) {
                    $count = sizeof($package['order_package']);
                    if ($count > 0) {
                        $packageIds = [];
                        foreach ($package['order_package'] as $id) {
                            $packageIds[] = $id['id'];
                        }
                        $otpCode = generateOtpCode();
                        $phone_number = convertPhoneNumber( $package['phone_number'] ?? 0);
                        $params = [
                            "phone_number" => $phone_number,
                            "otpCode" => $otpCode,
                            "template_id" => SmsLog::ZALO_TEMPLATE_ID_KIEN_HANG_DEN_KHO_VN,
                            "message" => "Xin chào " . $package['name'] . " Quý khách có $count kiện hàng đã đến kho Việt Nam",
                            "template_data" => [
                                "order_code" => $count . " kiện hàng",
                                "customer_name" => $package['name'],
                                "customer_code" => $package['code'],
                                "status" => "Đã về kho Việt Nam",
                            ]
                        ];
                        $response = (new SmsZaLoService($params))
                            ->getAccessToken()
                            ->validate()
                            ->sent()
                            ->getResult();
                        if ($response['code'] == 500) {
                            Log::info('SMS ZaLo Error Kiện Về Kho Việt Nam ' . $response['message'] . ' phone ' . $phone_number);
                            $array = [
                                'phone_number' => $package['phone_number'] ?? '',
                                'user_code' => $package['code'] ?? '',
                                'log_type' => SmsLog::LOG_TYPE_SMS_CHANGE_STATUS_PACKAGE,
                                'message' => 'Co ' . $count . ' kien hang da ve VN. Quy khach den kho nhan hang hoac goi 19006825 (1000d/p) de yeu cau ship. De biet them chi tiet quy khach truy cap website https://hangve.com'
                            ];
                            $response = SmsService::send($array);
                            /* gui thanh cong thi update */
                            if ($response == 1) {
                                OrderPackage::query()
                                ->select('id', 'is_sms')
                                ->whereIn('id', $packageIds)
                                ->update(['is_sms' => 1]);
                            }
                        } else {
                            Log::info('SMS ZaLo Success Kiện Về Kho Việt Nam!' . $phone_number);
                            SmsLog::query()->create([
                                'phone_number' => $phone_number,
                                'content' => $params['message'],
                                'user_code' => $package['code'],
                                'sms_source' => SmsLog::SMS_SOURCE_ZALO,
                                'log_type' => SmsLog::LOG_TYPE_SMS_CHANGE_STATUS_PACKAGE,
                                'response_status' => 0
                            ]);
                            OrderPackage::query()
                                ->select('id', 'is_sms')
                                ->whereIn('id', $packageIds)
                                ->update(['is_sms' => 1]);
                        }
                    }
                }
            });
    }
    /*Đóng tài khoản khách hàng*/
    public function closeCustomer($params = []): JsonResponse
    {
        DB::beginTransaction();
        try {
            $time = '2020-12-01 00:00:00';
            $customer = Customer::query();
            if (!empty($params['customer_id'])) {
                $customer = $customer->where('customers.id', $params['customer_id']);
            }
            $customer = $customer->select(
                'customers.id', 'customers.name', 'customers.status', 'customers.last_order_at',
                'labels.name'
            )
                ->whereDoesntHave('packages', function ($packages) use ($time) {
                    $packages->where('created_at', '>', $time);
                })
                ->join('customer_label', 'customer_label.customer_id', '=', 'customers.id')
                ->join('labels', 'labels.id', '=', 'customer_label.label_id')
                ->where('labels.code', 'KHONG_TUONG_TAC_DUOC')
                ->where('customers.status', 1)
                ->where('customers.last_order_at', '<', $time);
            $customer->chunk(50, function ($items) {
                foreach ($items as $item) {
                    $item->status = 0;
                    $item->save();
                }
                Log::info('List Customer Closed ' . json_encode($items->toArray()));
            });
            DB::commit();
            return resSuccess();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::info('List Customer Closed errors ' . $exception->getMessage());
            return resError($exception->getMessage());
        }
    }
    public function saveCustomerLabel($customerId)
    {
        $labelOrdered = Label::query()->where('code', Label::CONSIGNMENT_ORDERED)->first();
        if($labelOrdered){
            $arrayLabel = [
                Label::THAM_KHAO,
                Label::KHONG_TUONG_TAC_DUOC,
                Label::KHONG_CO_NHU_CAU,
                Label::MAT_HANG_KHONG_NHAP_DUOC,
                Label::DANG_FOLLOW,
                Label::KI_GUI
            ];
            $customer =  Customer::query()->where('id', $customerId)->first();
            $params['label_id'] = $labelOrdered->id;
            $customer->update($params);
            CustomerLabel::query()->updateOrCreate(
                [
                    'customer_id' => $customerId,
                    'label_id' => $labelOrdered->id ?? 0
                ],
                [
                    'customer_id' => $customerId,
                    'label_id' => $labelOrdered->id ?? 0
                ]
            );
            $labelIds = Label::query()->whereIn('code', $arrayLabel)->pluck('id')->toArray();
            CustomerLabel::query()
                ->where('customer_id', $customerId)
                ->whereIn('label_id', $labelIds)
                ->delete();
        }
    }

    public function updateNotiLogic($customer, $params)
    {
        $staff_care_id = isset($params['staff_care_id']) ? $params['staff_care_id'] :  null;
        $staff_counselor_id = isset($params['staff_counselor_id']) ? $params['staff_counselor_id'] :  null;
        $staff_group_id = isset($params['staff_group_id']) ? $params['staff_group_id'] :  null;

        // Thêm log chuyển nhân viên
        $notificationService = \App::make('App\Services\NotificationService');

        $data_customer = [
            'customer_name'  => $customer->name,
            'customer_email' => $customer->email,
            'customer_id'    => $customer->id,
        ];
        $data = [
            'group'           => 1,
            'name'            => 1002,
            'data'            => json_encode($data_customer),
            'time_create'     => time(),
            'status'          => 1
        ];

        if ($staff_care_id) {
            $notificationService->storeNoti($staff_care_id, $data);
        }
        if ($staff_counselor_id) {
            $notificationService->storeNoti($staff_counselor_id, $data);
        }
        if ($staff_group_id) {
            $notificationService->storeNoti($staff_group_id, $data);
        }
    }

    // Lấy ra danh sách các đơn chờ đặt cọc 3 tháng nhưng ko cọc
    public function getOrderWaitingDeposit3Month()
    {
        $orderModel = \App::make('App\Models\Order');

        $time = time() - 90 * 86400;
        $timeCheck = date('Y-m-d H:i:s', $time);
        $orderDBs = $orderModel->join('order_status_times', 'order_status_times.order_id', '=', 'orders.id')
                                ->select('orders.id', 'orders.code', 'orders.status')
                                ->where('orders.status', OrderConstant::KEY_STATUS_WAITING_DEPOSIT)
                                ->where('order_status_times.status_1', '<=', $timeCheck)
                                ->limit(5000)
                                ->offset(0)
                                ->get();

        if (count($orderDBs) > 0) {
            \Log::info('getOrderWaitingDeposit3Month: ' . count($orderDBs));
            foreach ($orderDBs as $key => $orderDB) {
                // $this->updateOrderWaitingDeposit3Month($orderDB);
                // \Log::info('check:' . $orderDB->id . ' - code: ' .  $orderDB->code . ' - status: ' . $orderDB->status) ;
                $job = new \App\Jobs\Order\UpdateOrderWaitingDeposit3MonthJob($orderDB);
                dispatch($job)->delay(now()->addSeconds(3))->onQueue('sent-noti-admin');
            }
        }
    }

    public function updateOrderWaitingDeposit3Month($order)
    {
        try {
            \DB::beginTransaction();

            $order_id = (int) $order->id;
            // \Log::info('updateOrderWaitingDeposit3Month: ' . $order_id);

            $orderModel = \App::make('App\Models\Order');
            $orderStatusTimeModel = \App::make('App\Models\OrderStatusTime');
            $orderDB = $orderModel->where('id', $order_id)->first();

            if ($orderDB->status == OrderConstant::KEY_STATUS_WAITING_DEPOSIT) {
                // \Log::info('updateOrderWaitingDeposit3Month: run: ' . (int) $order_id);
                $time = time() - 90 * 86400;
                $timeCheck = date('Y-m-d H:i:s', $time);
                $orderStatusTimeDB = $orderStatusTimeModel->where('order_id', $order_id)
                                                        ->where('status_1', '<=', $timeCheck)
                                                        ->first();
                if (!is_null($orderStatusTimeDB)) {
                    // Cập nhật
                    $content = 'Đơn hàng bị hủy tự động do đã ở trạng thái Chờ đặt cọc quá 3 tháng.';
                    $orderDB->status = OrderConstant::KEY_STATUS_CANCEL;
                    $orderDB->reason_cancel = $content;
                    $orderDB->staff_cancel_id = 1;
                    $orderDB->save();
                }
            }

            \DB::commit();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function getCustomerWaitingForCare($query)
    {
        // Logic lấy khách màn chờ chăm sóc
        $timeLabel = Carbon::createFromFormat('Y-m', '2024-12')->endOfmonth();
        $timeNoLabel =  [
            Carbon::createFromFormat('Y', '2020')->startOfYear(),
            Carbon::createFromFormat('Y', '2024')->endOfYear()
        ];
        $labelIds = Label::whereIn('code', [
            'DA_DAT_HANG',
            'DA_LEN_DON',
            'KI_GUI',
            'KI_GUI_DA_LEN_DON'
        ])->pluck('id')->toArray();
        return $query->where(function($subQuery) use ($timeLabel, $labelIds, $timeNoLabel){
                    $subQuery->where(function($q) use ($timeLabel, $labelIds) {
                        $q->where("customers.last_order_at", '<', $timeLabel)
                            ->whereHas('labels', function($q1) use ($labelIds) {
                                $q1->whereIn('label_id', $labelIds);
                            });
                        })
                    ->orWhere(function($q) use ($timeNoLabel) {
                        $q->whereBetween("customers.last_order_at", $timeNoLabel)
                            ->whereDoesntHave('labels');
                    });
                });
    }

    public function getCustomerCloseAccount($query)
    {
        // Job auto đóng tài khoản sẽ lấy theo query ở đây
        $timeLabel = Carbon::createFromFormat('Y-m', '2024-12')->endOfmonth();
        $labelIds = Label::whereIn('code', [
            'KHONG_CO_NHU_CAU',
            'MAT_HANG_KHONG_NHAP_DUOC',
            'KHONG_TUONG_TAC_DUOC'
        ])->pluck('id')->toArray();
        return $query->where(function($subQuery) use ($timeLabel, $labelIds) {
                    $subQuery->where(function($q) use ($timeLabel, $labelIds) {
                                $q->where("customers.last_order_at", '<', $timeLabel)
                                    ->whereHas('labels', function($q1) use ($labelIds) {
                                        $q1->whereIn('label_id', $labelIds);
                                    });
                                })
                                ->orWhere(function($q) use ($timeLabel) {
                                    $q->where(DB::raw("IF(customers.last_order_at IS NOT NULL, customers.last_order_at, customers.created_at)"), '<', $timeLabel)
                                        ->whereDoesntHave('labels');
                                });
                });
        return $query->where('status', CustomerConstant::STATUS_INACTIVE);
    }

    public function push9PayAction($amount, Customer $customer, $account_number, $id_pay)
    {
        $amount = floatval($amount);

        DB::beginTransaction();
        try {
            $customerId = $customer->id;
            $admin = User::query()->where('email', User::ADMIN_EMAIL)->firstOrFail();

            $fund = Fund::query()->where('bank_number', $account_number)->first();

            if (!$fund) {
                return;
            } else {
                $fund->total_money_in  += $amount;
                $fund->current_balance += $amount;
                $fund->save();
            }
            // update fund transaction
            $fundTypePay = FundTypePay::query()->where([
                ['type', FundConstant::FUND_TYPE_PAY_CHARGE], ['code', FundConstant::FUND_TYPE_PAY_CHARGE_CODE]
            ])->firstOrFail();

            $temp_log = [
                'action'             => 'Tạo mới',
                'user_request_name'  => $admin->name,
                'user_request_email' => $admin->email,
                'user_request_id'    => $admin->id,
                'column'             => "",
                'value_old'          => "",
                'value_new'          => "",
                'time'               => date('Y-m-d H:i:s', time()),
            ];

            $dataFundTransaction = [
                'organization_id'    => $admin->organization_id,
                'user_create_id'     => $admin->id,
                'code'               => $this->generateRandomCode(),
                'type_object'        => FundTransaction::CUSTOMER_TYPE_OBJECT,
                'type_pay'           => FundTransaction::REVENUE_TYPE_PAY,
                'fund_type_pay_id'   => $fundTypePay->id,
                'fund_type_pay_code' => $fundTypePay->code,
                'fund_id'            => $fund->id,
                'fund_name'          => $fund->name,
                'fund_type_currency' => $fund->type_currency,
                'fund_unit_currency' => $fund->unit_currency,
                'customer_id'        => $customer->id,
                'customer_code'      => $customer->code,
                'customer_name'      => $customer->name,
                'customer_phone'     => $customer->phone_number,
                'money'              => $amount,
                'money_update'       => $amount,
                'balance'            => $fund->current_balance,
                'note'               => 'Chuyển tiền mua hàng',
                'log'                => json_encode([$temp_log]),
                'status'             => 1
            ];

            $fundTransaction = FundTransaction::create($dataFundTransaction);
            // update fund transaction log
            $dataFundTransaction['id'] = $fundTransaction->id;
            $dataFundLog = [
                'fund_transactions' => json_encode($dataFundTransaction),
                'name'              => 'Nạp tiền từ 9pay',
                'user_id'           => $admin->id,
                'user_name'         => $admin->name,
                'user_email'        => $admin->email
            ];
            LogFundTransaction::create($dataFundLog);

            // Tạo giao dịch cho khách
            (new TransactionService())->setTransactionIncrement(
                $amount,
                TransactionConstant::STATUS_DEPOSIT,
                'Chuyển tiền mua hàng',
                $customerId,
                $fundTransaction
            );
            // Cộng vào số tiền khách đã nạp
            ReportCustomer::query()->where('customer_id', $customerId)->increment('deposited_amount', $amount);

            // Đánh dấu log là đã nạp
            $logAutoPayDB = LogAutoPay::query()->where('id', $id_pay)->first();
            if ($logAutoPayDB) {
                $logAutoPayDB->status = 1;
                $logAutoPayDB->date_done = date('Y-m-d H:i:s');
                $logAutoPayDB->save();
            }

            DB::commit();
            return resSuccess();
        } catch (\Illuminate\Database\QueryException $e) {
            Log::info('Lỗi khi nạp tiền từ 9pay :' . $e->getMessage());
            DB::rollBack();

            //dd($e->getMessage());
            throw new \Exception('Nạp tiền thất bại 9pay: '.$e->getMessage());
        }
    }

    public function push9PayActionNull($amount, $customer, $account_number, $id_pay, $content)
    {
        $amount = floatval($amount);

        DB::beginTransaction();
        try {
            // update fund
            $admin = User::query()->where('email', User::ADMIN_EMAIL)->firstOrFail();

            $fund = Fund::query()->where('bank_number', $account_number)->first();
            if (!$fund) { // create new fund
                return;
            } else {
                $fund->total_money_in  += $amount;
                $fund->current_balance += $amount;
                $fund->save();
            }

            // update fund transaction
            $fundTypePay = FundTypePay::query()->where([
                ['type', FundConstant::FUND_TYPE_PAY_CHARGE], ['code', FundConstant::FUND_TYPE_PAY_CHARGE_CONFIRM_CODE]
            ])->firstOrFail();

            $temp_log = [
                'action'             => 'Tạo mới',
                'user_request_name'  => $admin->name,
                'user_request_email' => $admin->email,
                'user_request_id'    => $admin->id,
                'column'             => "",
                'value_old'          => "",
                'value_new'          => "",
                'time'               => date('Y-m-d H:i:s', time()),
            ];

            $dataFundTransaction = [
                'organization_id'    => $admin->organization_id,
                'user_create_id'     => $admin->id,
                'code'               => $this->generateRandomCode(),
                'type_object'        => FundTransaction::CUSTOMER_TYPE_OBJECT,
                'type_pay'           => FundTransaction::REVENUE_TYPE_PAY,
                'fund_type_pay_id'   => $fundTypePay->id,
                'fund_type_pay_code' => $fundTypePay->code,
                'fund_id'            => $fund->id ?? null,
                'fund_name'          => $fund->name ?? null,
                'fund_type_currency' => 2, // 1 - tiền mặt, 2- tiền ngân hàng, 3- tiền ảo
                'fund_unit_currency' => 1, // 1- VND, 2- ¥
                'customer_code'      => $customer->code ?? null,
                'customer_name'      => $customer->name ?? null,
                'customer_phone'     => $customer->phone_number ?? null,
                'money'              => $amount,
                'money_update'       => $amount,
                'balance'            => $fund->current_balance ?? null,
                'note'               => 'Nạp tiền từ 9pay: ' . $content,
                'log'                => json_encode([$temp_log]),
                'status'             => 1
            ];
            $fundTransaction = FundTransaction::create($dataFundTransaction);
            // update fund transaction log
            $dataFundTransaction['id'] = $fundTransaction->id;
            $dataFundLog = [
                'fund_transactions' => json_encode($dataFundTransaction),
                'name'              => 'Nạp tiền từ 9pay',
                'user_id'           => $admin->id,
                'user_name'         => $admin->name,
                'user_email'        => $admin->email
            ];
            LogFundTransaction::create($dataFundLog);


            // Đánh dấu log là đã nạp
            $logAutoPayDB = LogAutoPay::query()->where('id', $id_pay)->first();
            if ($logAutoPayDB) {
                $logAutoPayDB->status = 1;
                $logAutoPayDB->date_done = date('Y-m-d H:i:s');
                $logAutoPayDB->save();
            }

            DB::commit();
            //return resSuccess();
        } catch (\Exception $e) {
            Log::info('Lỗi khi lưu quỹ giao dịch (nạp tiền từ 9pay) :' . $e->getMessage());
            DB::rollBack();
        }
    }

    public function waitingForCare($perPage)
    {
        $data = $this->getCustomerWaitingForCare();
        $customers = $data->paginate($perPage);
        return resSuccessWithinData(new $this->_paginateResource($customers, $this->_resource));
    }

    public function syncCustomerByOrganization($params)
    {
        $data = [
            'code'  => data_get($params, 'code') ?? RandomHelper::customerCode(),
            'name' => data_get($params, 'name'),
            'phone_number' => data_get($params, 'phone_number'),
            'email' => data_get($params, 'email'),
            'password' => Hash::make(123123),
            'bod' => data_get($params, 'bod'),
            'service' => data_get($params, 'service') ?? 0,
            'creator_id' => 1,
            'organization_id' => data_get($params, 'organization_id') ?? getOrganizationTD(),
            'level' => 1,
            'point_level' => 25,
            'point_way' => 20,
            'source' => data_get($params, 'source') ?? 1,
            'status' => CustomerConstant::STATUS_ACTIVE
        ];
        $customer = Customer::query()->where(function ($query) use ($data) {
            $query->where('email', $data['email'])
                ->orWhere('phone_number', $data['phone_number']);
        })->where('organization_id', $data['organization_id'])->first();
        if(is_null($customer)) {
            $customer = Customer::query()->create($data);
            // Lưu thêm thời gian gán point_way
            $dataPointwayTime = [
                'customer_id' => (int) $customer->id,
                'type' => CustomerPointTime::CUSTOMER_POINT_WAY,
                'case' => CustomerPointTime::CUSTOMER_POINT_WAY_MAKE_ACCOUNT
            ];
            $customerPointTimeModel = \App::make('App\Models\CustomerPointTime');
            $customerPointTimeModel->create($dataPointwayTime);
            ReportCustomer::query()->create(['customer_id' => $customer->id]);
        }
        return $customer;
    }
}
