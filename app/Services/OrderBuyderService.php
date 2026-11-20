<?php

namespace App\Services;

use App\Abstracts\AbstractBaseService;
use App\Constants\Buyder\BuyderStatusConstant;
use App\Constants\OrderConstant;
use App\Constants\ResourceConstant;
use App\Constants\RoleConstant;
use App\Factories\BasePlatformFactory;
use App\Jobs\QuickCreateOrderPackageJob;
use App\Models\Order;
use App\Models\OrderPackage;
use App\Services\PlatformServices\BasePlatformService;
use App\Traits\PlaceOrder\CreateLocalDataTrait;
use App\Traits\PlaceOrder\GetPlatformStatusConstantNameTrait;
use Carbon\Carbon;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class OrderBuyderService extends AbstractBaseService
{
    use CreateLocalDataTrait, GetPlatformStatusConstantNameTrait;
    protected function setModel()
    {
        $namespaceModel = 'App\Models\\' . str_replace('Service', '', class_basename($this));
        $this->_model = class_exists($namespaceModel) ? new $namespaceModel() : null;
    }

    /**
     * @throws BindingResolutionException
     * @throws \Exception
     */
    public function updateOrderTaoBao($orderBuyder, $user_id_taobao = false, $arrayInsertOrderPackage = [], $queue = true){
        if ($queue) {
            sleep(3);
        }
        $source = $orderBuyder->source;
        $closeReason = $this->getOrderBuyderSubsClosedStatus($source);
        $params['orderId'] = $orderBuyder->buyder_no ?? 0;
        $service = new BasePlatformService(BasePlatformFactory::createBuyerService($source));
        $result = $service->detailOrderPlatform($params);
        if ($result && !empty($result->code)) {
            if ($result->code == 200) {
                $subOrderVos = data_get($result, 'data.0.subOrderVO');
                if (!empty($subOrderVos)) {
                    if ($subOrderVos[0]->subOrderStatus == 'TRADE_CLOSED') {
                        Log::info('Đơn đã đã bị đóng buyder_no ' . $params['orderId']);
                        return;
                    }
                }
                $orderBuyder->loadMissing('subOrderBuyders');
                $subOrderBuyders = $orderBuyder->subOrderBuyders;
                $platformGoodsAmount = 0;
                $platformTotalAmount = 0;
                $purchaseOrderOuterId = '';
                $subOrderBuyders->each(function ($subOrderBuyder) use (
                    $subOrderVos, $closeReason, &$purchaseOrderOuterId, &$platformGoodsAmount, &$platformTotalAmount
                ) {
                    $vos = Arr::first($subOrderVos, fn ($vo) => (
                        data_get($vo, 'skuId') == data_get($subOrderBuyder, 'sku_id')
                    ));
                    // nếu đơn con không ở trạng thái 'đã hủy' thì cộng dồn tiền hàng và tổng tiền vào đơn cha
                    if (data_get($vos, 'subOrderStatus') != $closeReason) {
                        $purchaseAmount = (float)(data_get($vos, 'purchaseAmount') / 100);
                        $platformGoodsAmount += $purchaseAmount;
                        $platformTotalAmount += $purchaseAmount;
                    }
                    // lấy classification_zh
                    $zh = json_decode(data_get($vos, 'optionFields.zh'));
                    // lấy tiền hàng thực tế, phí ship thực tế, tổng tiền thực tế của đơn con

                    if ($purchaseOrderOuterId == '') {
                        $purchaseOrderOuterId = isset($subOrderVos[0]) ? $subOrderVos[0]->purchaseOrderOuterId : 0;
                        /*$purchaseOrderOuterId = data_get($vos, 'purchaseOrderOuterId');*/
                    }

                    $vosPlatformGoodsAmount = (float)(data_get($vos, 'purchaseAmount') / 100);
                    $vosPlatformDomesticPostFee = (float)(data_get($vos, 'domesticPostFee') / 100);
                    $subOrderBuyder->update([
                        'classification_zh' => $zh ? implode(';', get_object_vars($zh)) : '',
                        'platform_total_amount' => $vosPlatformGoodsAmount + $vosPlatformDomesticPostFee,
                        'platform_goods_amount' => $vosPlatformGoodsAmount,
                        'platform_domestic_post_fee' => $vosPlatformDomesticPostFee,
                        'buyder_status' => BuyderStatusConstant::ORDER_WAIT_BUYER_P, /*data_get($vos, 'subOrderStatus')*/
                        'sub_purchase_order_id' => data_get($vos, 'subPurchaseOrderId')
                    ]);
                });

                $platformDomesticPostFee = $platformTotalAmount
                    ? (float)(data_get($result, 'data.0.domesticPostFee') / 100)
                    : 0;
                $arrayUpdate = [
                    'platform_total_amount' => $platformTotalAmount,
                    'platform_goods_amount' => $platformGoodsAmount,
                    'platform_domestic_post_fee' => $platformDomesticPostFee,
                ];
                if ($purchaseOrderOuterId) {
                    $arrayUpdate['purchase_order_outer_id'] = $purchaseOrderOuterId;
                }

                if (!empty($arrayInsertOrderPackage) && $purchaseOrderOuterId != '') {
                    if ($queue) {
                        $jobs = new QuickCreateOrderPackageJob(
                            $arrayInsertOrderPackage,
                            $purchaseOrderOuterId,
                            'taobao'
                        );
                        dispatch($jobs)->onQueue('sync-order-platform');
                    } else {
                        $orderPackageService = app()->make(OrderPackageService::class);
                        foreach ($arrayInsertOrderPackage as $key => $item) {
                            if (
                                OrderPackage::query()->where('order_id', $key)
                                ->where('buyder_no', $item['buyder_no'])->first()
                            ) {
                                continue;
                            }
                            $item['code_po'] = $purchaseOrderOuterId;
                            $order = Order::query()->find($key);
                            $orderPackageService->quickCreatePackage($item, $order);
                            Log::info('quickCreatePackage updateOrderTaoBao' . $orderBuyder->buyder_no);
                        }
                    }
                }
                // Log::info('orderBuyder Update ' . json_encode($arrayUpdate));
                $orderBuyder->update($arrayUpdate);
                // Log::info('Cập nhật dữ liệu thành công, mã đặt hàng buyder_no: ' . $orderBuyder->buyder_no . 'orderBuyder purchaseOrderOuterId' . $purchaseOrderOuterId);
            } else {
                // Log::error('Cập nhật dữ liệu thật bại, mã đặt hàng buyder_no: ' . $orderBuyder->buyder_no);
            }
            return;
        }
        // Log::error('Cập nhật dữ liệu thật bại, mã đặt hàng buyder_id: ' . $orderBuyder->buyder_no );
    }

    public function listOrderBuyder($request = []){
        $order = DB::table('orders');
        $user = auth()->user();
        $is_view_all = false;
        $waitingToOrder = $request['waiting_to_order'] ?? '';
        $source = $request['source'] ?? BuyderServiceConstant::SOURCE_TAOBAO;
        if ($user->hasRoleName(RoleConstant::ADMIN) || $user->hasRoleName(RoleConstant::TRUONG_BP_GDQT)) {
            $is_view_all = true;
        }
        if (!$is_view_all) {
            $order->where('orders.staff_order_id', auth()->id());
        }
        $order->join('users', 'users.id', '=', 'orders.staff_order_id');
        $order->whereExists(function ($query) use ($source) {
            $query->select(DB::raw(1))
                ->from('order_details')
                ->whereRaw('order_details.order_id = orders.id')
                ->where('source', $source);
        });
        $order->join('customers', 'customers.id', '=', 'orders.customer_id');
        $order->whereIn('orders.status', [
            OrderConstant::KEY_STATUS_DEPOSITED,
            OrderConstant::KEY_STATUS_WAIT_TO_PAY,
            OrderConstant::KEY_STATUS_ORDERED,
        ]);

        if ($waitingToOrder) {
            $order->leftJoin('order_buyder_order', 'orders.id', '=', 'order_buyder_order.order_id');
            $order->whereNull('order_buyder_order.order_buyder_id');
       }
        if (!empty($request['order_status'])) {
            $order->where('orders.status', $request['order_status']);
        }
        if (!empty($request['order_code'])) {
            $order->where('orders.code', $request['order_code']);
        }
        if (!empty($request['staff_id'])) {
            $order->where('orders.staff_order_id', $request['staff_id']);
        }
        if (!empty($request['from'])) {
            $order->where('orders.created_at', '>=', $request['from'] . ' 00:00:00');
        }
        if (!empty($request['to'])) {
            $order->where('orders.created_at', '<=', $request['to'] . ' 23:59:59');
        }
        if (!empty($request['order_buyder_no'])) {
            $outerId = $request['order_buyder_no'];
            $order->whereExists(function ($query) use ($outerId, $source) {
                $query->select(DB::raw(1))
                    ->from('order_buyder_order')
                    ->join('order_buyders', 'order_buyder_order.order_buyder_id', '=', 'order_buyders.id')
                    ->whereRaw('order_buyder_order.order_id = orders.id');
                if ($source == BuyderServiceConstant::SOURCE_TAOBAO) {
                    $query->where('order_buyders.purchase_order_outer_id', $outerId);
                } else {
                    $query->where('order_buyders.buyder_no', $outerId);
                }
            });
        }
        $orderBuyderStatus = $request['order_buyder_status'] ?? '';
        if (!empty($orderBuyderStatus) && $orderBuyderStatus != 'all') {
            $order->whereExists(function ($query) use ($orderBuyderStatus) {
                $query->select(DB::raw(1))
                    ->from('order_buyder_order')
                    ->join('order_buyders', 'order_buyder_order.order_buyder_id', '=', 'order_buyders.id')
                    ->whereRaw('order_buyder_order.order_id = orders.id');
                $query->where('order_buyders.supplier_status', $orderBuyderStatus);
            });
        }
        $order->whereNull('users.deleted_at');
        $order->whereNull('customers.deleted_at');
        $order->whereNull('orders.deleted_at');
        $order->select(
            'orders.id as order_id', 'orders.code as order_code', 'orders.staff_order_id', 'orders.customer_id',
            'orders.status', 'orders.china_shipping_cost', 'orders.created_at',
            'orders.organization_id',
            'users.id as user_id', 'users.name as staff_name', 'users.code as user_code',
            'customers.id as customer_id', 'customers.name as customer_name', 'customers.code as customer_code',
            'customers.source as customer_source'
        );
        $perPage = $request['per_page'] ?? 10;
        $orders = $order->paginate($perPage);
        return $this->formatOrder($orders, $source);
    }

    private function formatOrder($orders, $source){
        $orderIds = $orders->pluck('order_id')->toArray();
        $orderFormat = $this->getOrderHistories($orders, $orderIds);
        $orderFormat = $this->getOrderNotes($orderFormat, $orderIds);
        $orderFormat = $this->getOrderDetails($orderFormat, $orderIds);
        $orderFormat = $this->getOrderPackages($orderFormat, $orderIds);
        return $this->getOrderBuyders($orderFormat, $orderIds, $source);
    }

    private function getOrderHistories($orders, $orderIds){
        $orderHistories = DB::table('order_histories')
            ->whereIn('order_id', $orderIds)
            ->orderBy('created_at')
            ->get()
            ->groupBy('order_id');
        $orders->getCollection()->transform(function ($order) use ($orderHistories) {
            $orderHistory = $orderHistories[$order->order_id] ?? collect();
            $orderHistory->transform(function ($orderHis) {
                $orderHis->date = date('d-m-Y H:i:s', strtotime($orderHis->created_at));
                return $orderHis;
            });
            $order->order_note_internal = $orderHistory;
            unset($order->staff_order_id);
            return $order;
        });
        return $orders;
    }
    private function getOrderNotes($orders, $orderIds){
        $orderNotes = DB::table('order_notes')
            ->whereIn('order_id', $orderIds)
            ->orderBy('created_at')
            ->get()
            ->groupBy('order_id');
        $orders->getCollection()->transform(function ($order) use ($orderNotes) {
            $orderNote = $orderNotes[$order->order_id] ?? collect();
            $order->order_note_internal = $orderNote;
            $customerNote = $orderNote->firstWhere('subject_type', 'App\\Models\\Customer');
            $hasStaffNote = $orderNote->contains('subject_type', 'App\\Models\\Staff');
            $order->order_note_internal_count = $hasStaffNote ? 1 : 0;
            $order->order_note_hang_ve = $customerNote->content ?? null;
            return $order;
        });
        return $orders;
    }

    private function getOrderPackages($orders, $orderIds){
        $orderPackages = DB::table('order_package')
            ->whereIn('order_id', $orderIds)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as total_package, order_id')
            ->get()
            ->groupBy('order_id');
        $orders->getCollection()->transform(function ($order) use ($orderPackages) {
            $orderPackage = $orderPackages[$order->order_id] ?? collect();
            $order->order_number_packages = $orderPackage[0]->total_package ?? 0;
            return $order;
        });
        return $orders;
    }

    private function getOrderBuyders($orders, $orderIds, $source = BuyderServiceConstant::SOURCE_TAOBAO)
    {
        $orderBuyders = DB::table('order_buyder_order')
            ->join('order_buyders', 'order_buyder_order.order_buyder_id', '=', 'order_buyders.id')
            ->leftJoin('users', 'users.id', '=', 'order_buyders.creator_id')
            ->whereIn('order_buyder_order.order_id', $orderIds)
            ->select(
                'order_buyder_order.order_id',
                'order_buyders.id as order_buyder_id',
                'order_buyders.buyder_id',
                'order_buyders.supplier_status',
                'order_buyders.created_at',
                'order_buyders.delay_time',
                'order_buyders.platform_total_amount',
                'order_buyders.platform_domestic_post_fee',
                'order_buyders.platform_goods_amount',
                'order_buyders.buyder_no',
                'order_buyders.source',
                'order_buyders.purchase_order_outer_id',
                'order_buyders.supplier_status',
                'users.id', 'users.name', 'users.email', 'users.avatar', 'users.code', 'users.phone_number'
            )
            ->when(request('order_buyder_no'), function ($query, $order_buyder_no) use ($source) {
                if ($source == BuyderServiceConstant::SOURCE_TAOBAO) {
                    $query->where('order_buyders.purchase_order_outer_id', $order_buyder_no);
                }
                if ($source == BuyderServiceConstant::SOURCE_1688) {
                    $query->where('order_buyders.buyder_no', $order_buyder_no);
                }
            })
            ->when(request('order_buyder_status'), function ($query, $status) {
                $query->where('order_buyders.supplier_status', $status);
            })
            ->get()
            ->groupBy('order_id');
        $orderBuyderIds = collect($orderBuyders)
            ->flatten(1)
            ->pluck('order_buyder_id')
            ->unique()
            ->toArray();
        $orderBuyderSubs = DB::table('order_buyder_subs')
            ->whereIn('order_buyder_subs.order_id', $orderIds)
            ->whereIn('order_buyder_subs.order_buyder_id', $orderBuyderIds)
            ->where('order_buyder_subs.sourceable_type', 'App\\Models\\OrderDetail')
            ->whereNull('order_buyder_subs.close_reason')
            ->whereNull('order_details.deleted_at')
            ->select(
                'order_buyder_subs.id',
                'order_buyder_subs.order_buyder_id',
                'order_buyder_subs.buyder_sub_id',
                'order_buyder_subs.buyder_status',
                'order_buyder_subs.order_id',
                'order_buyder_subs.quantity',
                'order_buyder_subs.sourceable_id',
                'order_buyder_subs.sourceable_type',
                'order_buyder_subs.created_at',
                'order_buyder_subs.classification_zh',
                'order_buyder_subs.logistic_number',
                'order_buyder_subs.domestic_post_fee',
                'order_buyder_subs.close_reason',
                'order_buyder_subs.platform_total_amount',
                'order_buyder_subs.platform_domestic_post_fee',
                'order_buyder_subs.platform_goods_amount',
                'order_buyder_subs.staff_id',
                'order_details.unit_price_cny',
                'order_details.quantity as order_detail_quantity',
                'order_details.id as order_detail_id',
                'order_details.is_from_addon',
                'order_details.name as order_detail_name',
                'order_details.image as order_detail_image',
                'order_details.link as order_detail_link',
                'order_details.classification as order_detail_classification',
                'items.num_iid', 'items.seller_id'
            )
            ->join('order_details', 'order_details.id', '=','order_buyder_subs.sourceable_id')
            ->leftJoin('items', 'items.detail_url', '=', 'order_details.link')
            ->get()
            ->groupBy('order_buyder_id');
        $authId = auth()->id();
        $orders->getCollection()->transform(function ($order) use ($orderBuyders, $orderBuyderSubs, $authId, $source) {
            $buyders = $orderBuyders[$order->order_id] ?? collect();
            $buyders->transform(function ($orderBuyder) use ($orderBuyderSubs, $authId, $source) {
                $supplierStatus = data_get($orderBuyder, 'supplier_status');
                $orderBuyderSub = $orderBuyderSubs[$orderBuyder->order_buyder_id] ?? collect();
                $mutualOrderBuyder = [
                    'buyder_id' => data_get($orderBuyder, 'buyder_id'),
                    'order_buyder_platform_source' => data_get($orderBuyder, 'source'),
                    'order_buyder_id' => data_get($orderBuyder, 'order_buyder_id'),
                    'order_buyder_date' => Carbon::parse($orderBuyder->created_at)->format('d/m/Y'),
                    'order_buyder_code' => data_get($orderBuyder, 'buyder_no'),
                    'order_buyder_outer_id' => $source == 'taobao'
                        ? data_get($orderBuyder, 'purchase_order_outer_id')
                        : data_get($orderBuyder, 'buyder_id')
                    ,
                    'order_buyder_status' => BuyderStatusConstant::LIST_ORDER_STATUS_NAME[$supplierStatus] ?? '',
                    'order_buyder_status_color' => BuyderStatusConstant::LIST_ORDER_STATUS_COLOR[$supplierStatus] ?? '',
                    'order_buyder_status_code' => data_get($orderBuyder, 'supplier_status'),
                    'order_buyder_quantity_placed' => $orderBuyderSub->sum('quantity') ?? 0,
                ];
                $orderBuyderSubFirst = $orderBuyderSub->first();

                $orderBuyderSub = $orderBuyderSub->map(function ($sub) use (
                    $mutualOrderBuyder, $orderBuyder, $authId, $source
                ) {
                    $productCost = $sub->unit_price_cny ?? 0;
                    $productQuantity = (int)$sub->quantity ?? 0;
                    $orderDetailQuantity = $sub->order_detail_quantity ?? 0;
                    if ($productQuantity > $orderDetailQuantity) {
                        $productQuantity = $orderDetailQuantity;
                    }
                    $isCheckable = $authId == $sub->staff_id
                        && (
                            data_get($orderBuyder, 'supplier_status') == BuyderStatusConstant::ORDER_WAIT_BUYER_P
                            || data_get($orderBuyder, 'supplier_status') == BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_BUYER_PAY
                        ) && is_null(data_get($sub, 'close_reason'));
                    $dataMerge = [
                        'order_id' => $sub->order_id,
                        'is_similar_classification' => true,
                        'buyder_sub_id' => data_get($sub, 'buyder_sub_id'),
                        'buyder_sub_status' => BuyderStatusConstant::getConstantValue(
                            'SUB_BUYDER_ORDER_STATUSES',
                            data_get($sub, 'buyder_status')
                        ),
                        'buyder_sub_status_color' => BuyderStatusConstant::getConstantValue(
                            'SUB_BUYDER_ORDER_STATUS_COLORS',
                            data_get($sub, 'buyder_status')
                        ),
                        'order_detail_id' => data_get($sub, 'order_detail_id'),
                        'product_name' => data_get($sub, 'order_detail_name'),
                        'product_image' => data_get($sub, 'order_detail_image'),
                        'product_url' => data_get($sub, 'order_detail_link'),
                        'product_classification' => data_get($sub, 'order_detail_classification')
                            ?? data_get($sub, 'classification_zh'),
                        'product_classification_zh' => data_get($sub, 'classification_zh'),
                        'product_cost' => $productCost,
                        'product_quantity' => $productQuantity,
                        'product_total_cost' => round($productCost * $productQuantity, 2),
                        'product_logistic_number' => data_get($sub, 'logistic_number'),
                        'product_delay_time' => data_get($orderBuyder, 'delay_time'),
                        'staff' => [
                            'id' => $orderBuyder->id,
                            'name' => $orderBuyder->name,
                            'email' => $orderBuyder->email,
                            'avatar' => $orderBuyder->avatar,
                            'code' => $orderBuyder->code,
                            'phone_number' => $orderBuyder->phone_number,
                        ],
                        'shipping_cost' => data_get($sub, 'domestic_post_fee'),
                        'close_reason' => data_get($sub, 'close_reason'),
                        'platform_total_amount' => ((float)data_get($sub, 'platform_total_amount'))
                            - ((float)data_get($sub, 'platform_domestic_post_fee')),
                        'platform_goods_amount' => ((float)data_get($sub, 'platform_goods_amount'))
                            - ((float)data_get($sub, 'platform_domestic_post_fee')),
                        'platform_domestic_post_fee' => ((float)data_get($sub, 'platform_domestic_post_fee')),
                        'is_from_addon' => $sub->is_from_addon ?? 0
                    ];
                    if ($source == 'taobao') {
                        $dataMerge['is_checkable'] = $isCheckable;
                    }
                    return array_merge($mutualOrderBuyder, $dataMerge);
                });
                if ($source == BuyderServiceConstant::SOURCE_1688) {
                    $orderBuyder->list_order['order_buyder_id'] = data_get($orderBuyder, 'buyder_id');
                    $orderBuyder->list_order['order_buyder_date'] =
                        Carbon::parse($orderBuyder->created_at)->format('d/m/Y');
                    $orderBuyder->list_order['order_buyder_status'] =
                        BuyderStatusConstant::LIST_ORDER_STATUS_NAME[data_get($orderBuyder, 'supplier_status')] ?? '';
                    $orderBuyder->list_order['order_buyder_status_color'] =
                        BuyderStatusConstant::LIST_ORDER_STATUS_COLOR[data_get($orderBuyder, 'supplier_status')] ?? '';
                    $orderBuyder->list_order['order_buyder_status_code'] = data_get($orderBuyder, 'supplier_status');
                    $orderBuyder->list_order['order_buyder_delay_time'] = data_get($orderBuyder, 'delay_time');
                    $orderBuyder->list_order['order_buyder_platform_total_amount'] =
                        data_get($orderBuyder, 'platform_total_amount');
                    $orderBuyder->list_order['order_buyder_platform_goods_amount'] =
                        data_get($orderBuyder, 'platform_goods_amount');
                    $orderBuyder->list_order['order_buyder_platform_domestic_post_fee'] =
                        data_get($orderBuyder, 'platform_domestic_post_fee');
                    $isCheckable = $authId == data_get($orderBuyderSubFirst, 'staff_id')
                        && is_null($orderBuyderSubs->whereNotNull('close_reason')->first());
                    $orderBuyder->list_order['is_checkable'] = $isCheckable;
                    $orderBuyder->list_order['shipping_cost'] = data_get($orderBuyderSubFirst, 'domestic_post_fee');
                    $orderBuyder->list_order['seller_open_id'] = $orderBuyderSubFirst->seller_id ?? 0;
                    $orderBuyder->list_order['offer_id'] = $orderBuyderSubFirst->num_iid ?? 0;
                    $orderBuyder->list_order['order_buyder_platform_source'] = BuyderServiceConstant::SOURCE_1688;
                    $orderBuyder->list_order['data'] = $orderBuyderSub;
                } else {
                    $orderBuyder->list_order = $orderBuyderSub;
                }

                $total = [
                    "platform_total_amount" => $orderBuyderSub->sum('platform_total_amount') ?? 0,
                    "platform_goods_amount" => $orderBuyderSub->sum('platform_goods_amount') ?? 0,
                    "platform_domestic_post_fee" => $orderBuyderSub->sum('platform_domestic_post_fee') ?? 0,
                    "total_quantity" => $orderBuyderSub->sum('product_quantity') ?? 0,
                    "total_price" => round($orderBuyderSub->sum('product_total_cost') ?? 0, 2),
                    'order_buyder_date' => Carbon::parse($orderBuyder->created_at)->format('d/m/Y'),
                    'order_buyder_outer_id' => $source == 'taobao'
                        ? data_get($orderBuyder, 'purchase_order_outer_id')
                        : data_get($orderBuyder, 'buyder_id')
                    ,
                    'order_id' => data_get($orderBuyder, 'buyder_id'),
                    "buyder_sub_status" => BuyderStatusConstant::LIST_ORDER_STATUS_NAME[$supplierStatus] ?? '',
                    "buyder_sub_status_color" => BuyderStatusConstant::LIST_ORDER_STATUS_COLOR[$supplierStatus] ?? '',
                    "amount_difference" => round($orderBuyderSub->sum('product_total_cost')
                        - $orderBuyderSub->sum('platform_total_amount')
                        - $orderBuyderSub->sum('platform_domestic_post_fee'), 2)
                ];
                $orderBuyder->total = $total;
                $this->unsetOrderBuyder($orderBuyder);
                return $orderBuyder;
            });
            $order->order_buyders = $buyders;
            return $order;
        });
        return $orders;
    }

    private function unsetOrderBuyder($orderBuyder){
        unset($orderBuyder->order_id);
        unset($orderBuyder->order_buyder_id);
        unset($orderBuyder->buyder_id);
        unset($orderBuyder->supplier_status);
        unset($orderBuyder->created_at);
        unset($orderBuyder->buyder_no);
        unset($orderBuyder->source);
        unset($orderBuyder->purchase_order_outer_id);
        unset($orderBuyder->id);
        unset($orderBuyder->name);
        unset($orderBuyder->email);
        unset($orderBuyder->avatar);
        unset($orderBuyder->code);
        unset($orderBuyder->phone_number);
        unset($orderBuyder->delay_time);
        unset($orderBuyder->platform_total_amount);
        unset($orderBuyder->platform_domestic_post_fee);
        unset($orderBuyder->platform_goods_amount);
    }

    private function getOrderDetails($orders, $orderIds){
        $orderDetails = DB::table('order_details')
            ->whereIn('order_id', $orderIds)
            ->whereNull('order_details.deleted_at')
            ->select(
                'order_details.id as order_detail_id', 'order_details.link', 'order_details.quantity as product_number_customer',
                'order_details.is_from_addon', 'order_details.order_id', 'order_details.unit_price_cny as product_price',
                'order_details.source as order_detail_source', 'order_details.mp_id as product_mp_id',
                'order_details.mp_sku_id as product_mp_sku_id', 'order_details.name as product_name',
                'order_details.note_staff_latest', 'order_details.amount_cny',
                'order_details.image as product_image', 'order_details.classification as product_classification',
                'order_details.link as product_url', 'order_details.note as product_note',
                'items.sku_properties as product_sku_properties',
                'order_details.id as product_id', 'items.num_iid', 'items.seller_id'
            )
            ->leftJoin('items', 'items.detail_url', '=', 'order_details.link')
            ->get();
        $orderDetailIds = $orderDetails->pluck('order_detail_id')->toArray();

        $subQuantities = DB::table('order_buyder_subs')
            ->where('sourceable_type', 'App\\Models\\OrderDetail')
            ->whereIn('sourceable_id', $orderDetailIds)
            ->select('sourceable_id', DB::raw('SUM(quantity) as total_sub_quantity'))
            ->groupBy('sourceable_id')
            ->get()
            ->keyBy('sourceable_id');

        $assignedQuantities = DB::table('assign_support_orders')
            ->whereIn('order_detail_id', $orderDetailIds)
            ->select('order_detail_id', DB::raw('SUM(quantity) as total_assigned_quantity'))
            ->groupBy('order_detail_id')
            ->get()
            ->keyBy('order_detail_id');

        $noteCounts = DB::table('order_detail_notes')
            ->whereIn('order_detail_id', $orderDetailIds)
            ->where('subject_type', 'App\\Models\\Staff')
            ->select('order_detail_id', DB::raw('COUNT(*) as note_count'))
            ->groupBy('order_detail_id')
            ->get()
            ->keyBy('order_detail_id');

        $allNotes = DB::table('order_detail_notes')
            ->whereIn('order_detail_id', $orderDetailIds)
            ->where('subject_type', 'App\\Models\\Staff')
            ->select('id', 'order_detail_id', 'content', 'subject_type', 'created_at', 'priority')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('order_detail_id');

        $latestNotes = $allNotes->map(fn($group) => $group->first());
        $orderDetails->transform(function ($detail) use ($subQuantities, $assignedQuantities, $noteCounts, $latestNotes) {
            $total_sub_quantity = $subQuantities[$detail->order_detail_id]->total_sub_quantity ?? 0;
            $total_assigned_quantity = $assignedQuantities[$detail->order_detail_id]->total_assigned_quantity ?? 0;
            $detail->total_sub_quantity = $total_sub_quantity;
            $detail->total_assigned_quantity = $total_assigned_quantity;
            $product_number_available = $detail->product_number_customer - $total_sub_quantity - $total_assigned_quantity;
            $detail->product_number_available = $product_number_available;
            $detail->product_total_cost = round($product_number_available * $detail->product_price, 2);
            $detail->note_by_admin = $noteCounts[$detail->order_detail_id]->note_count ?? 0;
            $text_note_last = $detail->note_staff_latest ?? null;
            if (!$text_note_last) {
                $text_note_last = $latestNotes[$detail->order_detail_id]->content ?? null;
            }
            $detail->text_note_last = $text_note_last;
            $detail->text_note_priority = $latestNotes[$detail->order_detail_id]->priority ?? null;
            return $detail;
        });
        $orderDetailsGrouped = $orderDetails->groupBy('order_id');
        $orders->getCollection()->transform(function ($order) use ($orderDetailsGrouped) {
            $details = $orderDetailsGrouped[$order->order_id] ?? collect();
            $total_amount = $details->sum('amount_cny') ?? 0;
            $order->products = $details;
            $order->total_amount = $total_amount;
            $order->total_amount_all = $order->china_shipping_cost + $total_amount;
            $order->chat_info['offer_id'] = $details->pluck('num_iid')->first();
            $order->chat_info['seller_open_id'] = $details->pluck('seller_id')->first();
            $order->order_date = Carbon::parse($order->created_at)->format('d/m/Y');
            $order->order_status = OrderConstant::getConstantValue('STATUSES', $order->status);
            $order->order_status_color = OrderConstant::getConstantValue('STATUSES_COLOR', $order->status);
            return $order;
        });
        return $orders;
    }
}
