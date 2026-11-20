<?php

namespace App\Http\Controllers;

use App\Constants\ActivityConstant;
use App\Constants\Buyder\OrderBuyderConstant;
use App\Constants\NotifyConstant;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\TransactionConstant;
use App\Exports\OrderExport;
use App\Factories\BasePlatformFactory;
use App\Helpers\ConvertHelper;
use App\Helpers\PaginateHelper;
use App\Http\Requests\CustomerOrderRequest;
use App\Http\Requests\NoteOrderDetailStoreRequest;
use App\Http\Requests\Order\ChangeSupplierRequest;
use App\Http\Requests\Order\ListOrderAdvanceMoneyRequest;
use App\Http\Requests\Order\OrderChangeStatusRequest;
use App\Http\Requests\Order\StoreOrderAdvanceMoneyRequest;
use App\Http\Requests\Order\UpdateOrderAdvanceMoneyRequest;
use App\Http\Requests\OrderCancelRequest;
use App\Http\Requests\OrderChangeStaffRequest;
use App\Http\Requests\OrderChangeStaffToMultipleRequest;
use App\Http\Requests\OrderOfferRequest;
use App\Http\Resources\ListResource;
use App\Http\Resources\Note\NoteResource;
use App\Http\Resources\Order\OrderAdvanceMoneyResource;
use App\Http\Resources\Order\ProductPaginateResource;
use App\Http\Resources\PaginateJsonResource;
use App\Interfaces\Validation\UpdateValidationInterface;
use App\Jobs\NotifyUserOfCompletedExport;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\OrderSupplier;
use App\Models\OrderAdvanceMoney;
use App\Models\OrderDetail;
use App\Models\OrderPackage;
use App\Models\Permission;
use App\Models\RefundFromXadal;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Supplier;
use App\Rules\OrderDetail\MinProductsInOrder;
use App\Rules\PlaceOrder\MaxProductsInOrder;
use App\Services\ActivityService;
use App\Services\ConfigService;
use App\Services\DeliveryService;
use App\Services\FcmTokenService;
use App\Services\OrderDetailService;
use App\Services\OrderHistoryService;
use App\Services\OrderNoteService;
use App\Services\OrderService;
use App\Services\AccountingService;
use App\Services\PlatformServices\BasePlatformService;
use Carbon\Carbon;
use DateInterval;
use DatePeriod;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Str;
use App\Models\OrderContract;
use App\Models\User;
use App\Models\ReasonConfirmOrder;
use App\Jobs\ExportOrderJob;
use App\Services\NotificationService;
use App\Services\TransactionService;
use App\Jobs\ExportOrderActualPaymentJob;
use App\Jobs\Order\SyncDateByOrderStatusTime;
use App\Jobs\Order\SyncOrderCost;

use GuzzleHttp\Client as Guzzle;

use App\Constants\ConversationConstant;

class OrderController extends Controller implements UpdateValidationInterface
{
    public function __construct(OrderService $service)
    {
        $this->_service = $service;
    }

    public function totalAmountPayable(): JsonResponse
    {
        $key = 'total_amount_payable_ceo';
        $results = Cache::get($key, []);
        if (empty($results)) {
            /*$results = $this->_service->totalAmountPayable();
            Cache::put($key, $results, now()->addMinutes(10));*/
            $data = $this->_service->totalAmountPayable();
            $data['last_updated'] = now()->format('H:i:s d/m/Y');
            Cache::put($key, $data, now()->addMinutes(10));
            $results = $data;
        }
        return resSuccessWithinData($results);
    }

    /**
     * @throws Exception
     */
    public function createOrderBuyder(Request $request, ConfigService $configService, $order_id): JsonResponse
    {
        $listReceivers = $configService->listReceivers();
        $content = json_decode($listReceivers->getContent(), true);
        $dataReceivers = $content['data'];
        $listProductSelect = $request->listProductSelect ?? [];
        if (empty($listProductSelect)) {
            return resError('Chưa có sản phẩm đươc chọn!');
        }
        $orderDetailsIds = [];
        foreach ($listProductSelect as $select) {
            $orderDetailsIds[] = $select['id'];
        }
        $sourceTaoBao = '';
        $source1688 = '';
        $orderDetails = OrderDetail::query()->whereIn('id', $orderDetailsIds)
            ->with(['supplier' => function ($supplier) {

        }])->get();
        foreach ($orderDetails as $orderDetail) {
            if ($orderDetail->source == 'taobao') {
                $sourceTaoBao = 'taobao';
            }
            if ($orderDetail->source == '1688') {
                $source1688 = '1688';
            }
        }
        if ($sourceTaoBao && $source1688) {
            return resError('Không thể đặt đơn cùng 1 lúc với 2 nguồn hàng!');
        }
        if (!$sourceTaoBao && !$source1688) {
            return resError('Đơn chưa có nguồn hàng!');
        }
        if ($sourceTaoBao) {
            $response = $this->createOrderTaobao($orderDetails, $dataReceivers, $order_id);
            if ($response['code'] == 500) {
                return resError($response['message']);
            }
            return resSuccess($response['message']);
        }
        if ($source1688) {
            $response = $this->createOrder1688($orderDetails, $dataReceivers, $order_id);
            if ($response['code'] == 500) {
                return resError($response['message']);
            }
            return resSuccess($response['message']);
        }
        return resError('Tạo đơn thất bại!');
    }

    /**
     * @throws Exception
     */
    private function createOrderTaobao($orderDetails, $dataReceivers, $order_id): array
    {
        $receivers = $dataReceivers['value']['taobao'] ?? [];
        $receiver = '';
        foreach ($receivers as $receiverItem) {
            if (isset($receiverItem['is_default']) && $receiverItem['is_default'] == 1) {
                $receiver = $receiverItem;
            }
        }
        $orderLineList = [];
        foreach ($orderDetails->toArray() as $orderDetail) {
            $orderLineList[] = [
                "itemId" => $orderDetail['mp_id'],
                "skuId" => !empty($orderDetail['mp_sku_id_fast'])
                    ? $orderDetail['mp_sku_id_fast']
                    : $orderDetail['mp_sku_id'],
                "orderLineNo" => 1,
                "quantity" => $orderDetail['quantity'] ?? 1,
                "price" => $orderDetail['amount_cny'],
                "currency" => "CNY",
                "purchaseAmount" => $orderDetail['amount_cny'] * 100,
                "sellerOrderNumber" => $orderDetail['supplier']['code'],
            ];
        }
        $payload = [
            'orderSource' => OrderBuyderConstant::HANGVE,
            'receiver' => $receiver,
            'userId' => Auth::user()->id_taobao ?? 0,
            'orderLineList' => $orderLineList
        ];
        $service = new BasePlatformService(BasePlatformFactory::createBuyerService('taobao'));
        $response = $service->createOrderPlatform($payload);
        if ($response && !empty($response->code)) {
            if ($response->code == 200) {
                sleep(3);
                $orderId = $response->data->orderNo ?? 0;
                $results = $service->detailOrderPlatform(['orderId' => $orderId]);
                if ($results && !empty($results->code)) {
                    if ($results->code == 200) {
                        $data = $results->data[0];
                        $purchaseAmount = $data->purchaseAmount / 100;
                        $domesticPostFee = $data->domesticPostFee / 100;
                        $subOrderVO = $data->subOrderVO ?? null;
                        $supplier = '';
                        if ($subOrderVO) {
                            foreach ($subOrderVO as $value) {
                                if ($value->subOrderStatus == 'WAIT_BUYER_P') {
                                    $supplier = $value->optionFields->shopName ?? '';
                                    break;
                                }
                            }
                        }
                        $contentLog = 'Nhân viên ' . Auth::user()->name . '  đã đặt đơn báo giá NCC [ ' . $supplier . ' ] với phí nội địa là '. $domesticPostFee .'¥ và tổng tiền đơn hàng là ' . $purchaseAmount . '¥';
                        (new ActivityService())->setOrderLog(
                            Order::query()->find($order_id),
                            $contentLog,
                            $order_id,
                            ActivityConstant::IS_VIEW_ADMIN,
                            ActivityConstant::IS_ACTIVITY_TYPE_1
                        );
                    }
                }
                return [
                    'code' => 200,
                    'message' => 'Tạo đơn hàng Taobao thành công!'
                ];
            }
            return [
                'code' => 500,
                'message' => 'Tạo đơn hàng Taobao thất bại! ' . $response->message
            ];
        }
        return [
            'code' => 500,
            'message' => $response->message ?? 'Tạo đơn hàng Taobao thất bại!'
        ];
    }

    /**
     * @throws Exception
     */
    private function createOrder1688($orderDetails, $dataReceivers, $order_id): array
    {
        $receivers = $dataReceivers['value']['m1688'] ?? [];
        $receiver = '';
        foreach ($receivers as $receiverItem) {
            if (isset($receiverItem['is_default']) && $receiverItem['is_default'] == 1) {
                $receiver = $receiverItem;
            }
        }
        $cargoParamList = [];
        foreach ($orderDetails->toArray() as $orderDetail) {
            $cargoParamList[] = [
                "offerId" => $orderDetail['mp_id'],
                "specId" => !empty($orderDetail['mp_sku_id_fast'])
                    ? $orderDetail['mp_sku_id_fast']
                    : $orderDetail['mp_sku_id'],
                "quantity" => $orderDetail['quantity'],
                "sellerOrderNumber" => $orderDetail['supplier']['code'],
            ];
        }
        $payload = [
            'orderSource' => OrderBuyderConstant::HANGVE,
            'addressParam' => [
                "address" => $receiver['address'],
                "mobile" => $receiver['mobile_phone'],
                "fullName" => $receiver['name'],
                "postCode" => $receiver['zipcode'],
                "areaText" => $receiver['state'],
                "cityText" => $receiver['city'],
                "provinceText" => $receiver['district'],
            ],
            'cargoParamList' => $cargoParamList,
            'minOrderQuantity' => 1,
            'userId' => Auth::user()->id_1688 ?? 0
        ];
        $service = new BasePlatformService(BasePlatformFactory::createBuyerService('1688'));
        $response = $service->createOrderPlatform($payload);
        if ($response && !empty($response->code)) {
            if ($response->code == 200) {
                $data = $response->data;
                $domesticPostFee = (float)data_get($data, 'baseInfo.shippingFee');
                $purchaseAmount = (float)(collect(data_get($data, 'productItems'))->sum('itemAmount'));
                $supplier = data_get($data, 'baseInfo.sellerContact.imInPlatform');
                $contentLog = 'Nhân viên ' . Auth::user()->name . '  đã đặt đơn báo giá NCC [ ' . $supplier . ' ] với phí nội địa là '. $domesticPostFee .'¥ và tổng tiền đơn hàng là ' . $purchaseAmount.'¥';
                (new ActivityService())->setOrderLog(
                    Order::query()->find($order_id),
                    $contentLog,
                    $order_id,
                    ActivityConstant::IS_VIEW_ADMIN,
                    ActivityConstant::IS_ACTIVITY_TYPE_1
                );
                return [
                    'code' => 200,
                    'message' => 'Tạo đơn hàng 1688 thành công!'
                ];
            }
            return [
                'code' => 500,
                'message' => 'Tạo đơn hàng 1688 thất bại! ' . $response->message
            ];
        }
        return [
            'code' => 500,
            'message' => $response->message ?? 'Tạo đơn hàng 1688 thất bại!'
        ];
    }

    public function update(string $id): JsonResponse
    {
        $params = request()->all();
        if (!empty($params['warehouse_id'])) {
            $validate = $this->validateWarehouseBeforeUpdate($id, $params);
            if ($validate['code'] == 500) {
                return resError($validate['message']);
            }
            // Đẩy sang kho lưu lại
            $this->pushUpdateWarehouseToOne($id, $params);
        }
        $data = parent::update($id);
        if ($error = json_decode($data->getContent(), true)) {
            if(!empty($error) && isset($error['data']['original'])) {
                $check = $error['data']['original'];

                return resError($check['message'], $check['code']);
            }
        }
        return $data;
    }

    public function pushUpdateWarehouseToOne($id, $params)
    {
        $order = Order::query()->select('id','code', 'status', 'warehouse_id')->find($id);
        $orderPackage = OrderPackage::query()->select('id', 'order_id', 'status')->where('order_id', $id)
            ->where('order_type', Order::class)
            ->get();

        $arr_ids = [];
        if (count($orderPackage)) {
            foreach ($orderPackage as $key => $pack) {
                array_push($arr_ids, (int) $pack->id);
            }
        }

        if (count($arr_ids) == 0) {
            // \Log::info('Không cập nhật kiện pushUpdateWarehouseToOne');
            return;
        }

        // \Log::info('pushUpdateWarehouseToOneRun');

        $temp = [
            'arr_ids'      => $arr_ids,
            'order_code'   => $order->code,
            'warehouse_id' => $params['warehouse_id'],
        ];

        $guzzle = new Guzzle;
        $url = env('URL_DONGDONG') . 'webhook/hangve-x329kxm2k2ixk39x92x1911947165';
        $options = [
            'json' => [
                'event_name' => 'update_warehouse_id',
                'data'       => $temp
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);
    }

    private function validateWarehouseBeforeUpdate($id, $params): array
    {
        $order_status_array = [
            Order::ORDER_STATUS_WAITING_FOR_QUOTE,
            Order::ORDER_STATUS_WAITING_FOR_DEPOSIT,
            Order::ORDER_STATUS_DEPOSITED,
            Order::ORDER_STATUS_NEED_TO_CONFIRM,
            Order::ORDER_STATUS_WAITING_FOR_PAYMENT,
            Order::ORDER_STATUS_ORDER
        ];
        $order_package_array = [
            PackageConstant::STATUS_PENDING,
            PackageConstant::STATUS_ON_THE_WAY_CHINA
        ];
        $order = Order::query()->select('id', 'status', 'warehouse_id')->find($id);
        if ($order->warehouse_id != (int)$params['warehouse_id']) {
            if (!in_array($order->status, $order_status_array)){
                return [
                    'code' => 500,
                    'message' => 'Không được phép thay đổi kho!'
                ];
            }
            $is_update_order_package = true;
            $orderPackage = OrderPackage::query()->select('id', 'order_id', 'status')->where('order_id', $id)
                ->where('order_type', Order::class)
                ->get();
            foreach ($orderPackage as $package) {
                if (!in_array($package->status , $order_package_array)) {
                    $is_update_order_package = false;
                }
            }
            if (!$is_update_order_package) {
                return [
                    'code' => 500,
                    'message' => 'Không được phép thay đổi kho!'
                ];
            }

        }
        return [
            'code' => 200,
            'message' => 'Success!'
        ];

    }

    public function relatedOrder(Request $request): JsonResponse
    {
        $params = $request->all();
        $results = $this->_service->relatedOrder($params);
        return resSuccessWithinData($results ?? []);
    }

    /**
     * @throws BindingResolutionException
     */
    public function demoPer(Request $request)
    {
        $roleModel = app()->make(Role::class);
        $perModel = app()->make(Permission::class);
        $userOnline = Auth::user();
        $role = $roleModel->where('name', 'okRole')->first();
        $permission = $perModel->where('name', 'per1')->first();
    }

    /**
     * @param  OrderChangeStaffRequest  $request
     * @param  Order  $order
     * @return JsonResponse
     */
    public function changeStaff(OrderChangeStaffRequest $request, Order $order): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Tự động gán lại nếu nó gán lại staff_care_id,staff_counselor_id
            $params  = request()->all();
            $this->updateStaffLogic($order, $params);

            // Thêm noti khi cập nhật
            $this->updateOrderNotiLogic($order, $params);

            $dataUpdate         = request()->only(['staff_quotation_id', 'staff_order_id', 'staff_care_id',
                'staff_counselor_id', 'staff_group_id']);
            $staff_care_id      = $dataUpdate['staff_care_id'] ?? null;
            $staff_counselor_id = $dataUpdate['staff_counselor_id'] ?? null;

            if ($staff_care_id) {
                $dataUpdate['staff_counselor_id'] = null;
            }
            if ($staff_counselor_id) {
                $dataUpdate['staff_care_id'] = null;
            }

            // Nếu đổi nhân viên staff_group_id
            if (isset($dataUpdate['staff_group_id']) && $dataUpdate['staff_group_id']) {
                // Tìm xem đơn đó khách có tại hội thoại gì không
                $conversationModel = \App::make('App\Models\Conversation');
                $conversationDB = $conversationModel->where('user_id', $order->staff_group_id)
                                                    ->where('type_id', $order->id)
                                                    ->where('type', ConversationConstant::TYPE_ORDER)
                                                    ->first();
                if ($conversationDB) {
                    $userModel = \App::make('App\Models\User');
                    $userDB = $userModel->where('id', $dataUpdate['staff_group_id'])->first();
                    $userOldDB = $userModel->where('id', $conversationDB->user_id)->first();
                    $conversationDB->user_id    = $userDB->id;
                    $conversationDB->user_code  = $userDB->code;
                    $conversationDB->user_name  = $userDB->name;
                    // $conversationDB->user_email = $userDB->email;
                    $conversationDB->user_image = $userDB->avatar_origin;
                    $conversationDB->save();

                    // Cập nhật số chưa đếm
                    if ($conversationDB->unread_admin > 0) {
                        // Chuyển số chưa đợc từ người cũ sang người mới
                        // $userOldDB->total_chat_unread -= 1; // Tp070125
                        // $userOldDB->save();

                        // $userDB->total_chat_unread += 1; // Tp070125
                        // $userDB->save();
                    }
                }
            }

            $order->update($dataUpdate);

            DB::commit();
            return resSuccess();
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function changeStaffMulti(OrderChangeStaffToMultipleRequest $request): JsonResponse
    {
        try {
            \DB::beginTransaction();

            $params  = request()->all();
            // Nếu là cập nhật tư vấn và bao giá thì cần gom của 1 đơn là dc
            $staff_care_id = isset($params['staff_care_id']) ? $params['staff_care_id'] :  null;
            $staff_counselor_id = isset($params['staff_counselor_id']) ? $params['staff_counselor_id'] :  null;
            $staff_group_id = isset($params['staff_group_id']) ? $params['staff_group_id'] :  null;

            if ($staff_care_id || $staff_counselor_id || $staff_group_id) {
                $orderRepo = \App::make('App\Models\Order');
                $orders = $orderRepo->whereIn('id', $params['orders'])
                    ->where('status', '!=', OrderConstant::KEY_STATUS_DONE)
                    ->groupBy('customer_id')
                    ->get();
                foreach ($orders as $key => $order) {
                    $this->updateStaffLogic($order, $params);
                    // Cập nhật
                    if ($staff_care_id) {
                        $order->staff_care_id = $staff_care_id;
                        $order->save();
                    }
                    if ($staff_counselor_id) {
                        $order->staff_counselor_id = $staff_counselor_id;
                        $order->save();
                    }
                    if ($staff_group_id) {
                        $order->staff_group_id = $staff_group_id;
                        $order->save();
                    }
                }
            }

            // Nếu là staff_order_id và staff_quotation_id
            $staff_order_id = isset($params['staff_order_id']) ? $params['staff_order_id'] :  null;
            $staff_quotation_id = isset($params['staff_quotation_id']) ? $params['staff_quotation_id'] :  null;

            if ($staff_order_id || $staff_quotation_id || $staff_group_id) {
                $orderRepo = \App::make('App\Models\Order');
                $orders = $orderRepo->whereIn('id', $params['orders'])
                    ->where('status', '!=', OrderConstant::KEY_STATUS_DONE)
                    ->get();
                foreach ($orders as $key => $order) {
                    $this->updateStaffLogic($order, $params);
                    if (!$staff_order_id && !$staff_quotation_id && !$staff_group_id) continue;
                    // Cập nhật
                    if ($staff_order_id) {
                        $order->staff_order_id = $staff_order_id;
                    }
                    if ($staff_quotation_id) {
                        $order->staff_quotation_id = $staff_quotation_id;
                    }
                    if ($staff_group_id) {
                        $order->staff_group_id = $staff_group_id;
                    }
                    $order->save();
                }
            }

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function fromScreenWaitForAssignmentLogic($params, $customer, $order)
    {
        $staff_care_id      = isset($params['staff_care_id']) ? $params['staff_care_id'] :  null;
        $staff_counselor_id = isset($params['staff_counselor_id']) ? $params['staff_counselor_id'] :  null;
        $staff_group_id     = isset($params['staff_group_id']) ? $params['staff_group_id'] :  null;

        // Lấy hết đơn hàng cũ của khách ra
        $orderRepo = \App::make('App\Models\Order');
        $arr_status = [
            OrderConstant::KEY_STATUS_WAITING_QUOTE, // Chờ báo giá
            OrderConstant::KEY_STATUS_WAITING_DEPOSIT, // Chờ đặt cọc
        ];
        $orderOld = $orderRepo->with('packages')
            ->whereIn('status', $arr_status)
            ->where('customer_id', $customer->id)
            ->get();

        if (count($orderOld)) {
            $logService = \App::make('App\Services\ActivityService');
            $packService = \App::make('App\Services\OrderPackageService');
            foreach ($orderOld as $key => $item) {
                if ($staff_care_id) {
                    $content = 'Cập nhật nhân viên chăm sóc';
                    $logService->setChangeStaffOrderLog($item, $content, $item->staff_care_id, $staff_care_id);

                    $item->staff_care_id      = $staff_care_id;
                    $item->staff_counselor_id = null;
                }
                if ($staff_counselor_id) {
                    $content = 'Cập nhật nhân viên tư vấn';
                    $logService->setChangeStaffOrderLog($item, $content, $item->staff_counselor_id, $staff_counselor_id);

                    $item->staff_counselor_id = $staff_counselor_id;
                    $item->staff_care_id      = null;
                }
                if ($staff_counselor_id) {
                    $content = 'Cập nhật nhân viên quản lý';
                    $logService->setChangeStaffOrderLog($item, $content, $item->staff_group_id, $staff_group_id);

                    $item->staff_group_id = $staff_group_id;
                }
                $isSave = $item->save();
                if ($isSave) {
                    // update staff for package
                    $packageIds = $item->packages->whereIn('status', [PackageConstant::STATUS_PENDING])->pluck('id');
                    $packService->updateStaffForOrderPackage($packageIds, $staff_care_id, $staff_counselor_id);
                }

                // send noti change order for staff care, counselor
                if ($item->id != $order->id) { // skip with current order
                    $this->_service->sendNotiOrder($item);
                }
            }
        }

         // Chèn thằng ý cho thằng khách hàng  ý luôn
        $logService = \App::make('App\Services\ActivityService');
        if ($staff_care_id) {
            $content = 'Cập nhật nhân viên chăm sóc';
            $logService->setChangeStaffCustomerLog($customer, $content, $customer->staff_care_id, $staff_care_id);

            $customer->staff_care_id      = $staff_care_id;
            $customer->staff_counselor_id = null;
        }
        if ($staff_counselor_id) {
            $content = 'Cập nhật nhân viên tư vấn';
            $logService->setChangeStaffCustomerLog($customer, $content, $customer->staff_counselor_id, $staff_counselor_id);

            $customer->staff_counselor_id = $staff_counselor_id;
            $customer->staff_care_id      = null;
        }
        $customer->save();
        // notify change customer for staff care, counselor
        $this->sendNotiCustomer($customer);
    }

    public function updateStaffLogic($order, $params)
    {
        if ($order->status == 'status_4') {
            return;
        }

        $logService = \App::make('App\Services\ActivityService');
        $customer = $order->customer;
        $staff_care_id      = isset($params['staff_care_id']) ? $params['staff_care_id'] :  null;
        $staff_counselor_id = isset($params['staff_counselor_id']) ? $params['staff_counselor_id'] :  null;
        $staff_order_id     = isset($params['staff_order_id']) ? $params['staff_order_id'] :  null;
        $staff_quotation_id = isset($params['staff_quotation_id']) ? $params['staff_quotation_id'] :  null;
        $staff_group_id     = isset($params['staff_group_id']) ? $params['staff_group_id'] :  null;

        if ($staff_care_id || $staff_counselor_id || $staff_group_id) {
            // Nếu lệnh này đi từ màn chờ gán thì xử lý thêm logic
            $wait_for_assignment = isset($params['wait_for_assignment']) ? $params['wait_for_assignment'] : false;
            if ($wait_for_assignment) {
                $this->fromScreenWaitForAssignmentLogic($params, $customer, $order);
            }
        }

        // Ghi log
        if ($staff_care_id) {
            $content = 'Cập nhật nhân viên chăm sóc';
            $logService->setChangeStaffOrderLog($order, $content, $order->staff_care_id, $staff_care_id);
        }
        if ($staff_counselor_id) {
            $content = 'Cập nhật nhân viên tư vấn';
            $logService->setChangeStaffOrderLog($order, $content, $order->staff_counselor_id, $staff_counselor_id);
        }
        if ($staff_group_id) {
            $content = 'Cập nhật nhân viên quản lý';
            $logService->setChangeStaffOrderLog($order, $content, $order->staff_group_id, $staff_group_id);
        }
        if ($staff_order_id) {
            $content = 'Cập nhật nhân viên đặt hàng';
            $logService->setChangeStaffOrderLog($order, $content, $order->staff_order_id, $staff_order_id);
        }

        if ($staff_quotation_id) {
            $content = 'Cập nhật nhân viên báo giá';
            $logService->setChangeStaffOrderLog($order, $content, $order->staff_quotation_id, $staff_quotation_id);
        }

        // Cập nhật vào Kiện của đơn
        $packagesDB = $order->packages->whereNotIn('status', [PackageConstant::STATUS_RECEIVED_GOODS, PackageConstant::STATUS_CANCEL]);
        if ($packagesDB) {
            foreach ($packagesDB as $key => $packDB) {
                if (!$staff_care_id && !$staff_counselor_id && !$staff_order_id && !$staff_group_id && !$staff_quotation_id) {
                    continue;
                }
                if ($staff_care_id) {
                    $packDB->staff_care_id = $staff_care_id;
                }
                if ($staff_counselor_id) {
                    $packDB->staff_counselor_id = $staff_counselor_id;
                }
                if ($staff_group_id) {
                    $packDB->staff_group_id = $staff_group_id;
                }
                if ($staff_order_id) {
                    $packDB->staff_order_id = $staff_order_id;
                }
                if ($staff_quotation_id) {
                    $packDB->staff_quotation_id = $staff_quotation_id;
                }
                $packDB->save();
            }
        }
        // Cập nhật vào khiêu nại của đơn
        $complainsDB = $order->complains;
        if (count($complainsDB)) {
            foreach ($complainsDB as $key => $complainDB) {
                if (!$staff_care_id && !$staff_counselor_id && !$staff_order_id && !$staff_group_id) {
                    continue;
                }
                if ($staff_care_id) {
                    $complainDB->staff_care_id = $staff_care_id;
                }
                if ($staff_counselor_id) {
                    $complainDB->staff_counselor_id = $staff_counselor_id;
                }
                if ($staff_order_id) {
                    $complainDB->staff_order_id = $staff_order_id;
                }
                if ($staff_group_id) {
                    $complainDB->staff_group_id = $staff_group_id;
                }
                $complainDB->save();
            }
        }
        // Cập nhật giao hàng
        $dataStaff = [];
        if ($staff_care_id) {
            $dataStaff = [
                'staff_care_id' => $staff_care_id
            ];
        }
        if ($staff_counselor_id) {
            $dataStaff = [
                'staff_counselor_id' => $staff_counselor_id
            ];
        }
        if ($staff_group_id) {
            $dataStaff = [
                'staff_group_id' => $staff_group_id
            ];
        }
        if ($staff_order_id) {
            $dataStaff = [
                'staff_order_id' => $staff_order_id
            ];
        }
        if ($staff_quotation_id) {
            $dataStaff = [
                'staff_quotation_id' => $staff_quotation_id
            ];
        }
        $order->deliveries()->update($dataStaff);
    }

    public function sendNotiCustomer(Customer $customer)
    {
        $notiService = \App::make('App\Services\NotificationService');

        $keyGroup = NotifyConstant::KEY_GROUP_CUSTOMER;
        $keyName  = NotifyConstant::KEY_NAME_CUSTOMER_CHANGE_STAFF;
        $data     = [
            'customer_name'  => $customer->name,
            'customer_email' => $customer->email,
            'customer_id'    => $customer->id,
        ];

        if ($customer->staff_care_id) {
            $notiService->sendNoti($keyGroup, $keyName, $customer->staff_care_id, $data);
        }

        if ($customer->staff_counselor_id) {
            $notiService->sendNoti($keyGroup, $keyName, $customer->staff_counselor_id, $data);
        }
    }

    /**
     * @param  OrderCancelRequest  $request
     * @param  Order  $order
     * @return JsonResponse
     */
    public function cancel(OrderCancelRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            // thay đổi logic cho phép hủy nhiều đơn 1 lúc
            $orderIds = request()->input('order_ids');
            $reason = request()->input('reason_cancel');
            // $orders = Order::whereIn('id', $orderIds)->whereIn('status', [OrderConstant::KEY_STATUS_WAITING_QUOTE, OrderConstant::KEY_STATUS_WAITING_DEPOSIT])->get();
            // Thay đổi logic cho phép hủy đơn ở tất cả các trạng thái
            $orders = Order::whereIn('id', $orderIds)->get();
            //dd($orders);
            foreach($orders as $order){
                $order->status = OrderConstant::KEY_STATUS_CANCEL;
                $order->reason_cancel = $reason;
                $order->staff_cancel_id = getCurrentUserId();
                $order->is_waiting_confirm = 0;
                $order->save();
            }
            DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            DB::rollBack();
            return resError();
        }
    }

    public function listRefund(Request $request)
    {
        $params = $request->all();
        return $this->_service->listRefund($params);
    }

    public function refundCancel(Request $request)
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            $results = $this->_service->refundCancel($params);
            DB::commit();
            return resSuccessWithinData($results ?? []);
        } catch (Exception $e) {
            DB::rollBack();
            return resError();
        }
    }
    public function refundMultiple(Request $request)
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            $results = $this->_service->refundMultiple($params);
            DB::commit();
            if(is_array($results)){
                return resError('Không thể hủy đơn này do trạng thái của kiện không hợp lệ! ');
            }
            return resSuccessWithinData($results ?? []);
        } catch (Exception $e) {
            DB::rollBack();
            return resError();
        }
    }

    public function createCancel($id, Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $reason = $request->input('reason_cancel');
            $inspectioned = $request->input('inspectioned');
            if (!$reason) {
                return resError('Vui lòng chọn lý do hủy đơn');
            }
            $order = Order::query()->findOrFail($id);

            $orderRefundCheck = OrderRefund::query()
                ->where('order_id', $id)
                ->where('status', OrderRefund::STATUS_PROCESSING)
                ->exists();
            if($orderRefundCheck) {
                return resError('Đơn hàng đang có yêu cầu hoàn tiền nên không thể tạo chờ hủy');
            }
            if (in_array($order->status,
                [OrderConstant::KEY_STATUS_ORDERED, OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY])
            ) {
                $images = $request->input('images');
                $this->_service->createCancel($order, $reason, $images, $inspectioned);
                // lưu lại số tiền hoàn từ xadal
                $this->_service->refundFromXadal($id);
            } else {
                return resError('Đơn hàng ở trạng thái không phù hợp, không thể tạo chờ hủy');
            }
            DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            DB::rollBack();
            return resError();
        }
    }


    public function createRefund($id, Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $data = $request->all();
            $reason = $data['reason'];
            if (!$reason) {
                return resError('Vui lòng chọn lý do hủy đơn');
            }
            $order = Order::query()->with('details')->findOrFail($id);
            $totalAmountNotShip = $order->getTotalAmountNotShip();

            if($order->delivery_pay_id == 0 && roundMoney($order->deposit_cost) != roundMoney($totalAmountNotShip * $order->deposit_percent / 100)) {
                return resError('Không được tạo yêu cầu vì số tiền đặt cọc bị chênh lệch');
            }
            $orderRefundCheck = OrderRefund::query()
                ->where('order_id', $id)
                ->where('status', OrderRefund::STATUS_PROCESSING)
                ->exists();
            if($orderRefundCheck) {
                return resError('Đơn hàng đang có yêu cầu hoàn tiền nên không thể tạo hoàn tiền');
            }
            if (in_array($order->status,
                [OrderConstant::KEY_STATUS_ORDERED, OrderConstant::KEY_STATUS_WAIT_TO_PAY])
            ) {
                $check = $this->_service->validateAfterRefund($order, $data);
                if(!$check) {
                    return resError('Đơn hàng không thể tạo hoàn tiền do không chênh lệch giá');
                }
                $checkCreate = $this->_service->createRefund($order, $data);
                // lưu lại số tiền hoàn từ xadal
                $this->_service->refundFromXadal($id);

                if(!$checkCreate) {
                    return resError('Đơn hàng không thể tạo hoàn tiền');
                }
            } else {
                return resError('Đơn hàng ở trạng thái không phù hợp, không thể tạo hoàn tiền');
            }
            DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            Log::error($e);
            DB::rollBack();
            return resError();
        }
    }

    /**
     * @param  string  $orderId
     * @return JsonResponse
     */
    public function getNotes(string $orderId): JsonResponse
    {
        return resSuccessWithinData(
            new ListResource(Note::query()->where('order_id', $orderId)->get(), NoteResource::class)
        );
    }

    /**
     * @param  NoteOrderDetailStoreRequest  $request
     * @param  string  $orderId
     * @return JsonResponse
     */
    public function addNote(NoteOrderDetailStoreRequest $request, string $orderId): JsonResponse
    {
        $params = $request->all();
        $params['user_id'] = Auth::user()->id;
        $params['order_id'] = $orderId;
        return resSuccessWithinData(new NoteResource(Note::query()->create($params)));
    }

    public function getProducts(string $id, OrderDetailService $orderDetailService): JsonResponse
    {
        $suppliers = $orderDetailService->getSupplier($id);
        return resSuccessWithinData($suppliers);
    }

    public function updateQuickOrder(string $id, Request $request, OrderNoteService $orderNoteService): JsonResponse
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            $is_update = $params['is_update'] ?? '';
            if (in_array($is_update, ['order_provisional_date', 'note_private', 'note_business'])) {
                $orderNoteService->updateOrderNote($id, $params);
            }
            if(in_array($is_update, ['china_shipping_cost', 'china_shipping_cost_real', 'order_cost_real'])) {
                $this->_service->updateFeeOther($id, $params);
            }
            DB::commit();
            return resSuccess();
        } catch (Exception $exception) {
            DB::rollBack();
            return resError();
        }
    }

    public function updateQuickInspection(string $id, Request $request): JsonResponse
    {
        $validationRules = [
            'version_edit' => 'required',
        ];
        $validationMessages = [
            'version_edit.required' => 'Vui lòng điền phiên bản đơn'
        ];
        $validated = $request->validate($validationRules, $validationMessages);
        DB::beginTransaction();
        try {
            $params = $request->all();
            $order = Order::findOrFail($id);
            if($order->version_edit != request()->get('version_edit')) {
                return resError('Đơn đã được thay đổi, vui lòng tải lại trang để cập nhật thông tin đơn');
            }
            $validate = $this->isCheckValidPackge($order);
            if ($validate) {
                $result = [
                    'error' => $validate
                ];
                return resErrorWithinData($result, null, 422);
            }
            $this->_service->updateQuickInspection($id, $params);
            DB::commit();
            return resSuccess();
        } catch (Exception $exception) {
            DB::rollBack();
           \Log::error($exception);
            return resError();
        }
    }
    public function updateQuickAdvancedInspection(string $id, Request $request): JsonResponse
    {
        $validationRules = [
            'version_edit' => 'required',
        ];
        $validationMessages = [
            'version_edit.required' => 'Vui lòng điền phiên bản đơn'
        ];
        $validated = $request->validate($validationRules, $validationMessages);
        DB::beginTransaction();
        try {
            $params = $request->all();
            $order = Order::findOrFail($id);
            if($order->version_edit != request()->get('version_edit')) {
                return resError('Đơn đã được thay đổi, vui lòng tải lại trang để cập nhật thông tin đơn');
            }
            $validate = $this->isCheckValidPackge($order);
            if ($validate) {
                $result = [
                    'error' => $validate
                ];
                return resErrorWithinData($result, null, 422);
            }
            $this->_service->updateQuickAdvancedInspection($id, $params);
            DB::commit();
            return resSuccess();
        } catch (Exception $exception) {
            DB::rollBack();
            return resError($exception->getMessage());
        }
    }

    public function updateQuickWoodworking(string $id, Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            $order = Order::findOrFail($id);
            $validate = $this->isCheckValidPackge($order);
            if ($validate) {
                $result = [
                    'error' => $validate
                ];
                return resErrorWithinData($result, null, 422);
            }
            $this->_service->updateQuickWoodworking($id, $params);
            DB::commit();
            return resSuccess();
        } catch (Exception $exception) {
            DB::rollBack();
            \Log::error($exception);
            return resError();
        }
    }

    public function updateShockProof(string $id, Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            $order = Order::findOrFail($id);
            $validate = $this->isCheckValidPackge($order);
            if ($validate) {
                $result = [
                    'error' => $validate
                ];
                return resErrorWithinData($result, null, 422);
            }
            $this->_service->updateShockProof($id, $params);
            DB::commit();
            return resSuccess();
        } catch (Exception $exception) {
            DB::rollBack();
            \Log::error($exception);
            return resError();
        }
    }
    public function updateWarehouse(string $id, Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            $this->_service->updateWarehouse($id, $params);
            DB::commit();
            return resSuccess();
        } catch (Exception $exception) {
            DB::rollBack();
            return resError();
        }
    }
    public function updateCustomerDelivery(string $id, Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            $this->_service->updateCustomerDelivery($id, $params);
            DB::commit();
            return resSuccess();
        } catch (Exception $exception) {
            DB::rollBack();
            return resError();
        }
    }
    public function updateOfficial(string $id, Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            $order = Order::findOrFail($id);
            if($order->status == OrderConstant::KEY_STATUS_DONE) {
                return resError('Đơn hoàn thành không được phép sửa');
            }

            if($order->status == OrderConstant::KEY_STATUS_ORDERED) {
                $package = OrderPackage::query()
                    ->where('order_id', $id)
                    ->where('order_type', Order::class)
                    ->where('status', PackageConstant::STATUS_RECEIVED_GOODS)
                    ->exists();
                if($package) {
                    return resError('Không thể đổi loại vận chuyển vì đã có kiện hàng ở trạng thái không hợp lệ');
                }
            }
            $this->_service->updateOfficial($id, $params);
            DB::commit();
            return resSuccess();
        } catch (Exception $exception) {
            DB::rollBack();
            return resError();
        }
    }

    public function updateOfficialIsPartner(string $id, Request $request): JsonResponse
    {
        $validationRules = [
            'supplier_id' => 'required|exists:suppliers,id',
            'official_is_partner' => 'required|in:0,1'
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        DB::beginTransaction();
        try {
            $params = $request->all();
            $order = Order::findOrFail($id);
            if($order->status == OrderConstant::KEY_STATUS_DONE) {
                $result = ['error' => 'Đơn hoàn thành không được phép sửa'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            if($order->official != OrderConstant::OFFICIAL) {
                $result = ['error' => 'Phải là đơn chính ngạch mới được tích đi bên đối tác'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            $this->_service->updateOfficialIsPartner($id, $params);
            DB::commit();
            return resSuccess();
        } catch (Exception $exception) {
            DB::rollBack();
            return resError();
        }
    }

    public function getOrderHistory(OrderHistoryService $orderHistoryService, Request $request, $order_id): JsonResponse
    {
        $params = $request->all();
        $params['order_id'] = $order_id;
        $results = $orderHistoryService->index($params);
        return resSuccessWithinData($results);
    }

    /**
     * @throws BindingResolutionException
     */
    public function changeStatus(OrderChangeStatusRequest $request, Order $order): JsonResponse
    {
        // Check nếu đơn ở trạng thái Đã đặt cọc, trạng thái mới
        $status = $request->status ?? '';
        $arr_status_new = [
            OrderConstant::KEY_STATUS_WAIT_TO_PAY,
            OrderConstant::KEY_STATUS_CANCEL,
        ];
        if ($order->status == Order::ORDER_STATUS_DEPOSITED && !in_array($status, $arr_status_new)) {
            return resError('Không đổi được do trạng thái ko hợp lệ', 422);
        }

        if ($order->has_edit == 2 && in_array($order->status, [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY])) {
            return resError('Đơn hàng đã có sự thay đổi, vui lòng bấm lưu', 422);
        }
        $status = $request->status ?? '';
        if (
            in_array($order->status,
                [
                    OrderConstant::KEY_STATUS_DEPOSITED,
                    OrderConstant::KEY_STATUS_ORDERED,
                    OrderConstant::KEY_STATUS_WAIT_TO_PAY
                ])
        ) {
            $totalAmount = ($order->thuongdo_id != null && $order->has_edit == 0) ?
                $order->thuongdo_tong_tien_don_hang : $order->getTotalAmountNotShip();
            $depositCostReality = roundMoney($totalAmount * $order->deposit_percent / 100); // số tiền cần đặt cọc thực thế
            if ($depositCostReality - 5 > $order->deposit_cost) {
                return resError( 'Không được thay đổi trạng thái đơn vì số tiền đặt cọc bị chênh lệch', 422);
            }
        }
        $statusNew = $status;
        $statusOld = $order->status;
        $config = env('SEND_SMS_ORDER_PACKAGE', '');
        if ($statusNew && $statusNew != $statusOld && $config == 'PRODUCT') {
            $payload['user_id'] = $order->customer_id ?? 0;
            $fcmTokenService = app()->make(FcmTokenService::class);
            $title = 'Thay đổi trạng thái đơn hàng';
            $body = 'Đơn hàng ' . $order->code . ' được chuyển sang trạng thái ' . Order::LIST_STATUS[$statusNew] ?? '';
            $dataSent = [
                'type' => 'orders',
                'body' => (string) $order->id
            ];
            $fcmTokenService->sendMessageForApp($payload, $title, $body, $dataSent);
        }
        return $this->_service->changeStatus($order, $status);
    }
    /**
     * @param  Request $request
     * @return JsonResponse
     */
    public function isWaitingForSale(Request $request): JsonResponse
    {
        $id = $request->get('id');
        $isWaitingForSale = $request->get('is_waiting_for_sale');
        Order::query()->find($id)->update(['is_waiting_for_sale' => $isWaitingForSale]);

        return resSuccess();
    }
    public function isQuoting(Request $request): JsonResponse
    {
        $id = $request->get('id');
        $isQuoting = $request->get('is_quoting');
        Order::query()->find($id)->update(['is_quoting' => $isQuoting]);

        return resSuccess();
    }
    public function isWaitingConfirm(Request $request): JsonResponse
    {
        $id = $request->get('id');
        $isWaitingConfirm = $request->get('is_waiting_confirm');
        Order::query()->find($id)->update(['is_waiting_confirm' => $isWaitingConfirm]);
        return resSuccess();
    }


    public function isChinaShipping(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $id = $request->get('id');
            $isChinaShipping = $request->get('is_china_shipping');
            // Sẽ check cả đơn đó còn ncc nào có phí ship nữa không
            if ($isChinaShipping) {
                $content = 'is_china_shipping';
                Order::query()->find($id)->update(['is_china_shipping' => $isChinaShipping]);
            } else {
                $content =  'is_not_china_shipping';
                // Trường hợp không có thì phải check lại nếu vẫn còn ncc khác có ship thì không được update lại Order
                if (!OrderSupplier::query()
                    ->where('order_id', $id)
                    ->where('supplier_id', $request->get('supplier_id'))
                    ->where('is_china_shipping', 1)
                    ->exists()) {
                    Order::query()->find($id)->update(['is_china_shipping' => 0]);
                }
            }
            OrderSupplier::query()
                ->where('order_id', $id)
                ->where('supplier_id', $request->get('supplier_id'))
                ->update(['is_china_shipping' => $isChinaShipping]);
            //Tạo lịch sử
            $modifies['china_shipping'] = $isChinaShipping;

            (new ActivityService())->productActivity(
                $modifies,
                $id,
                '',
                $content,
                '',
                null,
                1,
                $request->get('supplier_id'));
            DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            DB::rollBack();
            return resError($e->getMessage(), $e->getCode());
        }

    }
    /**
     * @param  Request $request
     * @return JsonResponse
     */
    public function updateOrderCostAndChinaShippingCost(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $id = $request->get('id');
            $orderCost = $request->get('order_cost_real');
            $chinaShippingCost = $request->get('china_shipping_cost_real');
            $order = Order::query()->findOrFail($id);

            if(isset($orderCost) && $orderCost >= 0) {
                $order->orderSuppliers()->update(['order_cost_real' => $orderCost]);
                $order->order_cost_real = $orderCost ? $orderCost * $order->exchange_rate : 0;
            }

            if(isset($chinaShippingCost) && $chinaShippingCost >= 0) {
                $order->orderSuppliers()->update(['china_shipping_cost_real' => $chinaShippingCost]);
                $order->china_shipping_cost_real = $chinaShippingCost ? $chinaShippingCost : 0;
            }

            $order->save();

            DB::commit();
            return resSuccess();
        } catch (Exception $exception) {
            \Log::info($exception);
            DB::rollBack();
            return resError();
        }
    }


    public function updateMessage(): array
    {
        return [];
    }

    public function updateRequest(string $id): array
    {
        return [
            /*"warehouse_id" => 'required|exists:warehouses,id',
            "customer_delivery_id" => 'required|exists:customer_deliveries,id',*/
            "removes.suppliers" => 'array',
            "removes.suppliers.*" => 'exists:suppliers,id',
            "removes.products" => 'array',
            "removes.products.*" => 'exists:order_details,id',
            //"products" => ['array', new MaxProductsInOrder(true)],
            //"products" => ['array', 'max:50', new MinProductsInOrder(true)],
            "products" => ['array', 'max:500'],
            "products.*.id" => 'exists:order_details,id',
            /*"products.*.modifies.link" => 'url',*/
            "products.*.modifies.unit_price_cny" => 'numeric|min:0',
            "products.*.modifies.quantity" => 'numeric|min:0',
            "suppliers" => 'array',
            "suppliers.*.id" => 'exists:suppliers,id',
            "suppliers.*.modifies.delivery_type" => 'in:'.implode(',', array_keys(OrderConstant::DELIVERIES_TEXT)),
            "suppliers.*.modifies.is_woodworking" => 'boolean',
            "suppliers.*.modifies.is_shock_proof" => 'boolean',
            "suppliers.*.modifies.is_inspection" => 'boolean',
            "suppliers.*.notes.public" => 'max:500',
            "suppliers.*.notes.private" => 'max:500',
            "suppliers.*.order_fee" => 'numeric|min:0',
            "suppliers.*.discount_cost" => 'numeric|min:0',
            "suppliers.*.inspection_cost" => 'numeric|min:0',
            "suppliers.*.china_shipping_cost" => 'numeric|min:0',
            "suppliers.*.order_cost" => 'numeric|min:0',
        ];
    }

    protected function getAttributes(): array
    {
        return [

            "removes.suppliers" => 'Nhà cung cấp',
            "removes.suppliers.*" => 'Nhà cung cấp',
            "removes.products" => 'Sản phẩm',
            "removes.products.*" => 'Sản phẩm',
            "products" => 'Sản phẩm',
            "products.*.id" => 'Sản phẩm',
            "products.*.modifies.link" => 'Đường dẫn',
            "products.*.modifies.unit_price_cny" => 'Giá',
            "products.*.modifies.quantity" => 'Số lượng',
            "suppliers" => 'Nhà cung cấp',
            "suppliers.*.id" => 'Nhà cung cấp',
            "suppliers.*.modifies.delivery_type" => 'Loại vận chuyển',
            "suppliers.*.modifies.is_woodworking" => 'Loại vận chuyển',
            "suppliers.*.modifies.is_shock_proof" => 'Chống shock',
            "suppliers.*.modifies.is_inspection" => 'Kiểm đếm',
            "suppliers.*.notes.public" => 'Ghi chú khách hàng - Thương Đô',
            "suppliers.*.notes.private" => 'Ghi chú nội bộ',
            "suppliers.*.discount_cost" => 'Giá triết khấu',
            "suppliers.*.inspection_cost" => 'Giá kiểm đếm',
            "suppliers.*.china_shipping_cost" => 'Vận chuyện nội địa TQ',
            "suppliers.*.order_cost" => 'Giá đơn hàng',
            "suppliers.*.order_fee" => 'Phí đặt hàng',
        ];
    }

    /**
     * @param  string  $packageIds
     * @return JsonResponse
     */
    public function getDebt(string $packageIds): JsonResponse
    {
        $packageIds = explode(',', $packageIds);
        return resSuccessWithinData(
            (new DeliveryService())->getDebtCostByOrder(OrderPackage::query()->findMany($packageIds))
        );
    }

    public function countOrderWaithAssign(Request $request)
    {
        $orderRepo = \App::make('App\Models\Order');
        $orderCareCount = $orderRepo->whereNull('staff_care_id')
                                    ->where('reset_staff', 'counselor')
                                    ->where('status', '<>', 'status_4')
                                    ->count();

        $orderCounselorCount = $orderRepo->whereNull('staff_counselor_id')
                                    ->where('reset_staff', 'care')
                                    ->where('status', '<>', 'status_4')
                                    ->count();

        $data_result = [
            'orderCareCount'      => $orderCareCount,
            'orderCounselorCount' => $orderCounselorCount,
        ];

        return resSuccessWithinData($data_result);
    }

    public function hasEditTrue(Request $request, $id)
    {
        // Đánh đấu đơn đã bị edit
        $orderRepo = \App::make('App\Models\Order');
        $orderDB = $orderRepo->where('id', $id)->where('has_edit', 0)->first();
        if ($orderDB && $orderDB->has_edit = 0) {
            $orderDB->has_edit = 1;
            $orderDB = Order::withoutEvents(function () use ($orderDB) {
                $orderDB->save();
                return $orderDB;
            });
        }
        return resSuccessWithinData([]);
    }

    public function updateOrderNotiLogic($order, $params)
    {
        $staff_care_id      = isset($params['staff_care_id']) ? $params['staff_care_id'] :  null;
        $staff_counselor_id = isset($params['staff_counselor_id']) ? $params['staff_counselor_id'] :  null;
        $staff_quotation_id = isset($params['staff_quotation_id']) ? $params['staff_quotation_id'] :  null;
        $staff_order_id     = isset($params['staff_order_id']) ? $params['staff_order_id'] :  null;
        $staff_group_id     = isset($params['staff_group_id']) ? $params['staff_group_id'] :  null;

        // Thêm log chuyển nhân viên
        $notificationService = \App::make('App\Services\NotificationService');

        $data_order = [
            'order_code'  => $order->code,
            'order_id'    => $order->id,
        ];
        $data = [
            'group'           => NotifyConstant::KEY_GROUP_ORDER,
            'name'            => NotifyConstant::KEY_NAME_ORDER_CHANGE_STAFF, // Thay đổi nhân viên
            'data'            => json_encode($data_order),
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
        if ($staff_quotation_id) {
            $notificationService->storeNoti($staff_quotation_id, $data);
        }
        if ($staff_order_id) {
            $notificationService->storeNoti($staff_order_id, $data);
        }
    }

    public function updateOrderNotiChangeStatus($order)
    {
        // Chuyển trạng thái -> Chờ đặt cọc    Báo cho nhân viên tư vấn/chăm sóc status_1
        // Chuyển trạng thái -> Đã hủy     Báo cho nhân viên tư vấn/chăm sóc status_6
        // Chuyển trạng thái -> Đã đặt cọc     Báo cho nhân viên Đặt hàng , nhân viên tư vấn/chăm sóc status_2
        // Chuyển trạng thái -> các trạng thái còn lại     Báo cho nhân viên Đặt hàng , nhân viên tư vấn/chăm sóc
        $status = $order->status;

        $staff_care_id      = $order->staff_care_id;
        $staff_counselor_id = $order->staff_counselor_id;
        $staff_group_id     = $order->staff_group_id;
        $staff_order_id     = null;

        if ($status != 'status_1' && $status != 'status_6') {
            $staff_order_id = $order->staff_order_id;
        }

        $notificationService = \App::make('App\Services\NotificationService');

        $data_order = [
            'order_code'       => $order->code,
            'order_id'         => $order->id,
            'order_status'     => $order->status,
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
            'group'           => 2,
            'name'            => $name,
            'data'            => json_encode($data_order),
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
        if ($staff_order_id) {
            $notificationService->storeNoti($staff_order_id, $data);
        }
    }

    public function bankForUserIntroduce($order)
    {
        $customerDB = $order->customer;

        $timeOrder = $order->created_at->timestamp;
        // Nếu tạo đơn trước lúc gán người giới thiệu
        if ($customerDB->customer_introduce_time_add > $timeOrder) {
            \Log::info('Gán sau khi tạo đơn nên bị mất lượt rồi');
            return;
        }

        $customerRepo = \App::make('App\Models\Customer');
        $customerIntroduce = $customerRepo->where('code', $customerDB->customer_introduce_id)
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
                $fundRepo = \App::make('App\Models\Fund');
                $fund = $fundRepo->where('current_balance', '>=', $totalMoney)->first();

                // Nếu không tìm dc ai thì ghi log vào đơn hàng
                if (is_null($fund)) {
                    // Thanh toán số tiền hoa hồng $số tiền  cho khách $ABC thành công / thất bại
                    $contentLog = 'Thanh toán số tiền hoa hồng ' . number_format($totalMoney,0,".",".") . 'đ cho khách hàng ' . $customerIntroduce->code . ' thất bại';

                    $userOnline = \Auth::user();
                    // $logService = \App::make('App\Services\ActivityService');
                    // $logService->setOrderLog($userOnline, $contentLog, $order->id);

                    $job = new \App\Jobs\MakeLogOrderStatusDone($userOnline, $contentLog, $order);
                    dispatch($job)->delay(now()->addSeconds(2))->onQueue('sent-noti-admin'); // Dùng tạm chắc k ảnh hưởng gì
                }

                if ($fund) {
                    // Ghi log vào đơn
                    $contentLog = 'Thanh toán số tiền hoa hồng ' . number_format($totalMoney,0,".",".") . 'đ cho khách hàng ' . $customerIntroduce->code . ' thành công';

                    $userOnline = \Auth::user();
                    // $logService = \App::make('App\Services\ActivityService');
                    // $logService->setOrderLog($userOnline, $contentLog, $order->id);

                    $job = new \App\Jobs\MakeLogOrderStatusDone($userOnline, $contentLog, $order);
                    dispatch($job)->delay(now()->addSeconds(2))->onQueue('sent-noti-admin'); // Dùng tạm chắc k ảnh hưởng gì

                    // Trừ tiền từ quỹ
                    $fundTypePayModel = \App::make('App\Models\FundTypePay');
                    $fundTypePay8 = $fundTypePayModel->where('code', 8)->first();
                    if ($fundTypePay8) {
                        // Trừ tiền vào quỹ
                        $fundTransactionService = \App::make('App\Services\FundTransactionService');

                        $contentLog = 'Thanh toán số tiền hoa hồng ' . number_format($totalMoney,0,".",".") . 'đ từ đơn hàng ' . $order->code . ' cho khách hàng ' . $customerIntroduce->code . ' thành công';
                        $data_fund = [
                            'type_object'                => 2,
                            'customer_code'              => $customerIntroduce->code,
                            'type_pay'                   => 2,

                            'fund_id'                    => $fund->id,
                            'fund_type_pay_id'           => $fundTypePay8->id,
                            'fund_type_pay_code'         => $fundTypePay8->code,

                            'money'                      => $totalMoney,
                            'note'                       => $contentLog,
                            'lock'                       => 1
                        ];
                        $result = $fundTransactionService->store($data_fund);
                    }

                    // Nạp tiền vào ví khách
                    $amount = $totalMoney;
                    $content = $contentLog;
                    $customer_id = $customerIntroduce->id;
                    $soure = $order;

                    (new \App\Services\TransactionService())->setTransactionIncrement(
                        $amount,
                        \App\Constants\TransactionConstant::STATUS_REFUND,
                        $content,
                        $customer_id,
                        $soure,
                        $fund ? $order : null
                    );
                }
            }
        }
    }

    public function checkPointLevel($order)
    {
        $customerDB = $order->customer;

        // Nếu trạng thái đơn là đã đặt cọc
        if ($order->status == \App\Constants\OrderConstant::KEY_STATUS_DEPOSITED) {
            if ($customerDB->point_level < 75) {
                $customerDB->point_level = 75;
                $customerDB->save();
            }
        }

        // Nếu trạng thái đơn là hoàn thành
        if ($order->status == \App\Constants\OrderConstant::KEY_STATUS_DONE) {
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
        if ($order->status == \App\Constants\OrderConstant::KEY_STATUS_WAITING_DEPOSIT) {
            if ($customerDB->point_way < 80) {
                $customerDB->point_way = 80;
                $customerDB->save();
            }
        }
        // Nếu trạng thái đơn là Đặt hàng
        // if ($order->status == \App\Constants\OrderConstant::KEY_STATUS_ORDERED) {
        //     if ($customerDB->point_way < 100) {
        //         $customerDB->point_way = 100;
        //         $customerDB->save();
        //     }
        // }
    }

    /**
     * get list order paginate by customer
     * @param CustomerOrderRequest $request
     * @return mixed
     */
    public function getOrdersByCustomer(CustomerOrderRequest $request)
    {
        try {
            $params = $request->only('customer_id', 'page', 'per_page');
            return $this->_service->getOrderByCustomer($params);
        } catch (Exception $e) {
           return resError();
        }
    }

    /** change deposit percent
     * @param OrderOfferRequest $request
     * @param Order $order
     */
    public function changeDeposit(OrderOfferRequest $request, Order $order)
    {
        DB::beginTransaction();
        try {
            $data = $request->only('deposit_percent', 'order_percent', 'is_only_deposit_percent');
            if(isset($data['deposit_percent']) && $data['deposit_percent'] < 0) {
                return resError('% đặt cọc phải lớn hơn 0');
            }
            if(isset($data['order_percent']) && $data['order_percent'] < 0) {
                return resError('% phí đặt hàng phải lớn hơn 0');
            }
            $orderRefund = OrderRefund::query()
                ->where('order_id', $order->id)
                ->where('status', OrderRefund::STATUS_PROCESSING)
                ->exists();
            if($orderRefund) {
                return resError('Không sửa được % cọc do đơn đang có yêu cầu hoàn tiền chưa xử lý.');
            }
            $msg = $this->_service->changeDeposit($order, $data);
            DB::commit();
            if($msg) {
                return resError($msg);
            }
            return resSuccess($msg);
        } catch (Exception $e) {
            DB::rollBack();
            return resError($e->getMessage());
        }
    }

    public function storeAdvanceMoney(StoreOrderAdvanceMoneyRequest $request, Order $order): JsonResponse
    {
        $notificationService = App::make('App\Services\NotificationService');
        try {
            DB::beginTransaction();
            $data = $request->only('amount');
            /*if($order->deposit_cost + $data['amount'] > $order->getTotalAmount()){
                return resError("Đã vượt quá giá trị đơn hàng");
            }*/
            $create = $this->_service->storeAdvanceMoney($order, $data);
            if($create){
                $keyRole = 'ke-toan';
                $roles = Role::query()->where('name','like', '%'.$keyRole .'%')->pluck('id')->toArray();
                $users = User::query()->whereHas('roles', function($role) use($roles) {
                    $role->whereIn('id', $roles);
                })->where('status', 1)->get();
                $dataNotification = [
                    'group' => 2,
                    'name' => 2013,
                    'data' => json_encode(['order_code' => $order->code, 'amount' => $create->amount]),
                    'time_create' => time(),
                    'status' => 1
                ];
                foreach($users as $user) {
                    $notificationService->storeNoti($user->id, $dataNotification);
                }
            }

            DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            DB::rollback();
            Log::error($e);
            return resError($e->getMessage());
        }
    }

    public function approveAdvanceMoney(UpdateOrderAdvanceMoneyRequest $request): JsonResponse
    {
        try {
            $ids = $request->input('ids');
            $response = $this->_service->approveAdvanceMoney($ids);
            $message = '';
            if (!empty($response)) {
                $message = 'Các giao dịch [' . implode(', ', $response) . '] không thành công!';
                $result = [
                    'error' => $message
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            return resSuccess($message);
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function cancelAdvanceMoney(UpdateOrderAdvanceMoneyRequest $request): JsonResponse
    {
        try {
            $ids = $request->input('ids');
            $this->_service->cancelAdvanceMoney($ids);
            return resSuccess();
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function listAdvanceMoney(ListOrderAdvanceMoneyRequest $request): JsonResponse
    {
        try {
            $params = $request->only(
                'customer_code', 'customer_name',
                'customer_phone', 'order_code',
                'status', 'page', 'per_page'
            );
            $data = $this->_service->listAdvanceMoney($params);
            $_paginateResource = PaginateJsonResource::class;
            $_resource = OrderAdvanceMoneyResource::class;
            $extra = [
                'listStatus' => OrderConstant::ORDER_ADVANCE_STATUSES
            ];
            return resSuccessWithinData(new $_paginateResource($data, $_resource, $extra));
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }
    public function serviceCharge(Request $request)
    {
        $params = $request->all();
        $time = data_get($params, 'time');
        $year = data_get($params, 'year');
        [$startDate, $endDate] = getStartDateEndDateByType($time, $year);
        $selectBase = [
            // DB::raw('SUM(order_fee - discount_cost) AS total_order_fee'),
            DB::raw('SUM(order_fee) AS total_order_fee'),
            DB::raw('SUM(order_cost) AS total_order_cost'),
            DB::raw('SUM((orders.order_cost - orders.order_cost_real) +
                ((orders.china_shipping_cost - orders.china_shipping_cost_real) * orders.exchange_rate) +
                (orders.bonus * orders.exchange_rate)) AS total_negotiate'
            ),
        ];
        if(!empty($time) && $time == "year"){
            array_push($selectBase, DB::raw('DATE_FORMAT(date_done, "%m") as date'));
            $interval = DateInterval::createFromDateString('1 month');
            $formatDate = $returnDate = "m";
        }else{
            array_push($selectBase, DB::raw('DATE(date_done) as date'));
            $interval = DateInterval::createFromDateString('1 day');
            $formatDate = "Y-m-d";
            $returnDate = "d";
        }
        $query = Order::select($selectBase)
                        ->where('status', OrderConstant::KEY_STATUS_DONE)
                        ->whereBetween('date_done', [$startDate, $endDate]);
        $auth = getCurrentUser();
        if($auth->hasPermissionName('report.view-only-quote-by-self')) {
            $query->where(function($q) use ($auth) {
                $q->where('staff_order_id', $auth->id)
                    ->orWhere('staff_quotation_id', $auth->id);
            });
        }
        $orders = $query->groupByRaw('date')->get();
        $period = new DatePeriod($startDate, $interval, $endDate);
        $response = [];
        foreach ($period as $key => $interval){
            $timeCheck = $interval->format($formatDate);
            $result = $orders->filter(function ($item) use ($timeCheck) {
                return $item->date == $timeCheck;
            })->first();
            $response[$key]['interval'] =  $interval->format($returnDate);
            $response[$key]['total_order_fee'] =  !empty($result) ? $result->total_order_fee : 0;
            $response[$key]['total_order_cost'] =  !empty($result) ? $result->total_order_cost : 0;
            $response[$key]['total_negotiate'] =  !empty($result) ? $result->total_negotiate : 0;
        }
        return resSuccessWithinData($response);
    }

    public function changeEcommerce($orderId, Request $request): JsonResponse
    {
        try {
            $params = $request->only('ecommerce');
            Order::query()->where("id", $orderId)->update($params);
            $ecommerce = $request->ecommerce ?? '';
            if ($ecommerce) {
                $source = '';
                if (isset(OrderConstant::ECOMMERCE[$ecommerce])) {
                    if (in_array($ecommerce, [ OrderConstant::ECOMMERCE_TAO_BAO, OrderConstant::ECOMMERCE_T_MALL])) {
                        $source = 'taobao';
                    } elseif ($ecommerce == OrderConstant::ECOMMERCE_1688) {
                        $source = '1688';
                    }
                    if ($source) {
                        OrderDetail::query()->where('order_id', $orderId)->update(['source' => $source]);
                    }
                }

            }

            return resSuccess();
        } catch (Exception $e) {
            Log::info($e);
            return resError();
        }
    }

    public function export(Request $request)
    {
        $params = $request->all();
        try {
            $time = Carbon::now()->format('YmdHms');
            $fileName = getPathFileExport('order').'/order_'.$time.'.xlsx';
            dispatch(new ExportOrderJob($params, \Auth::user(), $fileName))->onQueue('export-1800');
            \Log::info("Nhân viên có id= ".getCurrentUserId()." đã click xuất excel đơn hàng");
            return resSuccess();
        } catch (Exception $e) {
            \Log::info($e);
            return resError();
        }
    }

    public function updateBonus($orderId, Request $request)
    {
        try {
            $params = $request->only('bonus');
            Order::where("id", $orderId)->update($params);
            return resSuccess();
        } catch (Exception $e) {
            \Log::info($e);
            return resError();
        }
    }

    public function getOrderDetail($id)
    {
        try {
            $orderDetail = OrderDetail::find($id);
            return resSuccessWithinData($orderDetail);
        } catch (Exception $e) {
            \Log::info($e);
            return resError();
        }
    }

    public function getUserInOrder($orderId)
    {

        try {
            $orderDetail = $this->_service->getUserInOrder($orderId);
            return resSuccessWithinData($orderDetail);
        } catch (Exception $e) {
            return resError();
        }
    }

    public function previewContactDocx($orderId, Request $request)
    {
        try {
            $params = $request->all();
            $isSubmit = data_get($params, 'isSubmit', false);
            $typeContract = data_get($params, 'typeContract', 1);
            $file = $request->file('file');
            $order = Order::findOrFail($orderId);
            $customer = $order->customer;
            $now = Carbon::now();
            $path = $this->createDirectory();
            if($order && $customer){
                $orderContractService = \App::make('App\Services\OrderContractService');
                $fileDefault = 'hop_dong_mau.docx';
                $customerDelivery = $order->customerDelivery;
                if($typeContract == 1){
                    $tProcessor = new TemplateProcessor(public_path('contract/'.$fileDefault), 'Word2007');
                    $tProcessor->setValues([
                        'name'=> $customer->name,
                        'address' => ($customerDelivery && $customerDelivery->getAddressOnlyAttribute()) ? $customerDelivery->getAddressOnlyAttribute() : "",
                        'phone_number' => $customer->phone_number,
                        'email' => $customer->email,
                        'day' => $now->format('d'),
                        'month' => $now->format('m'),
                        'year' => $now->format('Y'),
                        'signature' => '${signature}'
                    ]);
                    $fileConvertName = str_replace('.doc', '_'.$now->format('YmdHis').'.doc', $fileDefault);
                    $fullPath = $path.'/'.$fileConvertName;
                    $tProcessor->saveAs($fullPath);
                    if($isSubmit){
                        $data = [
                            'path' => $fileConvertName,
                            'original_name' => $fileDefault,
                            'creator_id' => getCurrentUserId(),
                        ];
                        $order->orderContracts()->create($data);
                        $orderContractService->setLogOrder($order, $fileDefault);
                        return resSuccess();
                    }else{
                        // download xong xoa file
                        return response()->download($fullPath)->deleteFileAfterSend(true);
                    }
                }else{
                    if(!$file){
                        return resErrorWithinData('', 'File không được để trống', 422);
                    }else{
                        $fileName = $file->getClientOriginalName();
                        $fileConvertName = str_replace('.doc', '_'.$now->format('YmdHis').'.doc', $fileName);
                        $fullPath = $path.'/'.$fileConvertName;
                        $file->move($path, $fileConvertName);
                        $data = [
                            'path' => $fileConvertName,
                            'original_name' => $fileName,
                            'creator_id' => getCurrentUserId(),
                        ];
                        $order->orderContracts()->create($data);
                        $orderContractService->setLogOrder($order, $fileName);
                        return resSuccess();
                    }
                }
            }else{
                return resError();
            }

        } catch (Exception $e) {
            \Log::error($e);
            return resError();
        }
    }
    private function createDirectory()
    {
        $disk = OrderConstant::DISK_CONTRACT;
        $fullName = $disk;
        $path = public_path($fullName);
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        return $path;
    }

    public function mapSignature($contract_id, Request $request)
    {
        // dd($contract_id, $request->all());
        try {
            $contract = OrderContract::findOrFail($contract_id);
            $file = $request->file('file');
            $order = $contract->order;
            if($contract){
                if($contract->signing_time){
                    return resError("Hợp đồng đơn hàng đã được kí", 400);
                }
                $path = public_path('/');
                $fileName = 'file_image.png';
                $file->move(storage_path('/uploads/'), $fileName);

                $pathFileOrinal = public_path('storage/uploads/order_contract/').$contract->path;
                $tProcessor = new TemplateProcessor($pathFileOrinal, 'Word2007');
                $fullName = storage_path('/uploads/').$fileName;
                $tProcessor->setImageValue('signature', ['path' => $fullName, 'width' => 130, 'height' => 130,]);
                // dd(33, $fullName);
                $tProcessor->saveAs($pathFileOrinal);
                unlink($fullName);
                $contract->signing_time = Carbon::now();
                $contract->save();
                $orderContractService = \App::make('App\Services\OrderContractService');
                $orderContractService->setLogOrderFromCustomer($order, $contract);
            }
            return resSuccessWithinData([
                'order_code' => $order->code,
                'contract_id' => $contract->id
            ]);
        } catch (Exception $e) {
            \Log::error($e);
            return resError();
        }
    }
    public function downloadContract($contract_id)
    {
        $contract = $contract = OrderContract::findOrFail($contract_id);
        $pathFileOrinal = public_path('storage/uploads/order_contract/').$contract->path;
        \Log::info($pathFileOrinal);
        $tProcessor = new TemplateProcessor($pathFileOrinal, 'Word2007');
        $fullName = storage_path('/uploads/').$contract->path;
        $tProcessor->saveAs($fullName);
        return response()->download($fullName)->deleteFileAfterSend(true);
    }

    /**
     * Lấy tổng tiền đơn hằng bằng tệ và vnđ
     *
     * @return JsonResponse
     */
    public function getListAmount(): JsonResponse
    {
        // cần truyền lên qua query string page và perPage của pagination,
        // và mọi filter fields khác tương tự như pagination
        return $this->_service->getListAmount();
    }

    public function listCancel()
    {
        return $this->_service->listCancel();
    }

    public function getProductOfOrder(Request $request, $id)
    {
        $orderModel = \App::make('App\Models\Order');
        $orderDB = $orderModel->where('code', $id)->where('status', '!=', OrderConstant::KEY_STATUS_CANCEL)->first();

        // Check tồn tại
        if (is_null($orderDB)) {
            $result = ['money' => 'Đơn hàng không tồn tại'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check trạng thái đơn
        $arr_status = [
            OrderConstant::KEY_STATUS_ORDERED,
            OrderConstant::KEY_STATUS_DONE,
        ];
        if (!in_array($orderDB->status, $arr_status)) {
            $result = ['money' => 'Trạng thái đơn không hợp lệ'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Trả dữ liệu
        $orderDetailModel = \App::make('App\Models\OrderDetail');
        $orderDetailDBs = $orderDetailModel->where('order_id', $orderDB->id)->get();

        $list_order_detail = [];
        if (count($orderDetailDBs) > 0) {
            foreach ($orderDetailDBs as $key => $orderDetail) {
                $temp = [
                    'id'                 => (int) $orderDetail->id,
                    'name'               => $orderDetail->name,
                    'link'               => $orderDetail->link,
                    'image'              => $orderDetail->getImage(),
                    'classification'     => $orderDetail->classification,
                    'unit_price_cny'     => $orderDetail->unit_price_cny,
                    'quantity'           => $orderDetail->quantity,
                    'amount_cny'         => $orderDetail->amount_cny,
                    'note'               => $orderDetail->note,

                    'quantity_new'       => $orderDetail->quantity,
                    'unit_price_cny_new' => $orderDetail->unit_price_cny,
                ];
                array_push($list_order_detail, $temp);
            }
        }

        // Thêm phần số tiền kiểm đếm cũ
        $orderSupplier = $orderDB->orderSuppliers->first();

        $data_result = [
            'order_id'            => (int)$orderDB->id,
            'order_code'          => $orderDB->code,
            'exchange_rate'       => $orderDB->exchange_rate, // Tỉ giá
            'order_percent'       => $orderDB->order_percent, // % đặt hàng
            'order_status'        => $orderDB->status,
            'order_is_inspection' => $orderDB->is_inspection, // Kiểm đếm
            'order_status_text'   => OrderConstant::STATUSES[$orderDB->status],
            'list_order_detail'   => $list_order_detail,
            'inspection_old'      => $orderSupplier ? $orderSupplier->inspection_cost : 0,
            'order_cost_real'     => round((float)$orderDB->order_cost_real / $orderDB->exchange_rate, 2)
        ];


        return resSuccessWithinData($data_result);
    }

    public function changeSupplier(ChangeSupplierRequest $request)
    {
        try {
            $data = $request->only('order_id', 'supplier_id_from', 'supplier_id_to', 'products', 'is_new', 'is_china_shipping',
                'supplier_name', 'supplier_code', 'customer_id', 'is_inspection', 'is_woodworking', 'is_advanced_inspection');
            $this->_service->changeSupplier($data);

            return resSuccess('Tách nhà cung cấp thành công!');
        } catch (Exception $e) {
            return resError($e->getMessage() . '   file  ' . $e->getFile() . '   line ' . $e->getLine() );
        }
    }

    public function fixOrderTD(Request $request)
    {
        $orderModel = \App::make('App\Models\Order');

        $arr_status = [
            OrderConstant::KEY_STATUS_WAITING_QUOTE,
            OrderConstant::KEY_STATUS_WAITING_DEPOSIT,
            OrderConstant::KEY_STATUS_RECONFIRM,
        ];

        $orderDBs = $orderModel->whereNotNull('thuongdo_id')
                            ->whereIn('status', $arr_status)
                            ->where('deposit_percent', 0)
                            // ->where('order_code', 1)
                            // ->limit(500)->offset(0)
                            ->get();

        if (count($orderDBs)) {
            foreach ($orderDBs as $key => $orderDB) {
                $job = new \App\Jobs\SyncTD\SyncDepoistPercent($orderDB);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin');
            }
        }
        return;


        foreach ($orderDBs as $key => $orderDB) {
            // Cập nhật
            $configService = \App::make('App\Services\ConfigService');
            $accounttingService = \App::make('App\Services\AccountingService');
            $orderDetailModel = \App::make('App\Models\OrderDetail');
            $customerDB = $orderDB->customer;
            if ($customerDB) {
                // % đặc cọc : deposit_percent
                $deposit_percent = data_get($customerDB->offer, 'deposit_rate') ? data_get($customerDB->offer, 'deposit_rate') : $configService->getResultFromValueByLevel($customerDB->level)->deposit; // Lấy % đặt cọc theo level
                if ($deposit_percent > 0) {
                    $orderDB->deposit_percent = $deposit_percent;
                }

                // Phí đặt hàng, % đặt hàng
                if ($orderDB->order_fee == 0) {
                    $order_fee = $accounttingService->getOrderFee($orderDB->order_cost, $orderDB->customer_id, $orderPercent);
                    $order_percent = $orderPercent;

                    // Lưu
                    if ($order_fee) {
                        $orderDB->order_fee = $order_fee;
                        $orderDB->order_percent = $order_percent;
                    }

                    // Chiết khấu
                    if ($orderDB->discount_cost == 0) {
                        $discount_cost = $accounttingService->getDiscountCost($order_fee, $orderDB->customer_id);
                        if ($discount_cost) {
                            $orderDB->discount_cost = $discount_cost;
                        }
                    }
                }

                // Kiểm đếm
                if ($orderDB->is_inspection == 1) {
                    $orderDetails = $orderDetailModel->where('order_id', $orderDB->id)->get();
                    if (count($orderDetails)) {
                        $total_quantity = $orderDetails->sum('quantity');
                        $inspection_cost = $accounttingService->getInspectionCost($total_quantity);
                        if ($inspection_cost > 0) {
                            $orderDB->inspection_cost = $inspection_cost;
                        }
                    }
                }

                $orderDB = Order::withoutEvents(function () use ($orderDB) {
                    $orderDB->save();
                    return $orderDB;
                });
            }
        }
    }

    public function fixOrderTDV2(Request $request)
    {
        $orderModel = \App::make('App\Models\Order');

        $arr_status = [
            OrderConstant::KEY_STATUS_DEPOSITED,
            OrderConstant::KEY_STATUS_WAIT_TO_PAY,
        ];
        return;

        $orderDBs = $orderModel->whereNotNull('thuongdo_id')
                            ->whereIn('status', $arr_status)
                            ->where('deposit_percent', 0)
                            // ->where('order_code', 1)
                            // ->limit(500)->offset(0)
                            ->get();

        \Log::info("Số đơn đặt cọc");
        \Log::info(count($orderDBs));

        if (count($orderDBs)) {
            foreach ($orderDBs as $key => $orderDB) {
                $job = new \App\Jobs\SyncTD\SyncDepoistPercent($orderDB);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin');
            }
        }
        return;
    }


    public function fixOrderTDV3(Request $request)
    {
        $orderModel = \App::make('App\Models\Order');

        $params = $request->all();

        $arr_status = [
            OrderConstant::KEY_STATUS_WAITING_QUOTE,
            OrderConstant::KEY_STATUS_WAITING_DEPOSIT,
            OrderConstant::KEY_STATUS_RECONFIRM,
        ];

        $orderDBs = $orderModel->whereIn('status', $arr_status)
                            ->where('deposit_percent', 0)
                            ->where('code', $params['order_code'])
                            ->get();

        if (count($orderDBs) > 1) {
            dd("Lỗi");
        } else {
            $orderDB = $orderDBs[0];
            $job = new \App\Jobs\SyncTD\SyncDepoistPercent($orderDB);
            dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin');
        }
        return;
    }

    public function updateOrderFee(Request $request)
    {
        $listOrderCode = $request->get('order_code');
        $orderPercent = $request->get('order_percent');
        if($orderPercent && $listOrderCode) {
            $listOrderCode = $request->get('order_code');
            $orderPercent = $request->get('order_percent');
            $orderCodes = explode(',',$listOrderCode);
            $orders = Order::query()
                ->whereIn('code', $orderCodes)
                ->get();

            foreach($orders as $order) {
                $orderSuppliers = OrderSupplier::query()->where('order_id', $order->id)->get();
                foreach($orderSuppliers as $supplier) {
                    $supplier = OrderSupplier::query()->where('id', $supplier->id)->first();
                    $orderCost = $order->order_cost;
                    $supplier->order_fee = round($orderCost * $orderPercent / 100);
                    $order->order_fee = $orderFeeTmp = round($orderCost * $orderPercent / 100);
                    $supplier->discount_cost = (new AccountingService())->getDiscountCost($orderFeeTmp, $order->customer_id);

                    $order->discount_cost = (new AccountingService())->getDiscountCost($orderFeeTmp, $order->customer_id);

                    $supplier->save();
                    $order->order_percent = $orderPercent;
                    $order->save();
                }
            }
        }
        if($request->get('check_all')) {
            $orders = Order::query()
                ->whereIn('status', [Order::ORDER_STATUS_DEPOSITED, Order::ORDER_STATUS_WAITING_FOR_PAYMENT, Order::ORDER_STATUS_ORDER])
                ->where('order_fee', 9000)
                ->where('deposit_percent', '!=', 100)
                ->where('created_at', '>', '2024-10-01 00:00:00')
                ->get();
            foreach($orders as $order) {
                if ($order->code_parent == null
                    && !Order::query()->where('code_parent', $order->code)->exists()
                ) {
                    continue;
                }
                $orderPercent = $order->order_percent;
                $orderSuppliers = OrderSupplier::query()->where('order_id', $order->id)->get();
                foreach($orderSuppliers as $supplier) {
                    $supplier = OrderSupplier::query()->where('id', $supplier->id)->first();
                    $orderCost = $order->order_cost;
                    $supplier->order_fee = round($orderCost * $orderPercent / 100);

                    $order->order_fee = $orderFeeTmp = round($orderCost * $orderPercent / 100);
                    $supplier->discount_cost = (new AccountingService())->getDiscountCost($orderFeeTmp, $order->customer_id);

                    $order->discount_cost = (new AccountingService())->getDiscountCost($orderFeeTmp, $order->customer_id);

                    $supplier->save();
                    $order->order_percent = $orderPercent;
                    $order->save();
                }
            }
        }
    }
    public function updateDepositCost(Request $request)
    {
        $orderCode = $request->get('order_code');
        $money = $request->get('money');
        $order = Order::query()->where('code', $orderCode)->first();

        $order->deposit_cost = $order->deposit_cost - $money;
        $order->save();
        (new TransactionService())->setTransaction(
            $money,
            // $order->deposit_cost - $depositcostAfter,
            TransactionConstant::STATUS_REFUND,
            trans(
                'transaction.order_purchase',
                [
                    'order_code' => $order->code,
                    'amount' => ConvertHelper::numericToVND($money),
                ]
            ),
            true,
            $order->customer_id,
            $order,
            true,
            $order,
        );
        $dataNotificationCustomer = [
            'order_code' =>$order->code,
            'amount' => ConvertHelper::numericToVND($money)
        ];
        (new NotificationService())->sendNotificationCustomer(8, 8014, $order->customer_id, $dataNotificationCustomer);
    }

    public function circleStaffOrder(Request $request)
    {
        try {
            DB::beginTransaction();

            // Tự động gán lại nếu nó gán lại staff_care_id,staff_counselor_id
            $params  = request()->all();
            $this->_service->circleStaffOrder($params);

            DB::commit();
            return resSuccess();
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }
    public function updateExchangeRate(Request $request)
    {
        $orderCode = $request->get('order_code');
        $exchangeRate = $request->get('exchange_rate');
        if(!$exchangeRate) {
            $exchangeRate = (new ConfigService())->getExchangeRate();
        }
        $order = Order::query()->where('code', $orderCode)->first();
        $order->exchange_rate = $exchangeRate;
        $order->save();
        $this->_service->updateOrderSupplierWhenChangeExchangeRate($order);

        return resSuccess();

    }

    public function splitOrder($supplierId, $orderId)
    {
        DB::beginTransaction();
        try {
            $this->_service->splitOrder($supplierId, $orderId);
            DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            DB::rollBack();
            return resError($e->getMessage());
        }
    }

    public function syncDateDone()
    {
        $orders = Order::whereIn('status', [OrderConstant::KEY_STATUS_ORDERED])
                    ->with(['packagesOrder' => function($q) {
                        return $q->where('status', '!=', PackageConstant::STATUS_CANCEL)->orderBy('date_done', 'desc');
                    }])
                    ->limit(1000)
            ->get();
        \Log::info($orders->count());
        $orderSuccess = 0;
        foreach($orders as $order){
            $packagesOrder = $order->packagesOrder;
            if($packagesOrder->count() > 0) {
                $countStatusDone = $packagesOrder->filter(function ($item) {
                    return $item->status == PackageConstant::STATUS_RECEIVED_GOODS;
                })->values();
                if($packagesOrder->count() == $countStatusDone->count()) {
                    ++$orderSuccess;
                    $order->status = OrderConstant::KEY_STATUS_DONE;
                    $order->date_done = data_get($packagesOrder[0], 'date_done');
                    $order->save();
                }
            }

        }
        \Log::info($orderSuccess);
        \Log::info('end');
        return resSuccess();

    }

    public function actualPaymentExport(Request $request)
    {
        $params = $request->all();
        try {
            $time = Carbon::now()->format('YmdHms');
            $fileName = getPathFileExport('order').'/order_'.$time.'.xlsx';
            dispatch(new ExportOrderActualPaymentJob($params, \Auth::user(), $fileName))->onQueue('export-1800');
            \Log::info("Nhân viên có id= ".getCurrentUserId()." đã click xuất excel thanh toán thực tế NCC");
            return resSuccess();
        } catch (Exception $e) {
            \Log::info($e);
            return resError();
        }
    }

    public function syncDateByOrderStatusTime()
    {
        dispatch(new SyncDateByOrderStatusTime())->onQueue('export-1800');
        return resSuccess();
    }

    public function syncOrderCost()
    {
        dispatch(new SyncOrderCost())->onQueue('export-1800');
        return resSuccess();
    }

    public function checkValidPackage($id)
    {

        $order = Order::findOrFail($id);
        $validate = $this->isCheckValidPackge($order);
        if ($validate) {
            $result = [
                'error' => $validate
            ];
            return resErrorWithinData($result, null, 422);
        }
        return resSuccess();
    }

    private function isCheckValidPackge($order)
    {
        // Check xem có quyền đặt thì dùng đoạn code này
        $userOnline = \Auth::user();
        $hasPerr = $userOnline->checkUserHasPer('order.is_edit_when_pack_in_qc');
        if ($hasPerr) {
            // TH1: Đơn chỉ có 1 kiện Đang ở kho QC -> pass
            // TH2: Đơn có nhiều kiện Đang ở kho QC-> pass
            // TH3: Đơn có nhiều kiện: 1 kiện Đang ở kho QC, 1 kiện Chờ xử lý -> pass
            // Th4: Đơn có nhiều kiện: 1 kiện Đang ở kho QC, 1 kiện Hủy -> pass
            // TH5: Đơn có nhiều kiện: 1 kiện Đang ở kho QC, 1 kiện Đang về kho TQ-> pass
            // TH6: Đơn có nhiều kiện: 1 kiện Đang ở kho QC, 1 kiện ngoài 4 trạng thái (Chờ xử lý, Hủy, Đang về kho TQ, Đang ở kho QC) -> fail
            $orderPackges = $order->packages;
            if(count($orderPackges) > 1) {
                $flag = false;
                foreach ($orderPackges as $key => $item) {
                    if (!in_array($item->status, PackageConstant::ARRAY_STATUS_UPDATE_SERVICE_WITH_PER)) {
                        $flag = true;
                        break;

                    }
                }
                if ($flag) {
                    return "Không thể sửa dịch vụ cho đơn này vì có kiện hàng ở trạng thái(".PackageConstant::STATUSES[$item->status].")";
                }
            }
            return null;
        }

        $orderPackges = $order->packages->whereNotIn('status', [
            PackageConstant::STATUS_PENDING,
            PackageConstant::STATUS_RECONFIRM,
            PackageConstant::STATUS_ON_THE_WAY_CHINA,
            PackageConstant::STATUS_ON_THE_WAY_PINGXIANG,
            PackageConstant::STATUS_CANCEL
        ]);
        if(count($orderPackges) > 0) {
            $status = data_get($orderPackges[0], 'status');
            return "Không thể sửa dịch vụ cho đơn này vì có kiện hàng ở trạng thái(".PackageConstant::STATUSES[$status].")";
        }
    }

    public function checkPrice(Request $request)
    {
        $orderCodes = $request->only('order_codes');
        $result = $this->_service->checkPrice($orderCodes);

        return resSuccessWithinData($result);
    }

    public function changeStatusConfirm(Request $request)
    {
        $validationRules = [
            'order_id' => 'required|exists:orders,id',
            'reason_id' => 'required|exists:reason_confirm_orders,id',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();
        $orderId = $params['order_id'];
        $reasonId = $params['reason_id'];
        $order = Order::withCount(['reasonConfirmOrders' => function($q) use ($reasonId) {
            return $q->where('reason_confirm_order_id', $reasonId);
        }])->find($orderId);
        $reason = ReasonConfirmOrder::find($reasonId);
        if(!in_array($order->status, [OrderConstant::KEY_STATUS_WAITING_QUOTE, OrderConstant::KEY_STATUS_WAITING_DEPOSIT])) {
            $result = [
                'error' => "Trạng thái đơn không hợp lệ"
            ];
            return resErrorWithinData($result, null, 422);
        }
        if($reason->type == 1) {
            if($order->reason_confirm_orders_count >= $reason->count) {
                $result = [
                    'error' => "Bạn đã quá số lần được đổi trạng thái với lý do này"
                ];
                return resErrorWithinData($result, null, 422);
            }
        }

        return $this->_service->changeStatus($order, OrderConstant::KEY_STATUS_RECONFIRM);
    }

    public function autoCancelOrder()
    {
        \Artisan::call("auto:cancel-order");
        return resSuccess();
    }

    public function linkBySupplier($id)
    {
        $order = Order::query()->with(['orderDetail', 'orderDetail.supplier'])->find($id);
        $groupOrderDetail = $order->orderDetail->groupBy('supplier_id');
        $data = [];
        foreach($groupOrderDetail as $key  => $orderDetails) {
            $shopName = null;
            $links = [];
            foreach($orderDetails as $orderDetail) {
                $supplier = $orderDetail->supplier;
                if($supplier) {
                    $shopName = $supplier->name;
                }
                if($orderDetail->link) {
                    array_push($links, $orderDetail->link);
                }
            }
            array_push($data, [
                'supplier_id' => $key,
                'supplier_name' => $shopName,
                'links' => $links
            ]);
        }
        return resSuccessWithinData($data);
    }

    public function requestDepositPercent(Request $request)
    {
        $validationRules = [
            'deposit_percent'   => 'required|numeric|min:0|max:100',
            'order_id'          => 'required|exists:orders,id',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        try {
            DB::beginTransaction();
            $params    = $request->all();
            $orderId = data_get($params, 'order_id');
            $depositPercent = data_get($params, 'deposit_percent') ?? 0;
            $order = Order::with('customer')->find($orderId);
            $customer = $order->customer;
            $configService = \App::make('App\Services\ConfigService');
            $depositPercentCustomer = data_get($customer->offer, 'deposit_rate') ? data_get($customer->offer, 'deposit_rate') : $configService->getResultFromValueByLevel($customer->level)->deposit;
            if($order->status != OrderConstant::KEY_STATUS_WAITING_QUOTE) {
                $result = [
                    'error' => "Chỉ được tick khi đơn ở trạng thái Chờ báo giá"
                ];
                return resErrorWithinData($result, null, 422);
            }
            $order->update(
                [
                    'is_request_deposit_advence' => $depositPercent > 0 ? 1 : 0,
                    'deposit_percent' => $depositPercent > 0 ? $depositPercent : $depositPercentCustomer
                ]
            );
            DB::commit();
            return resSuccess();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::info($e);
            return resError();
        }
    }

    public function requestDepositPercentCustomer(Request $request)
    {
        $validationRules = [
            'deposit_percent'   => 'required|numeric|min:1|max:100',
            'order_id'          => 'required|exists:orders,id',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        try {
            DB::beginTransaction();
            $params    = $request->all();
            $activityService = \App::make('App\Services\ActivityService');
            $orderId = data_get($params, 'order_id');
            $depositPercent = data_get($params, 'deposit_percent');
            $order = Order::with([
                'orderRefunds' => function ($q) {
                    $q->where('status', OrderRefund::STATUS_PROCESSING);
                },
                'orderDepositPercent' => function ($q) {
                    $q->where('status', 0);
                }
            ])->find($orderId);
            if($order->status != OrderConstant::KEY_STATUS_ORDERED) {
                $result = [
                    'error' => "Đơn không ở trạng thái đặt hàng"
                ];
                return resErrorWithinData($result, null, 422);
            }
            if(count($order->orderRefunds) > 0) {
                $result = [
                    'error' => "Đang có yêu cầu hoàn tiền chờ xử lý không được thay đổi phần trăm đặt cọc"
                ];
                return resErrorWithinData($result, null, 422);
            }
            $orderDepositPercent = $order->orderDepositPercent;
            $depositPercentOld = $orderDepositPercent ? $orderDepositPercent->deposit_percent_old : $order->deposit_percent;
            if($depositPercentOld >= $depositPercent) {
                $result = [
                    'error' => "Phần trăm đặt cọc mới không hợp lệ"
                ];
                return resErrorWithinData($result, null, 422);
            }
            if($order->deposit_percent != $depositPercent) {
                $exchangeRateNew = (new ConfigService)->getExchangeRate();
                if($exchangeRateNew != $order->exchange_rate) {
                    $order->exchange_rate = $exchangeRateNew;
                    $order->save();
                    $this->_service->updateOrderSupplierWhenChangeExchangeRate($order);
                    $order = Order::with([
                        'orderRefunds' => function ($q) {
                            $q->where('status', OrderRefund::STATUS_PROCESSING);
                        },
                        'orderDepositPercent' => function ($q) {
                            $q->where('status', 0);
                        },
                        'orderSuppliers'
                    ])->find($orderId);
                }
                $totalOrderNotShip = $order->getTotalAmountNotShip();
                if($order->orderDepositPercent) {
                    $order->orderDepositPercent()->update(['deposit_percent_new' => $depositPercent]);
                }else {
                    $order->orderDepositPercent()->create([
                        'deposit_percent_old' => $order->deposit_percent,
                        'deposit_percent_new' => $depositPercent
                    ]);
                }
                $order->update(['deposit_percent' => $depositPercent]);
                $totalAmountNotShip = $order->getTotalAmountNotShip();
                $diffDepositCost = roundMoney($totalAmountNotShip * $order->deposit_percent / 100) - roundMoney($order->deposit_cost);
                $content = __('activity.change_deposit_percent_customer', [
                    'amount' => number_format($diffDepositCost,0,".",",") . ' đ',
                    'deposit_percent' => $depositPercent,
                    'exchange_rate' => number_format($order->exchange_rate,0,".",",") . ' đ'
                ]);
                $activityService->setOrderLog(getCurrentUser(), $content, $order->id);
                $content = __('activity.order_diff_deposit_cost', ['order_code' => $order->code]);
                $dataOrder = [
                    'order_code' => $order->code,
                    'content' => $content,
                    'order_id' => $order->id,
                ];
                $notificationService = App::make('App\Services\NotificationService');
                $data = [
                    'group' => \App\Models\Notification::ORDER,
                    'name' => 2017,
                    'data' => json_encode($dataOrder),
                    'time_create' => time(),
                    'customer_id' => $order->customer_id,
                    'status' => 1
                ];

                $notificationService->storeNotificationCustomer($order->customer_id, $data);
            }
            DB::commit();
            return resSuccess();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::info($e);
            return resError();
        }
    }

    public function totalOrderByExchangeRateNew($id)
    {
        try {
            DB::beginTransaction();
            $order = Order::with(['orderSuppliers'])->find($id);
            $exchangeRateNew = (new ConfigService)->getExchangeRate();
            $totalOrderNotShip = $order->getTotalAmountNotShip();
            if($exchangeRateNew == $order->exchange_rate) {
                return resSuccessWithinData([
                    'total_amount' => $totalOrderNotShip,
                    'exchange_rate' => $exchangeRateNew
                ]);
            }else {
                $order->exchange_rate = $exchangeRateNew;
                $order->save();
                $this->_service->updateOrderSupplierWhenChangeExchangeRate($order);
                $totalOrderNotShipNew = Order::with(['orderSuppliers'])->find($id)->getTotalAmountNotShip();
                // Chỉ cập nhật tạm để lấy tiền hàng theo tỉ giá mới chứ không commit để lưu lại
                return resSuccessWithinData([
                    'total_amount' => $totalOrderNotShipNew,
                    'exchange_rate' => $exchangeRateNew
                ]);
            }
            return resSuccess();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::info($e);
            return resError();
        }
    }

    public function updateExchangeRateId(Request $request)
    {
        $validationRules = [
            'exchange_rate'   => 'required|integer|min:1',
            'order_id'          => 'required|exists:orders,id',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        try {
            DB::beginTransaction();
            $params    = $request->all();
            $activityService = \App::make('App\Services\ActivityService');
            $orderId = data_get($params, 'order_id');
            $exchangeRate = data_get($params, 'exchange_rate');

            $order = Order::find($orderId);
            if(!in_array($order->status, [OrderConstant::KEY_STATUS_WAITING_QUOTE, OrderConstant::KEY_STATUS_WAITING_DEPOSIT])) {
                $result = [
                    'error' => "Trạng thái đơn không hợp lệ"
                ];
                return resErrorWithinData($result, null, 422);
            }
            $exchangeRateOld = $order->exchange_rate;
            if($order->exchange_rate != $exchangeRate) {
                $order->exchange_rate = $exchangeRate;
                $order->save();
                $this->_service->updateOrderSupplierWhenChangeExchangeRate($order);
                $content = __('activity.order_change_exchange_rate', [
                    'user_name' => getCurrentUser()->name,
                    'exchange_rate_old' => number_format($exchangeRateOld,0,".",",") . ' đ',
                    'exchange_rate_new' => number_format($exchangeRate,0,".",",") . ' đ'
                ]);
                $activityService->setOrderLog(getCurrentUser(), $content, $order->id, ActivityConstant::IS_VIEW_ADMIN);
            }

            DB::commit();
            return resSuccess();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::info($e);
            return resError();
        }
    }
}
