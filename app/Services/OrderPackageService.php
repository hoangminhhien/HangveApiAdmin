<?php

namespace App\Services;

use App\Constants\ActivityConstant;
use App\Constants\ConfigConstant;
use App\Constants\ConsignmentConstant;
use App\Constants\ConversationConstant;
use App\Constants\MessageConstant;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\TransactionConstant;
use App\Factories\BasePlatformFactory;
use App\Helpers\AccountingHelper;
use App\Helpers\ConvertHelper;
use App\Helpers\StatusHelper;
use App\Http\Resources\Package\PackageDetailResource;
use App\Http\Resources\Package\PackagePaginationResource;
use App\Http\Resources\Package\PackageResource;
use App\Jobs\LiveChat\AutoChangeStatusConversation;
use App\Jobs\Report\SyncReportOrderConsignmentDateJob;
use App\Jobs\Report\SyncReportOrderProvinceJob;
use App\Jobs\SendNotiLiveChat;
use App\Jobs\UpdateBillCodeOrderPackageJob;
use App\Models\Config;
use App\Models\Conversation;
use App\Models\Department;
use App\Models\Order;
use App\Models\Consignment;
use App\Models\OrderBuyder;
use App\Models\OrderPackage;
use App\Models\OrderPackageImage;
use App\Models\OrderPackageNote;
use App\Models\OrderPackageStatusTime;
use App\Models\ReportOrderVN;
use App\Models\ReportPackage;
use App\Models\Staff;
use App\Models\Supplier;
use App\Models\Traits\Filters\StatusFilter;
use App\Scopes\OrganizationScope;
use App\Services\PlatformServices\BasePlatformService;
use DateTime;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use App\Models\OrderDetailPackage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Customer;
use App\Constants\WarehouseConstant;
use App\Models\Warehouse;
use App\Models\OrderDetail;
use App\Models\OrderSupplier;
use App\Models\CustomerOffer;
use App\Helpers\RandomHelper;
use Illuminate\Support\Arr;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Support\Facades\Log;
use App\Constants\TimeConstant;
use App\Helpers\TimeHelper;
use App\Models\Delivery;
use App\Models\FilePrint;
use App\Constants\DeliveryConstant;
use App\Constants\ApproveUnitPriceConstant;
use App\Models\ApproveUnitPrice;
use App\Models\User;

class OrderPackageService extends BaseService
{
    protected string $_resource = PackageResource::class;
    protected string $_paginateResource = PackagePaginationResource::class;

    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();
        $perService = app()->make(PerService::class);

        $query = $this->_model->newQuery();
        $query->select(
            'order_package.id', 'order_id', 'bill_code', 'order_code', 'order_type', 'unit_price',
            'transporter', 'transporter_id', 'china_shipping_cost', 'is_inspection', 'is_insurance',
            'is_woodworking', 'is_make_up', 'is_delivery', 'delivery_id', 'weight_real', 'weight',
            'width', 'height', 'length', 'type_quantitative', 'order_package.warehouse_id',
            'order_package.staff_order_id', 'order_package.staff_group_id', 'code_po',
            'order_package.created_at', 'package_number', 'category_id', 'category', 'order_package.status', 'note',
            'note_ordered', 'is_waiting_cancel', 'customer_id', 'international_shipping_cost', 'exchange_rate',
            'insurance_cost', 'inspection_cost', 'woodworking_cost', 'shock_proof_cost',
            'storage_cost', 'delivery_cost', 'discount_cost', 'order_package.organization_id', 'code_warehouse',
            'order_package.thuongdo_id', 'thuongdo_volumn', 'order_package.volume_enter',  'note_internal', 'is_shock_proof',
            'official', 'type_official', 'is_confirm', 'official_is_partner', 'cn_bag_real', 'diff_count_pack', 'sync_note', 'is_advanced_inspection',
            'is_block_package', 'share_fee_ship'
        );
        $query->with([
            'customer' => function($q) {
                return $q->select('id', 'name', 'phone_number', 'code', 'is_endow_ship', 'source')->withCount('orderCompleted');
            },
            'orderR' => function($q) {
                return $q->select('id', 'ecommerce');
            },
            'notes.staff' => function($q) {
                return $q->select('id', 'name');
            },
            'warehouse' => function($q){
                $q->withoutGlobalScopes();
            }, 'notes.staff', 'transporterRelation',
            'customer.warehouse.province',
            'categoryRelation',
            'customer.reportCustomerOrders' => function($q) {
                return $q->select('id');
            },
            'customer.warehouse.province',
            'statusTimes',
            'approveUnitPrices' => function($q) {
                return $q->select('id', 'status', 'order_package_id');
            }
        ]);

        if (!empty(request()->query('diff_count_pack'))) {
            $query->where('diff_count_pack', 1);
        }

        if (request()->query('customer_code') || request()->query('customer_name') || request()->query('customer_phone')) {
            $customerModel = Customer::query();
            if(!empty(request()->query('customer_name'))) {
                $customerModel->where('name', request()->query('customer_name'));
            }
            if(!empty(request()->query('customer_code'))) {
                $customerModel->where('code', request()->query('customer_code'));
            }
            if(!empty(request()->query('customer_phone'))) {
                $customerModel->where('phone_number', request()->query('customer_phone'));
            }
            $customerIds = $customerModel->pluck('id')->toArray();
            $query->whereIn('customer_id', $customerIds);
        }

        $isSearch = request()->input('isSearchPack');
        if(!empty(request()->input('date_done'))) {
            $query = $query->where('order_package.status', PackageConstant::STATUS_RECEIVED_GOODS);
        }
        if(!$isSearch || $isSearch == 'false') {
            $query = $perService->getConditionByUserLogin($query);
        }
        if(!empty(request()->input('status_report'))) {
            if (request()->input('status_report') == 'total_pack_waiting_confirm_cn') {
                $query = $query->where('order_package.status', PackageConstant::STATUS_IN_CHINA)
                    ->whereNull('order_type');
            }
            if (request()->input('status_report') == 'total_pack_confirmation_warehouse') {
                $query = $query->whereIn('order_package.status', [PackageConstant::STATUS_CHECKING_GOODS, PackageConstant::STATUS_LOADING_GOODS])
                    ->whereNull('order_type');;
            }
            if (request()->input('status_report') == 'total_pack_receive') {
                $query = $query->whereIn('order_package.status', [PackageConstant::STATUS_CHECKING_GOODS, PackageConstant::STATUS_LOADING_GOODS]);
            }
            if (request()->input('status_report') == 'total_pack_not_take') {
                $query = $query->where('order_package.status', PackageConstant::STATUS_WAREHOUSE_VN);
            }
            if (request()->input('status_report') == 'total_pack_in_warehouse') {
                $query = $query->where(['order_package.status' => PackageConstant::STATUS_WAREHOUSE_VN,
                        'order_package.warehouse_id' => request()->query('warehouse_id')]);
            }
            if (request()->input('status_report') == 'total_pack_received') {
                $query = $query->where(['order_package.status' => PackageConstant::STATUS_RECEIVED_GOODS,
                        'order_package.warehouse_id' => request()->query('warehouse_id')]);
            }
            if (request()->input('status_report') == 'total_pack_waiting_confirm_branch') {
                $dateRange = $this->dateRange(request()->input('time'));
                $orderPackageStatusTimeModel = App::make('App\Models\OrderPackageStatusTime');
                $a1 = $orderPackageStatusTimeModel->where('key', 'status_10')
                    ->whereBetween('order_package_status_times.updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                    ->groupBy('order_package_status_times.order_package_id')
                    ->get();

                $pack_ids = Arr::pluck($a1, 'order_package_id');
                $query = $query->whereIn('id', $pack_ids)
                    ->where('order_type', NULL)
                    ->where('order_package.warehouse_id', request()->query('warehouse_id'));
            }
            if (request()->input('status_report') == 'total_pack_waiting_confirm') {
                $query = $query->where(['order_package.status' => PackageConstant::STATUS_WAREHOUSE_VN,
                        'order_package.warehouse_id' => request()->query('warehouse_id')]);
            }
            if (request()->input('status_report') == 'total_pack_in_warehouse_cn') {
                $query = $query->where(['order_package.status' => PackageConstant::STATUS_IN_CHINA]);
            }
            if (request()->input('status_report') == 'total_pack_cod') {
                $dateRange = $this->dateRange(request()->input('time'));
                $orderPackageModel = App::make('App\Models\OrderPackage');
                $orderPackageA2 = $orderPackageModel->where('order_package.warehouse_id', request()->query('warehouse_id'));
                $data = $orderPackageA2->leftJoin('order_package_status_times', 'order_package.id', '=', 'order_package_status_times.order_package_id')
                    ->whereBetween('order_package_status_times.updated_at', [$dateRange['startDate'], $dateRange['endDate']])
                    ->where('order_package_status_times.key', 'status_14')
                    ->groupBy('order_package_status_times.order_package_id')
                    ->paginate($perPage);
                return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
            }
            if (request()->input('status_report') == 'not_take_pack' || request()->input('status_report') == 'waiting_for_liquidation') {
                $data = $this->outDateNotDone($query, $perPage);
                return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
            }
        }
        if (request()->query('time')) {
            $dateRange = $this->dateRange(request()->input('time'));
            if(request()->input('status_report') != 'total_pack_in_warehouse_cn') {
                if(request()->input('status_report') == 'total_pack_not_take'){
                    $query->whereBetween('order_package.created_at', [$dateRange['startDate'], $dateRange['endDate']]);
                } else if(request()->input('status_report') == 'not_take_pack') {
                    $startDate = date('Y-m-d H:i:s',strtotime('-3 day', strtotime($dateRange['startDate'])));
                    $endDate = date('Y-m-d H:i:s',strtotime('-3 day', strtotime($dateRange['endDate'])));
                    $query->whereBetween('order_package.updated_at', [$startDate, $endDate]);
                } else if(request()->input('status_report') == 'waiting_for_liquidation') {
                    $startDate = date('Y-m-d H:i:s',strtotime('-30 day', strtotime($dateRange['startDate'])));
                    $endDate = date('Y-m-d H:i:s',strtotime('-30 day', strtotime($dateRange['endDate'])));
                    $query->whereBetween('order_package.updated_at', [$startDate, $endDate]);
                } else {
                    $query->whereBetween('order_package.updated_at', [$dateRange['startDate'], $dateRange['endDate']]);
                }
            }
        }

        // Lọc Type
        $type = request()->query('type');
        if (isset($type) && $type == 'order') {
            $query = $query->where('order_type', 'App\Models\Order');
        }
        if (isset($type) && $type == 'consignment') {
            $query = $query->where('order_type', 'App\Models\Consignment');
        }
        if (isset($type) && $type == 'unconfirmed') {
            $query = $query->whereNull('order_type');
        }
        if (request()->input('is_block_package') == 1) {
            $query = $query->where('is_block_package', 1);
        }
        if (request()->input('is_inspection_today') == 1) {
            $now = Carbon::now();
            $query->where("is_inspection", 1)
                ->whereHas("statusTimes", function($q) use ($now) {
                    $q->where("key", PackageConstant::STATUS_ON_THE_WAY_CHINA)
                        ->whereBetween("created_at", [$now->copy()->startOfDay(), $now->copy()->endOfDay()]);
                });
        }
        if (request()->input('is_advanced_inspection_today') == 1) {
            $now = Carbon::now();
            $query->where("is_advanced_inspection", 2)
                ->whereHas("statusTimes", function($q) use ($now) {
                    $q->where("key", PackageConstant::STATUS_ON_THE_WAY_CHINA)
                        ->whereBetween("created_at", [$now->copy()->startOfDay(), $now->copy()->endOfDay()]);
                });
        }
        if (request()->input('is_request_approve_unit_price') == 1) {
            $query->whereHas("approveUnitPrices", function($q) {
                $q->where("status", ApproveUnitPriceConstant::PENDING);
            });
        }

        // loc kien ko co kg m3
        if (request()->input('missing_weight_volume') == 1) {
            $query->where('status', PackageConstant::STATUS_WAREHOUSE_VN)
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
                });
        }

        $query = $query->orderBy('id', 'DESC');
        $data = $query->paginate($perPage);

        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }

    /**
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function export()
    {
        $this->throwModel();
        $perService = app()->make(PerService::class);
        $query = $this->_model->newQuery();
        $query = $perService->getConditionByUserLogin($query);
        return $query->with([
            'staffOrder', 'staffCounselor',
            'staffCare', 'customer',
            'warehouse', 'categoryRelation',
            'transporterRelation', 'statusTimeVN',
            'statusTimeTQ'
        ])->get();
    }

    public function getSaleStaff($params){
        $query = $this->_model->query();
        $query = $query->select(
            'id', 'order_id',
            'order_code', 'bill_code',
            'code_warehouse', 'order_type'
        );
        if (!empty($params['order_code'])) {
            $query = $query->where('order_code', $params['order_code']);
        }
        if (!empty($params['bill_code'])) {
            $query = $query->where('bill_code', $params['bill_code']);
        }
        if (!empty($params['code_warehouse'])) {
            $query = $query->where('code_warehouse', $params['code_warehouse']);
        }
        $query = $query->with(['orderPackageWithOrder' => function ($order) {
            $order->select('id', 'code', 'staff_group_id')->with(['staffGroup' => function ($staffGroup) {
                $staffGroup->select('id', 'name');
            }]);
        }]);
        $query = $query->with(['orderPackageWithConsignment' => function ($order) {
            $order->select('id', 'code', 'customer_id')->with(['customer' => function ($customer) {
                $customer->select('id', 'name', 'staff_group_id')->with(['staffGroup' => function ($staffGroup) {
                    $staffGroup->select('id', 'name');
                }]);
            }]);
        }]);
        $item = $query->first();
        if ($item) {
            return $item;
        }
        return false;
    }
    public function getRawDataExport($data)
    {
        $result = [];
        foreach ($data as $key => $item) {
            $newItem = [
                $key + 1,
                $item->order_code,
                $item->code,
                $item->code_po,
                $item->bill_code,
                $item->warehouse ? $item->warehouse->name : '',
                $item->product_name,
                $item->quantity,
                $item->package_number,
                $item->order_cost,
                $item->getOrderKindOfAttribute(),
                PackageConstant::STATUSES[$item->status],
                $item->categoryRelation ? $item->categoryRelation->name : '',
                $item->inspection_cost,
                $item->staffCounselor ? $item->staffCounselor->name : '',
                $item->staffCare ? $item->staffCare->name : '',
                $item->customer ? $item->customer->name : '',
                $item->customer ? $item->customer->phone : '',
                $item->customer ? $item->customer->email : '',
                $item->customer ? $item->customer->address : '',
                Carbon::parse($item->created_at)->format('d-m-Y H:m:s')
            ];
            $result[] = $newItem;
        }
        return $result;
    }

    /**
     * @param  string  $orderId
     * @return int
     */
    public function getStatusesDone(string $orderId): int
    {
        return OrderPackage::query()->where(
            ['order_id' => $orderId, 'status' => PackageConstant::STATUS_RECEIVED_GOODS]
        )->count();
    }

    /**
     * @param  string  $key
     * @return string[]
     */
    public function getStatus(string $key): array
    {
        return StatusHelper::getInfo($key, PackageConstant::class) + ['value' => $key];
    }

    public function store(array $data): OrderPackage
    {
        $data['exchange_rate'] = getExchangeRate();

        $data['organization_id'] = getOrganization();

        // Thêm cái loại kiện
        if ($data['type'] == 'order') {
            $data['order_type'] = 'App\Models\Order';
        }

        if ($data['type'] == 'consignment') {
            $data['order_type'] = 'App\Models\Consignment';
        }
        if(data_get($data, 'china_shipping_cost')){
            $data['china_shipping_cost'] = data_get($data, 'china_shipping_cost') * data_get($data, 'exchange_rate');
        }
        if( data_get($data, 'customer_phone')){
            $customer = Customer::where('phone_number', data_get($data, 'customer_phone'))->first();
            if($customer){
                $data['customer_id'] = $customer->id;
                $data['customer_name'] = $customer->name;
                $data['staff_group_id'] = $customer->staff_group_id;
                if(empty($data['warehouse_id'])){
                    $data['warehouse_id'] = $customer->warehouse_id;
                }
                $customer->last_order_at = date('Y-m-d H:i:s');
                $customer->save();
            }
        }

        if(data_get($data, 'customer_code')){
            $customer = Customer::where('code', data_get($data, 'customer_code'))->first();
            if($customer){
                $data['customer_id'] = $customer->id;
                $data['customer_name'] = $customer->name;
                $data['staff_group_id'] = $customer->staff_group_id;
                if(empty($data['warehouse_id'])){
                    $data['warehouse_id'] = $customer->warehouse_id;
                }
                $customer->last_order_at = date('Y-m-d H:i:s');
                $customer->save();
            }
        }
        if(data_get($data, 'order_code')){
            $order = Order::where('code', data_get($data, 'order_code'))->with(['details'])->first();
            $customerId = data_get($order, 'customer_id');
            if($order){
                $data['order_id'] = $order->id;
                $data['order_type'] = 'App\Models\Order';
                $data['is_woodworking'] = $order->is_woodworking;
                $data['is_inspection'] = $order->is_inspection;
                $data['is_shock_proof'] = $order->is_shock_proof;
                $data['staff_group_id'] = $order->staff_group_id;
                $data['official'] = $order->official;
                $data['type_official'] = $order->type_official;
                $data['official_is_partner'] = $order->official_is_partner;
                if (empty($data['warehouse_id']) && !empty($order->warehouse_id)) {
                    // ko chọn kho, lấy wh của đơn
                    $data['warehouse_id'] = $order->warehouse_id;
                }
                if(empty($data['category_id'])){
                    foreach($order->details as $detail){
                        $data['category_id'] = $detail->category_id;
                    }
                }
            }
            $customer = Customer::find($customerId);
            if($customer){
                $data['customer_id'] = $customer->id;
                $data['customer_name'] = $customer->name;
                if(empty($data['warehouse_id'])){
                    $data['warehouse_id'] = $customer->warehouse_id;
                }
                $customer->last_order_at = date('Y-m-d H:i:s');
                $customer->save();
            }
        }
        if(!empty($customer) && $data['type'] == 'consignment') {
            $customerId = $customer->id;
            $customerService = app()->make(CustomerService::class);
            $customerService->saveCustomerLabel($customerId);
        }
        // Nếu không chọn kho nhận hàng thì gán mặc định là kho trần điền
        if(empty($data['warehouse_id'])){
            $warehouseCode = WarehouseConstant::DEFAULT;
            $warehouse = Warehouse::where('code', $warehouseCode)->first();
            $data['warehouse_id'] = data_get($warehouse, 'id');
        }
        /** @var OrderPackage $package */
        $package = parent::store($data);
        $this->changeCost($package, $update);
        $package->update($update);
        $images = [];
        foreach ($data['images'] as $image) {
            $images[] = [
                // 'id' => getUuid(),
                'order_package_id' => $package->id,
                'image' => $image
            ];
        }
        OrderPackageImage::query()->insert($images);

        // Thêm noti
        $this->updatePackageServiceNotiLogic($package);
        // check thêm điều kiện có mã vận đơn mới đồng bộ kho
        if($package && data_get($package, 'bill_code')){
            $oneWarehouseService = \App::make('App\Services\OneWarehouseService');
            $oneWarehouseService->syncPackManualOneWarehouse($package);
            // đẩy sang HV để đồng bộ
            $oneWarehouseService->syncOneWarehouse($package);
        }

        return $package;
    }

    public function quickCreatePackage(array $params, $order)
    {
        \Log::info($params);
        return DB::transaction(function() use($params, $order) {
            $quantity = 0;
            $orderCost = 0;
            if (!empty($params['products']) && count($params['products'])) {
                $detail = OrderDetail::query()->find($params['products'][0]['id']);
                $orderSupplier = OrderSupplier::query()
                    ->where(['order_id' => $order->id, 'supplier_id' => $detail->supplier_id])
                    ->first();

                $quantity  = array_sum(Arr::pluck($params['products'], 'quantity'));
                $orderCost = OrderDetail::query()->findMany(Arr::pluck($params['products'], 'id'))
                        ->sum('unit_price_cny') *  $quantity * $order->exchange_rate;
            } else {
                $orderSupplier = OrderSupplier::query()->where(['order_id' => $order->id])->first();
            }

            $params['order_type'] = get_class($order);
            $params['order_id'] = $order->id;
            $params['organization_id'] = $order->organization_id;
            $params['exchange_rate'] = getExchangeRate();
            $params['staff_order_id'] = $order->staff_order_id;
            $params['staff_quotation_id'] = $order->staff_quotation_id;
            $params['staff_group_id'] = $order->staff_group_id;
            $params['order_code'] = $order->code;
            $params['quantity'] = $quantity;
            $params['order_cost'] = $orderCost;
            $params['customer_id'] = $order->customer_id;
            $params['warehouse_id'] = $order->warehouse_id;
            $params['customer_delivery_id'] = $order->customer_delivery_id;
            $params['official'] = $orderSupplier->official;
            $params['type_official'] = $orderSupplier->type_official;
            $params['ecommerce'] = $order->ecommerce;
            $params['is_inspection'] = $orderSupplier->is_inspection ?? $order->is_inspection;
            $params['is_advanced_inspection'] = $orderSupplier->is_advanced_inspection ?? $order->is_advanced_inspection;
            $params['is_woodworking'] = $orderSupplier->is_woodworking ?? $order->is_woodworking;
            $params['is_shock_proof'] = $orderSupplier->is_shock_proof ?? $order->is_shock_proof;
            $params['is_extension'] = !!$order->ecommerce;
            $params['delivery_type'] = $orderSupplier->delivery_type ?? 'normal';
            $params['official_is_partner'] = $order->official_is_partner;
            $params['code'] = RandomHelper::getPackageCode();
            /** @var OrderPackage $package */
            $package = OrderPackage::query()->create($params);
            if ($package->bill_code) {
                Log::info('Jobs tạo kiện thành công mã đơn ' . $package->buyder_no . ' mã vận đơn là ' . $package->bill_code);

                Log::info('processData quickCreatePackage: ' . $package->bill_code);
                $this->pushToQuoteSlim($package, $package->bill_code);
            }

            $this->changeCost($package, $modify);

            $data = [];
            if (!empty($params['products']) && count($params['products'])) {
                foreach ($params['products'] as $value) {
                    $data[$value['id']] = ['quantity' => $value['quantity']];
                }
                $package->orderDetails()->sync($data);
            }

            $package->update($modify);
            // save images
            if (!empty($params['images']) && count($params['images'])) {
                $images = [];
                foreach ($params['images'] as $image) {
                    $images[] = [
                        'order_package_id' => $package->id,
                        'image' => $image
                    ];
                }
                OrderPackageImage::query()->insert($images);
            }
            $TOKEN_ONE_WAREHOUSE = env('TOKEN_ONE_WAREHOUSE', '');
            if(data_get($package, 'bill_code') && $package->order_type == Order::class) {
                if ($TOKEN_ONE_WAREHOUSE) {
                    $oneWarehouseService = \App::make('App\Services\OneWarehouseService');
                    $oneWarehouseService->syncPackWithOrderOneWarehouse($package,false, $params['products'] ?? []);
                } else {
                    $package->status = PackageConstant::STATUS_ON_THE_WAY_CHINA;
                    $package->code_warehouse = rand(1, 999);
                    $package->save();
                }

            }
            return $package;
        });
    }

    public function updatePackageServiceNotiLogic($pack)
    {
        $staff_order_id = $pack->staff_order_id;

        $notificationService = \App::make('App\Services\NotificationService');

        $data_pack = [
            'pack_id'    => $pack->id,
            'pack_code'  => $pack->code,
        ];

        $data = [
            'group'           => 3,
            'name'            => 3001, // Thêm mới kiện hàng
            'data'            => json_encode($data_pack),
            'time_create'     => time(),
            'status'          => 1
        ];

        if ($staff_order_id) {
            $notificationService->storeNoti($staff_order_id, $data);
        }
    }

    public function detail(string $id): JsonResponse
    {
        $orderPackages = OrderPackage::query()->with([
            'categoryRelation', 'transporterRelation', 'order', 'order.customerDelivery', 'notes', 'statusTimes', 'images'
            ])->findOrFail($id);
        return resSuccessWithinData(new PackageDetailResource($orderPackages));
    }

    public function storeActivity(OrderPackage $package)
    {
        $dongdong_id = isset($_POST['dongdong_id']) ? $_POST['dongdong_id']: null;
        if (!is_null($dongdong_id)) {
            // \Log::info('Bên DD tạo request này nên cần xử lý khác');

            if (($old = $package->getOriginal('status')) !== $package->status) {
                // \Log::info('Có đổi status');
                (new ActivityService())->setLog($package, $package->status, ActivityConstant::PACKAGE_STATUS);
                (new OrderPackageService())->reportOrderVN($package, $old);
                // (new ReportService())->inDecrementByOrganization(ReportPackage::class, $package->status, $old);
                (new OrderPackageService())->reportStatusTime($package->id, $package->status);
            }

            $provinceId = getProvinceX(optional($package->warehouse)->province_id);
            $this->processingWeight($package, $provinceId);
            $this->processingVolume($package, $provinceId);
            return;
        }

        if (($old = $package->getOriginal('status')) !== $package->status) {
            // dd($package, $old);
            (new ActivityService())->setLog($package, $package->status, ActivityConstant::PACKAGE_STATUS);
            (new OrderPackageService())->reportOrderVN($package, $old);
            (new OrderPackageService())->reportStatusTime($package->id, $package->status);
        }
        /*$this->processingNote($package, PackageConstant::TYPE_NOTE_NOTE);
        $this->processingNote($package, PackageConstant::TYPE_NOTE_ORDER);*/
        if ($package->note != null) {
            $this->processingNote($package, PackageConstant::TYPE_NOTE_NOTE);
        }
        if ($package->note_ordered != null) {
            $this->processingNote($package, PackageConstant::TYPE_NOTE_ORDER);
        }
        if ($package->note_internal != null) {
            $this->processingNote($package, PackageConstant::TYPE_NOTE_INTERNAL);
        }

        $this->processingInfo($package);

        $provinceId = getProvinceX(optional($package->warehouse)->province_id);
        $this->processingWeight($package, $provinceId);
        $this->processingVolume($package, $provinceId);
    }

    private function processingInfo(OrderPackage $package)
    {
        foreach (ActivityConstant::PACKAGE_PROPERTIES as $key => $value) {
            if (($old = $package->getOriginal($key)) !== ($new = $package->{$key}) && $old !== null) {
                if(in_array($key, array_keys(ActivityConstant::PACKAGE_MODELS))) {
                    $new = $this->getContentByModelId(ActivityConstant::PACKAGE_MODELS[$key], $new);
                    $old = $this->getContentByModelId(ActivityConstant::PACKAGE_MODELS[$key], $old);
                }
                // $exchangeRate = $package->exchange_rate ?? getExchangeRate();
                // if(is_numeric($new) && $key != 'package_number') {
                //     $old = ConvertHelper::numericToCNY($old / $exchangeRate);
                //     $new = ConvertHelper::numericToCNY($new / $exchangeRate);
                // }
                if($key == 'is_block_package') {
                    $new = $new == 1 ? 'Chặn xuất kho' : 'Bỏ chặn xuất kho';
                    $old = $old == 0 ? 'Bỏ chặn xuất kho' : 'Chặn xuất kho';
                }

                $type = ActivityConstant::PACKAGE_INFO;
                if($key == 'international_shipping_cost') {
                } else {
                    (new ActivityService())->setLog(
                        $package,
                        $value,
                        $type,
                        null,
                        json_encode(['old' => $old, 'new' => $new])
                    );
                }

            }
        }
    }

    private function getContentByModelId(string $model, string $id)
    {
        return optional((new $model)::query()->withoutGlobalScope(new OrganizationScope())->find($id))->name;
    }


    /**
     * @param  OrderPackage  $package
     * @param  string  $key
     */
    private function processingNote(OrderPackage $package, string $key)
    {
        $note = $package->{$key};
        if ($package->getOriginal($key) !== $package->{$key}) {
            (new ActivityService())->setLog(
                $package,
                $note,
                ActivityConstant::PACKAGE_NOTE,
                null,
                json_encode(['type' => PackageConstant::TYPES_NOTE[$key]])
            );
            $this->storeNote($package->id, $note, $key);
        }
    }

    /**
     * @param  OrderPackage  $package
     * @param  string  $provinceId
     */
    private function processingVolume(OrderPackage $package, string $provinceId): void
    {
        $package = OrderPackage::query()->find($package->id);
        if ($package->type_quantitative != 'volume') {
            return;
        }
        $volumeOld = $package->getOriginal('volume');
        $oldCost = $package->getOriginal('international_shipping_cost') ?? (new AccountingService(
            ))->getInternationShippingCost(
                $provinceId,
                0,
                round($volumeOld, 3),
                $package->customer_id,
                $isWeight,
                $package->id
            );
        $newCost = (new AccountingService())->getInternationShippingCost(
            $provinceId,
            $package->weight,
            $package->volume,
            $package->customer_id,
            $isWeight,
            $package->id
        );
        if ($oldCost != $newCost) {
            $result = $oldCost - $newCost;
            $properties = json_encode(
                [
                    "unit" => 'm3',
                    'value' => $package->volume,
                    "international_old_cost" => $oldCost,
                    "international_new_cost" => $newCost != $oldCost ? $newCost : 'Không thay đổi',
                    "modify" => $newCost != $oldCost ? abs($newCost - $oldCost) : 'Không thay đổi'
                ]
            );
            ReportOrderVN::query()->where('order_id', optional($package->order)->id)->update(
                ['international_shipping_cost' => $newCost, 'shock_proof_cost' => $package->shock_proof_cost]
            );
            (new ActivityService())->setLog(
                $package,
                ConvertHelper::numericToVND($result),
                ActivityConstant::PACKAGE_WEIGHT,
                null,
                $properties
            );
        }
    }

    /**
     * @param  OrderPackage  $package
     * @param  string  $provinceId
     */
    private function processingWeight(OrderPackage $package, string $provinceId): void
    {
        $package = OrderPackage::query()->find($package->id);
        if ($package->type_quantitative != 'weight') {
            return;
        }
        $weightOld = $package->getOriginal('weight');
        $oldCost = $package->getOriginal('international_shipping_cost') ?? (new AccountingService())->getInternationShippingCost(
            $provinceId,
            $weightOld,
            0,
            $package->customer_id,
            $isWeight,
            $package->id
        );
        $newCost = (new AccountingService())->getInternationShippingCost(
            $provinceId,
            $package->weight,
            $package->volume,
            $package->customer_id,
            $isWeight,
            $package->id
        );
        if ($oldCost != $newCost) {
            $properties = json_encode(
                [
                    "unit" => 'kg',
                    'value' => $package->weight,
                    "international_old_cost" => $oldCost,
                    "international_new_cost" => $newCost != $oldCost ? $newCost : 'Không thay đổi',
                    "modify" => $modify = $newCost != $oldCost ? abs($newCost - $oldCost) : 'Không thay đổi'
                ]
            );
            ReportOrderVN::query()->where('order_id', optional($package->order)->id)->update(
                ['international_shipping_cost' => $newCost, 'shock_proof_cost' => $package->shock_proof_cost]
            );
            (new ActivityService())->setLog(
                $package,
                $package->weight,
                ActivityConstant::PACKAGE_WEIGHT,
                null,
                $properties
            );
        }
    }

    public function storeNote(string $packageId, string $note, string $type): void
    {
        OrderPackageNote::query()->create(
            [
                'order_package_id' => $packageId,
                'cause_type' => Staff::class,
                'cause_id' => getCurrentUserId(),
                'content' => $note,
                'type' => $type
            ]
        );
    }

    public function changeCost(OrderPackage $package, &$array): void
    {
        $array['weight'] = $weight = (float)($array['weight'] ?? $package->weight);
        $array['height'] = $height = (float)($array['height'] ?? $package->height);
        $array['length'] = $length = (float)($array['length'] ?? $package->length);
        $array['width'] = $width = (float)($array['width'] ?? $package->width);
        $volume = roundXPrecision($height * $width * $length);
        $volume = ($height * $width * $length) / 1000000; // Logic mới nó nó tính theo cm
        $isWoodworking = (bool)($array['is_woodworking'] ?? $package->is_woodworking);
        $isShockProof = (bool)($array['is_shock_proof'] ?? $package->is_shock_proof);
        $isInsurance = (bool)($array['is_insurance'] ?? $package->is_insurance);
        $accounting = new AccountingService();
        $array['inspection_cost'] = $package->order_type === Order::class ? 0 : $package->inspection_cost;
        $array['insurance_cost'] = ($isInsurance && !$package->is_order) ? AccountingHelper::getCosts(
            $accounting->getInsuranceCost(
                $package->order_cost
            )
        ) : 0;
        $volume = $package->volume;
        if(data_get($array, 'm3') || data_get($package, 'm3')) {
            $volume = data_get($array, 'm3') ? data_get($array, 'm3') : data_get($package, 'm3');
        }
        // $international = $accounting->getInternationShippingCost(
        //     getProvinceX(optional($package->warehouse)->province_id),
        //     $weight,
        //     $volume,
        //     $package->customer_id
        // );
        // $array['international_shipping_cost'] = $international;
        // $array['discount_cost'] = AccountingHelper::getCosts($accounting->getDiscountCost($international, $package->customer_id));
        // Sửa lại đoạn lấy phí chiết khẩu
        // $this->getDiscountCostRefacter($international, $package, $array);
        // $array['woodworking_cost'] = $isWoodworking ? AccountingHelper::getCosts(
        //     $accounting->getWoodworkingCost($weight, $volume)
        // ) : 0;

        $array['shock_proof_cost'] = $isShockProof ? AccountingHelper::getCosts(
            $accounting->getShockCost($weight, $package)
        ) : 0;
    }

    // Hàm này tính toán lại phí chiết khấu chuẩn (21-09-2023)
    public function getDiscountCostRefacter($international_shipping_cost, $package, &$array)
    {
        // Bổ sung tính chiếu khấu
        $configService = \App::make('App\Services\ConfigService');
        $customerDB = $package->customer;
        if ($customerDB) {
            $levelCaculator = $customerDB->level == 0 ? 1 : (int)$customerDB->level;
        } else {
            $levelCaculator = 0;
        }
        $delivery_discount_per = $configService->getResultFromValueByLevel($levelCaculator)->delivery;
        $array['discount_cost'] = $international_shipping_cost * $delivery_discount_per / 100;
        $array['discount_percent'] = $delivery_discount_per;
    }

    public function getCost($that): array
    {
        return [
            'china_shipping_cost' => $that->china_shipping_cost,
            'china_shipping_cost_cny' => $that->china_shipping_cost,
            'international_shipping_cost' => $that->international_shipping_cost,
            'inspection_cost' => $that->inspection_cost,
            'insurance_cost' => $that->insurance_cost,
            'woodworking_cost' => $that->woodworking_cost,
            'shock_proof_cost' => $that->shock_proof_cost,
            'storage_cost' => $that->storage_cost,
            'discount_cost' => $that->discount_cost,
            'discount_percent' => $that->discount_percent,
            'amount' => $that->amount
        ];
    }

    /**
     * @param  array  $ids
     * @param  string|null  $delivery
     * @param  bool|null  $isCodToWallet
     * @param  bool|null  $isPaymentCod
     */
    public function updateDeliveryIdByIds(
        array $ids,
        ?string $delivery,
        ?bool $isCodToWallet,
        ?bool $isPaymentCod
    ): array
    {
        $data['amount'] = 0;
        $data['packages'] = [];
        OrderPackage::query()->findMany($ids)->each(
            function ($orderPackage) use ($delivery, &$data, $isCodToWallet, $isPaymentCod) {
                $orderPackage->delivery_id = $delivery;
                $orderPackage->is_delivery = !is_null($delivery);
                $orderPackage->save();
                $data['amount'] += $orderPackage->amount;
                $data['packages'][] = $orderPackage->bill_code;
                // Nếu là ví thì mới hoàn tiền hoặc thanh toán.
                // Nếu trường hợp chuyển cod sang ví thì hoàn tiền hoặc thanh toán xử lý ở bước sau chứ k xử lý ở bước này.
                if(!$isPaymentCod || (!$isCodToWallet && !$isPaymentCod)) {
                    if(is_null($delivery)) {
                        // xóa kiện thì hoàn tiền
                        $this->refundTransaction($orderPackage, 'increment');
                    } else {
                        // thêm kiện thì thanh toán
                        $this->refundTransaction($orderPackage, 'decrement');
                    }
                }
            }
        );
        return $data;
    }

    public function refundTransaction($orderPackage, $type)
    {
        $orderCode = $orderPackage->order_code;
        $billCode = $orderPackage->bill_code;
        $shippingCost = $orderPackage->shipping_cost;
        // thêm tiền hàng còn thiếu để thanh toán khi thêm kiện
        if($orderPackage->is_pack_first == 1) {
            $amount = $shippingCost + optional($orderPackage->order)->debt_cost;
        } else {
            $amount = $shippingCost;
        }

        if ($type == 'increment') {
            $content = __(
                'transaction.delivery_refund_remove_pack',
                [
                    'name'      => getCurrentUser()->name,
                    'code'      => $orderCode,
                    'bill_code' => $billCode,
                    'amount'    => number_format(AccountingHelper::getCosts($amount)),
                ]
            );
            (new TransactionService())->setTransactionIncrement(
                AccountingHelper::getCosts($amount),
                TransactionConstant::STATUS_REFUND,
                $content,
                $orderPackage->customer_id,
                $orderPackage,
                $orderPackage->order
            );
            (new DeliveryService())->updateNoti($orderPackage->customer_id, $content);
        } else if ($amount > 0){
            $content = __(
                'transaction.delivery_add_pack',
                [
                    'bill_code' => $billCode,
                    'code'      => $orderCode,
                    'amount'    => number_format(AccountingHelper::getCosts($amount)),
                ]
            );
            (new TransactionService())->setTransactionDecrement(
                AccountingHelper::getCosts($amount),
                TransactionConstant::STATUS_PURCHASE,
                $content,
                $orderPackage->customer_id,
                $orderPackage,
                $orderPackage->order
            );
            (new DeliveryService())->updateNoti($orderPackage->customer_id, $content);
        }

    }

    /**
     * @param  string  $deliveryId
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getListByDeliveryId(string $deliveryId)
    {
        return OrderPackage::query()->where('delivery_id', $deliveryId)->get();
    }

    /**
     * @param  OrderPackage  $package
     * @param  string  $oldStatus
     */
    public function reportOrderVN(OrderPackage $package, $oldStatus = 'status_0'): void
    {
        if (($decrease = $oldStatus === PackageConstant::STATUS_WAREHOUSE_VN) || ($increase = $package->status === PackageConstant::STATUS_WAREHOUSE_VN)) {

            // Chỗ này đoạn kiện từ HV bắn về chưa có order_id đính vào kiện thì bị dính
            // Tạm để next đoạn này sau check lại
            if ($package->order_id == null) {
                return;
            }

            $order = $package->order;
            if($order) {
                $report = ReportOrderVN::query()->firstOrCreate(
                    ['customer_id' => $package->customer_id, 'order_id' => $package->order_id]
                );
                $columns = [
                    'inspection_cost',
                    'insurance_cost',
                    'woodworking_cost',
                    'shock_proof_cost',
                    'international_shipping_cost',
                    'china_shipping_cost',
                    'delivery_cost',
                    'order_cost',
                    'total_order_cost',
                    'order_fee'
                ];
                $array = $package->only($columns);
                $dynamic = ($decrease ? -1 : 1);
                foreach ($array as $key => $value) {
                    $report->{$key} += (float)$value * $dynamic;
                }
                $deposit = optional($package->order)->deposit_cost ?? 0;
                $depositPercent = optional($package->order)->percent ?? 0;
                $report->order_type = $package->order_type;
                $report->order_cost = $orderCost = ($package->order_type === Order::class ? $package->order_cost : 0);
                $report->total_order_cost = $totalOrderCost = ($package->order_type === Order::class ? $order->getTotalAmount() : 0);
                $deposit = $deposit > $totalOrderCost ? AccountingHelper::getCosts($totalOrderCost * $depositPercent / 100) : $deposit;
                $report->deposit_cost = ($package->order_type === Order::class ? $order->deposit_cost : 0);
                $report->order_fee = $package->order_fee;
                $report->save();
            }
        }
    }

    // Tính lại tổng tiền đơn hàng
    private function getTotalOrderCost($package)
    {
        $orderFee = optional($package->order)->order_fee ?? 0; // Phí đặt hàng
        $discountCost = optional($package->order)->discount_cost ?? 0; // Phí chiết khấu
        $inspectionCost = optional($package->order)->inspection_cost ?? 0; // Phí kiểm đếm
        $chinaShippingCost = optional($package->order)->china_shipping_cost ?? 0; // Phí ship TQ
        $exchangeRate = optional($package->order)->exchange_rate ?? 0; // tỉ giá

        $detailOrders = optional($package->order)->details;
        $orderCost = 0;

        if(!empty($detailOrders)) {
            foreach($detailOrders as $detailOrder) {
                $orderCost += $detailOrder->unit_price_cny * $detailOrder->quantity * $exchangeRate;
            }
        }

        return $orderCost
        + $orderFee
        - $discountCost
        + $inspectionCost
        + $chinaShippingCost * $exchangeRate;
    }

    public function reportStatusTime(string $id, string $status)
    {
        OrderPackageStatusTime::updateOrCreate(
            ['key' => $status, 'order_package_id' => $id],
            ['key' => $status, 'order_package_id' => $id]
        );
    }

    /**
     * @param  string  $orderId
     * @return bool
     */
    public function checkPackageIsLatestByOrder(string $orderId, array $packages): bool
    {
        $results = OrderPackage::query()->where('order_id', $orderId)->orderBy('id')->get();
        $last = $results->last();
        $process = $results->whereIn('id', $packages)->all();
        return $last->id === array_pop($process)['id'] ?? '';
    }

    public function packageRemoveDelivery(string $deliveryId)
    {
        OrderPackage::query()->where('delivery_id', $deliveryId)->update(
            ['delivery_id' => null, 'is_delivery' => false]
        );
    }

    public function getPackageIds($ids)
    {
        return
            OrderPackage::query()->findMany($ids);
    }

    public function updateOrderStatus($orderPackage) {
        // Không dùng các logic này để xử lý nữa
        // Sẽ cập nhật các thông tin đơn khi trả hàng, giao hàng và chọn đã nhận hàng ở màn kiện
        return;

        \Log::info('Không bao giờ chạy hàm phía dưới');

        $consignmentService = \App::make('App\Services\ConsignmentService');

        $status = $this->getOrderStatusUpdate($orderPackage);
        if ($orderPackage->is_order && $status) {
            $order = Order::find($orderPackage->order_id);
            (new OrderService())->changeStatus($order, $status);
            return;
        }

        if ($status) {
            $dataUpdate = ['status' => $status];
            if ($status == ConsignmentConstant::KEY_STATUS_DONE) {
                $dataUpdate['date_done'] = Carbon::now();
            }
            if ($orderPackage->order_id) {
                $consignmentService->updateById($orderPackage->order_id, $dataUpdate);
                $consignmentService->reportStatuses($orderPackage->order_id, $status, optional($orderPackage->order)->status);
                $consignmentService->setLogActivity($orderPackage->order_id);
            }
            if ($status == ConsignmentConstant::KEY_STATUS_DONE) {
                dispatch(new SyncReportOrderConsignmentDateJob(date("Y-m-d"), date("Y-m-d")))
                    ->onQueue('sync-data-report');
            }
        }
    }

    public function getOrderStatusUpdate($orderPackage): string
    {
        $status = '';

        if ($orderPackage->is_order) {
            if ($orderPackage->status == PackageConstant::STATUS_RECEIVED_GOODS) {
                $status = $this->checkOrderPackagesChangedToStatus(PackageConstant::STATUS_RECEIVED_GOODS,
                    $orderPackage->order_id) ? OrderConstant::KEY_STATUS_DONE : '';
            }
            return $status;
        }

        switch ($orderPackage->status) {
            case PackageConstant::STATUS_IN_CHINA:
                $status = ConsignmentConstant::KEY_STATUS_IN_PROGRESS;
                break;
            case PackageConstant::STATUS_RECEIVED_GOODS:
                $status = $this->checkOrderPackagesChangedToStatus(
                    PackageConstant::STATUS_RECEIVED_GOODS, $orderPackage->order_id
                ) ?
                    ConsignmentConstant::KEY_STATUS_DONE :
                    '';
                break;
        }

        return $status;
    }

    public function checkOrderPackagesChangedToStatus($status, $orderId)
    {
        $query = OrderPackage::query()->where([
            ['order_id', $orderId], ['status', '!=', $status]
        ]);

        return intval($query->count()) ? false :  true;
    }

    // Backup code a a Minh
    private function processingInfo17042023(OrderPackage $package)
    {
        foreach (ActivityConstant::PACKAGE_PROPERTIES as $key => $value) {
            if (($old = $package->getOriginal($key)) !== ($new = $package->{$key})) {
                if(in_array($key, array_keys(ActivityConstant::PACKAGE_MODELS))) {
                    $new = $this->getContentByModelId(ActivityConstant::PACKAGE_MODELS[$key], $new);
                    $old = $this->getContentByModelId(ActivityConstant::PACKAGE_MODELS[$key], $old);
                }
                $exchangeRate = $package->exchange_rate ?? getExchangeRate();
                if(is_numeric($new) && $key != 'package_number') {
                    $old = ConvertHelper::numericToCNY($old / $exchangeRate);
                    $new = ConvertHelper::numericToCNY($new / $exchangeRate);
                }
                (new ActivityService())->setLog(
                    $package,
                    $value,
                    ActivityConstant::PACKAGE_INFO,
                    null,
                    json_encode(['old' => $old, 'new' => $new])
                );
            }
        }
    }

    /**
     * @param string $billCode
     * @return bool
     */
    public function checkBillCodeExist(string $billCode)
    {
        $query = OrderPackage::query()->where([
            ['bill_code', $billCode]
        ]);

        return $query->exists();
    }

    /**
     * force delete order pack
     * @return mixed
     */
    public function forceDeletePack() {
        $list = OrderPackage::query()
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
     * restore packages by customer
     * @param array $customerIds
     * @return mixed
     */
    public function restoreByCustomer(array $customerIds) {
        $list = OrderPackage::query()
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

    public function getDataReceiveGoods(array $params)
    {
        if(!empty($params['phone_number'])){
            $customer = Customer::select(['id', 'code', 'name', 'email'])
                                ->where(function ($q) use ($params) {
                                    $q->where('phone_number', $params['phone_number'])
                                        ->orWhere('code', $params['phone_number']);
                                })->where('status', 1)->first();
            if($customer){
                $customer->balance_amount = data_get($customer->report, 'balance_amount', 0);
            }
        }else{
            $customer = null;
        }
        $customerOffer = null;

        if($customer){
            $packages = OrderPackage::select("order_package.*")
                ->where('order_package.status', PackageConstant::STATUS_WAREHOUSE_VN)
                ->where('order_package.customer_id', $customer->id)
                ->with(['order', 'warehouse'])
                ->get();
            $customerOffer = CustomerOffer::where('customer_id', $customer->id)->first();
        }else{
            $packages = collect();
        }

        $data = $packages->map(function ($item) {
            if ($item->order_type == Order::class) {
                $order = $item->order;
                // Tiền đã thanh toán
                $depositCost = data_get($order, 'deposit_cost', 0);
                if(data_get($order, 'delivery_pay_id') > 0) {
                    $depositCost += data_get($order, 'delivery_pay_debt_cost', 0);
                }
                $totalAmount = (data_get($order, 'thuongdo_id') && data_get($order, 'has_edit') == 0) ? data_get($order, 'thuongdo_tong_tien_don_hang') : $order->getTotalAmountNotShip();
                $item->unsetRelation('order');
                $item->order = [
                    'id' => data_get($order, 'id'),
                    'code' => data_get($order, 'code'),
                    'total' => $totalAmount, // New
                    'deposit_cost' => $depositCost,
                    'balance' => $totalAmount - $depositCost, // New
                ];
            } else {
                $item->unsetRelation('order');
                $item->order = null;
            }
            return $item;
        });
        // $data = array_map(function($item) {
        //     if ($item['order_type'] == Order::class) {
        //         $order = $item['order'];
        //         // Tiền đã thanh toán
        //         $depositCost = data_get($order, 'deposit_cost', 0);
        //         if(data_get($order, 'delivery_pay_id') > 0) {
        //             $depositCost += data_get($order, 'delivery_pay_debt_cost', 0);
        //         }
        //         $totalAmount = (data_get($order, 'thuongdo_id') && data_get($order, 'has_edit') == 0) ? data_get($order, 'thuongdo_tong_tien_don_hang') : data_get($order, 'total_amount');
        //         $totalAmount = round($totalAmount);
        //         $item['order'] = [
        //             'id' => data_get($order, 'id'),
        //             'code' => data_get($order, 'code'),
        //             'total' => $totalAmount, // New
        //             'deposit_cost' => $depositCost,
        //             'balance' => $totalAmount - $depositCost, // New
        //         ];
        //     } else {
        //         $item['order'] = null;
        //     }

        //     return $item;
        // }, $packages);

        $data = [
            'order_package' => $data->toArray(),
            'customer' => $customer,
            'offer_debt' => data_get($customerOffer, 'debt')
        ];
        return $data;
    }

    public function getDataPrintPack($params)
    {
        $orderPackIds = data_get($params, 'orderPackIds', []);
        $customerId = data_get(OrderPackage::whereIn('id', $orderPackIds)->first(), 'customer_id');
        $orderPacks = OrderPackage::whereIn('id', $orderPackIds)
            ->with([
                'order',
                'customerDelivery',
                'customerDelivery.district',
                'customerDelivery.province',
                'organization',
                'warehouse'
            ])
            ->get();
        $orderIds = [];
        $warehouseCode = '';
        foreach($orderPacks as $pack){
            if($pack->order_type == Order::class){
                array_push($orderIds, $pack->order_id);
            }
            $warehouseCode = $pack->warehouse->code ?? '';
        }

        $orders = Order::whereIn('id', $orderIds)->get();
        $customer = Customer::find($customerId);
        $warehouse = $customer ? $customer->warehouse : '';
        return [
            'print_receipt_code' => getPrintReceiptCode($warehouseCode),
            'packages' => $orderPacks->toArray(),
            'orders' => $orders->toArray(),
            'customer' => $customer,
            'authLogin' => \Auth::user(),
            'warehouse' => $warehouse
        ];
    }
    public function getDataReportPrintPack($params)
    {
        $orderPackIds = data_get($params, 'package_ids', []);
        $deliveryIds  = data_get($params, 'delivery_id', []);

        if(empty($orderPackIds) && empty($deliveryIds)){
            return [];
        }
        $query = OrderPackage::query()
            ->with([
                'order:id,code',
                'customer:id,phone_number,name,code'
            ]);

        if (!empty($orderPackIds)) {
            $query->whereIn('id', $orderPackIds);
        } elseif (!empty($deliveryIds)) {
            $query->whereIn('delivery_id', $deliveryIds);
        }

        $orderPacks = $query->get();
        $data = [];
        $user = Auth::user();
        foreach($orderPacks as $pack){
            $transportFee = $pack->international_shipping_cost - $pack->discount_code;
            // tiền hàng còn thiếu
            $remainingAmount = $pack->order->totalAmountNotShip - $pack->order->deposit_cost;
            $otherFee = $remainingAmount + $pack->shock_proof_cost + $pack->insurance_cost + $pack->woodworking_cost;
            $totalAmount = $transportFee + $otherFee;
            if(!$pack->bill_code) {
                continue;
            }
            $data[] = [
                'order_package_id' => $pack->id,
                'customer_id' => $pack->customer->id ?? 0,
                'customer_code' => $pack->customer->code ?? '',
                'customer_name' => $pack->customer->name ?? '',
                'customer_phone' => $pack->customer->phone_number ?? '',
                'user_id' => $user->id ?? 0,
                'user_name' => $user->name ?? '',
                'user_code' => $user->code ?? '',
                'order_package_type' => $pack->order_type == Order::class ? 1 : 2, // 1 là order, 2 là ký gửi
                'order_code' => $pack->order->code ?? '',
                'order_id' => $pack->order->id ?? '',
                'bill_code' => $pack->bill_code,
                'status' => $pack->status ?? '',
                'type' => $params['type'] ?? 0,
                'warehouse_code' => $pack->warehouse->code ?? '',
                'print_receipt_code' => $params['print_receipt_code'] ?? '',
                'transport_fee' => $transportFee ?? 0,
                'other_fee' => $otherFee ?? 0,
                'total_amount' => $totalAmount ?? 0,
            ];
        }
//        dd($data);
        return $data;
    }

    /**
     * @param OrderPackage $package
     * @param array $data
     */
    public function setOrderCode(OrderPackage $package, array $data)
    {
        $package->order_code = $data['order_code'];
        $package->order_id   = data_get($data['orderByCode'], 'id');
        $package->order_type = get_class($data['orderByCode']);
        $package->staff_group_id = data_get($data['orderByCode'], 'staff_group_id');
        $package->staff_order_id = data_get($data['orderByCode'], 'staff_order_id');
        $package->is_inspection = data_get($data['orderByCode'], 'is_inspection');
        $package->is_woodworking = data_get($data['orderByCode'], 'is_woodworking');
        $package->is_shock_proof = data_get($data['orderByCode'], 'is_shock_proof');
        $package->is_advanced_inspection = data_get($data['orderByCode'], 'is_advanced_inspection');
        $package->warehouse_id = data_get($data['orderByCode'], 'warehouse_id');
        $package->official = data_get($data['orderByCode'], 'official');
        $package->type_official = data_get($data['orderByCode'], 'type_official');
        $package->official_is_partner = data_get($data['orderByCode'], 'official_is_partner');

        if (!$package->customer_id) {
            $package->customer_id = data_get($data['orderByCode'], 'customer_id');
        }
        if (!$package->customer_delivery_id) {
            $package->customer_delivery_id = data_get($data['orderByCode'], 'customer_delivery_id');
        }

        $package->save();
    }

    /**
     * @param $packageIds
     * @param $staffCare
     * @param $staffCounselor
     */
    public function updateStaffForOrderPackage($packageIds, $staffCare = null, $staffCounselor = null, $staffGroup = null)
    {
        if ($packageIds->isEmpty()) {
            return true;
        }
        $dataUpdate = [
            'staff_care_id' => $staffCare,
            'staff_counselor_id' => $staffCounselor
        ];
        if ($staffGroup) {
            $dataUpdate["staff_group_id"] = $staffGroup;
        }

        return $this->_model->newQuery()
            ->whereIn('id', $packageIds)
            ->update($dataUpdate);
    }


    public function storeByOrderId(array $params, $order)
    {
        $detail = OrderDetail::query()->find($params['products'][0]['id']);
        $orderSupplier = OrderSupplier::query()
            ->where('order_id', $order->id)
            ->firstWhere('supplier_id', $detail->supplier_id);

        $params['order_type'] = get_class($order);
        $params['order_id'] = $order->id;
        $params['order_code'] = $order->code;
        $params['order_cost'] = OrderDetail::query()
                ->findMany(Arr::pluck($params['products'], 'id'))
                ->sum('amount_cny') * $order->exchange_rate;

        $params['exchange_rate'] = getExchangeRate();
        $params['staff_order_id'] = $order->staff_order_id;
        $params['staff_quotation_id'] = $order->staff_quotation_id;
        $params['staff_care_id'] = $order->staff_care_id;
        $params['staff_group_id'] = $order->staff_group_id;
        $params['staff_counselor_id'] = $order->staff_counselor_id;
        $params['customer_id'] = $order->customer_id;
        $params['warehouse_id'] = $order->warehouse_id;
        $params['customer_delivery_id'] = $order->customer_delivery_id;
        $params['ecommerce'] = $order->ecommerce;

        $params['is_inspection'] = $orderSupplier->is_inspection ?? $order->is_inspection;
        $params['is_woodworking'] = $orderSupplier->is_woodworking ?? $order->is_woodworking;
        $params['is_shock_proof'] = $orderSupplier->is_shock_proof ?? $order->is_shock_proof;
        $params['is_extension'] = !!$order->ecommerce;

        $params['delivery_type'] = $orderSupplier->delivery_type ?? $order->delivery_type;

        $params['quantity'] = array_sum(Arr::pluck($params['products'], 'quantity'));
        $params['code'] = RandomHelper::getPackageCode();

        /** @var OrderPackage $package */
        $package = OrderPackage::query()->create($params);

        // Thêm noti
        $this->updatePackageNotiLogic($order, $package);

        $this->changeCost($package, $modify);
        $data = [];
        foreach ($params['products'] as $value) {
            $data[$value['id']] = ['quantity' => $value['quantity']];
        }
        $package->orderDetails()->sync($data);
        $package->update($modify);
    }

    public function updatePackageNotiLogic($order, $pack)
    {
        $staff_care_id      = $order->staff_care_id;
        $staff_counselor_id = $order->staff_counselor_id;

        $notificationService = \App::make('App\Services\NotificationService');

        $data_pack = [
            'order_code' => $order->code,
            'order_id'   => $order->id,
            'pack_id'    => $pack->id,
            'pack_code'  => $pack->bill_code,
        ];

        $data = [
            'group'           => 2,
            'name'            => 2007, // Tạo mới kiện hàng
            'data'            => json_encode($data_pack),
            'time_create'     => time(),
            'status'          => 1
        ];

        if ($staff_care_id) {
            $notificationService->storeNoti($staff_care_id, $data);
        }
        if ($staff_counselor_id) {
            $notificationService->storeNoti($staff_counselor_id, $data);
        }
    }
    public function outDateNotDone($query, $perPage) {
        $status = request()->input('status');
        if($status == 'not_take_pack') {
            $data = $query->withoutGlobalScopes()
                ->where('status', PackageConstant::STATUS_WAREHOUSE_VN)->get();
        }
        if($status == 'waiting_for_liquidation') {
            $data = $query->withoutGlobalScopes()
            ->where('status', PackageConstant::STATUS_PENDING_LIQUIDATION)->get();
        }
        return $this->ArrToCollection($data, $perPage);
    }
    protected function ArrToCollection($items, $perPage = 10, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, $options);
    }

    public function getReportPackageList()
    {
        $data['reports'] = [];
        $data['status_warehouse_vn']['quantity'] = 0;

        $customer = $this->getCustomer();

        if($customer) {
            $data['status_warehouse_vn']['quantity'] = $customer->packagesVN()->withoutGlobalScopes()->count();
        }

        if (request()->query('status') !== PackageConstant::STATUS_WAREHOUSE_VN) {
            return $data;
        }
        if($customer) {
            $data['reports'] = $this->getCalculator($customer);
        } else {
            $data['status_warehouse_vn']['quantity'] = OrderPackage::query()
                ->with('order')->where('status', PackageConstant::STATUS_WAREHOUSE_VN)
                ->withoutGlobalScopes()->count();
        }

        return $data;
    }

    public function getDebtCost()
    {
        $query = OrderPackage::query();

        if (request()->query('customer_code') || request()->query('customer_name') || request()->query('customer_phone')) {
            $query->join('customers', 'customers.id', '=', 'order_package.customer_id');
        }

        $costByStatus = $query
            ->selectRaw(
                DB::raw(
                    'SUM(ROUND( ROUND( COALESCE(international_shipping_cost,0) +
                    (COALESCE(china_shipping_cost, 0) * exchange_rate ) + COALESCE(insurance_cost, 0) +
                    COALESCE(inspection_cost, 0) + COALESCE(woodworking_cost, 0) + COALESCE(shock_proof_cost, 0)
		            + COALESCE(storage_cost, 0) + COALESCE(delivery_cost, 0) ) - COALESCE(discount_cost, 0) )
	                ) as totalAmount'
                )
            )
            ->whereNotIn('order_package.status', [PackageConstant::STATUS_RECEIVED_GOODS, PackageConstant::STATUS_CANCEL])
            ->groupBy('order_package.status')
            ->get();

        $debt_cost = 0;
        foreach ($costByStatus as $item) {
            $debt_cost += floatval($item->totalAmount);
        }

        return [$debt_cost];
    }

    private function getCustomer()
    {
        if (!request()->query('customer_code') &&
            !request()->query('customer_phone')) {
            return null;
        }

        $query = Customer::query()->withoutGlobalScopes()->with([
            'reportOrderVN.order',
            'report' => function($q) {
                return $q->select('customer_id', 'balance_amount');
            }
        ]);

        $query->select('id')->where('status', 1);

        if(!!request()->query('customer_code'))
        {
            $query->where('code', request()->query('customer_code'));
        }
        if (!!request()->query('customer_phone'))
        {
            $query->where('phone_number', request()->query('customer_phone'));
        }

        return $query->first();
    }

    private function getCalculator($customer): array
    {
        $data = [];

        $reports = OrderPackage::where('customer_id', $customer->id)
            ->with('order')
            ->where('status', PackageConstant::STATUS_WAREHOUSE_VN)
            ->withoutGlobalScopes()
            ->get();
        $totalDepositCost = 0;
        $orderCost = 0;
        $shipping = 0;
        $data['orders'] = [];
        $totalWeight = 0;
        $totalVolume = 0;
        foreach($reports as $report){
            $shipping +=  $report->amount;
            if($report->type_quantitative == 'weight') {
                $totalWeight += $report->weight;
            } else {
                $totalVolume += $report->volume;
            }
            if(data_get($report, 'order')){
                $order = data_get($report, 'order');
                $checkExits = array_filter($data['orders'], function ($i) use ($order){
                    return $i['order_id'] == $order->id;
                });
                if(count($checkExits) == 0){
                    if($order->getTable() == 'orders'){
                        //Check xem kiện đã về hết VN chưa
                        $isDoneOrderVN = OrderPackage::where('order_id', $order->id)
                        ->where('order_type', Order::class)
                        ->withoutGlobalScopes()
                        ->whereNotIn('status', [PackageConstant::STATUS_CANCEL, PackageConstant::STATUS_WAREHOUSE_VN, PackageConstant::STATUS_RECEIVED_GOODS])
                        ->whereNull('deleted_at')
                        ->count();
                        $totalAmount = (data_get($order, 'thuongdo_id') && data_get($order, 'has_edit') == 0) ? data_get($order, 'thuongdo_tong_tien_don_hang') : data_get($order, 'total_amount');
                        $totalAmount = round($totalAmount);
                        // Tiền đã thanh toán
                        $depositCost = data_get($order, 'deposit_cost', 0);
                        if(data_get($order, 'delivery_pay_id') > 0) {
                            $depositCost += data_get($order, 'delivery_pay_debt_cost', 0);
                        }
                        $data['orders'][] = [
                            'order_id' => $order->id,
                            'time' => $order->date_ordered ? TimeHelper::format($order->date_ordered,TimeConstant::DATE_VI) : null,
                            'code' => $order->code,
                            'amount' => $totalAmount,
                            'deposit_cost' => $depositCost,
                            'debt_cost' => $totalAmount - $depositCost,
                            'is_done_vn' => $isDoneOrderVN == 0
                        ];
                         // Hàng liên quan đế ký gửi thì không có tiền hàng và tiền tạm ứng
                        $totalDepositCost += $depositCost;
                        $orderCost += $totalAmount;
                    }else{
                        //Check xem kiện đã về hết VN chưa
                        $isDoneConVN = OrderPackage::where([
                            'order_id' => $order->id,
                            'order_type' => Consignment::class
                        ])
                        ->withoutGlobalScopes()
                        ->whereNull('deleted_at')
                        ->whereNotIn('status', [PackageConstant::STATUS_CANCEL, PackageConstant::STATUS_WAREHOUSE_VN, PackageConstant::STATUS_RECEIVED_GOODS])
                        ->count();
                        $data['orders'][] = [
                            'order_id' => $order->id,
                            'time' => $order->date_ordered ? TimeHelper::format($order->date_ordered,TimeConstant::DATE_VI) : null,
                            'code' => $order->code,
                            'amount' => 0,
                            'deposit_cost' => 0,
                            'debt_cost' => 0,
                            'is_done_vn' => $isDoneConVN == 0
                        ];
                    }
                }
            }
        }

        $data['orders_remainder_fee'] = $orderCost - $totalDepositCost;
        $data['total_shipping'] = $shipping;
        $data['e_wallet'] = $eWallet = optional($customer->report)->balance_amount ?? 0;
        $balance = $eWallet - $data['orders_remainder_fee'];
        $data['balance'] = $balance > 0 ? $balance : 0;
        $data['total_weight'] = roundXPrecision($totalWeight, 2);
        $data['total_volume'] = roundXPrecision($totalVolume, 3);
        $data['recharge'] = $balance > 0 ? 0 : abs($balance);

        return $data;
    }



    public function syncPackChangeBillCode($package, $bill_code)
    {
        $data_package = [
            'pack_id'             => $package->id, // ID bên HV
            'pack_code_warehouse' => $package->code_warehouse,
            'bill_code_old'       => $package->bill_code,
            'bill_code_new'       => $bill_code, // Mã vận đơn
        ];
        $guzzle = new Guzzle;
        $options = [
            'headers' => [
                'X-Token-Auth' => env('TOKEN_ONE_WAREHOUSE')
            ],
            'json' => $data_package,
            'http_errors' => false
        ];
        $url = env('BASE_URL_DONGDONG') . 'open/sync/pack-change-bill-code';
        $request = $guzzle->request('POST', $url, $options);
        $reponse = json_decode($request->getBody());

        if($reponse->code == 200) {
            $package->bill_code = $bill_code;
            $package->save();
            return null;
        }

        if($reponse->code == 422) {
            \Log::info('syncPackChangeBillCode data_package: ' . request()->ip() . ' - ' . json_encode($data_package, JSON_UNESCAPED_UNICODE));
            \Log::info('syncPackChangeBillCode : ' . request()->ip() . ' - ' . json_encode($reponse, JSON_UNESCAPED_UNICODE));
            return $reponse->data->error;
        }
    }

    public function formatDataPrint($packages)
    {

        $totalPurchaseOrder = 0;
        $totalAmountOrder = 0;
        $totalAmountPackage = 0;
        $ordersData = [];
        $customerDelivery = null;
        $organization = null;
        $warehouseCode = '';
        foreach($packages as $package) {
            $warehouseCode = $package->warehouse->code ?? '';
            $order = $package->order;
            if($package->organization) {
                $organization = $package->organization->only('name', 'avatar', 'address', 'tax_code', 'phone_number');
                $customerDelivery = $package->customerDelivery;
                if($order) {
                    $checkExist = array_filter($ordersData, function($v) use ($order) {
                        return $v['code'] == $order->code;
                    });
                    if(count($checkExist) == 0) {
                        if($order->getTable() == 'consignments') {
                            $totalAmount = 0;
                            $depositCost = 0;
                        }else {
                            $totalAmount = (data_get($order, 'thuongdo_id') && data_get($order, 'has_edit') == 0) ? data_get($order, 'thuongdo_tong_tien_don_hang') : data_get($order, 'total_amount');
                            $totalAmount = round($totalAmount);
                            $depositCost = $order->thuongdo_id ? $order->thuongdo_tong_tien_tam_ung : $order->deposit_cost;
                            if(data_get($order, 'delivery_pay_id') > 0) {
                                $depositCost += data_get($order, 'delivery_pay_debt_cost', 0);
                            }
                        }
                        $ordersData[] = $tmp = [
                            'code' => $order->code,
                            'total_amount' => $totalAmount,
                            'deposit_cost' => $depositCost,
                            'balance' => $balance = $totalAmount - $depositCost
                        ];

                        $totalPurchaseOrder += AccountingHelper::getCosts($balance);
                        $totalAmountOrder += AccountingHelper::getCosts($balance);
                    }

                }
                $totalAmountPackage += AccountingHelper::getCosts($package->amount);
            }

        }
        // dd($packages);
        return [
            'print_receipt_code' => getPrintReceiptCode($warehouseCode),
            'info' => [
                'time' => "Ngày ".date('d')." tháng ".date('m')." năm ".date('Y'),
                'staff' => data_get(getCurrentUser(), 'name')
            ],
            'receiver' => [
                'name' => data_get($customerDelivery, 'receiver'),
                'address' => data_get($customerDelivery, 'custom_name_second'),
                'phone_number' => data_get($customerDelivery, 'phone_number')
            ],
            'packages' => [
                'data' => $packages,
                'total_amount' => $totalAmountPackage
            ],
            'orders' => [
                'data' => $ordersData,
                'total_amount' => $totalAmountOrder,
                'total_purchase' => $total = $totalPurchaseOrder + $totalAmountPackage,
                'str_cost' => convert_number_to_words($total) . ' đồng'
            ],
            'organization' => $organization,
        ];
    }

    public function checkCodeExist(string $codePo)
    {
        return $this->_model->newQuery()->where('code_po', $codePo)->exists();
    }

    public function getDataExportList($params, $auth)
    {
        $query = $this->_model->newQuery()->select([
            'order_package.id',
            'order_package.created_at',
            'order_package.date_done',
            'order_package.code_warehouse',
            'order_package.bill_code',
            'order_package.code_po',
            'order_package.order_code',
            'order_package.warehouse_id',
            'order_package.order_type',
            'order_package.customer_id',
            'order_package.category_id',
            'order_package.package_number',
            'order_package.weight',
            'order_package.volume_enter',
            'order_package.width',
            'order_package.length',
            'order_package.height',
            'order_package.staff_group_id',
            'order_package.staff_order_id',
            'order_package.status',
            'order_package.china_shipping_cost',
            'order_package.discount_cost',
            'order_package.international_shipping_cost',
            'order_package.exchange_rate',
            'order_package.insurance_cost',
            'order_package.inspection_cost',
            'order_package.woodworking_cost',
            'order_package.shock_proof_cost',
            'order_package.delivery_cost',
            'order_package.type_quantitative',
            DB::raw('((
                order_package.international_shipping_cost
                + order_package.insurance_cost
                + order_package.inspection_cost
                + order_package.woodworking_cost
                + order_package.shock_proof_cost
                + order_package.delivery_cost
                + (order_package.china_shipping_cost * order_package.exchange_rate))
                - order_package.discount_cost)
                 as total_amount'
            )
        ])->with(['customer', 'staffOrder', 'staffGroup', 'categoryRelation', 'warehouse', 'statusTimeLast']);
        $perService = \App::make('App\Services\PerService');
        $query = $perService->getConditionByUserLogin($query, OrderPackage::query(), $auth);
        if(!empty($params['customer_name']) || !empty($params['customer_code']) || !empty($params['customer_phone'])) {
            $customerModel = Customer::query();
            if(!empty($params['customer_name'])) {
                $customerModel->where('name', $params['customer_name']);
            }
            if(!empty($params['customer_code'])) {
                $customerModel->where('code', $params['customer_code']);
            }
            if(!empty($params['customer_phone'])) {
                $customerModel->where('phone_number', $params['customer_phone']);
            }
            $customerIds = $customerModel->pluck('id')->toArray();
            $query->whereIn('customer_id', $customerIds);
        }
        if(!empty($params['bill_code'])) {
            $query->where('order_package.bill_code', $params['bill_code']);
        }
        if(!empty($params['code_warehouse'])) {
            $query->where('order_package.code_warehouse', $params['code_warehouse']);
        }
        if(!empty($params['code_po'])) {
            $query->where('order_package.code_po', $params['code_po']);
        }
        if(!empty($params['order_code'])) {
            $query->where('order_package.order_code', $params['order_code']);
        }
        if(!empty($params['category_id'])) {
            $query->where('order_package.category_id', $params['category_id']);
        }
        if(!empty($params['staff_group_id'])) {
            $staffGroupIds = is_array($params['staff_group_id']) ? $params['staff_group_id'] : [$params['staff_group_id']];
            $query->whereIn('order_package.staff_group_id', $staffGroupIds);
        }
        if(!empty($params['staff_order_id'])) {
            $query->where('order_package.staff_order_id', $params['staff_order_id']);
        }
        if(!empty($params['warehouse_id'])) {
            $query->where('order_package.warehouse_id', $params['warehouse_id']);
        }
        if(!empty($params['type'])) {
            if($params['type'] == 'order') {
                $query->where('order_package.order_type', Order::class);
            }
            if($params['type'] == 'consignment') {
                $query->where('order_package.order_type', Consignment::class);
            }
            if($params['type'] == 'unconfirmed') {
                $query->whereNull('order_package.order_type');
            }
        }
        if(!empty($params['status'])) {
            $statuses = is_array($params['status']) ? $params['status'] : [$params['status']];
            $query->whereIn('order_package.status', $statuses);
        }
        if(!empty($params['modify_key'])) {
            if($params['modify_key'] == 0) {
                $query->whereNull('bill_code');
            }
            if($params['modify_key'] == 1) {
                $query->whereNull('code_po');
            }
            if($params['modify_key'] == 2) {
                $query->where(function ($q) {
                    $q->whereNull('transporter');
                    $q->orWhereNull('transporter_id');
                });
            }
            if($params['modify_key'] == 3) {
                $query->whereNull('customer_id');
            }
            if($params['modify_key'] == 4) {
                $query->where('is_extension', 1);
            }
            if($params['modify_key'] == 5) {
                $query->whereNotNull('bill_code')->whereNotNull('customer_id')->whereNotNull('code_po')->where(function($q){
                    $q->whereNotNull('transporter');
                    $q->orWhereNotNull('transporter');
                });
            }
        }
        if(!empty($params['status_extend'])) {
            if($params['status_extend'] == 0) {
                $query->where('status', '!=' ,PackageConstant::STATUS_WAREHOUSE_VN);
            }
            if($params['status_extend'] == 1) {
                $query->where('status', '!=' ,PackageConstant::STATUS_RECEIVED_GOODS);
            }
        }
        if(!is_null(data_get($params, 'request_key'))) {
            if(data_get($params, 'request_key') == 0) {
                $query->where('is_inspection', 1);
            }
            if(data_get($params, 'request_key') == 1) {
                $query->where('is_woodworking', 1);
            }
            if(data_get($params, 'request_key') == 2) {
                $query->where('is_insurance', 1);
            }
            if(data_get($params, 'request_key') == 4) {
                $query->where('is_delivery', 1);
            }
            if(data_get($params, 'request_key') == 5) {
                $query->where('delivery_type', OrderConstant::DELIVERY_NORMAL);
            }
            if(data_get($params, 'request_key') == 6) {
                $query->where('is_advanced_inspection', 2);
            }
            if(data_get($params, 'request_key') == 3) {
                $query->where('is_shock_proof', 1);
            }
        }
        if(!empty($params['type_time'])) {
            if($params['type_time'] == PackageConstant::ORDER_IN_DAY) {
                $query->whereDate('created_at', date(TimeConstant::DATE));
            }
            if($params['type_time'] == PackageConstant::ORDER_IN_WEEK) {
                $query->whereBetween(
                    'created_at',
                    [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]
                );
            }
            if($params['type_time'] == PackageConstant::ORDER_IN_MONTH) {
                $query->whereMonth('created_at', date('m'))
                        ->whereYear('created_at', date('Y'));
            }
        }
        if(!empty($params['bill_code_at'])) {
            $dates = explode(',', $params['bill_code_at']);
            $start = Carbon::parse($dates[0])->startOfDay();
            $end = Carbon::parse($dates[1])->endOfDay();
            $query->whereBetween('bill_code_at', [$start, $end]);
        }
        if(!empty($params['created_at'])) {
            $dates = explode(',', $params['created_at']);
            $start = Carbon::parse($dates[0])->startOfDay();
            $end = Carbon::parse($dates[1])->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }
        if(!empty($params['date_done'])) {
            $dates = explode(',', $params['date_done']);
            $start = Carbon::parse($dates[0])->startOfDay();
            $end = Carbon::parse($dates[1])->endOfDay();
            $query->whereBetween('date_done', [$start, $end]);
        }
        if (data_get($params, 'is_inspection_today') == 1) {
            $now = Carbon::now();
            $query->where("is_inspection", 1)
                ->whereHas("statusTimes", function($q) use ($now) {
                    $q->where("key", PackageConstant::STATUS_ON_THE_WAY_CHINA)
                        ->whereBetween("created_at", [$now->copy()->startOfDay(), $now->copy()->endOfDay()]);
                });
        }
        if (data_get($params, 'is_advanced_inspection_today') == 1) {
            $now = Carbon::now();
            $query->where("is_advanced_inspection", 2)
                ->whereHas("statusTimes", function($q) use ($now) {
                    $q->where("key", PackageConstant::STATUS_ON_THE_WAY_CHINA)
                        ->whereBetween("created_at", [$now->copy()->startOfDay(), $now->copy()->endOfDay()]);
                });
        }
        if (data_get($params, 'is_request_approve_unit_price') == 1) {
            $query->whereHas("approveUnitPrices", function($q) {
                $q->where("status", ApproveUnitPriceConstant::PENDING);
            });
        }

        // loc kien ko co kg m3
        if (data_get($params, 'missing_weight_volume') == 1) {
            $query->where('status', PackageConstant::STATUS_WAREHOUSE_VN)
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
                });
        }


        // $timeStart = Carbon::now()->subMonths(1)->startOfMonth();
        // // Chỉ cho xuất từ 2 tháng đổ lại
        $query->where('order_package.created_at', '>=', '2024-01-01 00:00:00');
        return $query;
    }
    public function listStatus(): array
    {
        $query = OrderPackage::selectRaw(
           'COUNT(id) as quantity,status as status'
        )->withoutGlobalScopes()->whereNull('deleted_at')->groupBy('status')->get();
        // COUNT(DISTINCT order_package.id) as quantity
        $packageAll = [
            'status_0' => 'Chờ xử lý',
            'status_4' => 'Đang về kho TQ',
            'status_3' => 'Đang về Bằng Tường',
            'status_5' => 'Đã đến kho TQ',
            'status_7' => 'Đến kho Bằng Tường',
            'status_2' => 'Đang ở kho QC',
            'status_6' => 'Đang về VN',
            'status_8' => 'Đang kiểm hàng',
            'status_9' => 'Đang chuyển hàng',
            'status_11' => 'Đang về Tp.HCM',
            'status_12' => 'Đang kiểm hàng Tp.HCM',
            'status_10' => 'Đến kho VN',
            'status_15' => 'Đang giao hàng',
            'status_14' => 'Chờ COD',
            'status_16' => 'Đã nhận hàng',
            'status_17' => 'Đã hủy',
            'status_18' => 'Hàng chờ thanh lý',
            'status_1' => 'Xác nhận lại',

        ];
        $listStatus = [];
        foreach ($packageAll as $key => $package) {
            $filter = $query->filter(function ($item) use ($key) {
                return $item->status == $key;
            })->first();
            $listStatus[] = [
                'value' => $key,
                'quantity' => data_get($filter, 'quantity') ?? 0,
                'name' => $package,
                'color' => $key == 'status_0' ? '#ED1C24' : '#26B01C'
            ];
        }

        return $listStatus;
    }

    public function subMoneyAndDoneOrderAfterModifiPack($package)
    {
        // Xử lý trừ tiên hàng còn thiếu vs phí ship như ở hàm updateReceiveGoods
        $orderModel = \App::make('App\Models\Order');
        $consigmentModel = \App::make('App\Models\Consignment');

        if ($package->order_type == 'App\Models\Consignment') {
            $orderDB = $consigmentModel->where('id', $package->order_id)->first();
        } else {
            $orderDB = $orderModel->where('id', $package->order_id)->first();
        }

        // Nếu chưa ghi nhận lấy dc tiền hàng còn thiếu
        if ($orderDB && $orderDB->delivery_pay_id == 0 && $package->order_type == 'App\Models\Order') {
            // Đánh dấu đã trừ tiền hàng còn thiếu
            $orderDB->delivery_pay_id        = (int) $package->id;
            $orderDB->delivery_pay_debt_cost = $orderDB->debt_cost;
            $orderDB->delivery_pay_source    = OrderConstant::DELIVERY_PAY_SOURCE_PACK;

            // Đơn TD đồng bộ thì không làm tròn tiền
            if ($orderDB->thuongdo_id != null && $orderDB->has_edit == 0) {
                $orderDB->delivery_pay_debt_cost = $orderDB->thuongdo_tong_tien_don_hang - $orderDB->thuongdo_tong_tien_tam_ung;
            }

            // Tạo giao dịch trừ tiền hàng còn thiếu
            $this->makeTransactionDebtCost($orderDB, $package);
            // Khi thanh toán kiện thì tiền hàng còn thiếu cũng đã được thanh toán. Nên update field này về 0
            $orderDB->remaining_amount =  0;
            $orderDB->save();
        }

        // Nếu đã nhận 1 phần
        if ($orderDB && $orderDB->delivery_pay_id > 0 && $package->order_type == 'App\Models\Order') {
            // Nếu có update thêm vào đơn
            if ($orderDB->remaining_amount > 0) {
                $debt_cost_more = $orderDB->debt_cost - $orderDB->delivery_pay_debt_cost;
                if ($debt_cost_more > 0) {
                    $orderDB->delivery_pay_debt_cost += $debt_cost_more;
                    // Tạo giao dịch trừ tiền hàng còn thiếu
                    $this->makeTransactionDebtCost($orderDB, $package, $debt_cost_more);
                    // Khi thanh toán kiện thì tiền hàng còn thiếu cũng đã được thanh toán. Nên update field này về 0
                    $orderDB->remaining_amount =  0;
                    $orderDB->save();
                }
            }
        }

        if ($orderDB && $package->order_type == 'App\Models\Order') {
            // Tiền ship
            $orderDB->delivery_pay_shipping_cost += $package->amount;
            $orderDB->save();
        }

        // Tạo giao dịch trừ tiền ship (Tạo mảng để khớp với hàm chung đã tạo, đỡ phải viết lại)
        $packs = [$package];
        $this->makeTransactionShipingCost($orderDB, $package, $packs);


        // Cập nhật trạng thái đơn
        if ($orderDB && $package->order_type == 'App\Models\Order') {
            \Log::info('hehe1');
            // Kiểm tra xem đã nhận hết kiện chưa, tự động hoàn thành đơn
            // $packagesDB = $orderDB->packagesOrder;
            $packagesDB = $orderDB->packagesOrder->where('status', '!=', PackageConstant::STATUS_CANCEL);
            if (count($packagesDB)) {
                // Nếu toàn bộ kiện đểu đã về hoàn thành thì cho hoàn thành đơn
                $flagDoneAll = true;
                foreach ($packagesDB as $key => $item) {
                    if ($item->status !=  PackageConstant::STATUS_RECEIVED_GOODS && $item->id != $package->id) {
                        $flagDoneAll = false;
                        break;
                    }
                }
                if ($flagDoneAll) {
                    \Log::info('Hoàn thành đơn đê subMoneyAndDoneOrderAfterModifiPack Order');
                    $orderDB->date_done = Carbon::now();
                    $orderDB->status = OrderConstant::KEY_STATUS_DONE;
                    $orderDB->save();
                }
            }
        }

        if ($orderDB && $package->order_type == 'App\Models\Consignment') {
            \Log::info('hehe2');
            // Kiểm tra xem đã nhận hết kiện chưa, tự động hoàn thành đơn
            // $packagesDB = $orderDB->orderPackage;
            $packagesDB = $orderDB->orderPackage->where('status', '!=', PackageConstant::STATUS_CANCEL);

            if (count($packagesDB)) {
                // Nếu toàn bộ kiện đểu đã về hoàn thành thì cho hoàn thành đơn
                $flagDoneAll = true;
                foreach ($packagesDB as $key => $item) {
                    if ($item->status !=  PackageConstant::STATUS_RECEIVED_GOODS && $item->id != $package->id) {
                        $flagDoneAll = false;
                        break;
                    }
                }
                if ($flagDoneAll) {
                    \Log::info('Hoàn thành đơn đê subMoneyAndDoneOrderAfterModifiPack kí gửi');
                    $orderDB->date_done = Carbon::now();
                    $orderDB->status = ConsignmentConstant::KEY_STATUS_DONE;
                    $orderDB->save();
                }
            }
        }
    }

    public function makeTransactionDebtCost($orderDB, $filePrintDB, $moreSub = 0)
    {
        // Làm tròn lại tiền
        $amount = roundMoney($orderDB->delivery_pay_debt_cost);
        $amount = $orderDB->delivery_pay_debt_cost;

        // Nếu số tiền <= 0 thì không cần xử lý
        if ($amount <= 0) {
            return;
        }

        // Thu thêm tiền khi delivery_pay_id > 0
        if ($moreSub > 0) {
            $amount = $moreSub;
        }

        $params   = [
            'amount' => ConvertHelper::numericToVND(floatval($amount)),
            'bill'   => $orderDB->code,
            'code'   => $orderDB->code
        ];
        $msgTrans = trans(
            "transaction.delivery_debt_cost",
            $params
        );

        $transactionService = \App::make('App\Services\TransactionService');

        $transactionService->setTransaction(
            $amount,
            TransactionConstant::STATUS_PURCHASE,
            $msgTrans,
            false,
            $filePrintDB->customer_id,
            $filePrintDB,
            true,
            $orderDB
        );
    }

    public function makeTransactionShipingCost($orderDB, $filePrintDB, $packs)
    {
        $countNextTime = 0;
        foreach($packs as $package) {
            $amount = $package['shipping_cost'] - $package['discount_cost'];

            if ($amount <= 0) {
                continue;
            }

            $params   = [
                'amount' => ConvertHelper::numericToVND(floatval($amount)),
                'bill'   => $package['bill_code'],
                'code'   => $package['order_code']
            ];
            $msgTrans = trans(
                "transaction.delivery_shipping_cost",
                $params
            );

            $transactionService = \App::make('App\Services\TransactionService');


            $countNextTime = $countNextTime +1;
            $_POST['postion_6308270688'] = $countNextTime;

            $transactionService->setTransaction(
                $amount,
                TransactionConstant::STATUS_PURCHASE,
                $msgTrans,
                false,
                $filePrintDB->customer_id,
                $filePrintDB,
                true,
                $orderDB
            );
        }
    }

    public function validateSubMoneyAndDoneOrderAfterModifiPack($package)
    {
        // Lấy ra khách hàng xem có cấu hình ưu đãi không
        $customerId = $package->customer_id;
        $customerOfferModel = \App::make('App\Models\CustomerOffer');
        $customerOfferDB = $customerOfferModel->where('customer_id', $customerId)->first();
        // Nếu khách hàng có công nợ thì cho phép trừ âm
        if ($customerOfferDB && $customerOfferDB->debt == 1) {
            return null;
        }

        // Nếu trạng thái là hoàn thành rồi thì cũng thôi
        if ($package->status == PackageConstant::STATUS_RECEIVED_GOODS) {
            return 'Kiện Đã nhận hàng, không thể xử lý';
        }

        // Xử lý trừ tiên hàng còn thiếu vs phí ship như ở hàm updateReceiveGoods
        $total_debt_cost = 0;
        $total_all_ship_1 = 0;
        $orderModel = \App::make('App\Models\Order');
        $orderDB = $orderModel->where('id', $package->order_id)->first();
        // Nếu chưa ghi nhận lấy dc tiền hàng còn thiếu
        if ($orderDB && $orderDB->delivery_pay_id == 0 && $package->order_type == 'App\Models\Order') {
            $total_debt_cost += $orderDB->debt_cost;
        }

        // Nếu có delivery_pay_id
        if ($orderDB && $orderDB->delivery_pay_id > 0 && $package->order_type == 'App\Models\Order') {
            // Nếu có update thêm vào đơn
            if ($orderDB->remaining_amount > 0) {
                $debt_cost_more = $orderDB->debt_cost - $orderDB->delivery_pay_debt_cost;
                $total_debt_cost += $debt_cost_more;
            }
        }
        // Tiền ship
        $total_all_ship_1 += $package->amount;

        // Tổng tiền phải thanh toán:
        $allMoney = $total_debt_cost + $total_all_ship_1 ;

        $customerService = \App::make('App\Services\CustomerService');
        $balance = $customerService->getBalanceAmount($customerId);
        if ($allMoney > $balance) {
            return 'Bạn không đủ tiền';
        }
        return null;
    }

    public function refundAmount($orderPack)
    {
        $amount = $orderPack->amount;
        if($amount > 0) {
            $customerId = $orderPack->customer_id;
            $paramsBillCode = $orderPack->bill_code;
            $order = $orderPack->orderPackageWithOrder;
            $consignment = $orderPack->orderPackageWithConsignment;
            $object = null;
            if($order) {
                $object = $order;
                $packagesOrder = $order->packagesOrder;
                $isPackageDone = false;
                $orderCode = $order->code;
                // Cập nhật lại phí vc quốc tế với đóng gỗ
                $total_international_shipping_cost = 0;
                $total_woodworking_cost = 0;
                foreach($packagesOrder as $item) {
                    if($item->status == PackageConstant::STATUS_RECEIVED_GOODS) {
                        $isPackageDone = true;
                    }
                    if($item->status != PackageConstant::STATUS_CANCEL) {
                        $total_international_shipping_cost += $item->international_shipping_cost;
                        $total_woodworking_cost += $item->woodworking_cost;
                    }
                }
                $order->international_shipping_cost = $total_international_shipping_cost;
                $order->woodworking_cost = $total_woodworking_cost;
                // update internal shipping cost for order supplier first
                $orderSupplierFirst = optional($order->orderSuppliers)->first();
                if ($orderSupplierFirst) {
                    $orderSupplierFirst->international_shipping_cost = $total_international_shipping_cost;
                    $orderSupplierFirst->woodworking_cost = $total_woodworking_cost;
                    $orderSupplierFirst->save();
                }
                if($isPackageDone == false) {
                    // Nếu trong đơn không còn kiện nào ở trạng thái đã nhận hàng thì Hoàn tiền hàng còn thiếu
                    // Nếu tiền hàng còn thiếu > 0 thì tạo giao dịch
                    $remainingAmount = $order->delivery_pay_debt_cost;
                    if($remainingAmount > 0) {
                        $msgTrans = trans(
                            "transaction.refund_remaining_amount",
                            [
                                "amount" => ConvertHelper::numericToVND(floatval($remainingAmount)),
                                "order_code" => $orderCode
                            ]
                        );
                        $status = TransactionConstant::STATUS_REFUND;
                        $transactionService = app()->make('App\Services\TransactionService');
                        $transactionService->setTransactionIncrement(
                            $remainingAmount,
                            $status,
                            $msgTrans,
                            $customerId,
                            null,
                            $order
                        );
                        $dataNotificationCustomer = [
                            'amount' => ConvertHelper::numericToVND(floatval($remainingAmount)),
                            'bill_code' =>$orderCode
                        ];
                        (new NotificationService())->sendNotificationCustomer(8, 8016, $customerId, $dataNotificationCustomer);
                    }
                    $order->delivery_pay_id = 0;
                    $order->delivery_pay_debt_cost = 0;
                    $order->delivery_pay_source = 0;
                }
                // Tìm đơn order của kiện để trừ tiền và thay đổi trạng thái
                $order->delivery_pay_shipping_cost = $order->delivery_pay_shipping_cost - $amount;
                if($order->status == OrderConstant::KEY_STATUS_DONE) {
                    $order->status = OrderConstant::KEY_STATUS_ORDERED;
                    $order->date_done = null;
                }
                $order->save();
            }
            if($consignment) {
                $object = $consignment;
                $orderCode = $consignment->code;
                $consignment->update([
                    'status' => ConsignmentConstant::KEY_STATUS_IN_PROGRESS,
                    'date_done' => null
                ]);
            }

            $codeMsg = '';
            if(isset($orderCode)) {
                $codeMsg = $orderCode;
            }
            if(!$codeMsg) {
                $codeMsg = $order->code ?? '';
            }
            $msgTrans = trans(
                "transaction.refund_shipping_cost",
                [
                    "amount" => ConvertHelper::numericToVND(floatval($amount)),
                    "bill_code" => $paramsBillCode,
                    "order_code" => $codeMsg
                ]
            );
            $status = TransactionConstant::STATUS_REFUND;
            $transactionService = app()->make('App\Services\TransactionService');
            $transactionService->setTransactionIncrement(
                $amount,
                $status,
                $msgTrans,
                $customerId,
                null,
                $object
            );
            $dataNotificationCustomer = [
                'amount' => ConvertHelper::numericToVND(floatval($amount)),
                'bill_code' =>$paramsBillCode
            ];
            (new NotificationService())->sendNotificationCustomer(8, 8015, $customerId, $dataNotificationCustomer);
        }
    }

    public function calculatorWhenCancelPackage($orderPack)
    {
        $order = $orderPack->orderPackageWithOrder;
        if($order) {
            $packagesOrder = $order->packagesOrder;
            // Cập nhật lại phí vc quốc tế với đóng gỗ
            $total_international_shipping_cost = 0;
            $total_woodworking_cost = 0;
            foreach($packagesOrder as $item) {
                if($item->status != PackageConstant::STATUS_CANCEL) {
                    $total_international_shipping_cost += $item->international_shipping_cost;
                    $total_woodworking_cost += $item->woodworking_cost;
                }
            }
            $order->international_shipping_cost = $total_international_shipping_cost;
            $order->woodworking_cost = $total_woodworking_cost;
            $orderSupplierFirst = optional($order->orderSuppliers)->first();
            if ($orderSupplierFirst) {
                $orderSupplierFirst->international_shipping_cost = $total_international_shipping_cost;
                $orderSupplierFirst->woodworking_cost = $total_woodworking_cost;
                $orderSupplierFirst->save();
            }
            $order->save();
        }
    }

    /**
     * @throws Exception
     */
    public function sentSmsToChat($package, array $params = []){
        $paramsConversation = [
            'customer_id' => $package->customer_id,
            'type' => ConversationConstant::TYPE_PACKAGE,
            'type_id' => $package->id
        ];
        $conversation = (new ConversationService())->getDetail($paramsConversation);
        $body = $params['body'] ?? '';
        if (!$conversation) {
            $supplier = '';
            $supplier_id = '';
            if ($package->order_type == Order::class) {
                if (isset($package->order->orderSuppliers) && !empty($package->order->orderSuppliers)) {
                    $supplier_id = $package->order->orderSuppliers->first()->supplier_id ?? 0;
                }
            }
            if ($supplier_id) {
                $supplier = Supplier::query()->where('id', $supplier_id)->first();
            }
            $conversation = (new ConversationService())->store([
                'organization_id' => 1,
                'type' => ConversationConstant::TYPE_PACKAGE,
                'type_id' => $package->id,
                'type_code' => $package->bill_code ?? '',
                'type_status' => $params['status'] ?? $package->status,
                'order_id' => optional($package->order)->id ?? 0,
                'customer_id' => $package->customer->id ?? 0,
                'customer_code' => $package->customer->code ?? '',
                'customer_name' => $package->customer->name ?? '',
                'customer_phone' => $package->customer->phone_number ?? '',
                'supplier_id' => $supplier->id ?? '',
                'supplier_name' => $supplier->name ?? '',
                'user_id' => $package->customer->userStaff->id ?? 0,
                'user_code' => $package->customer->userStaff->code ?? 0,
                'user_name' => $package->customer->userStaff->name ?? '',
                "unread_client" => 1,
                "unread_admin" => 0,
                'conversation_config_id' => 2,
                'content_last_send' => $body,
                'time_last_send' => date('Y-m-d H:i:s'),
                'time_last_send_client' => date('Y-m-d H:i:s'),
                'time_last_send_admin' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $conversation->content_last_send = $body;
            $conversation->time_last_send = date('Y-m-d H:i:s');
            $conversation->time_last_send_client = date('Y-m-d H:i:s');
            $conversation->time_last_send_admin = date('Y-m-d H:i:s');
            $conversation->save();
        }
        if(isset($params['type']) && $params['type'] == MessageConstant::TYPE_CREATE_DELIVERY) {
            $data_message = [
                'conversation_id' => $conversation->id ?? 0,
                'source' => MessageConstant::SOURCE_USER,
                'type' => MessageConstant::TYPE_CREATE_DELIVERY,
                'organization_id' => 1,
                'content' => $body
            ];
        } else {
            $data_message = [
                'conversation_id' => $conversation->id ?? 0,
                'source' => MessageConstant::SOURCE_USER,
                'type' => MessageConstant::TYPE_TEXT,
                'organization_id' => 1,
                'content' => $body
            ];
        }
        $messageDB = (new MessageService())->store($data_message);
        $customer = Customer::query()->where('id', $package->customer_id)->first();
        if ($customer) {
            $customer->total_chat_unread += 1;
            $customer->save();
        }
        $conversationConfigDB =  getConversationConfig(0);
        $data_last_send_old = [
            'time_last_send_client' => $conversation->time_last_send_client,
            'time_last_send_admin' => $conversation->time_last_send_admin,
        ];

        $job = new AutoChangeStatusConversation(
            $data_message['conversation_id'], $conversationConfigDB, $data_last_send_old
        );
        dispatch($job)->delay(now()->addSeconds($conversationConfigDB->time ?? 5))->onQueue('auto-conversation');
        $name = $package->customer->userStaff->name ?? null;
        $content = $name . ' đã gửi: ' . $body;
        $job = new SendNotiLiveChat($package->customer, $content);
        dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-live-chat');
        // Đẩy noti cho app
        $payload['user_id'] = $package->customer_id ?? 0;
        $fcmTokenService = app()->make(FcmTokenService::class);
        $title = 'Hàng Về có tin nhắn mới !';
        $dataSent = [
            'type' => 'livechat',
            'body' => (string) $conversation->id
        ];
        $fcmTokenService->sendMessageForApp($payload, $title, $body, $dataSent);
        return [
            'total_chat_unread' => $customer->total_chat_unread,
            'message_id' => (int)$messageDB->id,
            'customer_code' => $customer->code,
            'content' => $body
        ];
    }
    public function saveConfigs($orderPackage)
    {
        $configs = Config::query()
            ->whereIn('key',
                [
                    ConfigConstant::SERVICE_SHOCK_PROOF,
                    ConfigConstant::SERVICE_WOODWORKING_WEIGHT,
                    ConfigConstant::SERVICE_WOODWORKING_VOLUME
                ]
            )->get();
        $dataSave = [];
        foreach ($configs as $config) {
            $dataSave[$config->key] = [
                json_decode($config->value, true)
            ];
        }
        $orderPackage->configs = json_encode($dataSave);
        // $orderPackage->save();
        $orderPackage = OrderPackage::withoutEvents(function () use ($orderPackage) {
            $orderPackage->save();
            return $orderPackage;
        });
    }

    public function updateBillCode($params = [])
    {
        $order_code = $params['order_code'] ?? '';
        OrderBuyder::query()->select(
            'id', 'source', 'is_auto_update',
            'buyder_id', 'buyder_no', 'taobao_user_id', 'purchase_order_outer_id', 'purchase_id'
        )
        ->where('created_at', '>=', now()->subMonth()) // Vương nhờ
        ->where('is_auto_update', 1)
        ->whereHas('orderPackage', function ($orderPackage) use ($order_code) {
            $orderPackage->whereNotNull('buyder_no')
                ->where('order_type', Order::class);
            if ($order_code) {
                $orderPackage->where('order_code', $order_code);
            }
        })
        ->with(['orderPackage' => function ($orderPackage) use ($order_code) {
            $orderPackage->select('id', 'buyder_no', 'bill_code', 'order_id', 'category_id', 'order_code');
            if ($order_code) {
                $orderPackage->where('order_code', $order_code);
            }
        }])->chunk( 10 , function ($items) use ($order_code) {
            foreach ($items as $item) {
                if ($order_code) {
                    $this->handleUpdateBillCode($item);
                } else {
                    $jobs = new UpdateBillCodeOrderPackageJob($item);
                    dispatch($jobs)->onQueue('sync-order-platform');
                }
            }
        });
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handleUpdateBillCode($item)
    {
        $source = $item->source ?? '';
        $service = new BasePlatformService(BasePlatformFactory::createBuyerService($source));
        $params = [
            'userId' => $item->taobao_user_id ?? 0,
            'orderId' => $item->buyder_no ?? 0
        ];
        $results = $service->detailOrderPlatform($params);
        if ($source == 'taobao') {
            $this->handleTaoBao($results, $item);
        } else {
            $this->handle1688($results, $item);
        }
    }

    /**
     * @throws BindingResolutionException
     */
    private function handleTaoBao($results, $item)
    {
        if ($results && !empty($results->code)) {
            $res = $results->data;
            $subOrderVO = $res[0]->subOrderVO ?? [];
            $logisticNumber = [];
            foreach ($subOrderVO as $value) {
                if (!empty($value->logisticOrders)) {
                    foreach ($value->logisticOrders as $logisticOrder) {
                        if (!in_array($logisticOrder->logistic_number, $logisticNumber)) {
                            $logisticNumber[] = $logisticOrder->logistic_number;
                        }
                    }
                }
            }
            if (!empty($logisticNumber)) {
                $this->processData($logisticNumber, 'taobao', $item);
            }
        }
    }

    /**
     * @throws BindingResolutionException
     */
    private function handle1688($results, $item)
    {
        if ($results && !empty($results->code)) {
            $res = $results->data;
            $nativeLogistics = $res->nativeLogistics ?? [];
            $logisticNumber = [];
            if (!empty($nativeLogistics)) {
                $logisticsItems = $nativeLogistics->logisticsItems ?? [];
                foreach ($logisticsItems as $logisticsItem) {
                    if (!in_array($logisticsItem->logisticsBillNo, $logisticNumber)) {
                        $logisticNumber[] = $logisticsItem->logisticsBillNo;
                    }
                }
            }
            if (!empty($logisticNumber)) {
                $this->processData($logisticNumber, '1688', $item);
            }
        }
    }

    /**
     * @throws BindingResolutionException
     */
    private function processData($logisticNumber, $type, $item){
        $category_id = '';
        if (sizeof($logisticNumber) == 1)
        {
            foreach ($item->orderPackage as $orderPackage) {
                $checkUpdate = OrderPackage::query()
                    ->where('id', $orderPackage->id)
                    ->whereNull('bill_code')
                    ->update(['bill_code' => $logisticNumber[0]]);
                $orderPackageDetail = OrderPackage::query()->find($orderPackage->id);
                if ($orderPackageDetail->status == PackageConstant::STATUS_PENDING) {
                    $this->syncPackWarehouseBillCode($orderPackageDetail);
                }
                if ($checkUpdate == 1) {
                    Log::info('processData IF: ' . $orderPackageDetail->bill_code);
                    $this->pushToQuoteSlim($orderPackageDetail, $logisticNumber[0]);
                }
            }
        }
        else
        {
            $orderBuyderOrders = DB::table('order_buyder_order')
                ->where('order_buyder_id', $item->id)
                ->pluck('order_id')
                ->toArray();
            $arrayInsertOrderPackage = [];
            foreach ($logisticNumber as $key => $number) {
                if ($key == 0) {
                    foreach ($item->orderPackage as $orderPackage) {
                        $category_id = $orderPackage->category_id ?? 0;
                        $checkUpdate = OrderPackage::query()
                            ->where('id', $orderPackage->id)
                            ->whereNull('bill_code')
                            ->update(['bill_code' => $number]);
                        $orderPackageDetail = OrderPackage::query()->find($orderPackage->id);
                        if ($orderPackageDetail->status == PackageConstant::STATUS_PENDING) {
                            $this->syncPackWarehouseBillCode($orderPackageDetail);
                        }
                        if ($checkUpdate == 1) {
                            Log::info('processData ELSE 1: ' . $orderPackageDetail->bill_code);
                            $this->pushToQuoteSlim($orderPackageDetail, $number);
                        }
                    }
                } else {
                    foreach ($orderBuyderOrders as $orderBuyderOrder) {
                        $arrayInsertOrderPackage[$orderBuyderOrder]['category_id'] = $category_id;
                        $arrayInsertOrderPackage[$orderBuyderOrder]['package_number'] = 1;
                        $arrayInsertOrderPackage[$orderBuyderOrder]['code_po'] = $type == 'taobao' ? $item->purchase_order_outer_id : $item->buyder_no;
                        $arrayInsertOrderPackage[$orderBuyderOrder]['buyder_no'] = $item->buyder_no;
                        $arrayInsertOrderPackage[$orderBuyderOrder]['bill_code'] = $number;
                    }
                }
            }
            if (!empty($arrayInsertOrderPackage)) {
                $this->quickCreatePackageBillCode($arrayInsertOrderPackage);
            }
        }
    }

    /**
     * @throws BindingResolutionException
     */
    private function syncPackWarehouseBillCode($orderPackage){
        $TOKEN_ONE_WAREHOUSE = env('TOKEN_ONE_WAREHOUSE', '');
        if ($orderPackage->bill_code && $orderPackage->order_type == Order::class) {
            if ($TOKEN_ONE_WAREHOUSE) {
                $oneWarehouseService = \App::make('App\Services\OneWarehouseService');
                $oneWarehouseService->syncPackWithOrderOneWarehouse($orderPackage);
            } else {
                $orderPackage->status = PackageConstant::STATUS_ON_THE_WAY_CHINA;
                $orderPackage->code_warehouse = rand(1, 999);
                $orderPackage->save();
            }
        }
    }

    private function quickCreatePackageBillCode($arrayInsertOrderPackage)
    {
        if (!empty($arrayInsertOrderPackage)) {
            foreach ($arrayInsertOrderPackage as $key => $insert) {
               /* ->where('buyder_no' , $insert['buyder_no'])*/
                $validated = OrderPackage::query()
                    ->where('bill_code', $insert['bill_code'])
                    ->where('order_id', $key)
                    ->where('order_type', Order::class)
                    ->exists();
                if (!$validated) {
                    $order = Order::query()->find($key);
                    $this->quickCreatePackage($insert, $order);
                }
            }
        }
    }
    /* Kiện hàng ký gửi quá 3 tháng về kho VN mà không lấy hàng => chuyển hết về trạng thái  HÀNG CHỜ THANH LÝ */
    public function updateOrderPackageStatus()
    {
        $today = new DateTime();
        $today->modify('-90 days');
        $day = $today->format('Y-m-d') . ' 00:00:00';
        OrderPackage::query()
            ->select('id', 'order_type', 'status')
            ->where('order_type', Consignment::class)
            ->where('status', PackageConstant::STATUS_WAREHOUSE_VN)
            ->whereHas('statusTimeVN', function ($statusTimeVN) use ($day) {
                $statusTimeVN->where('created_at', '<=', $day);
            })
            ->chunk(50, function ($orderPackages) {
                foreach ($orderPackages as $orderPackage) {
                    $orderPackage->status = PackageConstant::STATUS_PENDING_LIQUIDATION;
                    $orderPackage->save();
                }
            });
    }

    public function reportControl($params)
    {
        $now = Carbon::now();
        $time = data_get($params, 'time');
        $warehouseId = data_get($params, 'warehouse');
        [$start, $end] = getStartDateEndDateByType($time);
        // Nếu kiện nhận hàng bằng giao hàng thì lấy theo cái này
        $amountDelivery = "deliveries.debt_cost
                    + deliveries.international_shipping_cost
                    + deliveries.china_shipping_cost
                    + deliveries.shock_proof_cost
                    + deliveries.woodworking_cost
                    + deliveries.inspection_cost
                    + deliveries.insurance_cost
                    - deliveries.discount_cost";
        $queryTotalWarehouse = $this->_model->select(
            DB::raw("COUNT(DISTINCT customer_id) as total_customer"),
            DB::raw('COUNT(*) as total_package'), // Tổng kiện đến kho nhận hàng
        )
        ->whereIn('status', [PackageConstant::STATUS_IN_PROGRESS, PackageConstant::STATUS_RECEIVED_GOODS])
        ->whereNull('delivery_id')
        ->whereBetween('date_done', [$start, $end])
        ->when($warehouseId, function($q) use ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        })
        ->where('date_done', '>', '2025-05-28 00:00:00')
        ->first();
        $queryTotalDelivery = $this->_model->select(
            DB::raw("COUNT(DISTINCT customer_id) as total_customer"),
            DB::raw('COUNT(DISTINCT order_package.id) as total_package'), // Tổng kiện nhận hàng bằng giao hàng
        )
        ->whereIn('status', [PackageConstant::STATUS_IN_PROGRESS, PackageConstant::STATUS_RECEIVED_GOODS])
        ->whereNotNull('delivery_id')
        ->join('order_package_status_times', function($join) use ($start, $end){
            $join->on('order_package_status_times.order_package_id', '=', 'order_package.id')
                ->whereIn('order_package_status_times.key', [PackageConstant::STATUS_IN_PROGRESS, PackageConstant::STATUS_RECEIVED_GOODS])
                ->whereBetween('order_package_status_times.created_at', [$start, $end])
                ->where('order_package_status_times.created_at', '>', '2025-05-28 00:00:00');
            }
        )
        ->when($warehouseId, function($q) use ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        })->first();
        $transactionModel = \App::make('App\Models\Transaction');
        $fundTransactionModel = \App::make('App\Models\FundTransaction');
        $warehouseWallet = 0;
        $warehousePayment = 0;
        $warehouseCash = $fundTransactionModel->select(DB::raw("SUM(money) as total_money"))
                        ->where('type_object', 2)
                        ->where('type_pay', 1)
                        ->where('status', 1)
                        ->where('fund_unit_currency', 1)
                        ->where('fund_type_pay_id', 11)
                        ->whereIn('fund_id', [15, 19, 21, 21])
                        ->whereBetween('created_at', [$start, $end])
                        ->where('created_at', '>', '2025-05-28 00:00:00')
                        ->first();
        $deliveryWallet = 0;
        $deliveryPayment = 0;
        $deliveryCash = 0;
        $amountPack = $transactionModel->select(DB::raw("SUM(amount) as total_amount"))->whereIn('sourceable_type', [OrderPackage::class, FilePrint::class])
                    ->whereBetween('created_at', [$start, $end])
                    ->where('created_at', '>', '2025-05-28 00:00:00')
                    ->where('id_check', 0)
                    ->when($warehouseId, function($q) use ($warehouseId) {
                        $q->whereHas('order', function($q) use ($warehouseId) {
                            $q->where('warehouse_id', $warehouseId);
                        });
                    })
                    ->first();
        $warehouseWallet += $amountPack->total_amount ?? 0;
        $transactionPackages = $transactionModel->select(
                DB::raw("IF(amount <= balance_old, amount, 0) as warehouse_wallet_all"), // Lưu id_check để xử lý phần này
                DB::raw("IF(amount > balance_old, amount - balance_old, 0) as warehouse_pay"),
                DB::raw("IF(amount > balance_old, balance_old, 0) as warehouse_wallet"),
            )
            ->whereIn('sourceable_type', [FilePrint::class, OrderPackage::class])
            ->whereBetween('created_at', [$start, $end])
            ->where('created_at', '>', '2025-05-28 00:00:00')
            ->where('id_check', '>', 0)
            ->when($warehouseId, function($q) use ($warehouseId) {
                $q->whereHas('order', function($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                });
            })
            ->get();
        foreach($transactionPackages as $tran) {
            $warehouseWallet += $tran->warehouse_wallet ?? 0;
            $warehouseWallet += $tran->warehouse_wallet_all ?? 0;
            $warehousePayment += $tran->warehouse_pay ?? 0;
        }
        $queryAmountDelivery = Delivery::select(
            DB::raw("SUM(IF(deliveries.amount_import > 0, amount_import, 0)) as total_amount_paid"),
            DB::raw("SUM(IF($amountDelivery < customer_balance_old + amount_import, $amountDelivery, 0)) as total_amount_wallet_all"),
            DB::raw("SUM(IF($amountDelivery > customer_balance_old && customer_balance_old > 0, customer_balance_old, 0)) as total_amount_wallet"),
            DB::raw("SUM(IF($amountDelivery > customer_balance_old + amount_import, $amountDelivery - customer_balance_old, 0)) as total_amount_waiting"),

        )->whereIn('deliveries.status', [DeliveryConstant::KEY_STATUS_DONE, DeliveryConstant::KEY_STATUS_RUN])
        ->whereHas('transactions', function($q) use ($start, $end) {
            $q->whereBetween('created_at', [$start, $end]);
        })
        ->whereHas('packages', function($q) use ($start, $end, $warehouseId) {
            $q->when($warehouseId, function($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            });
        })
        ->where('created_at', '>', '2025-05-28 00:00:00')
        ->first();
        $totalAmountWaiting = 0;
        $deliveryWallet += $queryAmountDelivery->total_amount_wallet ?? 0;
        $deliveryWallet += $queryAmountDelivery->total_amount_wallet_all ?? 0;
        $totalAmountWaiting += $queryAmountDelivery->total_amount_waiting_all ?? 0;
        $totalAmountWaiting += $queryAmountDelivery->total_amount_waiting ?? 0;

        return [
            "total_customer_wh" => $queryTotalWarehouse->total_customer ?? 0, // Tổng khách hàng
            "total_package_wh" => $queryTotalWarehouse->total_package ?? 0, // Tổng kiện đến kho nhận hàng
            "total_customer_delivery" => $queryTotalDelivery->total_customer ?? 0, // Tổng khách hàng
            "total_package_delivery" => $queryTotalDelivery->total_package ?? 0, // Tổng kiện nhận hàng bằng giao hàng
            "warehouse_wallet" => $warehouseWallet,
            "warehouse_payment" => $warehousePayment,
            "warehouse_cash" => $warehouseCash->total_money ?? 0,
            "delivery_wallet" => $deliveryWallet,
            "delivery_payment" => $deliveryPayment,
            "delivery_cash" => $deliveryCash,
            "total_amount_paid" => $queryAmountDelivery->total_amount_paid ?? 0, // // Giao hàng đã thanh toán
            "total_amount_waiting" => $totalAmountWaiting
        ];
    }

    public function reportControlTable($params)
    {
        $time = data_get($params, 'time');
        [$start, $end] = getStartDateEndDateByType($time);
        $query = $this->_model->select(
            "order_package.id",
            "order_package.date_done",
            "order_package.bill_code",
            "order_package.customer_id",
            "order_package.delivery_id",
            "order_package.staff_group_id",
            "order_package.status",
            "order_package.international_shipping_cost",
            "order_package.china_shipping_cost",
            "order_package.exchange_rate",
            "order_package.insurance_cost",
            "order_package.inspection_cost",
            "order_package.woodworking_cost",
            "order_package.shock_proof_cost",
            "order_package.delivery_cost",
            "order_package.discount_cost",
            "order_package.order_id",
            "order_package.order_type",
        )
        ->with([
            'customer' => function($q) {
                return $q->select('id', 'name', 'code');
            },
            'staffGroup' => function($q) {
                return $q->select('id', 'name', 'code');
            },
            'delivery', 'delivery.transporter',
            'delivery.orderPackages',
            'orderPackageWithOrder',
            'orderPackageWithOrder.packages' => function($q) {
                return $q->orderBy('created_at', 'asc');
            }
        ])
        ->whereIn('order_package.status', [PackageConstant::STATUS_IN_PROGRESS, PackageConstant::STATUS_RECEIVED_GOODS])
        ->join('order_package_status_times', function($join) use ($start, $end){
            $join->on('order_package_status_times.order_package_id', '=', 'order_package.id')
                ->whereIn('order_package_status_times.key', [PackageConstant::STATUS_IN_PROGRESS, PackageConstant::STATUS_RECEIVED_GOODS])
                ->where('order_package_status_times.created_at', '>', '2025-05-28 00:00:00')
                ->whereBetween('order_package_status_times.created_at', [$start, $end]);
            }
        );
        if(!empty($params['bill_code'])) {
            $query->where('order_package.bill_code', $params['bill_code']);
        }
        if(data_get($params, 'transporter_id') != "") {
            if($params['transporter_id'] == 0) {
                $query->whereNull('order_package.delivery_id');
            }else {
                $query->whereHas('delivery', function ($q) use ($params) {
                    $q->where('transporter_id', $params['transporter_id']);
                });
            }
        }
        if(data_get($params, 'type_id') != "") {
            if($params['type_id'] == 0) {
                $query->whereNull('order_package.delivery_id');
            }else {
                $query->whereNotNull('order_package.delivery_id');
            }
        }
        if(!empty($params['customer_code']) || !empty($params['customer_name'])) {
            $query->whereHas('customer', function ($q) use ($params) {
                $q->when(!empty($params['customer_code']), function($q1) use ($params){
                    return $q1->where('code', $params['customer_code']);
                })->when(!empty($params['customer_name']), function($q1) use ($params){
                    return $q1->where('name', 'LIKE', "%{$params['customer_name']}%");
                });
            });
        }
        if(!empty($params['delivery_code'])) {
            $query->whereHas('delivery', function ($q) use ($params) {
                $q->where('code', $params['delivery_code']);
            });
        }
        $data = $query->paginate(data_get($params, 'per_page') ?? 1);
        return resSuccessWithinData(new $this->_paginateResource($data, \App\Http\Resources\Package\ReportControlResource::class));
    // Xử lý các kiện đánh dấu thất lạc và đã trả hàng cho khách thì đồng bộ ngược cho bên kho
    }

    public function updatePackLostAndReturnCustomer()
    {
        $packModel = \App::make('App\Models\OrderPackage');
        $packDBs = $packModel->where('is_lost', 1)
                            ->where('status', PackageConstant::STATUS_RECEIVED_GOODS)
                            ->select('id', 'status', 'is_lost')
                            ->get();
        if (count($packDBs) > 0) {
            $arr_ids = [];
            foreach ($packDBs as $key => $packDB) {
                array_push($arr_ids, (int) $packDB->id);
            }

            $guzzle = new Guzzle;
            $url = env('URL_DONGDONG') . 'webhook/hangve-x329kxm2k2ixk39x92x1911947165';
            $options = [
                'json' => [
                    'event_name' => 'update_pack_lost',
                    'data'       => [
                        'arr_ids' => $arr_ids
                    ]
                ],
                'verify' => false,
                'http_errors' => false,
            ];

            $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
            $result = json_decode($result, true);

            if (isset($result)) {
                if ($result['code'] == 200) {
                    foreach ($packDBs as $key => $packDB) {
                        \Log::info('updatePackLostAndReturnCustomer');
                        $packDB->is_lost = 0;
                        $packDB->save();
                    }
                }
            }
        }
    }

    // Đẩy tự động sang Báo giá
    public function pushToQuoteSlim($orderPackDB, $bill_code_new)
    {
        $data_push = [
            'hangve_pack_id'    => (int) $orderPackDB->id,
            'bill_code_old'     => null,
            'bill_code_new'     => $bill_code_new,
            'hangve_order_id'   => $orderPackDB->order_id,
            'hangve_order_code' => $orderPackDB->order_code
        ];
        $job = new \App\Jobs\Quote\SyncUpdateBillCodePackOrder($data_push);
        dispatch($job)->delay(now()->addSeconds(5))->onQueue('cron-data-2023');
    }

    public function syncFeeFromQuote()
    {
        $now = Carbon::now();
        $startMonth = $now->copy()->startOfMonth();
        OrderPackage::where('official', OrderPackage::OFFICIAL_CN)
            ->where('status', PackageConstant::STATUS_RECEIVED_GOODS)
            ->where('date_done', '>=', $startMonth)
            ->whereNotNull('bill_code')
            ->where('official_service_fee', 0)
            ->chunk(50, function ($orderPackages) {
                $billCodes = $orderPackages->pluck('bill_code')->toArray();
                \Log::info($billCodes);
                $guzzle = new Guzzle;
                $url = env('URL_QUOTE') . 'webhook/hangve-m2329k1947165msh';
                $options = [
                    'json' => [
                        'event_name' => 'sync_fee_quote',
                        'data' => [
                            'bill_codes' => $billCodes,
                        ],
                        'token2000a'         => env('TOKEN_SYNC_DD_HV')
                    ],
                    'verify' => false,
                    'http_errors' => false,
                ];

                \Log::info($options['json']['data']);

                $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
                $result = json_decode($result, true);
            });
        return null;
    }

    public function updateService($id, $service, $orderCost = null)
    {
        $accountingService = App::make('App\Services\AccountingService');
        $orderPackage = OrderPackage::query()->find($id);
        if($service == 'insurance') {
            $orderPackage->is_insurance = $orderPackage->is_insurance == 1 ? 0 : 1;
            $isInsurance = (bool)($orderPackage->is_insurance);
            if ($isInsurance) {
                // Lưu giá trị order_cost do nv nhập
                $orderPackage->order_cost = $orderCost;
                $orderPackage->insurance_cost = AccountingHelper::getCosts(
                    $accountingService->getInsuranceCost($orderPackage->order_cost)
                );
            } else {
                $orderPackage->insurance_cost = 0;
            }
        }
        if($service == 'woodworking') {
            $orderPackage->is_woodworking = $orderPackage->is_woodworking == 1 ? 0 : 1;
            $isWoodworking = (bool)($orderPackage->is_woodworking);
            $orderPackage->woodworking_cost = $isWoodworking ? AccountingHelper::getCosts(
                $accountingService->getWoodworkingCost($orderPackage->weight, $orderPackage->volume, $orderPackage)
            ) : 0;
        }
        if($service == 'shock_proof') {
            $orderPackage->is_shock_proof = $orderPackage->is_shock_proof == 1 ? 0 : 1;
            $isShockProof = (bool)($orderPackage->is_shock_proof);
            $orderPackage->shock_proof_cost = $isShockProof ? AccountingHelper::getCosts(
                $accountingService->getShockCost($orderPackage->weight, $orderPackage)
            ) : 0;
        }
        $orderPackage->save();
        $this->processingInfo($orderPackage);
        $data = [
            'is_woodworking' => $orderPackage->is_woodworking,
            'is_inspection' => $orderPackage->is_inspection,
            'is_advanced_inspection' => $orderPackage->is_advanced_inspection,
            'is_shock_proof' => $orderPackage->is_shock_proof,
            'is_insurance' => $orderPackage->is_insurance,
        ];
        $oneWarehouseService = \App::make('App\Services\OneWarehouseService');
        $data['is_advanced_inspection'] = $orderPackage->is_advanced_inspection == 2 ? 1 : 0;
        $syncServiceOWJob = new \App\Jobs\OneWarehouse\SyncServicePackJob([$id], $data);
        dispatch($syncServiceOWJob)->onQueue('export-1800');
    }

    public function requestChangePrice($params)
    {
        $listPack = $params['list_pack'] ?? [];
        $requestReason = $params['request_reason'] ?? '';
        $data = [];
        $user = getCurrentUser();
        foreach($listPack as $pack) {
            $orderPackage = OrderPackage::query()->find($pack['id']);
            if($orderPackage) {
                $formatData = [
                    'order_package_id' => $orderPackage->id,
                    'customer_id' => $orderPackage->customer_id ?? 0,
                    'user_id' => $user->id ?? 0,
                    'user_name_id' => $user->name ?? null,
                    'request_reason' => $requestReason,
                    'source' => ApproveUnitPriceConstant::SOURCE_HV,
                    'unit_price' => $pack['unit_price'] ?? 0,
                    'unit' => $orderPackage->type_quantitative == 'weight' ? "{$orderPackage->weight} kg" : "{$orderPackage->volume} m3"
                ];
                $approveUnitPrice = ApproveUnitPrice::query()->where('order_package_id', $orderPackage->id)->where('status', ApproveUnitPriceConstant::PENDING)->first();
                if($approveUnitPrice) {
                    $approveUnitPrice->update($formatData);
                } else {
                    $create = ApproveUnitPrice::query()->create($formatData);
                    $this->notiToUserApproveUnitPrice($create);
                }
            }
        }
        return null;
    }

    private function notiToUserApproveUnitPrice($approveUnitPrice)
    {
        // Gửi thông báo cho tất cả NV có quyền duyệt yêu cầu
        $permission = "pack.approve-unit-price-hangve";
        $users = User::join('model_has_roles', function($join) {
            $join->on('model_has_roles.model_id', '=', 'users.id')
                ->where('model_has_roles.model_type', User::class);
        })
        ->join('role_has_permissions', 'model_has_roles.role_id', 'role_has_permissions.role_id')
        ->join('permissions', function($joinPer) use ($permission) {
            $joinPer->on('permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('permissions.name', $permission);
            })
        ->select('users.*')
        ->groupBy('users.id')
        ->get();
        if(count($users) > 0) {
            $notificationService = \App::make('App\Services\NotificationService');
            $orderPackage = $approveUnitPrice->orderPackage;
            $dataJson = [
                'bill_code' => data_get($orderPackage, 'bill_code'),
            ];
            $data = [
                'group'           => 3,
                'name'            => 3011,
                'data'            => json_encode($dataJson),
                'time_create'     => time(),
                'status'          => 1
            ];
            foreach($users as $user) {
                $notificationService->storeNoti($user->id, $data);
            }
        }

    }

    public function updateShareShip($packDB)
    {
        if ($packDB->status != PackageConstant::STATUS_CANCEL) {
            return;
        }
        if ($packDB->share_fee_ship != PackageConstant::SHARE_FEE_SHIP) {
            return;
        }

        $arr_status = [
            PackageConstant::STATUS_CANCEL,
            PackageConstant::STATUS_RECEIVED_GOODS,
        ];

        $baseQuery = OrderPackage::query()
            ->where('bill_code', $packDB->bill_code)
            ->where('id', '<>', $packDB->id)
            ->whereNotIn('status', $arr_status);

        // Phí vận chuyển quốc tế
        $packages = (clone $baseQuery)->pluck('id')->toArray();
        if (count($packages) > 0) {
            // $new_international_shipping_cost = $packDB->international_shipping_cost * (count($packages) + 1) / count($packages);
            // OrderPackage::query()
            //     ->whereIn('id', $packages)
            //     ->update([
            //         'international_shipping_cost' => $new_international_shipping_cost,
            //     ]);

            foreach ($packages as $key => $pack_id) {
                $packageDB = OrderPackage::query()->where('id', $pack_id)->first();
                $new_international_shipping_cost = $packageDB->international_shipping_cost * (count($packages) + 1) / count($packages);
                $new_international_shipping_cost = ceil($new_international_shipping_cost);
                $packageDB->international_shipping_cost = $new_international_shipping_cost;
                $packageDB->save();
            }
        }

        if ($packDB->is_woodworking == 1) {
            $packages = (clone $baseQuery)->where('is_woodworking', 1)->pluck('id')->toArray();
            if (count($packages) > 0) {
                $new_woodworking_cost = $packDB->woodworking_cost * (count($packages) + 1) / count($packages);
                OrderPackage::query()
                    ->whereIn('id', $packages)
                    ->update([
                        'woodworking_cost' => (int) $new_woodworking_cost,
                    ]);
            }
        }

        if ($packDB->is_shock_proof == 1) {
            $packages = (clone $baseQuery)->where('is_shock_proof', 1)->pluck('id')->toArray();
            if (count($packages) > 0) {
                $new_shock_proof_cost = $packDB->shock_proof_cost * (count($packages) + 1) / count($packages);
                OrderPackage::query()
                    ->whereIn('id', $packages)
                    ->update([
                        'shock_proof_cost' => (int) $new_shock_proof_cost,
                    ]);
            }
        }
    }
}
