<?php

namespace App\Services;

use App\Actions\PlaceOrder\AssignSupportAction;
use App\Actions\PlaceOrder\CancelAssignSupportAction;
use App\Actions\PlaceOrder\CheckIsAssignedSupportAction;
use App\Actions\PlaceOrder\DetailAction;
use App\Actions\PlaceOrder\GetOrderSubsAction;
use App\Actions\PlaceOrder\ListAction;
use App\Actions\PlaceOrder\ListAssignSupportAction;
use App\Actions\PlaceOrder\ListPaymentAction;
use App\Actions\PlaceOrder\ReportAction;
use App\Actions\PlaceOrder\SupplierStatusListAction;
use App\Constants\Buyder\BuyderCancelReasonConstant;
use App\Constants\Buyder\BuyderStatusConstant;
use App\Constants\Buyder\OrderBuyderConstant;
use App\Constants\CurrencyConstant;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\ResourceConstant;
use App\Constants\TimeConstant;
use App\Events\PlaceOrder\PlaceOrderCreateLocalDataEvent;
use App\Factories\BasePlatformFactory;
use App\Helpers\AccountingHelper;
use App\Helpers\TimeHelper;
use App\Http\Requests\PlaceOrder\PaymentRequest;
use App\Http\Resources\ReportStatusResource;
use App\Jobs\Buyder\SyncOrderBuyderDetailData;
use App\Models\AssignSupportOrder;
use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderBuyder;
use App\Models\OrderDetail;
use App\Models\OrderPackage;
use App\Models\OrderPackageCancel;
use App\Models\SubOrderBuyder;
use App\Services\PlatformServices\BasePlatformService;
use App\Traits\PlaceOrder\GetPlatformStatusConstantNameTrait;
use App\Traits\PlaceOrder\ListActionTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ThuongDo\Buyder\src\Buyder;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class PlaceOrderService extends BaseService
{
    use GetPlatformStatusConstantNameTrait, ListActionTrait;

    public function getSupplierStatusList($params): array
    {
        $source = $params['source'] ?? 'taobao';
        $user_id = Auth::user()->id ?? 0;
        $key_cache = 'getSupplierStatusList_'.$source.'_'.$user_id;
        $list_status_cache = Cache::get($key_cache, '');
        $list_status = [];
        if ($list_status_cache) {
            $list_status = json_decode($list_status_cache);
        } else {
            $array_status = [];
            foreach (BuyderStatusConstant::LIST_ORDER_STATUS_NAME as $key => $item) {
                $array_status[] = $key;
                foreach (BuyderStatusConstant::LIST_ORDER_STATUS_COLOR as $k => $value) {
                    if ($key == $k) {
                        $list_status[] = [
                            'code' => $key,
                            'name' => $item,
                            'color' => $value,
                            'count' => 0
                        ];
                    }
                }
            }
            $orderBuyders = OrderBuyder::query()
                ->select(['supplier_status'])
                ->owned()
                ->whereSource($source)
                ->whereIn('supplier_status', $array_status)
                ->whereHas('orders', fn ($query) => $query->owned())
                ->get();
            if ($orderBuyders) {
                foreach ($list_status as &$status) {
                    foreach ($orderBuyders as $orderBuyder) {
                        if ($status['code'] == 'all') {
                            $status['count'] = $orderBuyders->count();
                        } else if ($status['code'] == $orderBuyder->supplier_status) {
                            $status['count'] ++;
                        }
                    }
                }
                unset($status);
            }
            Cache::put($key_cache, json_encode($list_status), now()->addMinutes(10));
        }

        return $list_status;
    }

    public function getSupplierSubStatusList($params = []): array
    {
        $list_status = [];
        foreach (BuyderStatusConstant::LIST_ORDER_STATUS_NAME as $key => $item) {
            foreach (BuyderStatusConstant::LIST_ORDER_STATUS_COLOR as $k => $value) {
                if ($key == $k) {
                    $list_status[$key] = [
                        'code' => $key,
                        'name' => $item,
                        'color' => $value,
                        'count' => 0
                    ];
                }
            }
        }
        $source = $params['source'] ?? 'taobao';
        $assignSupportOrders = AssignSupportOrder::query()
            ->whereHas('orderDetail', fn($query) => $query->where('source', $source))
            ->whereHas('orderBuyder', fn($query) => $query->where('source', $source))
            ->with('orderBuyder')
            ->assigned()
            ->get();
        foreach ($list_status as &$status) {
            foreach ($assignSupportOrders as $assignSupportOrder) {
                if (!empty($assignSupportOrder->orderBuyder)) {
                    if ($status['code'] == $assignSupportOrder->orderBuyder->supplier_status) {
                        $status['count'] ++;
                    }
                }
            }
        }
        unset($status);
        $list_status['all']['count'] = $assignSupportOrders->count();
        return array_values($list_status);
    }

    /**
     * Danh sách Lý do hủy đặt hàng
     *
     * @return array|null
     */
    public function getCancelReasons(): ?array
    {
        return BuyderCancelReasonConstant::getFormattedArrayConstant('CANCEL_REASON_LIST');
    }

    /**
     * Lấy danh sách trạng thái màn 'Danh sách thanh toán'
     *
     * @throws BindingResolutionException
     */
    public function getPaymentSupplierStatusList(): array
    {
        $action = app()->make(SupplierStatusListAction::class);

        return $action->handle(['method' => __FUNCTION__]);
    }

    /**
     * @throws BindingResolutionException
     */
    public function listPlaceOrder(): JsonResponse
    {
        if (! auth()->user()->can('place_order_list')) {
            return resError('Không có quyền truy cập', 403);
        }

        $action = new ListAction();
        $data = $action->handle([]);
        return resSuccessWithinData(new $this->_paginateResource($data));
    }

    private function formatListOrder($placeOrderList, $source)
    {
        $placeOrderList->getCollection()->transform(function ($order) use ($source) {
            $generalInfo = [
                'order_id' => data_get($order, 'id'),
                'order_code' => data_get($order, 'code'),
                'order_date' => Carbon::parse($order->date_purchased)->format('d/m/Y'),
                'order_status' => OrderConstant::getConstantValue('STATUSES', $order->status),
                'order_number_packages' => $order->packages->count(),
                'organization_id' => data_get($order, 'organization_id'),
            ];

            $orderDetails = $order->orderDetail ?? [];
            $products = [];
            $orderBuyders = $order->orderBuyerSub ?? [];
            $order_detail_ids = [];
            foreach ($orderBuyders as &$orderBuyder) {
                $order_detail_ids[] = $orderBuyder['sourceable_id'];
                $orderBuyder->user_code = $orderBuyder->userStaff->code;
                $orderBuyder->order_buyder_date = Carbon::parse($orderBuyder->created_at)->format('d/m/Y');
                $orderBuyder->buyder_sub_status = BuyderStatusConstant::getConstantValue(
                    'SUB_BUYDER_ORDER_STATUSES',
                    data_get($orderBuyder, 'buyder_status')
                );
                $orderBuyder->buyder_sub_status_color = BuyderStatusConstant::getConstantValue(
                    'SUB_BUYDER_ORDER_STATUS_COLORS',
                    data_get($orderBuyder, 'buyder_status')
                );
            }
            unset($orderBuyder);
            foreach ($orderDetails as $orderDetail) {
                $productPrice = data_get($orderDetail, 'unit_price_cny');
                $item = $orderDetail->item;
                $product_number_customer = data_get($orderDetail, 'quantity');
                foreach ($orderBuyders as &$orderBuyder) {
                    if (
                        $orderBuyder->order_id == $orderDetail->order_id
                        && $orderBuyder->sourceable_id == $orderDetail->id
                    ) {
                        $orderBuyder->product_url = data_get($orderDetail, 'link');
                        $orderBuyder->product_image = data_get($orderDetail, 'image');
                        $orderBuyder->product_name = data_get($orderDetail, 'name');
                        $orderBuyder->product_cost = data_get($orderDetail, 'unit_price_cny');
                        $orderBuyder->product_total_cost = $orderBuyder->product_cost
                            * $orderBuyder->quantity;
                    }

                    unset($orderBuyder);
                }
                if (!in_array(data_get($orderDetail, 'id'), $order_detail_ids)) {
                    $products[] = [
                        'order_detail_id' => data_get($orderDetail, 'id'),
                        'order_detail_source' => data_get($orderDetail, 'source'),
                        'product_mp_id' => data_get($orderDetail, 'mp_id'),
                        'product_mp_sku_id' => data_get($orderDetail, 'mp_sku_id'),
                        'product_id' => data_get($orderDetail, 'id'),
                        'product_name' => data_get($orderDetail, 'name'),
                        'product_image' => data_get($orderDetail, 'image'),
                        'product_classification' => data_get($orderDetail, 'classification'),
                        'product_url' => data_get($orderDetail, 'link'),
                        'product_note' => data_get($orderDetail, 'note'),
                        'product_number_customer' => $product_number_customer,
                        'product_number_available' => $product_number_customer,
                        'product_price' => $productPrice,
                        'product_total_cost' => round($product_number_customer * $productPrice, 2),
                        'product_sku_properties' => data_get($item, 'sku_properties', []),
                    ];
                }
            }
            $orderBuyderFormat = [];
            foreach ($orderBuyders as $buyer) {
                $orderBuyderFormat[$buyer->order_buyder_outer_id][] = $buyer;
            }

            return array_merge($generalInfo, [
                'products' => !empty($products) ? $products : [],
                'order_buyders' => $orderBuyderFormat,
            ]);
        });

        return $placeOrderList;
    }

    private function listOrder1688(){

    }

    private function listOrderTaoBao(){

    }

    private function buildQuery($request): Builder
    {
        $source = $request['source'] ?? BuyderServiceConstant::SOURCE_TAOBAO;
        $orderBuyderStatus = $request['order_buyder_status'] ?? '';
        $query = Order::query();
        $query->withoutGlobalScopes();
        $query->whereNotNull('date_purchased');
        if (!empty($request['staff_id'])) {
            $query->where('staff_order_id', $request['staff_id']);
        }

        if (!empty($request['order_code'])) {
            $query->where('code', $request['order_code']);
        }

        if (!empty($request['order_buyder_no'])) {
            $orderBuyderNo = $request['order_buyder_no'];
            $query->whereHas('orderBuyders', fn($q) => $q->where('buyder_no', $orderBuyderNo))
                ->orWhereHas('orderBuyders', fn($q) => $q->where('purchase_order_outer_id', $orderBuyderNo));
        }

        if (!empty($request['from'])) {
            $query->where('date_purchased', '>=', $request['from'] . ' 00:00:00');
        }

        if (!empty($request['to'])) {
            $query->where('date_purchased', '<=', $request['to'] . ' 23:59:59');
        }

        if (!empty($orderBuyderStatus)) {
            $query->whereHas('orderBuyders', fn($q) => $q->where('supplier_status', $orderBuyderStatus));
        }

        $query->with(['packages' => function ($packages) {

        }]);

        $query->with(['orderDetail' => function ($orderDetail) use ($source) {
            $orderDetail->select(
                'id', 'order_id', 'source', 'name',
                'link', 'image', 'note', 'classification',
                'unit_price_cny', 'quantity', 'packages_number',
                'amount_cny', 'supplier_id', 'mp_id', 'mp_sku_id'
            );
            if ($source) {
                $orderDetail->where('source', $source);
            }
        }]);
        $query->with(['subOrderBuyders' => function ($subOrderBuyders) {
            $subOrderBuyders->with('userStaff');
        }]);
        return $query;
    }


    /**
     * @throws Exception
     */
    public function placeOrder(array $data): array
    {
        $source = collect(data_get($data, 'data'))
            ->pluck('order_detail_source')
            ->unique()
            ->first();
        $service = new BasePlatformService(BasePlatformFactory::createBuyerService($source));
        if ($source == 'taobao') {
            $response = $this->processDataTaoBaoBeforeCreateOrder($data, $service, $source);
        } else {
            $response = $this->processData1688BeforeCreateOrder($data, $service, $source);
        }
        return $response;
    }

    private function processData1688BeforeCreateOrder($params, $service, $platform): array
    {
        $source = $params['source'];
        // dữ liệu đặt hàng
        $data = $params['data'];
        $listOrder = data_get($params, 'list_order', []);
        // danh sách order_details được user gửi lên
        $orderDetailIds = Arr::pluck($data, 'order_detail_id');
        $assignSupportIds = Arr::pluck($data, 'assign_support_id');
        $groupByColumn = $source == 'place_order_list' ? 'supplier_id' : 'mp_id';

        $orderDetailList = OrderDetail::query()
            ->with(['order', 'supplier', 'item'])
            ->whereIn('id', $orderDetailIds)->get()
            ->transform(function ($orderDetail, $index) use ($assignSupportIds, $data) {
                $orderDetail->offsetSet('assign_support_id', $assignSupportIds[$index]);
                $orderDetail->offsetSet(
                    'quantity_submit',
                    Arr::first($data, fn($d) => $d['order_detail_id'] == $orderDetail->id)['quantity']
                );
                $orderDetail->offsetSet('amount_cny', $orderDetail->unit_price_cny * $data[$index]['quantity']);
                $minOrderQuantity = 1;
                $orderDetail->offsetSet('min_order_quantity', $minOrderQuantity);

                return $orderDetail;
            })
            ->groupBy($groupByColumn);
        foreach ($listOrder as &$item) {
            if (empty($item['category_id'])) {
                $category = $item['category'] ?? null;
                if ($category) {
                    $checkExits = Category::query()->where('name', $category)->first();
                    if ($checkExits) {
                        return [
                            'code' => 500,
                            'message' => 'Danh mục [ ' . $category . ' ] đã trùng trong hệ thống, vui lòng chọn lại',
                        ];
                    }
                    $checkExits = Category::query()->create(['name' => $category, 'organization_id' => 1]);
                    $item['category_id'] = $checkExits->id;
                    unset($item['category']);
                }
            }
        }
        unset($item);
        $buyderInputData = $this->getBuyderInputData(
            $orderDetailList,
            data_get($params, 'receiver'),
            $listOrder
        );
        return $this->createOrderSingerSupplier1688($buyderInputData, $service, $source, $platform);
    }

    private function processData1688BeforePreviewOrder($params): array
    {
        $source = $params['source'];
        // dữ liệu đặt hàng
        $data = $params['data'];
        $listOrder = data_get($params, 'list_order', []);
        // danh sách order_details được user gửi lên
        $orderDetailIds = Arr::pluck($data, 'order_detail_id');
        $assignSupportIds = Arr::pluck($data, 'assign_support_id');
        $groupByColumn = $source == 'place_order_list' ? 'supplier_id' : 'mp_id';

        $orderDetailList = OrderDetail::query()
            ->with(['order', 'supplier', 'item'])
            ->whereIn('id', $orderDetailIds)->get()
            ->transform(function ($orderDetail, $index) use ($assignSupportIds, $data) {
                $orderDetail->offsetSet('assign_support_id', $assignSupportIds[$index]);
                $orderDetail->offsetSet(
                    'quantity_submit',
                    Arr::first($data, fn($d) => $d['order_detail_id'] == $orderDetail->id)['quantity']
                );
                $orderDetail->offsetSet('amount_cny', $orderDetail->unit_price_cny * $data[$index]['quantity']);
                $minOrderQuantity = 1;
                $orderDetail->offsetSet('min_order_quantity', $minOrderQuantity);

                return $orderDetail;
            })
            ->groupBy($groupByColumn);
        $buyderInputData = $this->getBuyderInputData(
            $orderDetailList,
            data_get($params, 'receiver'),
            $listOrder
        );

        $userId = Auth::user()->id_1688 ?? 0;
        $results = [];
        // Hiện tại chỉ check theo 1 sản phẩm thôi
        $buyderData = $buyderInputData[0] ?? null;

        if ($buyderData) {
            $value = [];
            foreach ($buyderData['cargoParamList'] as $list) {
                $key = $list['offerId'] . '-' . $list['specId'];

                if (!in_array($key, $value)) {
                    $value[] = $key;
                }
            }

            $buyderData['userId'] = $userId;
            $results[] = $buyderData;
        }
        if (!empty($results)) {
            return $results[0];
        }

        return $results;
    }

    private function createOrderSingerSupplier1688($params, $service, $source, $platform): array
    {
        $userId = Auth::user()->id_1688 ?? 0;
        $errors = [];
        $success = [];
        $messageArray = [];
        $errorsAttribute = [];
        foreach ($params as $buyderData) {
            $validateCargoParamListDuplicate = [];
            $value = [];
            foreach ($buyderData['cargoParamList'] as $list) {
                $key = $list['offerId'] .'-'.$list['specId'];
                if (in_array($key, $value)) {
                    $validateCargoParamListDuplicate[] = $key;
                } else {
                    $value[] = $key;
                }
            }
            if (!empty($validateCargoParamListDuplicate)) {
                foreach ($buyderData['list_order'] as $list_order) {
                    $errors[] = $list_order['order_code'];
                }
                foreach ($validateCargoParamListDuplicate as $valueItem) {
                    $valueItemStr = explode('-', $valueItem);
                    $item = Item::query()->where('num_iid', $valueItemStr[0])
                        ->whereNotNull('buyder_data_last_time_sync')
                        ->first();
                    if ($item) {
                        $sku_properties = $item->sku_properties ?? null;
                        if ($sku_properties) {
                            $sku_properties = json_decode($sku_properties);
                            $details = $sku_properties->details ?? null;
                            if ($details) {
                                foreach ($details as $detail) {
                                    if ($detail->mp_sku_id == $valueItemStr[1]) {
                                        $errorsAttribute[] = $detail->classification_cn ?: $detail->classification;
                                    }
                                }
                            }
                        }
                    }
                }
                if (!empty($errors)) {
                    return [
                        'code' => 500,
                        'message' => 'Các mã đơn hàng [' . implode(', ', $errors) . '] không thể tạo đơn do trùng thuộc tính sản phẩm [ ' . implode(', ', $errorsAttribute) . ' ]'
                    ];
                }
                break;
            }
            $buyderData['userId'] = $userId;
            $placeOrderResult = $service->createOrderPlatform($buyderData);
            if (data_get($placeOrderResult, 'code') == 200) {
                $results = (array)$placeOrderResult->data;
                foreach ($buyderData['list_order'] as $order) {
                    $success[] = data_get($order, 'order_code');
                }
                event(new PlaceOrderCreateLocalDataEvent(
                    $buyderData,
                    $results,
                    $source,
                    $platform

                ));
            } else {
                foreach ($buyderData['list_order'] as $order) {
                    $errors[] = data_get($order, 'order_code');
                    $messageArray[] = $placeOrderResult->message ?? '';
                }
            }
        }
        $success = array_unique($success);
        $errors = array_unique($errors);
        $message = '';
        if (!empty($errors)) {
            $message .= 'Các mã đơn hàng thất bại [' . implode(', ', $errors) .']. ';
        }
        if (!empty($success)) {
            $message .= 'Các mã đơn hàng thành công [' . implode(', ', $success) .']. ';
        }
        if (!empty($errors) && !empty($success)) {
            return [
                'code' => 200,
                'message' => $message
            ];
        }
        if (empty($errors) && !empty($success)) {
            return [
                'code' => 200,
                'message' => $message
            ];
        }
        if (!empty($errors) && empty($success)) {
            return [
                'code' => 500,
                'message' => $message . implode(', ', $messageArray)
            ];
        }
        return [
            'code' => 200,
            'message' => ''
        ];
    }

    private function createOrderMultiSupplier1688($params, $service, $source, $platform)
    {
       $payloads = [
            'userId' => Auth::user()->id_1688 ?? 0,
            'orderSource' => '',
            'addressParam' => [],
            'list_order' => [],
            'cargoParamList' => [],
       ];
       foreach ($params as $param) {
           $payloads['cargoParamList'][] = $param['cargoParamList'];
           $payloads['list_order'][] = $param['list_order'];
           $payloads['orderSource'] = $param['orderSource'];
           $payloads['addressParam'] = $param['addressParam'];
       }
        $payloads['list_order'] = array_merge(...$payloads['list_order']);
        $payloads['cargoParamList'] = array_merge(...$payloads['cargoParamList']);
        foreach ($payloads['cargoParamList'] as &$list) {
            $list['sellerOpenId'] = $list['sellerOrderNumber'];
        }
        $response = $service->createManyOrderPlatform($payloads);
    }

    private function getBuyderInputData(Collection $orderDetailList, array $submitReceiver, array $listOrder): array
    {
        $mutualData = [
            'orderSource' => OrderBuyderConstant::HANGVE,
            'addressParam' => [
                'address' => data_get($submitReceiver, 'address'),
                'mobile' => data_get($submitReceiver, 'mobile_phone'),
                'fullName' => data_get($submitReceiver, 'name'),
                'postCode' => data_get($submitReceiver, 'zip'),
                'areaText' => data_get($submitReceiver, 'state'),
                'cityText' => data_get($submitReceiver, 'city'),
                'provinceText' => data_get($submitReceiver, 'district'),
                'checked' => data_get($submitReceiver, 'checked', false),
            ]
        ];
        $placeOrderData = $orderDetailList->map(function ($orderDetailItem) use ($mutualData, $orderDetailList, $listOrder) {
            $cargoParamList = $orderDetailItem->map(fn($orderDetail) => ([
                'localOrderId' => $orderDetail->order->id,
                'orderDetailId' => data_get($orderDetail, 'id'),
                'assignSupportId' => data_get($orderDetail, 'assign_support_id'),
                'offerId' => data_get($orderDetail, 'mp_id'),
                'specId' => data_get($orderDetail, 'mp_sku_id'),
                'quantity' => data_get($orderDetail, 'quantity_submit'),
                'sellerOrderNumber' => data_get($orderDetail->supplier, 'code')
            ]))->toArray();

            $mutualData = array_merge($mutualData, [
                'product_name' => data_get($orderDetailItem->first(), 'name')
            ]);
            $orderId = collect($cargoParamList)->pluck('localOrderId')->toArray();
            return array_merge($mutualData, [
                'cargoParamList' => $cargoParamList,
                'minOrderQuantity' => $orderDetailItem->first()->min_order_quantity,
                'realPlacedQuantity' => collect($cargoParamList)->sum('quantity'),
                'list_order' => array_filter($listOrder, function($a) use ($orderId) {
                    return in_array($a['order_id'], $orderId);
                })
            ]);
        });

        return array_values($placeOrderData->toArray());
    }

    private function checkMinOrderQuantity(array $buyderInputData): array
    {
        $failedProducts = [];

        Arr::each($buyderInputData, function ($data) use (&$failedProducts) {
            $minOrderQuantity = data_get($data, 'minOrderQuantity');
            if (data_get($data, 'realPlacedQuantity') < $minOrderQuantity) {
                $failedProducts[] = data_get($data, 'product_name') . " ({$minOrderQuantity} sản phẩm)";
            }
        });

        return $failedProducts;
    }

    private function processDataTaoBaoBeforeCreateOrder($params, $service, $source): array
    {
        $data = $params['data'];
        $logisticDelayTime = data_get($params, 'delay_time');

        $orderDetailIds = Arr::pluck($data, 'order_detail_id');
        $assignSupportIds = Arr::pluck($data, 'assign_support_id');
        $orderDetailList = OrderDetail::query()
            ->with(['order', 'supplier'])
            ->whereIn('id', $orderDetailIds)->get()
            ->transform(function ($item, $index) use ($assignSupportIds, $data) {
                $item->offsetSet('assign_support_id', $assignSupportIds[$index]);
                $item->offsetSet(
                    'quantity_submit',
                    Arr::first($data, fn($d) => $d['order_detail_id'] == $item->id)['quantity']
                );
                $item->offsetSet('amount_cny', $item->unit_price_cny * $data[$index]['quantity']);
                return $item;
            });
        $mutualData = [
            'orderSource' => OrderBuyderConstant::HANGVE,
            'receiver' => $params['receiver'],
            'userId' => Auth::user()->id_taobao ?? 0,
            'delayTime' => $logisticDelayTime,
            'list_order' => $params['list_order']
        ];
        $rawBuyerInputData = $this->getBuyerTaoBaoInputData($orderDetailList, $mutualData);
        $dataFormat = $rawBuyerInputData;
        $payload = [];
        $list_order = $dataFormat['list_order'];
        $orderLineList = $dataFormat['orderLineList'];
        foreach ($list_order as $order) {
               if (empty($order['category_id'])) {
                    $category = $order['category'] ?? null;
                    if ($category) {
                        $checkExits = Category::query()->where('name', $category)->first();
                        if ($checkExits) {
                            return [
                                'code' => 500,
                                'message' => 'Danh mục [ ' . $category . ' ] đã trùng trong hệ thống, vui lòng chọn lại',
                            ];
                        }
                        $checkExits = Category::query()->create(['name' => $category, 'organization_id' => 1]);
                        $order['category_id'] = $checkExits->id;
                        unset($order['category']);
                    }
               }
            foreach ($orderLineList as $orderList) {
                if ($order['order_id'] == $orderList['localOrderId']) {
                    $payload[$order['order_id']]['orderSource'] = $dataFormat['orderSource'];
                    $payload[$order['order_id']]['receiver'] = $dataFormat['receiver'];
                    $payload[$order['order_id']]['delayTime'] = $dataFormat['delayTime'];
                    $payload[$order['order_id']]['userId'] = $dataFormat['userId'];
                    $payload[$order['order_id']]['list_order'] = $order;
                    $payload[$order['order_id']]['orderLineList'][] = $orderList;
                }
            }
        }
        $errors = [];
        $success = [];
        $sellerOrderNumber = [];
        $messageArray = [];
        foreach ($payload as $pay) {
            foreach ($pay['orderLineList'] as $list) {
                $sellerOrderNumber[$list['sellerOrderNumber']]['orderSource'] = $pay['orderSource'];
                $sellerOrderNumber[$list['sellerOrderNumber']]['receiver'] = $pay['receiver'];
                $sellerOrderNumber[$list['sellerOrderNumber']]['delayTime'] = $pay['delayTime'];
                $sellerOrderNumber[$list['sellerOrderNumber']]['userId'] = $pay['userId'];
                $sellerOrderNumber[$list['sellerOrderNumber']]['orderLineList'][] = $list;
                $sellerOrderNumber[$list['sellerOrderNumber']]['list_order'][] = $pay['list_order'];
                $sellerOrderNumber[$list['sellerOrderNumber']][] = $pay;
            }
        }
        foreach ($sellerOrderNumber as $seller) {
            $arrayCreateOrder['orderSource'] = $seller['orderSource'] ?? 'hangve.com';
            $arrayCreateOrder['receiver'] = $seller['receiver'] ?? [];
            $arrayCreateOrder['delayTime'] = $seller['delayTime'] ?? 0;
            $arrayCreateOrder['userId'] = $seller['userId'] ?? 0;
            $arrayCreateOrder['orderLineList'] = $seller['orderLineList'] ?? [];
            if ($params['source'] == 'place_order_assign_support') {
                $type = AssignSupportOrder::class;
            } else {
                $type = OrderDetail::class;
            }
            $validate = $this->validateExitOrderBuyerSub($seller['orderLineList'], $type);
            /*$validate = false;*/
            if ($validate) {
                foreach ($seller['list_order'] as $list_order) {
                    if ($list_order['order_id'] == $validate) {
                        $errors[] = $list_order['order_code'];
                    }

                }
                return [
                    'code' => 500,
                    'message' => 'Đơn hàng có mã '
                        . implode(',', $errors)
                        . ' đã được đặt. Vui lòng F5 lại trang sau 15s'
                ];
            }
            $results = $service->createOrderPlatform($arrayCreateOrder);
            if ($results && !empty($results->code)) {
                if ($results->code == 200) {
                    $response = (array)$results->data;
                    foreach ($seller['list_order'] as $list_order) {
                        $success[] = $list_order['order_code'];
                    }
                    event(new PlaceOrderCreateLocalDataEvent(
                        $seller,
                        $response,
                        $params['source'] ?? '',
                        $source
                    ));
                } else {
                    foreach ($seller['list_order'] as $list_order) {
                        $errors[] = $list_order['order_code'];
                    }
                    $messageArray[] = $results->message ?? '';
                }
            } else {
                foreach ($seller['list_order'] as $list_order) {
                    $errors[] = $list_order['order_code'];
                }
            }
        }
        $success = array_unique($success);
        $errors = array_unique($errors);
        $message = '';
        if (!empty($errors)) {
            $message .= 'Các mã đơn hàng thất bại [' . implode(', ', $errors) .']. ';
        }
        if (!empty($success)) {
            $message .= 'Các mã đơn hàng thành công [' . implode(', ', $success) .']. ';
        }
        if (!empty($errors) && !empty($success)) {
            return [
                'code' => 200,
                'message' => $message
            ];
        }
        if (empty($errors) && !empty($success)) {
            return [
                'code' => 200,
                'message' => $message
            ];
        }
        if (!empty($errors) && empty($success)) {
            return [
                'code' => 500,
                'message' => $message . implode(', ', $messageArray)
            ];
        }
        return [
            'code' => 200,
            'message' => ''
        ];
    }
    // hàm này trùng với hàm trên nhiều, nhưng cũ xử lý lỏ nên đành phải double code trước :D
    private function processDataTaoBaoBeforePreviewOrder($params): array
    {
        $data = $params['data'];
        $logisticDelayTime = data_get($params, 'delay_time');

        $orderDetailIds = Arr::pluck($data, 'order_detail_id');
        $assignSupportIds = Arr::pluck($data, 'assign_support_id');
        $orderDetailList = OrderDetail::query()
            ->with(['order', 'supplier'])
            ->whereIn('id', $orderDetailIds)->get()
            ->transform(function ($item, $index) use ($assignSupportIds, $data) {
                $item->offsetSet('assign_support_id', $assignSupportIds[$index]);
                $item->offsetSet(
                    'quantity_submit',
                    Arr::first($data, fn($d) => $d['order_detail_id'] == $item->id)['quantity']
                );
                $item->offsetSet('amount_cny', $item->unit_price_cny * $data[$index]['quantity']);
                return $item;
            });
        $mutualData = [
            'orderSource' => OrderBuyderConstant::HANGVE,
            'receiver' => $params['receiver'],
            'userId' => Auth::user()->id_taobao ?? 0,
            'delayTime' => $logisticDelayTime,
            'list_order' => $params['list_order']
        ];
        $rawBuyerInputData = $this->getBuyerTaoBaoInputData($orderDetailList, $mutualData);
        $dataFormat = $rawBuyerInputData;
        $payload = [];
        $list_order = $dataFormat['list_order'];
        $orderLineList = $dataFormat['orderLineList'];
        foreach ($list_order as $order) {
            if (empty($order['category_id'])) {
                $category = $order['category'] ?? null;
                if ($category) {
                    $checkExits = Category::query()->where('name', $category)->first();
                    if ($checkExits) {
                        return [
                            'code' => 500,
                            'message' => 'Danh mục [ ' . $category . ' ] đã trùng trong hệ thống, vui lòng chọn lại',
                        ];
                    }
                    $checkExits = Category::query()->create(['name' => $category, 'organization_id' => 1]);
                    $order['category_id'] = $checkExits->id;
                    unset($order['category']);
                }
            }
            foreach ($orderLineList as $orderList) {
                if ($order['order_id'] == $orderList['localOrderId']) {
                    $payload[$order['order_id']]['orderSource'] = $dataFormat['orderSource'];
                    $payload[$order['order_id']]['receiver'] = $dataFormat['receiver'];
                    $payload[$order['order_id']]['delayTime'] = $dataFormat['delayTime'];
                    $payload[$order['order_id']]['userId'] = $dataFormat['userId'];
                    $payload[$order['order_id']]['list_order'] = $order;
                    $payload[$order['order_id']]['orderLineList'][] = $orderList;
                }
            }
        }
        $sellerOrderNumber = [];
        foreach ($payload as $pay) {
            foreach ($pay['orderLineList'] as $list) {
                $sellerOrderNumber[$list['sellerOrderNumber']]['orderSource'] = $pay['orderSource'];
                $sellerOrderNumber[$list['sellerOrderNumber']]['receiver'] = $pay['receiver'];
                $sellerOrderNumber[$list['sellerOrderNumber']]['delayTime'] = $pay['delayTime'];
                $sellerOrderNumber[$list['sellerOrderNumber']]['userId'] = $pay['userId'];
                $sellerOrderNumber[$list['sellerOrderNumber']]['orderLineList'][] = $list;
                $sellerOrderNumber[$list['sellerOrderNumber']]['list_order'][] = $pay['list_order'];
                $sellerOrderNumber[$list['sellerOrderNumber']][] = $pay;
            }
        }
        $arrayCreateOrder = [];
        foreach ($sellerOrderNumber as $seller) {
            $arrayCreateOrder['orderSource'] = $seller['orderSource'] ?? 'hangve.com';
            $arrayCreateOrder['receiver'] = $seller['receiver'] ?? [];
            $arrayCreateOrder['delayTime'] = $seller['delayTime'] ?? 0;
            $arrayCreateOrder['userId'] = $seller['userId'] ?? 0;
            $arrayCreateOrder['orderLineList'] = $seller['orderLineList'] ?? [];
        }
        return $arrayCreateOrder;
    }


    private function validateExitOrderBuyerSub($orderLineList, $type)
    {
        foreach ($orderLineList as $list) {
            if (SubOrderBuyder::query()
                ->where('order_id', $list['localOrderId'])
                ->where('sourceable_id', $list['orderDetailId'])
                ->where('sourceable_type', $type)
                ->exists()) {
                return $list['localOrderId'];
            }
        }
        return false;
    }

    private function getBuyerTaoBaoInputData($orderDetailList, $mutualData): array
    {
        $orderLineList = $orderDetailList->map(function ($orderDetail, $index) use (&$purchaseAmount) {
            return [
                'localOrderId' => $orderDetail->order->id,
                'orderDetailId' => data_get($orderDetail, 'id'),
                'assignSupportId' => data_get($orderDetail, 'assign_support_id'),
                'itemId' => data_get($orderDetail, 'mp_id'),
                'skuId' => data_get($orderDetail, 'mp_sku_id'),
                'orderLineNo' => ++$index,
                'quantity' => data_get($orderDetail, 'quantity_submit'),
                'price' => data_get($orderDetail, 'unit_price_cny'),
                'currency' => CurrencyConstant::CNY,
                'purchaseAmount' => $orderDetail->amount_cny * 100,
                'sellerOrderNumber' => data_get($orderDetail->supplier, 'code')
            ];
        })->toArray();

        return array_merge($mutualData, [
            'orderLineList' => $orderLineList
        ]);
    }

    private function checkCoincidenceItemSku($rawBuyerInputData): array
    {
        $orderLineList = data_get($rawBuyerInputData, 'orderLineList');
        $check = [];
        foreach ($orderLineList as $data) {
            $checkExist = Arr::find($check, fn ($c) => (
                data_get($c, 'itemId') == data_get($data, 'itemId')
                && data_get($c, 'skuId') == data_get($data, 'skuId')
            ));

            if (is_null($checkExist)) {
                $check[] = $data;
            }
        }

        return count($orderLineList) == count($check)
            ? $rawBuyerInputData
            : [];
    }

    /**
     * Hủy đặt hàng
     *
     * @param array $data
     * @return array[]
     * @throws Exception
     */
    public function cancelOrder(array $data): ?array
    {
        $platformSource = collect($data['data'])->pluck('platform_source')->unique()->first();
        $service = new BasePlatformService(BasePlatformFactory::createBuyerService($platformSource));
        if ($platformSource == '1688') {
            $response = $this->processData1688BeforeCancelOrder($data, $service);
        } else {
            $response = $this->processDataTaoBaoBeforeCancelOrder($data, $service);
        }
        return $response;
    }

    private function processData1688BeforeCancelOrder($data, $service): array
    {
        $lists = $data['data'] ?? [];
        $payload['userId'] = Auth::user()->id_1688 ?? 0;
        $payload['cancelReason'] = $data['cancel_type'] ?? 0;
        $payload['remark'] = 'string';
        $errors = [];
        $success = [];
        $listOrders = [];
        foreach ($lists as $order) {
            $listOrders[$order['buyder_id']] = $order;
        }
        if (!empty($listOrders)) {
            foreach ($listOrders as $listOrder) {
                $payload['orderId'] = $listOrder['buyder_id'] ?? '';
                $orderBuyer = OrderBuyder::query()->where('buyder_id',$payload['orderId'])->first();
                if ($orderBuyer->is_xadal == 1) {
                    $errors[] = $payload['orderId'];
                    continue;
                }
                $payload['webSite'] = $listOrder['platform_source'] ?? '1688';
                $response = $service->cancelOrderPlatform($payload);
                Log::info('Cancel Order 1688 ' . $response->message ?? '');
                if ($response && !empty($response->code)) {
                    OrderPackage::query()->where('buyder_no', $orderBuyer->buyder_no)
                        ->forceDelete();
                    $this->updateCloseReason($listOrder['buyder_sub_ids'], $payload['cancelReason']);
                    if ($response->code == 200) {
                        $success[] = $payload['orderId'];
                    } else {
                        $errors[] = $payload['orderId'];
                    }
                } else {
                    $errors[] = $payload['orderId'];
                }
            }
        }
        return [
            'errors' => $errors,
            'success' => $success,
        ];
    }

    private function processDataTaoBaoBeforeCancelOrder($params, $service): array
    {
        $cancel_type = $params['cancel_type'] ?? '';
        $payload['userId'] = Auth::user()->id_taobao ?? 0;
        $payload['cancelType'] = $cancel_type;
        $success = [];
        $errors = [];
        $payloads = [];
        foreach ($params['data'] as $format) {
            $payloads[$format['buyder_id']]['buyder_sub_ids'][] = $format['buyder_sub_ids'];
            $payloads[$format['buyder_id']]['platform_source'] = $format['platform_source'];
        }
        foreach ($payloads as $key => $param) {
            $orderId = $key ?? 0;
            $orderBuyer = OrderBuyder::query()->where('buyder_id', $orderId)->first();
            $payload['orderId'] = $orderBuyer->purchase_id ?? 0;
            if ($orderBuyer->is_xadal == 1) {
                $errors[] = $orderId;
                continue;
            }
            $subOrderIds = SubOrderBuyder::query()->select('buyder_sub_id')
                ->where('order_buyder_id', $orderBuyer->id)
                ->pluck('buyder_sub_id')
                ->toArray();
            $response = $service->cancelOrderPlatform($payload);

            Log::info('Cancel Order TaoBao ' . $response->message ?? '');
            if ($response && !empty($response->code)) {
                if ($response->code == 200) {
                    OrderPackage::query()->where('buyder_no', $orderBuyer->buyder_no)
                        ->update(['deleted_at' => date('Y-m-d H:i:s')]);
                    $success[] = $orderId;
                    $this->updateCloseReason($subOrderIds, $cancel_type);
                    dispatch(
                        new SyncOrderBuyderDetailData($orderBuyer, $payload['userId'], [])
                    )->delay(Carbon::now()->addSeconds(TimeConstant::QUEUE_MIN_DELAY_TIME))
                        ->onQueue('sync-order-platform');
                } else {
                    $errors[] = $orderId;
                }
            } else {
                $errors[] = $orderId;
            }
        }
        return [
            'errors' => $errors,
            'success' => $success,
        ];
    }

    public function updateCloseReason($subOrderIds, $cancelType){
        SubOrderBuyder::query()
            ->with(['assignSupportOrders', 'orderPackage', 'orderDetailSub'])
            ->whereIn('buyder_sub_id', $subOrderIds)
            ->each(function ($subOrderBuyder) use ($cancelType) {
                // Set null mã đặt hàng 'order_no' của đơn hàng hỗ trợ
                $sourceable = $subOrderBuyder->assignSupportOrders;
                if ($subOrderBuyder->sourceable_type == AssignSupportOrder::class) {
                    $sourceable->order_no = null;
                    $sourceable->status = $this->getOrderBuyderSubsClosedStatus($subOrderBuyder->source);
                    $sourceable->save();
                }
                $image = $subOrderBuyder->orderDetailSub->image;
                // tạo bản ghi tương ứng trong bảng 'order_package_cancels', rồi soft delete 'order_package'
                $orderPackage = $subOrderBuyder->orderPackage;
                if ($orderPackage) {
                    OrderPackageCancel::query()->create([
                        'order_package_id' => data_get($orderPackage, 'id'),
                        'bill_code' => data_get($orderPackage, 'bill_code'),
                        'customer_id' => data_get($orderPackage, 'customer_id'),
                        'staff_id' => data_get($orderPackage, 'staff_quotation_id'),
                        'type_cancel_value' => PackageConstant::TYPE_CANCEL_ORDER_CANCEL,
                        'staff_management_id' => data_get($orderPackage, 'staff_quotation_id'),
                        'image' => $image,
                        'status' => PackageConstant::STATUS_CANCEL_DELETE
                    ]);
                    $orderPackage->forceDelete();
                }

                // update order_buyder_subs.close_reason
                $subOrderBuyder->update([
                    'close_reason' => $cancelType,
                    'buyder_status' => $this->getOrderBuyderSubsClosedStatus($subOrderBuyder->source)
                ]);
            });
    }

    public function getAllOrderOrderBuyders(string $orderId)
    {
        $order = Order::query()->findOrFail($orderId);

        return $order->orderBuyders ?? [];
    }

    public function getAllOrderSubs(string $orderId): Collection
    {
        $action = new GetOrderSubsAction();

        return $action->handle([
            'type' => 'order',
            'id' => $orderId
        ]);
    }

    public function getAllOrderDetailSubs(string $orderDetailId): Collection
    {
        $action = new GetOrderSubsAction();

        return $action->handle([
            'type' => 'orderDetail',
            'id' => $orderDetailId
        ]);
    }

    public function getAssignSupportList(): LengthAwarePaginator
    {
        $action = new ListAssignSupportAction();
        return $action->handle([]);
    }

    public function assignSupport(array $data): void
    {
        $action = new AssignSupportAction();
        $action->handle($data);
    }

    public function assignSupportCancel(array $assignSupportIds): JsonResponse
    {
        $action = new CancelAssignSupportAction();
        return $action->handle($assignSupportIds);
    }

    public function checkIsAssignedSupport(array $data): JsonResponse
    {
        $action = new CheckIsAssignedSupportAction();

        return $action->handle($data);
    }

    public function getPlaceOrderDetail(string $id): array
    {
        $action = new DetailAction();

        return $action->handle(['id' => $id]);
    }

    public function getOrderReport(): LengthAwarePaginator
    {
        $action = new ReportAction();

        return $action->handle([]);
    }

    public function getPaymentList(): JsonResponse
    {
        $action = new ListPaymentAction();
        $data = $action->handle([]);

        return resSuccessWithinData(new $this->_paginateResource($data));
    }

    /**
     * @throws Exception
     */
    public function handlePayment(array $data): array
    {
        $source = data_get($data, 'platform_source');
        $service = new BasePlatformService(BasePlatformFactory::createBuyerService($source));
        if ($source == 'taobao') {
            $response = $this->paymentTaoBao($data, $service);
        } else {
            $response = $this->payment1688($data, $service);
        }
        return $response;
    }

    private function payment1688($data, $service): array
    {
        $payment['userId'] = Auth::user()->id_1688 ?? 0;
        $payment['orderIds'] = $data['buyder_ids'] ?? [];
        $response = $service->paymentOrderPlatform($payment);
        Log::info('Payment Order 1688 ' . json_encode($response));
        if ($response && !empty($response->code)) {
            if ($response->code == 200) {
                return [
                    'code' => 200,
                    'message' => 'Thanh toán thành công!',
                    'data' => $response->data ?? []
                ];
            }
            return [
                'code' => 500,
                'message' => $response->message ?? 'Thanh toán thất bại!'
            ];
        }
        return [
            'code' => 500,
            'message' => 'Thanh toán thất bại!'
        ];
    }

    private function paymentTaoBao($data, $service): array
    {
        $payment['userId'] = $data['userId'] ?? Auth::user()->id_taobao;
        $payment['orderIds'] = $data['buyder_ids'] ?? [];
        $response = $service->paymentOrderPlatform($payment);
        Log::info('Payment Order TaoBao ' . json_encode($response));
        if ($response && !empty($response->code)) {
            if ($response->code == 200) {
                return [
                    'code' => 200,
                    'message' => 'Thanh toán thành công!'
                ];
            }
            return [
                'code' => 500,
                'message' => $response->message ?? 'Thanh toán thất bại!'
            ];
        }
        return [
            'code' => 500,
            'message' => 'Thanh toán thất bại!'
        ];
    }

    /**
     * @throws Exception
     */
    public function connectChat(array $data): array
    {
        $source = data_get($data, 'source');
        $service = new BasePlatformService(BasePlatformFactory::createBuyerService($source));
        $data['userId'] = Auth::user()->id_1688 ?? 0;
        $results = $service->connectChat($data);
        if ($results) {
            if ($results->code == 200) {
                return [
                    'code' => $results->code,
                    'data' => $results->data->wangwangUrl ?? ''
                ];
            }
            return [
                'code' => $results->code,
                'data' => []
            ];
        }
        return [
            'code' => 500,
            'data' => []
        ];
    }

    public function previewOrder($data)
    {
        $source = collect(data_get($data, 'data'))
            ->pluck('order_detail_source')
            ->unique()
            ->first();
        $service = new BasePlatformService(BasePlatformFactory::createBuyerService($source));
        if ($source == 'taobao') {
            $data = $this->processDataTaoBaoBeforePreviewOrder($data);
        } else {
            $data = $this->processData1688BeforePreviewOrder($data);
        }
        $result =  $service->previewOrderPlatform($data);

        if($result->code == 500) {
            $text = $result->message;
            if($source == 'taobao') {
                preg_match('/itemId:(\d+)/', $text, $matches);
                $result = $matches ? [$matches[1]] : null;
            } else {
                if (preg_match('/\[(.*?)\]/', $text, $matches)) {
                    $result = $matches ? array_map('trim', explode(',', $matches[1])) : '';
                } else {
                    $result = $text;
                }
            }
            return $result;
        }

        return '';
    }
}
