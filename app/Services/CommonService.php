<?php

namespace App\Services;

use App\Constants\ComplainConstant;
use App\Constants\ConsignmentConstant;
use App\Constants\CustomerConstant;
use App\Constants\DeliveryConstant;
use App\Constants\DepartmentConstant;
use App\Constants\FineConstant;
use App\Constants\LocateConstant;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\ViaConstant;
use App\Helpers\PaginateHelper;
use App\Http\Resources\Customer\InfoDeliveryResource;
use App\Http\Resources\CustomerDelivery\CustomerDeliveryResource;
use App\Http\Resources\ListResource;
use App\Http\Resources\Note\NoteResource;
use App\Http\Resources\OnlyIdNameResource;
use App\Http\Resources\OnlyValueKeyResource;
use App\Http\Resources\TransporterResource;
use App\Http\Resources\Warehouse\WarehouseListResource;
use App\Models\Category;
use App\Models\ComplainType;
use App\Models\Customer;
use App\Models\CustomerReasonInactive;
use App\Models\Delivery;
use App\Models\DeliveryNote;
use App\Models\Fine;
use App\Models\Label;
use App\Models\Order;
use App\Models\OrderPackage;
use App\Models\OrderRefund;
use App\Models\ReportLevel;
use App\Models\ReportOrganizationOrder;
use App\Models\Solution;
use App\Models\Supplier;
use App\Models\Transporter;
use App\Models\TransporterDetail;
use App\Models\Warehouse;
use App\Models\ReportOrganizationComplain;
use App\Models\ReportDelivery;
use App\Models\ReportFine;
use Illuminate\Support\Arr;
use App\Models\Complain;
use App\Models\OrderSupplier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\CustomerDelivery;
use App\Models\ApproveUnitPrice;
use App\Constants\CustomerDeliveryConstant;

class CommonService extends BaseService
{
    private const PAGE_CUSTOMER = 'customer';
    private const PAGE_ORDER = 'order';
    private const PAGE_PACKAGE = 'package';
    private const PAGE_DELIVERY = 'delivery';
    private const PAGE_COMPLAIN = 'complain';
    private const PAGE_CUSTOMER_LEVEL = 'customer-level';

    private StaffService $_staffService;

    public function __construct()
    {
        $this->_staffService = new StaffService();
        parent::__construct();
    }

    /**
     * @return array
     */
    public function getCategoriesCustomer(): array
    {
        $fromPage = request()->get('page');
        if (!$fromPage) {
            return $this->getCategoriesCustomerCommon();
        }

        switch ($fromPage) {
            case self::PAGE_CUSTOMER:
                return $this->getCommonCustomer();
            case self::PAGE_COMPLAIN:
                return $this->getCommonCustomerInComplain();
            case self::PAGE_CUSTOMER_LEVEL:
                return $this->getCommonCustomerInLevels();
        }
    }

    public function getCategoriesCustomerCommon(): array
    {
        $data['service'] = new OnlyValueKeyResource(CustomerConstant::CUSTOMER_SERVICE);
        $data['level'] = ReportLevel::query()
            ->where('organization_id', request()->input('organization_id'))
            ->select('level', 'name', 'quantity')
            ->orderBy('level')
            ->get();
        $data['label'] = Label::query()->limit(PaginateHelper::getLimit())->select('id', 'name')->get()->toArray();
        $data['provinces'] = (new LocateService())->getProvincesByCountry(LocateConstant::COUNTRY_VI);
        $data['reasons'] = CustomerReasonInactive::query()->select('id', 'name')->get();
        $sources = CustomerConstant::CUSTOMER_SOURCES;
        unset($sources[0]);
        $data['sources'] = $sources;
        $this->processData($data);

        return $data;
    }

    /**
     * @return array
     */
    public function getCommonCustomer(): array
    {
        $data['service'] = new OnlyValueKeyResource(CustomerConstant::CUSTOMER_SERVICE);
        $dataLabel = Label::query()
            ->limit(PaginateHelper::getLimit())
            ->get(['id', 'name']);
        $dataNotLabel = collect(['id' => 999, 'name' => 'Không có nhãn']);
        $data['label'] = collect([$dataNotLabel])->merge($dataLabel);

        $data['provinces'] = (new LocateService())->getProvincesByCountry(LocateConstant::COUNTRY_VI);
        $data['reasons'] = CustomerReasonInactive::query()->select('id', 'name')->get();
        $data['via'] = new OnlyValueKeyResource(ViaConstant::STATUSES);
        $data['warehouses'] = $this->getWarehouseByCountry();
        $data['business_type'] = new OnlyValueKeyResource(CustomerConstant::CUSTOMER_BUSINESS_TYPE);
        $data['staffs'] = $this->getStaffs(self::PAGE_CUSTOMER);

        $data['statuses'] = new OnlyValueKeyResource(OrderConstant::STATUSES);

        return $data;
    }

    public function getConsignmentStatus(): array
    {
        return ConsignmentConstant::LIST_STATUS;
    }

    /**
     * @return array
     */
    public function getCommonCustomerInComplain(): array
    {
        $data['staffs'] = $this->getStaffs(self::PAGE_COMPLAIN);

        return $data;
    }

    /**
     * @return array
     */
    public function getCommonCustomerInLevels(): array
    {
        $data['provinces'] = (new LocateService())->getProvincesByCountry(LocateConstant::COUNTRY_VI);

        return $data;
    }

    public function getReportOrder()
    {
        //DB::enableQueryLog();
        $perService = app()->make(PerService::class);
        $data = [
            'all' => $this->getOrderReportAll(),
            'time' => (new ReportService())->reports(Order::class),
            'outDates' => Order::query()
                ->whereRaw('DATE(date_ordered) < DATE_SUB(CURDATE(),INTERVAL 15 DAY)')
                ->where('status', OrderConstant::KEY_STATUS_ORDERED)
                ->count(),
            'total_order_refund' => OrderRefund::query()
                ->where('status', OrderRefund::STATUS_PROCESSING)
                ->count(),
            'waitingSale' => Order::query()->where('is_waiting_for_sale', 1)->count(),
            'waitingConfirm' => $perService
                ->getConditionByUserLogin(
                    Order::query()->where('is_waiting_confirm', 1)
                )
                ->count(),
        ];
        //dd(DB::getQueryLog());
        return $data;
    }

    /**
     * @return array
     */
    public function getCategoriesOrder(): array
    {
        //DB::enableQueryLog();
        $levels = CustomerConstant::CUSTOMER_LEVEL;
        $levels = new OnlyValueKeyResource($levels);
        $levels->offsetUnset(0);
        $data['level'] = $levels;

        $data['ecommerce'] = new OnlyValueKeyResource(OrderConstant::ECOMMERCE);
        $data['sorts'] = [[
            'name' => 'Thời gian',
            'value' => 'created_at_sort',
            'data' => new OnlyValueKeyResource(OrderConstant::SORT_TIME)
        ], [
            'name' => 'Giá trị',
            'value' => 'order_cost_sort',
            'data' => new OnlyValueKeyResource(OrderConstant::SORT_COST)
        ]];
        $data['is_website'] = new OnlyValueKeyResource(OrderConstant::TOOL);
        $data['is_purchase'] = new OnlyValueKeyResource(OrderConstant::ORDER_TYPES);
        $data['is_tax'] = new OnlyValueKeyResource(OrderConstant::TAX);
        $data['statuses'] = new OnlyValueKeyResource(OrderConstant::STATUSES);
        $data['statuses_allow'] = (new OrderService())->getListStatusByRole();
        $data['label'] = Label::query()->limit(PaginateHelper::getLimit())->select('id', 'name')->get()->toArray();
        $data['chanels'] = OrderSupplier::LIST_SOURCE_TYPE;
        $data['count_is_up_order_cost'] = Order::where('is_up_order_cost', 1)
                                            ->whereIn('status', [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY, OrderConstant::KEY_STATUS_ORDERED])
                                            ->count();

        $this->processData($data, self::PAGE_ORDER);
        //dd(DB::getQueryLog());
        $data['sources'] = Customer::LIST_SOURCE;
        return $data;
    }

    private function processData(array &$data, $page = null)
    {
        $data['via'] = new OnlyValueKeyResource(ViaConstant::STATUSES);
        $warehouses = $this->getWarehouseByCountry(
            LocateConstant::COUNTRY_VI,
            LocateConstant::COUNTRY_CN
        );
        $data['warehouses'] = $warehouses->collection->filter(function ($item) {
            return $item->country == LocateConstant::COUNTRY_VI && $item->status == 1;
        })->values()->all();
        $data['warehouseCn'] = $warehouses->collection->filter(function ($item) {
            return $item->country == LocateConstant::COUNTRY_CN;
        })->values()->all();
        $data['business_type'] = new OnlyValueKeyResource(CustomerConstant::CUSTOMER_BUSINESS_TYPE);
        $data['staffs'] = $this->getStaffs($page);
    }

    /**
     * Lấy danh sách các warehouse đã được format từ resource
     *
     * @param ...$args
     * @return WarehouseListResource
     */
    public function getWarehouseByCountry(...$args): WarehouseListResource
    {
        $countries = count($args) ? $args : [LocateConstant::COUNTRY_VI];
        return new WarehouseListResource(
            (new WarehouseService())->getWarehousesCountry($countries)
        );
    }

    public function getListCategoriesFine(): array
    {
        $data['reports'] = $this->addReportAll($this->getReportsHasQuantity(ReportFine::class, FineConstant::class));
        $data['types'] = new OnlyValueKeyResource(FineConstant::TYPES);
        //$data['users'] = new ListResource($this->getUsers('user_id'), OnlyIdNameResource::class);
        $data['causes'] = new ListResource($this->getUsers('cause_id'), OnlyIdNameResource::class);
        return $data;
    }

    public function getCategoriesPackages(): array
    {
        $fromPage = request()->get('page');
        if (!$fromPage) {
            return $this->getCategoriesPackagesCommon();
        }

        switch ($fromPage) {
            case self::PAGE_CUSTOMER:
                return $this->getCategoriesPackagesInCustomer();
            case self::PAGE_ORDER:
                return $this->getCategoriesPackagesInCustomer();
        }
    }

    public function getCategoriesPackagesCommon()
    {
        $categories = Category::query()->select('id', 'name')->orderBy('id', 'asc')->get();

        $data['categories'] = new ListResource($categories, OnlyIdNameResource::class);
        $data['suppliers'] = new ListResource(Supplier::query()->limit(1000)->get(['name', 'id']), OnlyIdNameResource::class);

        $transporters = Transporter::query()->where('country', LocateConstant::COUNTRY_CN)->where('thuongdo_hidden', 1)->get(['name', 'id', 'short_name']);

        //$data['transporters'] = new ListResource($transporters, OnlyIdNameResource::class);
        $data['transporters'] = new ListResource($transporters->transform(function($item) {
            return ['id' => $item->id, 'name' => $item->short_name . ' - ' . $item->name];
        }));

        $data['types'] = new OnlyValueKeyResource(PackageConstant::TYPES);
        $data['order'] = [
            //'statuses' => new OnlyValueKeyResource(OrderConstant::STATUSES),
            'delivery_types' => new OnlyValueKeyResource(OrderConstant::DELIVERIES_TEXT),
            'ecommerce' => new OnlyValueKeyResource(OrderConstant::ECOMMERCE),
            'categories' => new ListResource($categories, OnlyIdNameResource::class)
        ];
        $data['reports'] = [
            'time' => (new ReportService())->reports(OrderPackage::class),
        ];
        //$data['warehouses'] = ['vi' => $this->getWarehouseByCountry(), 'cn' => $this->getWarehouseByCountry(LocateConstant::COUNTRY_CN)];
        $data['warehouses'] = $this->getListWarehouseByCountry();
        $data['modifies'] = new OnlyValueKeyResource(PackageConstant::MODIFIES);
        $data['requests'] = new OnlyValueKeyResource(PackageConstant::REQUESTS);
        $data['status'] = new OnlyValueKeyResource(PackageConstant::STATUSES_FILTER);
        $data['statuses'] = new OnlyValueKeyResource(PackageConstant::STATUSES);
        $data['staffs'] = $this->getStaffs(self::PAGE_PACKAGE);
        //$data['units'] = new OnlyValueKeyResource(PackageConstant::UNITS);
        $data['pack_type_cancel'] = new OnlyValueKeyResource(PackageConstant::TYPES_CANCEL);

        $data['diff_count_pack'] = $this->getDiffCountPack();
        $data['count_is_inspection'] = $this->countOrderPackageByService('is_inspection');
        $data['count_is_advanced_inspection'] = $this->countOrderPackageByService('is_advanced_inspection');
        $data['count_is_block_package'] = $this->countIsBlockPackage();
        $data['count_is_request_approve_unit_price'] = $this->countIsRequestApproveUnitPrice();

        $data['count_missing_weight_volume'] = $this->countMissingWeightVolume();
        return $data;
    }

    public function getDiffCountPack()
    {
        $packModel = \App::make('App\Models\OrderPackage');
        $packCount = $packModel->where('diff_count_pack', 1)->count();
        return $packCount;
    }

    public function getCategoriesPackagesInCustomer(): array
    {
        $data['requests'] = new OnlyValueKeyResource(PackageConstant::REQUESTS);
        $data['statuses'] = new OnlyValueKeyResource(PackageConstant::STATUSES);

        return $data;
    }

    private function getOrderReportAll()
    {
        $data = [];
        $user = Auth::user();

        // $dataReport = ReportOrganizationOrder::query()->where('organization_id', $user->organization_id)->first();
        // if (!$dataReport) {
        //     return $data;
        // }
        // Lấy dataReport theo tk đăng nhập
        $dataReport = $this->getQuantityOrderByStatus();
        $totalQuantity = 0;
        foreach (OrderConstant::STATUSES as $status => $statusName) {
            $quantity       = !empty($dataReport->{$status}) ? $dataReport->{$status} : 0;
            $totalQuantity += $quantity;

            $data[] = [
                'name'     => $statusName,
                'color'    => !empty(OrderConstant::STATUSES_COLOR[$status]) ?
                    OrderConstant::STATUSES_COLOR[$status] : "",
                'value'    => $status,
                'quantity' => $quantity,
            ];
        }

        $data = collect($data)->prepend(
            [
                'value'    => '',
                'quantity' => $totalQuantity,
                'name'     => 'Tất cả',
                'color'    => '#45B4CE'
            ]
        );

        return $data;
    }

    private function getQuantityOrderByStatus()
    {
        $perService = app()->make(PerService::class);
        $select = "sum(if(status = 'status_0', 1, 0)) as status_0,
                   sum(if(status = 'status_1', 1, 0)) as status_1,
                   sum(if(status = 'status_2', 1, 0)) as status_2,
                   sum(if(status = 'status_3', 1, 0)) as status_3,
                   sum(if(status = 'status_4', 1, 0)) as status_4,
                   sum(if(status = 'status_5', 1, 0)) as status_5,
                   sum(if(status = 'status_6', 1, 0)) as status_6,
                   sum(if(status = 'status_8', 1, 0)) as status_8";
        $query = Order::query()->selectRaw($select);
        $query = $perService->getConditionByUserLogin($query)->first();
        return $query;
    }

    private function getListWarehouseByCountry()
    {
        $warehouses = Warehouse::query()
            ->where('status', 1)
            ->select('id', 'name', 'country', 'province_id', 'address', 'address_other')
            ->orderByRaw('FIELD(country,"' . implode('","', [LocateConstant::COUNTRY_VI, LocateConstant::COUNTRY_CN]) . '")')
            ->get();

        $warehousesByCountry = $warehouses->groupBy('country')->map(function ($item, $country) {
            return new WarehouseListResource($item);
        });

        return $warehousesByCountry;
    }

    private function getUsers(string $column)
    {
        $relation = $column == 'user_id' ? 'staff' : 'cause';
        return Fine::query()->groupBy($column)->with("$relation:id,name")->get()->map(
            function ($fine) use ($relation) {
                return optional($fine->{$relation});
            }
        )->filter(fn($item) => !!$item->id);
    }

    private function addReportAll($array)
    {
        array_unshift(
            $array,
            [
                'value' => '',
                'quantity' => array_sum(Arr::pluck($array, '_quantity')),
                'name' => 'Tất cả',
                'color' => '#45B4CE'
            ]
        );
        return $array;
    }

    /**
     * @param null|string $page
     * @return array
     */
    public function getStaffs($page = null): array
    {
        $roles = $this->getRoleStaffByPage($page);

        $staffs = $this->_staffService->getStaffByDepartmentTypes($roles);
        if (!$page) {
            return [
                // 'care' => $staffs->where('department_type', DepartmentConstant::TYPE_TAKE_CARE),
                // 'counselor' => $staffs->where('department_type', DepartmentConstant::TYPE_CONSULTING),
                'order' => $staffs->filter(function ($item) {
                    return $item['department_type'] == DepartmentConstant::TYPE_ORDER;
                })->values()->all(),
                'group' => $staffs->filter(function ($item) {
                    return $item['department_type'] == DepartmentConstant::TYPE_BUSINESS;
                })->values()->all(),
                'complain' => $staffs->where('department_type', DepartmentConstant::TYPE_COMPLAIN),
                'quote' => $staffs->where('department_type', DepartmentConstant::TYPE_QUOTE),
            ];
        }

        $data = [];
        switch ($page) {
            case self::PAGE_ORDER:
                $data = [
                    'order' => $staffs->where('department_type', DepartmentConstant::TYPE_ORDER),
                    'group' => $staffs->where('department_type', DepartmentConstant::TYPE_BUSINESS),
                    'quote' => $staffs->where('department_type', DepartmentConstant::TYPE_QUOTE),
                ];
                break;
            case self::PAGE_CUSTOMER:
                $data = [
                    'order' => $staffs->where('department_type', DepartmentConstant::TYPE_ORDER),
                    'group' => $staffs->where('department_type', DepartmentConstant::TYPE_BUSINESS),
                ];
                break;
            case self::PAGE_PACKAGE:
                $data = [
                    'order' => $staffs->where('department_type', DepartmentConstant::TYPE_ORDER),
                    'group' => $staffs->where('department_type', DepartmentConstant::TYPE_BUSINESS),
                ];
                break;
            case self::PAGE_COMPLAIN:
                $data = [
                    'complain' => $staffs->where('department_type', DepartmentConstant::TYPE_COMPLAIN),
                ];
                break;
        }

        return $data;
    }

    public function getRoleStaffByPage($page)
    {
        $roles = [
            DepartmentConstant::TYPE_ORDER,
            DepartmentConstant::TYPE_BUSINESS,
            DepartmentConstant::TYPE_COMPLAIN,
            DepartmentConstant::TYPE_QUOTE,
        ];

        if (!$page) {
            return $roles;
        }

        if ($page == self::PAGE_ORDER) {
            return [
                DepartmentConstant::TYPE_ORDER,
                DepartmentConstant::TYPE_BUSINESS,
                DepartmentConstant::TYPE_QUOTE
            ];
        }

        if (in_array($page, [self::PAGE_CUSTOMER, self::PAGE_PACKAGE])) {
            return [
                DepartmentConstant::TYPE_ORDER,
                DepartmentConstant::TYPE_BUSINESS
            ];
        }

        if ($page == self::PAGE_COMPLAIN) {
            return [ DepartmentConstant::TYPE_COMPLAIN ];
        }
    }

    /**
     * @param string $customerId
     * @param string|null $deliveryId
     * @return array
     */
    public function getCategoriesDelivery(?string $customerId, ?string $deliveryId = null, array $packageIds = []): array
    {
        $data = [
            'reports' => [
                'all' => $this->getDeliveryReportAll(),
                'time' => (new ReportService())->reports(Delivery::class),
            ],
            'payments' => new OnlyValueKeyResource(DeliveryConstant::PAYMENTS),
            'transporters' => new ListResource(Transporter::query()->where('country', LocateConstant::COUNTRY_VI)->get(), TransporterResource::class),
            'transporters_detail' => TransporterDetail::query()->select('id', 'name', 'phone_number')->get(),
            'status_package' => new OnlyValueKeyResource(PackageConstant::DELIVERIES),
            'status_payment' => new OnlyValueKeyResource(DeliveryConstant::STATUSES_PAYMENT),
            'warehouses' => $this->getWarehouseByCountry(),
            'statuses' => new OnlyValueKeyResource(DeliveryConstant::STATUSES)
        ];

        if ($customerId) {
            $data['customer'] = new InfoDeliveryResource(Customer::query()->findOrFail($customerId));
            $data['customer_deliveries'] = new ListResource(
                (new CustomerDeliveryService())->getListByCustomerId($customerId),
                CustomerDeliveryResource::class
            );
        }

        if ($deliveryId) {
            $data['notes'] = [
                'data' => $dataNotes = new ListResource(
                    DeliveryNote::query()
                        ->with('staff')
                        ->select('id', 'content', 'created_at')
                        ->where('delivery_id', $deliveryId)
                        ->get(),
                    NoteResource::class
                ),
                'quantity' => $dataNotes->count()
            ];
        }
        if ($customerId && !empty($packageIds)) {
            $data['customer_delivery_id_default'] =
                $this->getCustomerDeliveryIdDefault($packageIds, $customerId);
        }

        return $data;
    }

    public function getCustomerDeliveryIdDefault(array $packageIds, string $customerId): ?int
    {
        /*Case 1: Nếu yêu cầu giao chỉ có kiện thuộc 1 đơn hàng -> check theo Thông tin nhận hàng của đơn đó -> Lấy thông tin nhận hàng ở thời điểm hiện tại để hiển thị ra khi tạo giao hàng
        Case 2: Nếu yêu cầu giao gồm kiện thuộc nhiều đơn hàng -> khi tạo yêu cầu giao hàng, lấy luôn thông tin nhận hàng mặc định của tài khoản*/
        if (empty($packageIds)) {
            return null;
        }
        $orderIds = OrderPackage::whereIn('id', $packageIds)
            ->distinct()
            ->pluck('order_id');
        if ($orderIds->count() === 1) {
            return Order::where('id', $orderIds->first())
                ->value('customer_delivery_id');
        }
        return CustomerDelivery::query()
            ->where('customer_id', $customerId)
            ->where('status', CustomerDeliveryConstant::STATUS_ACTIVE)
            ->where('is_default', 1)
            ->value('id');
    }

    /**
     * @return OnlyValueKeyResource[]
     */
    public function getCategoriesComplain(): array
    {
        // hienhm update start
        $perService = \App::make('App\Services\PerService');
        $query = $perService->getConditionByUserLogin(Complain::query(), 'complain');
        $data = $this->getReportsHasQuantity(ReportOrganizationComplain::class, ComplainConstant::class);
        $dataNew = array_map(function ($item) use ($query) {
            $item->_quantity = (clone $query)->where('status', $item->resource)->count();
            return $item;
        }, $data);
        // end
        return [
            'statuses' => new OnlyValueKeyResource(ComplainConstant::STATUSES),
            'solution' => Solution::query()->select('id', 'name')->get(),
            'complain_type' => ComplainType::query()->select('id', 'name')->get(),
            'staffs' => $this->getStaffs(),
            'note_types' => new OnlyValueKeyResource(ComplainConstant::NOTE_TYPES),
            'report' => [
                'all' => $this->addReportAll($dataNew),
            ]
        ];
    }

    public function getDeliveryReportAll()
    {
        $perService = app()->make(PerService::class);
        $select = "sum(if(status = 'status_0', 1, 0)) as status_0,
                   sum(if(status = 'status_1', 1, 0)) as status_1,
                   sum(if(status = 'status_2', 1, 0)) as status_2,
                   sum(if(status = 'status_3', 1, 0)) as status_3,
                   sum(if(status = 'status_4', 1, 0)) as status_4";
        $query = Delivery::query()->selectRaw($select);
        $query = $perService->getConditionByUserLogin($query)->first();
        $totalQuantity = 0;
        foreach (DeliveryConstant::STATUSES as $status => $statusName) {
            $quantity       = !empty($query->{$status}) ? $query->{$status} : 0;
            $totalQuantity += $quantity;

            $data[] = [
                'name'     => $statusName,
                'color'    => !empty(OrderConstant::STATUSES_COLOR[$status]) ?
                    OrderConstant::STATUSES_COLOR[$status] : "",
                'value'    => $status,
                'quantity' => $quantity,
            ];
        }

        $data = collect($data)->prepend(
            [
                'value'    => '',
                'quantity' => $totalQuantity,
                'name'     => 'Tất cả',
                'color'    => '#45B4CE'
            ]
        );
        return $data;
    }

    private function countOrderPackageByService($type)
    {
        $keyCheck = $type == 'is_advanced_inspection' ? 2 : 1;
        $now = Carbon::now();
        $query = OrderPackage::query()->join('order_package_status_times', 'order_package_status_times.order_package_id', 'order_package.id')
            ->where("order_package.{$type}", $keyCheck)
            ->where('order_package_status_times.key', PackageConstant::STATUS_ON_THE_WAY_CHINA)
            ->whereBetween('order_package_status_times.created_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()]);
        return $query->sum('order_package.package_number');
    }
    private function countIsBlockPackage()
    {
        $perService = app()->make(PerService::class);
        $query = OrderPackage::query();
        $query = $perService->getConditionByUserLogin($query);

        return $query->where('is_block_package', 1)->sum('package_number');
    }

    private function countIsRequestApproveUnitPrice()
    {
        $perService = app()->make(PerService::class);
        $query = OrderPackage::query();
        $query = $perService->getConditionByUserLogin($query);
        $query = $query->join('approve_unit_prices', 'approve_unit_prices.order_package_id', 'order_package.id')
                        ->where('approve_unit_prices.status', \App\Constants\ApproveUnitPriceConstant::PENDING);
        return $query->count();
    }

    private function countMissingWeightVolume()
    {
        $perService = app()->make(PerService::class);

        $query = OrderPackage::query();
        $query = $perService->getConditionByUserLogin($query);

        return $query
            ->where('status', PackageConstant::STATUS_WAREHOUSE_VN)
            ->where(function ($q) {
                $q->whereNull('weight')
                    ->orWhere('weight', 0);
            })
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('volume_enter')
                        ->orWhere('volume_enter', 0);
                })->where(function ($sub) {
                    $sub->whereNull('width')
                        ->orWhere('width', 0)
                        ->orWhereNull('height')
                        ->orWhere('height', 0)
                        ->orWhereNull('length')
                        ->orWhere('length', 0);
                });
            })
            ->count();
    }
}
