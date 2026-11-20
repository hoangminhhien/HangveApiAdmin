<?php

namespace App\Http\Controllers;

use App\Abstracts\AbstractBasePlatformService;
use App\Constants\Buyder\BuyderStatusConstant;
use App\Constants\OrderBuyderConstant;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\ResourceConstant;
use App\Constants\StatusConstant;
use App\Factories\BasePlatformFactory;
use App\Http\Requests\PlaceOrder\AssignSupportCancelRequest;
use App\Http\Requests\PlaceOrder\AssignSupportRequest;
use App\Http\Requests\PlaceOrder\CancelOrderRequest;
use App\Http\Requests\PlaceOrder\CheckIsAssignedSupportRequest;
use App\Http\Requests\PlaceOrder\ConnectChatRequest;
use App\Http\Requests\PlaceOrder\PaymentRequest;
use App\Http\Requests\PlaceOrder\PlaceOrderRequest;
use App\Http\Resources\PaginateJsonResource;
use App\Jobs\Buyder\SyncCodePo;
use App\Models\AssignSupportOrder;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderBuyder;
use App\Models\OrderDetail;
use App\Models\OrderPackage;
use App\Models\OrderPackageCancel;
use App\Models\SubOrderBuyder;
use App\Services\CustomerService;
use App\Services\FcmTokenService;
use App\Services\OrderBuyderService;
use App\Services\OrderPackageService;
use App\Services\OrderService;
use App\Services\PlatformServices\BasePlatformService;
use Exception;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\RequestOptions;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use App\Services\PlaceOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ThuongDo\Buyder\src\Actions\Payment\PaymentActionTrait;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class PlaceOrderController extends Controller
{
    use PaymentActionTrait;
    public function __construct(PlaceOrderService $service)
    {
        $this->_service = $service;
    }

    protected function getAttributes(): array
    {
        return [
            "data" => "Dữ liệu",
            'data.*.order_detail_id' => 'Sản phẩm',
            'data.*.quantity' => 'Số lượng sản phẩm',
        ];
    }

    /**
     * @throws BindingResolutionException
     */
    public function getSupplierStatusList(Request $request): JsonResponse
    {
        $supplierStatusList = $this->_service->getSupplierStatusList($request->all());

        return resSuccessWithinData($supplierStatusList);
    }

    /**
     * @throws BindingResolutionException
     */
    public function getSupplierSubStatusList(Request $request): JsonResponse
    {
        $supplierSubStatusList = $this->_service->getSupplierSubStatusList($request->all());
        return resSuccessWithinData($supplierSubStatusList);
    }

    public function getPaymentSupplierStatusList(Request $request): JsonResponse
    {
        $result = $this->_service->getSupplierStatusList($request->all());

        return resSuccessWithinData($result);
    }

    /**
     * @throws Exception
     */
    public function syncSkuId(Request $request): JsonResponse
    {
        $order_detail_id = $request->order_id ?? 0;
        $orderDetail = OrderDetail::query()
            ->where('id', $order_detail_id)
            ->get();
        $payload = [];
        foreach ($orderDetail as $item) {
            $source = $item->source ?? '';
            $service = new BasePlatformService(BasePlatformFactory::createBuyerService($source));
            $link = $item->link ?? '';
            $itemId = $this->getPlatformIdFromUrl($link) ?? $this->get1688IdFromRouteParam($link);
            if ($source == 'taobao') {
                $skuId = getTaoBaoSkuIdFromRouteParam($link);
                /*$payload = [
                    'itemId' => $itemId,
                ];
                $skuId = getTaoBaoSkuIdFromRouteParam($link);
                if (!$skuId) {
                    $skuId = $item->mp_sku_id ?? 0;
                }
                $results = $service->getProductPlatformDetail($payload);
                if ($results && $results['code'] == 200) {
                    $data = $results['data'];
                    $buyderData = json_decode(data_get($data, 'buyder_data'));
                    $skuList = $buyderData->skuList ?? [];
                    $mp_id = $buyderData->mpId ?? '';
                    $mp_sku_id = null;
                    if (sizeof($skuList) == 1 && empty($skuList[0]->properties)) {
                        $mp_sku_id = $skuList[0]->mpSkuId;
                    }
                    if (!empty($skuList)) {
                        foreach ($skuList as $i) {
                            if ($skuId && $i->skuId == $skuId) {
                                $mp_sku_id = $i->mpSkuId;
                                break;
                            }
                        }
                    }
                    if ($mp_id) {
                        $item->mp_id = $mp_id;
                    }
                    if ($mp_sku_id) {
                        $item->mp_sku_id = $mp_sku_id;
                    }
                    $item->save();
                }*/

                if ($itemId) {
                    $item->mp_id = $itemId;
                }
                if ($skuId) {
                    $item->mp_sku_id = $skuId;
                }
                $item->save();
            }
            if ($source == '1688') {
                $payload['offerId'] = $itemId;
                $payload['is_not_sync_product_detail'] = true;
                $response = $service->getProductPlatformDetail($payload);
                if ($response && $response['code'] == 200) {
                    $results = $response['data'];
                    $attributes = explode(';',$item->classification);
                    $productSkuInfos = $results->productSkuInfos ?? [];
                    $specId = '';
                    foreach ($productSkuInfos as $productSkuInfo) {
                        $arraySkuAttribute = [];
                        foreach ($productSkuInfo->skuAttributes as $skuAttribute) {
                            $arraySkuAttribute[] = $skuAttribute->value;
                        }
                        $arraySkuAttributeStr = implode(';', $arraySkuAttribute);
                        if ($arraySkuAttributeStr == $item->classification) {
                            $specId = $productSkuInfo->specId;
                            break;
                        }
                        $result = empty(array_diff($attributes, $arraySkuAttribute)) && empty(array_diff($arraySkuAttribute, $attributes));
                        if ($result) {
                            $specId = $productSkuInfo->specId;
                            break;
                        }
                    }
                    $product = $service->createOrUpdateProductDetail($results);
                    $item->mp_id = $itemId;
                    $item->link = $product->detail_url ?? "https://detail.1688.com/offer/{$itemId}.html";
                    $item->mp_sku_id = $specId;
                    $item->save();
                    Log::info('$specId ' . $specId);
                }
            }
        }
        return resSuccessWithinData($orderDetail);
    }

    public function cancelOrderDetail(Request $request): JsonResponse
    {
        $order_detail_ids = $request->order_detail_ids ?? [];
        if (!empty($order_detail_ids)) {
            OrderDetail::query()->whereIn('id', $order_detail_ids)->update(['is_display' => 0]);
        }
        return resSuccess();
    }

    private function getPlatformIdFromUrl(string $url): ?string
    {
        return getPlatformIdFromUrl($url);
    }

    private function get1688IdFromRouteParam(string $url): ?string
    {
        return get1688IdFromRouteParam($url);
    }

    /**
     * @throws BindingResolutionException
     */
    public function listPlaceOrder(Request $request, OrderBuyderService $orderBuyderService): JsonResponse
    {
        return $this->_service->listPlaceOrder();
        $results = $orderBuyderService->listOrderBuyder($request->all());
        return response()->json([
            'code' => 200,
            'message' => 'Success',
            'data' => [
                'items' => $results->items(),
                'pagination' => [
                    'total' => $results->total(),
                    'count' => $results->count(),
                    'per_page' => $results->perPage(),
                    'current_page' => $results->currentPage(),
                    'total_pages' => $results->lastPage(),
                ],
            ]
        ]);
    }

    public function getCancelReasons(): JsonResponse
    {
        $data = $this->_service->getCancelReasons();

        return resSuccessWithinData($data);
    }

    /**
     * @throws Exception
     */
/*PlaceOrderRequest*/
    public function placeOrder(PlaceOrderRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $data = array_merge($request->validated(), ['list_order' => data_get($request, 'list_order', [])]);
            $results = $this->_service->placeOrder($data);
            if ($results['code'] == 500) {
                return resError($results['message']);
            }
            DB::commit();
            return resSuccess($results['message']);
        } catch (Exception $exception) {
            DB::rollBack();
            return resError(
                $exception->getMessage() . ', line ' .
                $exception->getLine() . ', file ' .
                $exception->getFile());
        }
    }

    /**
     * @throws BindingResolutionException
     */
    public function updateOrderBuyerTaoBao(Request $request, OrderBuyderService $orderBuyderService): JsonResponse
    {
        $buyder_no = $request->buyder_no ?? '';
        $order = $orderBuyderService->detailWithCondition(['buyder_no' => $buyder_no]);
        if ($order) {
            $orderBuyderService->updateOrderTaoBao($order);
            return resSuccess();
        }
        return resError();
    }

    /**
     * @throws Exception
     */
    public function orderDetail(Request $request): JsonResponse
    {
        $params = $request->all();
        $source = $request->source ?? 'taobao';
        $service = new BasePlatformService(BasePlatformFactory::createBuyerService($source));
        $results = $service->detailOrderPlatform($params);
        if ($results && !empty($results->code)) {
            if ($results->code == 200) {
                return resSuccessWithinData($results->data);
            }
            return resError($results->message);
        }
        return resError();
    }

    public function xadal(Request $request): JsonResponse
    {
        if (! auth()->user()->can('place_order_list')) {
            return resError('Không có quyền truy cập', 403);
        }
        $url = env('URL_CREATE_REQUEST_PAYMENT_XADAL','http://localhost:8008/api/admin/request_payment/create');
        $method = 'POST';
        $params = $request->all();
        $buyerIds = $params['buyder_ids'];
        $orderBuyers = OrderBuyder::query()->whereIn('buyder_id', $buyerIds)->get();
        $dataSent['info'] = [
            'user_code' => Auth::user()->xadal_user_code
                ?? '',
            'user_name' => Auth::user()->name ?? '',
            'user_password' => Auth::user()->xadal_user_password
                ?? '',
            'source_id' => $params['platform_source'] == 'taobao' ? 2 : 1,
            'source_type' => 5 // Xadal
        ];

        foreach ($orderBuyers as $orderBuyer) {
            $dataSent['list_order'][] = [
                'order_id' => $orderBuyer->buyder_id ?? 0,
                'order_code' => $orderBuyer->buyder_no ?? 0,
                'purchase_order_outer_id' => $orderBuyer->purchase_order_outer_id ?? 0,
                'purchase_id' => $orderBuyer->source == 'taobao' ? $orderBuyer->purchase_id : $orderBuyer->buyder_no,
                'foreign_currency' => $orderBuyer->platform_total_amount ?? 0,
            ];
        }
        $response = $this->curl($dataSent, $url, $method);
        if ($response && $response->code == 200) {
            $success = $response->data->success ?? [];
            $orderNoSuccess = $response->data->orderNoSuccess ?? [];
            $errors = $response->data->errors ?? [];
            $message = '';
            if (!empty($orderNoSuccess)) {
                OrderBuyder::query()->whereIn('buyder_no', $orderNoSuccess)->update(['is_xadal' => 1]);
            }
            if (!empty($success)) {
                $message .= implode(', ', $success);
            }
            if (!empty($errors)) {
                $message .= implode(', ', $errors);
            }
            if (!empty($errors) && !empty($success)) {
                return resSuccess($message);
            }
            if (empty($errors) && !empty($success)) {
                return resSuccess($message);
            }
            if (!empty($errors) && empty($success)) {
                return resError($message);
            }
            return resSuccess();
        }
        return resError();
    }

    public function syncStatusOrderBuyder(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $status_old = $request->status_old ?? '';
            $status_new = $request->status_new ?? '';
            $source = $request->source ?? '';

            if (!$source) {
                return resError();
            }
            if (!$status_old) {
                if ($source == '1688') {
                    return resError();
                }
            }
            if (!$status_new) {
                return resError();
            }
            $items = OrderBuyder::query()
                ->with('orderBuyderSub')
                ->where('source', $source)
                ->where('supplier_status', $status_old)
                ->get();
            foreach ($items as $item) {
                $item->supplier_status = $status_new;
                $item->save();
                foreach ($item->subOrderBuyders as $subOrderBuyder) {
                    $subOrderBuyder->buyder_status = $status_new;
                    $subOrderBuyder->save();
                }
            }
            DB::commit();
            return resSuccess();
        } catch (\Exception $exception) {
            DB::rollBack();
            return resError($exception->getMessage());
        }

    }

    public function cancelOrderFromOtherWebsite(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $order_no = $request->order_no ?? '';
            if (!$order_no) {
                return resError('Mã đơn hàng không được để trống!');
            }
            $query = OrderBuyder::query();
            $query->select('id', 'buyder_no');
            if (is_array($order_no)) {
                $query->whereIn('purchase_id', $order_no);
            } else {
                $query->where('purchase_id', $order_no);
            }
            $orderBuyder = $query->get();
            $orderNo = [];
            $buyderIds = [];
            foreach ($orderBuyder as $item) {
                $orderNo[] = $item->buyder_no;
                $buyderIds[] = $item->id;
            }
            if (empty($orderNo)) {
                return resError('Mã đơn hàng không tồn tại!');
            }
            $this->updateCloseReason($buyderIds, 7);
            // Xóa yêu cầu hủy kiện nếu có
            $this->checkPackCancel($orderNo);
            OrderPackage::query()->whereIn('buyder_no', $orderNo)
                ->forceDelete();
            // Log::info('Hủy đơn hàng thành công ' . json_encode($orderNo));
            DB::commit();
            return resSuccess();
        } catch (\Exception $exception) {
            Log::info('Hủy đơn hàng thất bại ' . json_encode($exception->getMessage()));
            DB::rollBack();
            return resError($exception->getMessage());
        }

    }

    public function checkPackCancel($orderNo)
    {
        $packCancelModel = \App::make('App\Models\OrderPackageCancel');
        $packs = OrderPackage::query()->whereIn('buyder_no', $orderNo)->get();
        if (count($packs) > 0) {
            foreach ($packs as $key => $packDB) {
                $order = $packDB->orderPackageWithOrder;
                if ($order) {
                    $order->decrement('packages_number');
                }
                $order_package_id = (int) $packDB->id;
                $packCancelDB = $packCancelModel->where('order_package_id', $order_package_id)->first();
                if (!is_null($packCancelDB)) {
                    $packCancelDB->delete();
                }
            }
        }
    }

    public function updateOrderStatusFromOtherWebsite(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $order_no = $request->order_no ?? '';
            $status = $request->status ?? '';
            $params['status'] = '';
            if (in_array($status, ['Success', 'success', 'WAIT_BUYER_CONFIRM_GOODS'])) {
                $params['status'] = BuyderStatusConstant::ORDER_WAIT_BUYER_CONFIRM_GOODS;
            }
            if (!$order_no) {
                return resError('Mã đơn hàng không được để trống!');
            }
            $query = OrderBuyder::query();
            if (is_array($order_no)) {
                $query->whereIn('purchase_id', $order_no);
            } else {
                $query->where('purchase_id', $order_no);
            }
            $orderBuyerQuery = clone $query;
            $orderNoQuery = clone $query;
            $orderNo = $orderNoQuery->pluck('id')->toArray();
            if (empty($orderNo)) {
                return resError('Mã đơn hàng không tồn tại!');
            }

            if (empty($params['status'])) {
                return resError('Trạng thái không phù hợp!');
            }

            $this->updateOrderBuyer($orderBuyerQuery, $params);
            DB::commit();
            // Log::info("Cập nhật trạng thái đơn hàng $order_no thành công! Trang thai $status");
            return resSuccess();
        } catch (\Exception $exception) {
            // Log::info("Cập nhật trạng thái đơn hàng $order_no thất bại " . json_encode($exception->getMessage()));
            DB::rollBack();
            return resError($exception->getMessage());
        }
    }

    public function updateOrderOrderedFromOtherWebsite(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $order_no = $request->order_no ?? '';
            $status = $request->status ?? '';
            if (!$order_no) {
                return resError('Mã đơn hàng không được để trống!');
            }
            if (!$status || $status != OrderConstant::KEY_STATUS_ORDERED) {
                return resError('Trạng thái đơn hàng không hợp lệ!');
            }
            $query = OrderBuyder::query();
            if (is_array($order_no)) {
                $query->whereIn('purchase_id', $order_no);
            } else {
                $query->where('purchase_id', $order_no);
            }
            $orderBuyerQuery = clone $query;
            $orderNoQuery = clone $query;
            $orderNo = $orderNoQuery->pluck('id')->toArray();
            if (empty($orderNo)) {
                return resError('Mã đơn hàng không tồn tại!');
            }
            $this->updateOrderBuyer($orderBuyerQuery);
            $this->updateOrder($orderNo, $status);
            DB::commit();
            return resSuccess();
        } catch (\Exception $exception) {
            Log::info('Cập nhật đơn hàng thất bại ' . json_encode($exception->getMessage()));
            DB::rollBack();
            return resError($exception->getMessage());
        }
    }

    private function updateOrderBuyer($query, $params = []){
        foreach ($query->get() as $item) {
            $ids[] = $item->purchase_id ?? '';
            $this->handleChangeLocalStatuses(
                $ids,
                !empty($params['status'])
                    ? $params['status']
                    : BuyderStatusConstant::ORDER_WAIT_SELLER_SEND_GOODS,
                !empty($params['status'])
                    ? $params['status']
                    : BuyderStatusConstant::ORDER_WAIT_SELLER_SEND_GOODS
            );
        }
    }

    /**
     * @throws BindingResolutionException
     */
    private function updateOrder($buyderIds, $status){
        $orderIds = SubOrderBuyder::query()
            ->whereIn('order_buyder_id', $buyderIds)
            ->pluck('order_id')
            ->toArray();
        $orderIds = array_unique($orderIds);
        $listOrders = [];
        $subOrderBuyerOrders = SubOrderBuyder::query()
            ->select('order_id as order_id', DB::raw('SUM(quantity) as total_quantity'))
            ->whereIn('order_id', $orderIds)
            ->whereIn('buyder_status', [
                BuyderStatusConstant::ORDER_WAIT_SELLER_SEND_GOODS,
                BuyderStatusConstant::ORDER_WAIT_SELLER_SEND_GOODS
            ])
            ->groupBy('order_id')->get();
        foreach ($subOrderBuyerOrders as $subOrderBuyerOrder) {
            if (isset($listOrders[$subOrderBuyerOrder->order_id])) {
                $listOrders[$subOrderBuyerOrder->order_id] += (int)$subOrderBuyerOrder->total_quantity;
            } else {
                $listOrders[$subOrderBuyerOrder->order_id] = (int)$subOrderBuyerOrder->total_quantity;
            }
        }
        $orders = Order::query()
            ->whereIn('id', array_unique($orderIds))
            ->with('orderDetail')
            ->get();
        $orderService = app()->make(OrderService::class);
        foreach ($orders as $order) {
            if (isset($listOrders[$order->id])) {
                $total_quantity = $order->orderDetail->sum('quantity');
                $flag = $total_quantity - $listOrders[$order->id];
                if ($flag == 0) {
                    $statusNew = $status;
                    if ($order->status == OrderConstant::KEY_STATUS_DONE) {
                        Log::info('Không được cập nhật với đơn hàng đã Hoàn Thành');
                    } else {
                        $orderService->changeStatus($order, $statusNew);
                        // Log::info('Cập nhật trạng thái đơn hàng thành công ' . json_encode($order->code) . ' status ' . $status);
                    }
                }
            }
        }
    }

    private function updateCloseReason($buyderIds, $cancelType){
        SubOrderBuyder::query()
            ->with(['assignSupportOrders', 'orderPackage', 'orderDetailSub'])
            ->whereIn('order_buyder_id', $buyderIds)
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
                    $orderPackage->delete();
                }

                // update order_buyder_subs.close_reason
                $subOrderBuyder->update([
                    'close_reason' => $cancelType,
                    'buyder_status' => $this->getOrderBuyderSubsClosedStatus($subOrderBuyder->source)
                ]);
            });
    }

    private function getOrderBuyderSubsClosedStatus(string $source): string
    {
        switch ($source) {
            case BuyderServiceConstant::SOURCE_1688:
            case BuyderServiceConstant::SOURCE_TAOBAO:
                return BuyderStatusConstant::ORDER_TRADE_CLOSED;
            default:
                return '';
        }
    }

    private function curl($data, $url, $method)
    {
        try {
            $data['api_provider'] = 'x_port';
            $data_string = json_encode($data);
            $curl = curl_init($url);

            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER,
                $this->setHeaderCurl($data_string)
            );
            $result = curl_exec($curl);
            curl_close($curl);
            return json_decode($result, false);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    private function setHeaderCurl($data_string): array
    {
        return [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string)
        ];
    }

    /**
     * @throws Exception
     */
    public function cancelOrder(CancelOrderRequest $request): JsonResponse
    {
        $results = $this->_service->cancelOrder($request->validated());

        $errors = $results['errors'] ?? [];
        $success = $results['success'] ?? [];
        $message = '';
        if (!empty($errors)) {
            $message .= 'Các mã đơn hàng Hủy thất bại [' . implode(', ', $errors) .']. ';
        }
        if (!empty($success)) {
            $message .= 'Các mã đơn hàng Hủy thành công [' . implode(', ', $success) .']. ';
        }
        if (!empty($errors) && !empty($success)) {
            return resSuccess($message);
        }
        if (empty($errors) && !empty($success)) {
            return resSuccess($message);
        }
        if (!empty($errors) && empty($success)) {
            return resError($message);
        }
        return resError();
    }

    public function getAllOrderOrderBuyders(string $orderId): JsonResponse
    {
        $orderBuyders = $this->_service->getAllOrderOrderBuyders($orderId);

        return resSuccessWithinData($orderBuyders);
    }

    public function getAllOrderSubs(string $orderId): JsonResponse
    {
        $allOrderSubs = $this->_service->getAllOrderSubs($orderId);

        return resSuccessWithinData($allOrderSubs);
    }

    public function getAllOrderDetailSubs(string $orderId): JsonResponse
    {
        $allOrderSubs = $this->_service->getAllOrderDetailSubs($orderId);

        return resSuccessWithinData($allOrderSubs);
    }

    public function getAssignSupportList(): JsonResponse
    {
        if (! auth()->user()->can('place_order_assign_support_list')) {
            return resError('Không có quyền truy cập', 403);
        }

        $assignSupportLists = $this->_service->getAssignSupportList();

        return resSuccessWithinData(new PaginateJsonResource($assignSupportLists));
    }

    public function assignSupport(AssignSupportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated) {
                $this->_service->assignSupport([
                    'order_detail_id' => $validated['order_detail_id'],
                    'quantity' => $validated['quantity'],
                    'assignee_ids' => $validated['users'],
                ]);
            });

            return resSuccess('Gán người hỗ trợ thành công.');
        } catch (Exception $e) {
            Log::error($e);

            return resError($e->getMessage(), 400);
        }
    }

    public function assignSupportCancel(AssignSupportCancelRequest $request): JsonResponse
    {
        return $this->_service->assignSupportCancel($request->validated());
    }

    public function checkIsAssignedSupport(CheckIsAssignedSupportRequest $request): JsonResponse
    {
        return $this->_service->checkIsAssignedSupport($request->validated());
    }

    public function getPlaceOrderDetail(string $id): JsonResponse
    {
        $placeOrderDetail = $this->_service->getPlaceOrderDetail($id);

        return resSuccessWithinData($placeOrderDetail);
    }

    public function getOrderReport(): JsonResponse
    {
        if (! auth()->user()->can('place_order_report_list')) {
            return resError('Không có quyền truy cập', 403);
        }

        $reportOrders = $this->_service->getOrderReport();

        return resSuccessWithinData(new PaginateJsonResource($reportOrders));
    }

    public function getPaymentList(): JsonResponse
    {
        if (! auth()->user()->can('place_order_payment')) {
            return resError('Không có quyền truy cập', 403);
        }

        return $this->_service->getPaymentList();
    }

    /**
     * @throws Exception
     */
    public function handlePayment(PaymentRequest $request): JsonResponse
    {
        if (! auth()->user()->can('place_order_payment')) {
            return resError('Không có quyền truy cập', 403);
        }
        $data = $request->validated();
        $data['buyder_ids'] = array_unique($data['buyder_ids']);
        $response = $this->_service->handlePayment($data);
        if ($response && !empty($response['code'])) {
            if ($response['code'] == 200) {
                if ($data['platform_source'] == 'taobao') {
                    $this->handleChangeLocalStatuses(
                        data_get($data, 'buyder_ids'),
                        BuyderStatusConstant::BUYDER_ORDER_PAID,
                        BuyderStatusConstant::SUB_BUYDER_ORDER_WAIT_SELLER_SEND_GOODS
                    );
                } else {
                    $this->handleChangeLocalStatuses(
                        data_get($data, 'buyder_ids'),
                        BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_SELLER_SEND,
                        BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_SELLER_SEND
                    );
                }

                return resSuccessWithinData('Thanh toán thành công!');
            }
            return resError($response['message']);
        }
        return resError('Thanh toán thất bại !');
    }

    /**
     * @throws Exception
     */
    public function connectChat(ConnectChatRequest $request): JsonResponse
    {
        $results = $this->_service->connectChat($request->validated());
        if ($results['code'] == 200) {
            return resSuccessWithinData($results['data']);
        }
        return resError();
    }

    public function connectIm(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required',
            'login_name' => 'required',
            'password' => 'required'
        ],[
            'user_id.required' => 'Mã nhân viên không được để trống',
            'login_name.required' => 'Tên nhân viên không được để trống',
            'password.required' => 'Mật khẩu nhân viên không được để trống'
        ]);
        try {
            $params = $request->all();
            $userId = data_get($params, 'user_id');
            $loginName = data_get($params, 'login_name');
            $password = data_get($params, 'password');
            $pageName = "imDialog";
            $pageParamMap = [
                'sourceType' => $request->sourceType ?? '',
                'bizId' => $request->bizId ?? '',
            ];
            $data = [
                'loginName' => $loginName ?? Auth::user()->user_name_taobao,
                'pageName' => $pageName,
                'password' => $password ?? Auth::user()->password_taobao,
                'userId' => $userId ?? Auth::user()->id_taobao,
                'pageParamMap' => $pageParamMap
            ];
            $service = new BasePlatformService(BasePlatformFactory::createBuyerService('taobao'));
            $data['userId'] = Auth::user()->id_taobao ?? 0;
            $results = $service->connectChat($data);
            if ($results) {
                if ($results->code == 200) {
                    return resSuccessWithinData($results->data);
                }
                return resError($results->message ?? '');
            }
            return resError();
        } catch (Exception $e) {
            Log::error($e);
            return resError($e->getMessage(), 400);
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws BindingResolutionException
     */
    public function updateOrderCodePo(
        OrderBuyderService $orderBuyderService,
        OrderPackageService $orderPackageService
    ): JsonResponse
    {
        $request = request();
        $source = $request->get('source') ?? BuyderServiceConstant::SOURCE_TAOBAO;
        $orderBuyderStatus = $request->get('order_buyder_status');
        $order_buyder_no = $request->get('order_buyder_no');
        $order_status = $request->get('order_status');
        $orderQueryFirst = Order::query();
        if ($order_status) {
            $orderQueryFirst = $orderQueryFirst->where('orders.status', $order_status);
        }
        if ($source) {
            $orderQueryFirst = $orderQueryFirst->where('order_details.source', $source);
        }
        $orderIds =
            $orderQueryFirst->select([
                'orders.id as order_id', 'orders.created_at',
                'order_details.id as order_detail_id', 'order_details.order_id', 'order_details.source'
            ])
                ->join('order_details', 'orders.id', '=', 'order_details.order_id')
                ->when($request->get('organization_id'), function($q){
                    return $q->where('orders.organization_id', request()->get('organization_id'));
                })
                ->owned()->visibleInPlaceOrder()
                ->latest('orders.created_at')
                ->where('order_details.is_display', 1)
                ->get()->pluck('order_id');

        $orderQuery = Order::query()
            ->select('id', 'code')
            ->whereIn('id', $orderIds)
            ->when($request->get('staff_id'), fn($query, $staffId) => $query->where('staff_order_id', $staffId))
            ->when($request->get('order_code'), fn($query, $orderCode) => $query->where('code', $orderCode))
            ->when($request->get('from'), fn($query, $from) => $query->where('created_at', '>=', $from))
            ->when($request->get('to'), fn($query, $from) => $query->where('created_at', '<=', $from));

        if (isset($orderBuyderStatus) && $orderBuyderStatus != 'all') {
            $orderQuery = $orderQuery->whereHas('orderBuyders', function ($orderBuyders) use ($orderBuyderStatus) {
                $orderBuyders->where('supplier_status', $orderBuyderStatus);
            });
        }
        if ($order_buyder_no) {
            if ($source == 'taobao') {
                $orderQuery = $orderQuery->whereHas('orderBuyders', function ($orderBuyders) use ($order_buyder_no) {
                    $orderBuyders->where('purchase_order_outer_id', $order_buyder_no);
                });
            }

            if ($source == '1688') {
                $orderQuery = $orderQuery->whereHas('orderBuyders', function ($orderBuyders) use ($order_buyder_no) {
                    $orderBuyders->where('buyder_no', $order_buyder_no);
                });
            }
        }
        $orderQuery = $orderQuery->whereHas('orderBuyders', function ($orderBuyders) use ($orderBuyderStatus) {

        });
        $placeOrderLists = $orderQuery->whereDoesntHave('packagesOrder')
        ->with(['orderBuyders' => function ($orderBuyders) {
            $orderBuyders->select(
                'id', 'source', 'taobao_user_id', 'platform_total_amount', 'platform_goods_amount',
                'platform_domestic_post_fee', 'purchase_order_outer_id' , 'buyder_no', 'source'
            )->with([
                'subOrderBuyders' => fn($query) => $query
                    ->withoutGlobalScope('without_canceled')
                    ->orderByDesc('created_at'),
            ]);
        }])->get();
        if ($placeOrderLists->count() > 0) {
            foreach ($placeOrderLists as $list) {
                $orderBuyders = $list->orderBuyders;
                foreach ($orderBuyders as $orderBuyder) {
                    $subOrderBuyders = $orderBuyder->subOrderBuyders;
                    $arrayInsertOrderPackage = [];
                    foreach ($subOrderBuyders as $item) {
                        $arrayInsertOrderPackage[$item->order_id]['products'][] = [
                            'id' => (int)$item->sourceable_id,
                            'quantity' => $item->quantity
                        ];
                        $orderBuyderCategory = $item->orderBuyderCategory;
                        $arrayInsertOrderPackage[$item['order_id']]['category_id'] = data_get($orderBuyderCategory, 'category_id', 0);
                        $arrayInsertOrderPackage[$item['order_id']]['package_number'] = 1;
                        $arrayInsertOrderPackage[$item['order_id']]['code_po'] = $source == '1688'
                            ? $orderBuyder->buyder_no
                            : '';
                        $arrayInsertOrderPackage[$item['order_id']]['buyder_no'] = $orderBuyder->buyder_no;

                    }
                    if ($source == 'taobao') {
                        $orderBuyderService->updateOrderTaoBao(
                            $orderBuyder, false, $arrayInsertOrderPackage, false
                        );
                    } else {
                        foreach ($arrayInsertOrderPackage as $key => $item) {
                            if (
                                OrderPackage::query()->where('order_id', $key)
                                    ->where('buyder_no', $item['buyder_no'])->first()
                            ) {
                                continue;
                            }
                            $order = Order::query()->find($key);
                            $package = $orderPackageService->quickCreatePackage($item, $order);
                            Log::info('QuickCreateOrderPackageJob updateOrderCodePo 1688' . json_encode($package));
                        }
                    }

                }
            }
        }
        return resSuccess();
    }

    public function previewOrder(Request $request)
    {
        $results = $this->_service->previewOrder($request->all());
        return resSuccessWithinData($results);
    }
}
