<?php

namespace App\Http\Controllers;

use App\Constants\ActivityConstant;
use App\Constants\ConversationConstant;
use App\Constants\MessageConstant;
use App\Constants\PackageConstant;
use App\Constants\ReportConstant;
use App\Exports\PackPrintReceiveGoodsExport;
use App\Helpers\AccountingHelper;
use App\Helpers\MediaHelper;
use App\Helpers\RandomHelper;
use App\Http\Requests\Package\ChangeStatusRequest;
use App\Http\Requests\Package\PackageModifiesRequest;
use App\Http\Requests\Package\PackageStoreRequest;
use App\Http\Requests\Package\PackageUpdateRequest;
use App\Http\Requests\Package\SetOrderCodeRequest;
use App\Http\Resources\ListResource;
use App\Http\Resources\Package\PackageStoreByOrderResource;
use App\Http\Resources\Package\ProductResource;
use App\Interfaces\Validation\StoreValidationInterface;
use App\Jobs\SentSmsOrderPackageJob;
use App\Jobs\SentSmsToCustomerJob;
use App\Jobs\UpdateOrderPackageClearanceJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderPackage;
use App\Models\OrderPackageImage;
use App\Models\OrderSupplier;
use App\Models\Category;
use App\Models\Supplier;
use App\Services\ConversationService;
use App\Services\FcmService;
use App\Services\FcmTokenService;
use App\Services\MessageService;
use App\Services\OrderPackageService;
use App\Services\OrderService;
use App\Services\ReportService;
use App\Services\SmsService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Services\ReportCustomerService;
use Illuminate\Http\Request;
use App\Exports\PackageExport;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\View;
use App\Services\FilePrintService;
use App\Constants\FilePrintConstant;
use Illuminate\Support\Carbon;
use App\Models\Activity;
use App\Models\Staff;
use Illuminate\Support\Facades\Auth;
use App\Constants\CustomerConstant;
use GuzzleHttp\Client as Guzzle;
use App\Models\ApproveUnitPrice;
use App\Constants\ApproveUnitPriceConstant;
use App\Helpers\ConvertHelper;
use App\Constants\TransactionConstant;
use App\Constants\OrderConstant;
use App\Exports\PackPrintWarehouseExport;
use App\Jobs\ExportPackageJob;
use App\Models\ReportOrderVN;
use App\Services\ActivityService;
use App\Services\CustomerService;

use App\Constants\ConsignmentConstant;
use App\Jobs\OrderPackage\SyncDateDone;
use App\Models\OrderPackageStatusTime;
use App\Models\Consignment;

use App\Constants\DeliveryConstant;

class PackageController extends Controller implements StoreValidationInterface
{
    protected $filePrintService;
    protected string $dongDongUrl;

    public function __construct(OrderPackageService $service, FilePrintService $filePrintService)
    {
        $this->_service = $service;
        $this->filePrintService = $filePrintService;
        $this->dongDongUrl = env('URL_DONGDONG') . 'webhook/hangve-x329kxm2k2ixk39x92x1911947165';
    }

    public function pagination(): JsonResponse
    {
        $request = request();
        if (!empty($request->is_update_order_package)) {
            $jobs = new UpdateOrderPackageClearanceJob();
            dispatch($jobs)->onQueue('sync-data-report');
        }
        return parent::pagination();
    }

    private function validateViewOrder($request){

        $params['order_code'] = $request->order_code ?? '';
        $params['bill_code'] = $request->bill_code ?? '';
        $params['code_warehouse'] = $request->code_warehouse ?? '';
        if (!empty($params['order_code']) || !empty($params['bill_code']) || !empty($params['code_warehouse'])) {
            if (isBusiness() && !isSalesManager() && !isDeputySalesManager()) {
                $order = $this->_service->getSaleStaff($params);
                if ($order) {
                    if ($order->order_type == 'App\Models\Consignment') {
                        if (
                            !empty($order->orderPackageWithConsignment)
                            && !empty($order->orderPackageWithConsignment->customer)
                            && !empty($order->orderPackageWithConsignment->customer->staffGroup)
                            && $order->orderPackageWithConsignment->customer->staffGroup->id != getCurrentUser()->id
                        ) {
                            $message = 'Đơn hàng ' . $order->order_code . ' đang được quản lý bởi nhân viên ' . $order->orderPackageWithConsignment->customer->staffGroup->name;
                            return resError($message, 400);
                        }
                        if (empty($order->orderPackageWithConsignment->customer->staffGroup)) {
                            return resError('Không tìm thấy nhân viên quản lý!', 400);
                        }
                    }
                    if ($order->order_type == 'App\Models\Order') {
                        if (!empty($order->orderPackageWithOrder) && $order->orderPackageWithOrder->staff_group_id != getCurrentUser()->id && !empty($order->orderPackageWithOrder->staffGroup)) {
                            $message = 'Đơn hàng ' . $order->orderPackageWithOrder->code . ' đang được quản lý bởi nhân viên ' . $order->orderPackageWithOrder->staffGroup->name;
                            return resError($message, 400);
                        }
                        if (empty($order->orderPackageWithOrder) || empty($order->orderPackageWithOrder->staff_group_id)) {
                            return resError('Không tìm thấy nhân viên quản lý!', 400);
                        }
                    }

                } else {
                    return resError('Không tìm thấy nhân viên quản lý!', 400);
                }
            }
        }
    }

    /**
     * @param  string  $orderId
     * @return JsonResponse
     */
    public function getListByOrderId(string $orderId): JsonResponse
    {
        $data = OrderPackage::query()
            ->with(['statusTimes', 'transporterRelation', 'categoryRelation', ])
            ->where('order_id', $orderId)
            ->where('order_type', 'App\Models\Order')
            ->get();
        return resSuccessWithinData(new ListResource($data, PackageStoreByOrderResource::class));
    }

    public function handleBeforeStoreByOrderId($params, $orderDB)
    {
        // Validate
        // Cho tạo trùng mã vận đơn
        // Với 2 đơn hàng khách nhau
        // Chứ không phải cùng đơn hàng đc
        // ==> Đây là đối với đơn order nhé
        // Còn đơn ký gửi là ko đc tạo trùng luôn
        // Toàn hiểu sai vấn đề

        // Nếu ko có mã vận đơn thì bỏ qua
        if ($params['bill_code'] == "") {
            return null;
        }
        if(isValidBillCode($params['bill_code']) == false) {
            return 'Mã vận đơn ' . $params['bill_code'] . ' không hợp lệ.';
        }

        // Xử lý
        $orderPackageModel = \App::make('App\Models\OrderPackage');
        // Check không trùng với đơn kí gửi
        $orderPackageDBCount  = $orderPackageModel->where('bill_code', $params['bill_code'])
                                                ->where('order_type', 'App\Models\Consignment')
                                                ->count();
        if ($orderPackageDBCount > 0) {
            return 'Mã vận đơn ' . $params['bill_code'] . ' đã tồn tại.';
        }

        // Nếu đơn đó đã có 1 kiện trong đơn thì cũng báo lỗi luôn
        $orderPackageDBCount  = $orderPackageModel->where('bill_code', $params['bill_code'])
                                                ->where('order_id', $orderDB->id)
                                                ->where('order_type', 'App\Models\Order')
                                                ->where('status', '!=', PackageConstant::STATUS_CANCEL)
                                                ->count();

        if ($orderPackageDBCount > 0) {
            return 'Mã vận đơn ' . $params['bill_code'] . ' đã tồn tại..';
        }
        return null;
    }

    /**
     * @param  PackageStoreRequest  $request
     * @param  Order  $order
     * @return JsonResponse
     */
    public function storeByOrderId(PackageStoreRequest $request, Order $order): JsonResponse
    {
        $params = $request->all();
        $validate = $this->handleBeforeStoreByOrderId($params, $order);
        if ($validate) {
            $result = [
                'unit' => $validate
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        $category = $params['category'] ?? null;
        $category_id = $params['category_id'] ?? null;
        if ($category && !$category_id) {
            $checkExits = Category::query()->where('name', $category)->first();
            if ($checkExits) {
                return resError('Danh mục [ ' . $category . ' ] đã trùng trong hệ thống, vui lòng chọn lại');
            }
            $checkExits = Category::query()->create(['name' => $category, 'organization_id' => 1]);
            $params['category_id'] = $checkExits->id;
        }
        $package = $this->_service->quickCreatePackage($params, $order);
        return resSuccessWithinData(new PackageStoreByOrderResource($package));
    }

    public function updatePackageNotiLogic($order, $pack)
    {
        //$staff_care_id      = $order->staff_care_id;
        //$staff_counselor_id = $order->staff_counselor_id;
        $staff_group_id = $order->staff_group_id;

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

    /**
     * @param  PackageUpdateRequest  $request
     * @param  OrderPackage  $package
     * @return JsonResponse
     */
    public function updateByOrderId(PackageUpdateRequest $request, OrderPackage $package): JsonResponse
    {
        $params = $request->all();
        $params['staff_id'] = getCurrentUserId();
        $params['code'] = RandomHelper::getPackageCode();
        $this->_service->changeCost($package, $params);
        $package->update($params);
        $data = [];
        foreach ($params['products'] as $value) {
            $data[$value['id']] = ['quantity' => $value['quantity']];
                    }
        $package->orderDetails()->sync($data);
        return resSuccessWithinData(new PackageStoreByOrderResource($package));
    }

    public function storeMessage(): ?array
    {
        return [];
    }

    public function storeRequest(): array
    {
        return [
            'customer_phone' => 'nullable|exists:customers,phone_number',
            'customer_code' => 'nullable|exists:customers,code',
            'bill_code' => 'required|unique:order_package|string|regex:/^[a-zA-Z0-9_-]+$/u',
            //'bill_code' => 'required',
            'code_po' => 'required',
            'type' => 'nullable|in:' . implode(',', array_keys(PackageConstant::TYPES)),
            'weight' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'staff_order_id' => 'nullable|exists:users,id',
            'order_code' => 'nullable|exists:orders,code',
            // 'transporter_id' => 'required|exists:transporters,id',
            'category_id' => 'nullable|exists:categories,id',
            // 'product_name' => 'required|max:255',
            'china_shipping_cost' => 'nullable|numeric|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'package_number' => 'numeric|min:1',
        ];
    }

    protected function getAttributes(): array
    {
        return [
            'customer_phone' => 'Khách hàng',
            'bill_code' => 'Mã vận đơn',
            'type' => 'Loại kiện',
            'weight' => 'Cân nặng',
            'width' => 'Chiều rộng',
            'height' => 'Chiều cao',
            'length' => 'Chiều dài',
            'staff_order_id' => 'Nhân viên đặt hàng',
            'order_code' => 'Mã đơn hàng',
            'transporter_id' => 'Hãng vận chuyển',
            'category_id' => 'Danh mục',
            'product_name' => 'Tên sản phẩm',
            'china_shipping_cost' => 'Phí vận chuyển TQ',
            'warehouse_id' => 'Kho nhận',
            'images' => 'array',
            'package_number' => 'Số kiện',
            'code_po' => 'Mã đặt hàng',
        ];
    }

    public function checkPerForTBP($package)
    {
        $params = request()->all();
        $flagCheck = false;

        if (isset($params['status'])) {
            $flagCheck = true;
        }

        if (isset($params['type'])) {
            $flagCheck = true;
        }

        if (isset($params['china_shipping_cost_cny'])) {
            $flagCheck = true;
        }

        if ($flagCheck) {
            $userOnline = \Auth::user();
            $permissionDBs = $userOnline->getAllPermissions();
            $perOfRole = \Arr::pluck($permissionDBs, 'name');
            if (!in_array('pack.update-for-tbp', $perOfRole)) {
                return 'Bạn không có quyền thực hiện thao tác này !!!';
            }
            if ($userOnline->hasPermissionTo('pack.update-for-tbp') == false) {
                // return 'Bạn không có quyền thực hiện thao tác này !!!';
            }
        }

        return null;
    }

    /**
     * @param PackageModifiesRequest $request
     * @param OrderPackage $package
     * @return JsonResponse
     * @throws \Exception
     */
    public function modifies(PackageModifiesRequest $request, OrderPackage $package): JsonResponse
    {
        $params = $request->all();
        if(data_get($params, 'official') > 0
            && $package->order_type == Consignment::class
            && $package->status == PackageConstant::STATUS_RECEIVED_GOODS) {
            $result = ['err' => 'Kiện đã nhận hàng không được phép sửa'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        // Nếu trạng update thái đã nhận hàng thì cần kiểm tra kiện đó có nằm trong giao hàng nào không
        $statusOld = $package->status ?? '';
        $statusNew = !empty($params['status']) ? $params['status'] : '';
        if($package->status == PackageConstant::STATUS_RECEIVED_GOODS && data_get($params, 'type')) {
            $result = ['err' => 'Kiện đã nhận hàng không thể thay đổi được trọng lượng/kích thước'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        if($package->is_delivery == PackageConstant::IS_DELIVERY && data_get($params, 'type')) {
            // Logic ngày 29-01-2026: Chặn luôn, vì nó thu tiền từ đầu rồi
            // $result = ['err' => 'Kiện đã có yêu cầu giao, vui lòng xóa đơn giao hàng trước khi chỉnh sửa'];
            // return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            // Logic ngày 27-11-2024: Nếu yêu cầu giao đang ở Chờ xử lý+ đang xử lý thì vẫn cho thao tác
            $arr_status_delivery = [
                DeliveryConstant::KEY_STATUS_PENDING,
                DeliveryConstant::KEY_STATUS_PROCESS,
            ];
            $deliveryDB = $package->delivery;
            if (!in_array($deliveryDB->status, $arr_status_delivery)) {
                $result = ['err' => 'Kiện đã có yêu cầu giao, vui lòng xóa đơn giao hàng trước khi chỉnh sửa'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }
        // Logic ngày 3-12-2024: Nếu có giao hàng mà lại đổi trạng thái thì không cho phép
        if($package->is_delivery == PackageConstant::IS_DELIVERY && data_get($params, 'status')) {
            $result = ['err' => 'Kiện đã có yêu cầu giao, vui lòng xóa đơn giao hàng trước khi chỉnh sửa'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        if($package->status == PackageConstant::STATUS_CANCEL) {
            $result = ['err' => 'Kiện đã hủy không thể thao tác'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        if(data_get($params, 'status') == PackageConstant::STATUS_RECEIVED_GOODS){
            if (!is_null($package->delivery_id)) {
                $result = ['err' => 'Kiện hàng đã có giao hàng, không thể xử lý'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }

            $validate = $this->_service->validateSubMoneyAndDoneOrderAfterModifiPack($package);
            if ($validate) {
                $result = ['error' => $validate];
                return resErrorWithinData($result, null, 422);
            }
        }

        // Chặn không update bill_code ở màn này
        unset($params['bill_code']);

        // Check sửa m3,kg,status chỉ cho trưởng bộ phận mới dc sửa
        $message =  $this->checkPerForTBP($package);

        if (!is_null($message)) {
            $result = ['err' => $message];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Nếu mà request đẩy lên {"weight":0.018,"type":"volume"} là bị đẩy sai, loại nó
        $params = $request->all();
        if(count($params) == 4 && isset($params['weight']) && isset($params['type']) && $params['type'] == 'volume') {
            return resSuccessWithinData([]);
        }
        try {
            DB::beginTransaction();

            $params = $request->all();
            $dataSocket = [];

            // Chặn không update bill_code ở màn này
            unset($params['bill_code']);

            if ($statusNew && $statusNew != $statusOld) {
                if (!empty($package->customer_id)) {
                    $payload['user_id'] = $package->customer_id;
                    $fcmTokenService = app()->make(FcmTokenService::class);
                    $title = 'Thay đổi trạng thái kiện hàng';
                    $body = 'Kiện hàng ' . $package->bill_code . ' được chuyển sang trạng thái ' . PackageConstant::STATUSES[$statusNew] ?? '';
                    $dataSent = [
                        'type' => 'order_package',
                        'body' => (string) $package->id ?? 0
                    ];

                    if ($statusNew == PackageConstant::STATUS_WAREHOUSE_VN) {
                        $dataSocket = $this->_service->sentSmsToChat(
                            $package, [
                                'body' => $body,
                                'status' => PackageConstant::STATUS_WAREHOUSE_VN
                            ]
                        );

                        $this->_service->sentSmsToChat(
                            $package, [
                                'body' => 'Quý khách có thể tạo đơn giao hàng ngay bây giờ. Vui lòng click vào đây',
                                'status' => PackageConstant::STATUS_WAREHOUSE_VN,
                                'type' => MessageConstant::TYPE_CREATE_DELIVERY
                            ]
                        );
                    }

                    Log::info('sent notification order_package ' . $package->customer_id . ' data ' . $body);
                    $fcmTokenService->sendMessageForApp($payload, $title, $body, $dataSent);
                }
            }
            if (isset($params['china_shipping_cost_cny'])) {
                $params['china_shipping_cost'] = $params['china_shipping_cost_cny'];
                if ($params['china_shipping_cost_cny'] != $package->china_shipping_cost) {
                    $data_sync = [
                        'id' => $package->id,
                        'china_shipping_cost' => data_get($params, 'china_shipping_cost_cny')
                    ];
                    $payload = [
                        'event_name' => 'china_shipping_cost',
                        'data' => $data_sync
                    ];
                    $this->syncPackageToDongDong($payload);
                }
            }

            $this->_service->changeCost($package, $params);
            if(isset($params['status'])) {
                unset($params['shock_proof_cost']); // Phục vụ cho tasks updateShareShip ngày 25/05/26
            }
            if(data_get($params, 'status') == PackageConstant::STATUS_RECEIVED_GOODS){
                if(!$package->date_done) {
                    $params['date_done'] = Carbon::now();
                }
                $this->_service->subMoneyAndDoneOrderAfterModifiPack($package);
            }
            $billOld = data_get($package, 'bill_code');
            $statusOld = data_get($package, 'status');
            if(data_get($params, 'note_internal') && data_get($params, 'note_internal') != $package->note_internal) {
                // Đồng bộ ghi chú sang kho
                $data_sync = [
                    'id' => $package->id,
                    'note' => data_get($params, 'note_internal'),
                    'user_name' => getCurrentUser()->name
                ];
                $payload = [
                    'event_name' => 'update_note',
                    'data' => $data_sync
                ];
                if ($package->sync_note == 1) {
                    $this->syncPackageToDongDong($payload);
                }

                // Cập nhật trên hệ thống CN
                $data_sync = [
                    'origin_code' => $package->bill_code,
                    'note'        => data_get($params, 'note_internal'),
                ];
                $payload = [
                    'event_name' => 'hangve_update_note',
                    'data' => $data_sync
                ];
                if ($package->sync_note == 1) {
                     $this->syncPackageToCN($payload);
                 }
            }
            if(data_get($params, 'package_number') && data_get($params, 'package_number') != $package->package_number) {
                // Đồng bộ ghi chú sang kho
                $data_sync = [
                    'id' => $package->id,
                    'package_number' => data_get($params, 'package_number')
                ];
                $payload = [
                    'event_name' => 'update_package_number',
                    'data' => $data_sync
                ];
                $this->syncPackageToDongDong($payload);

                // Tính lại các phí
                $params['type'] = 'package_number';

                // Tính lại có khác với số bao bên CN không
                if ($package->cn_bag_real > 0) {
                    if ($package->cn_bag_real == $params['package_number']) {
                        $params['diff_count_pack'] = 0;
                    }
                    if ($package->cn_bag_real != $params['package_number']) {
                        $params['diff_count_pack'] = 1;
                    }
                }

                // Đồng bộ lại số kiện sang bao giá
                $orderService = \App::make('App\Services\OrderService');
                $orderService->syncPackNumberToQuote($data_sync['id'], $data_sync['package_number']);
            }
            if(!empty($params['status'])) {
                unset($params['international_shipping_cost']);
            }
            if(isset($params['international_shipping_cost']) && $params['international_shipping_cost'] == 0) {
                $params['discount_cost'] = 0;
                $params['discount_percent'] = 0;
            }
            if(isset($params['type'])) {
                $params['type_quantitative'] = data_get($params, 'type') == 'volume' ? 'volume' : 'weight';
            }
            if(isset($params['official']) && $params['official'] == 1) {
                $params['official_is_partner'] = 0;
            }
            $package->update($params);
            if ($statusOld !== $package->status) {
                if($statusOld == PackageConstant::STATUS_RECEIVED_GOODS) {
                    $this->_service->refundAmount($package);
                }else {
                    if($package->status == PackageConstant::STATUS_CANCEL) {
                        $this->_service->calculatorWhenCancelPackage($package);
                    }
                }
            }
            // Sửa mã vận đơn cũng đồng bộ kiện sang kho
            // Dùng hàm mới riêng chỉ xử lý việc thay đổi bill code
            // if(data_get($params, 'bill_code') && data_get($params, 'bill_code') != $billOld) {
            //     $orderPackageDetails = $package->orderDetailsPackage;
            //     if($orderPackageDetails->count() > 0) {
            //         $this->_service->syncPackWarehouse($package);
            //     }else {
            //         $this->_service->syncPackWarehouseScreenPack($package);
            //     }
            // }
            // Xử lý lại đoạn tính phí vận chuyển TQ, nếu nhập cái nào thì ăn cái đó, Taks 17052023
            if (isset($params['type']) || isset($params['official'])) {
                $params['weight_real'] = $params['weight'];
                $params['weight'] = ruleForKg($params['weight']);
                $package->weight = $params['weight'];
                if(isset($params['volume_enter'])) {
                    $params['volume_enter'] = round($params['volume_enter'], 3);
                }

                $accountingService = App::make('App\Services\AccountingService');
                $isWeight = false;
                $international_shipping_cost = $accountingService->getInternationShippingCost(
                    getProvinceX(optional($package->warehouse)->province_id),
                    $package->weight,
                    $package->volume,
                    $package->customer_id,
                    $isWeight,
                    $package->id
                );

                $params['type_quantitative'] = $isWeight ? 'weight' : 'volume';
                $params['international_shipping_cost'] = $international_shipping_cost;
                $params['type_international_shipping_cost'] = $params['type_quantitative'];

                // Lưu lại đơn giá
                if ($params['type_quantitative'] == 'weight') {
                    if ($params['weight'] > 0) {
                        $params['unit_price'] = $package->weight == 0 ? 0 : ($params['international_shipping_cost'] / $package->weight);
                    }
                } else {
                    $params['unit_price'] = $package->volume == 0 ? 0 : ($params['international_shipping_cost'] / $package->volume);
                    $params['m3'] = $package->volume;
                    if ($package->package_number > 1) {
                        // $params['international_shipping_cost'] = $params['international_shipping_cost'] * $package->package_number;
                    }
                }
                if($package->official == OrderPackage::OFFICIAL_CN) {
                    $params['unit_price'] = $params['international_shipping_cost'] == getFeeInternationalOfficialM3() ? getFeeInternationalOfficialM3() : $params['unit_price'];
                }
                if(array_key_exists('unit_price', $params) && $params['unit_price'] != $package->unit_price) {
                    $this->setLogShippingCost($package, $package->unit_price, $params['unit_price']);
                }

                // Bổ sung tính chiếu khấu
                $configService = App::make('App\Services\ConfigService');
                $customerDB = $package->customer;
                if ($customerDB) {
                    $levelCalculator = $customerDB->level == 0 ? 1 : (int)$customerDB->level;
                } else {
                    $levelCalculator = 0;
                }
                if ($international_shipping_cost > 0 && $package->official == OrderPackage::OFFICIAL_BCN) {
                    // Chiết khấu chỉ áp dụng với kiện bán chính ngạch
                    $delivery_discount_per = $configService->getResultFromValueByLevel($levelCalculator)->delivery;
                    $params['discount_cost'] = $international_shipping_cost * $delivery_discount_per / 100;
                    $params['discount_cost'] = round($params['discount_cost']);
                    $params['discount_percent'] = $delivery_discount_per;
                }else {
                    $params['discount_cost'] = 0;
                    $params['discount_percent'] = 0;
                }

                // Nếu khách hàng có ưu đãi thì không được hưởng chiếu khấu nữa

                if ($customerDB) {
                    $warehouseDB = $package->warehouse;
                    $offerDB = $customerDB->offer;
                    if ($offerDB && $warehouseDB) {
                        $levelOffer = $offerDB->level_rate;
                        if ($levelOffer == 0) {
                            $levelOffer = $customerDB->level;
                        }
                        if ($levelOffer > 1) {
                            if (
                                $offerDB['trans_weight_hn_fee'] > 0 ||
                                $offerDB['trans_volumn_hn_fee'] > 0
                            ) {
                                if (
                                    $warehouseDB->code == 'HN-TD' ||
                                    $warehouseDB->code == 'HN-HD' ||
                                    $warehouseDB->code == 'HN-TQB'
                                ) {
                                    $params['discount_cost'] = 0;
                                    $params['discount_percent'] = 0;
                                }
                            }

                            if (
                                $offerDB['trans_weight_hcm_fee'] > 0 ||
                                $offerDB['trans_volumn_hcm_fee'] > 0
                            ) {
                                if (
                                    $warehouseDB->code == 'HCM'
                                ) {
                                    $params['discount_cost'] = 0;
                                    $params['discount_percent'] = 0;
                                }
                            }
                        }
                    }
                }

                // Thêm phần đóng gỗ
                $isWoodworking = (bool)($array['is_woodworking'] ?? $package->is_woodworking);
                $package->type_quantitative = $params['type_quantitative'];
                $params['woodworking_cost'] = $isWoodworking ? AccountingHelper::getCosts(
                    $accountingService->getWoodworkingCost($package->weight, $package->volume, $package)
                ) : 0;


                // Nếu mã đơn bị trùng thì chia lại số tiền vào từng đơn (18-01-2026)
                // Nếu mã đơn bị trùng thì chia lại số tiền vào từng đơn
                // Nếu mã đơn bị trùng thì chia lại số tiền vào từng đơn
                $arr_status = [
                    PackageConstant::STATUS_CANCEL,
                    PackageConstant::STATUS_RECEIVED_GOODS,
                ];
                $packDBs = OrderPackage::query()->where('bill_code', $package->bill_code)
                                    ->whereNotIn('status', $arr_status)
                                    ->get();

                $count_pack = count($packDBs);
                if ($count_pack > 1) {
                    $params['international_shipping_cost'] = (int) ($params['international_shipping_cost'] / $count_pack);
                    $params['share_fee_ship']              = PackageConstant::SHARE_FEE_SHIP;
                    // Nếu có đóng gỗ
                    if ($isWoodworking) {
                        $count_good = $packDBs->where('is_woodworking', 1)->count();
                        $params['woodworking_cost'] = (int) ($params['woodworking_cost'] / $count_good);
                    }
                    // Nếu có chống sốc
                    $isShockProof = (bool)($array['is_shock_proof'] ?? $package->is_shock_proof);
                    if ($isShockProof) {
                        $params['shock_proof_cost'] = $isShockProof ? AccountingHelper::getCosts(
                            $accountingService->getShockCost($package->weight, $package)
                        ) : 0;

                        $count_good = $packDBs->where('is_shock_proof', 1)->count();
                        $params['shock_proof_cost'] = (int) ($params['shock_proof_cost'] / $count_good);
                    }
                }
                $package->update($params);
            }
            // update status for order consignment
            $this->_service->updateOrderStatus($package);
            // update internal shipping cost for order
            if($package->order_type === Order::class && !empty($package->order_id) && empty($params['status'])) {
                // (new OrderService())->updateInternalShippingCostOrderPackageById($package);
            }
            // Thêm noti nếu đổi status
            $this->updatePackageNotiStatus($package, $params);

            // Nếu hủy kiện thì tính lại tiền cho các kiện dc share ship nếu có
            $this->_service->updateShareShip($package);


            // Nếu là sự kiện này thì cần cập nhật vào trong giao hàng lại các thông số
            if($package->is_delivery == PackageConstant::IS_DELIVERY && data_get($params, 'type')) {
                $this->updateDelivery($deliveryDB);
            }


            DB::commit();

            // Đồng bộ sang HV
            if (env('APP_ENV') == 'production') {
                // $this->syncPackToDongDong($package);
            }

            // Cập nhật các kiện bị thất lạc nếu có
            $job = new \App\Jobs\OneWarehouse\SyncStatusPackLost();
            dispatch($job)->delay(now()->addSeconds(10))->onQueue('cron-data-2023');

            // Nếu kiện hủy thì đồng bộ nó sang bên kho
            if($package->status == PackageConstant::STATUS_CANCEL) {
                $arr_packs = [$package];
                // $this->syncCancelToDongDong($arr_packs);

                $job = new \App\Jobs\OneWarehouse\SyncCancelPack($arr_packs);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin');
            }
            if (!empty($dataSocket)) {
                return resSuccessWithinData($dataSocket);
            }
            return resSuccessWithinData($package);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

    }


    public function updateDelivery($deliveryDB)
    {
        $orderPackagesDBs = $deliveryDB->orderPackages;
        $data_update = [];
        $data_update['shock_proof_cost']            = $orderPackagesDBs->sum('shock_proof_cost');
        $data_update['storage_cost']                = $orderPackagesDBs->sum('storage_cost');
        $data_update['woodworking_cost']            = $orderPackagesDBs->sum('woodworking_cost');
        $data_update['inspection_cost']             = $orderPackagesDBs->sum('inspection_cost');
        $data_update['insurance_cost']              = $orderPackagesDBs->sum('insurance_cost');
        $data_update['international_shipping_cost'] = $orderPackagesDBs->sum('international_shipping_cost');
        $data_update['china_shipping_cost']         = $orderPackagesDBs->sum('china_shipping_cost');
        $data_update['discount_cost']               = $orderPackagesDBs->sum('discount_cost');
        $deliveryDB->update($data_update);
    }

    public function syncCancelToDongDong($packageDBs)
    {
        // Đồng bộ trạng thái hủy sang bên kho
        $arr_json = [];
        foreach ($packageDBs as $key => $packageDB) {
            $temp = [
                'pack_id'             => $packageDB->id,
                'pack_code_warehouse' => $packageDB->code_warehouse,
                'bill_code_old'       => $packageDB->bill_code
            ];
            if ($packageDB->code_warehouse != null && $packageDB->bill_code != null) {
                array_push($arr_json, $temp);
            }
        };

        if (count($arr_json) > 0) {
            $guzzle = new Guzzle;
            $url = env('BASE_URL_DONGDONG') . 'open/sync/pack-set-cancel-admin';
            $options = [
                'json' => [
                    'list_arr_pack' => $arr_json
                ],
                'headers' => [
                    'X-Token-Auth' => env('TOKEN_ONE_WAREHOUSE')
                ],
                'verify' => false,
                'http_errors' => false,
            ];

            $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
            $result = json_decode($result, true);
        }
    }


    public function syncPackToDongDong($record)
    {
        // Sau code bổ sung thêm đoạn nếu có thay đổi các thông tin đồng bộ thì mới bắn thêm sang

        $data_pack_update = [
            'weight'         => $record->weight,
            'length'         => $record->length,
            'width'          => $record->width,
            'height'         => $record->height,
            'volumn'         => $record->volume == null ? 0 : $record->volume,
            'package_number' => $record->package_number,
        ];

        // Nếu là kiện chưa xác định thì gửi thêm thông tin khách hàng nếu có
        if ($record->order_type == null) {
            $customerDB = $record->customer;
            if ($customerDB) {
                $data_pack_update['customer_id'] = $record->customer_id;
                $data_pack_update['customer_code'] = $customerDB->code;
                $data_pack_update['customer_name'] = $customerDB->name;
                $data_pack_update['warehouse_id'] = $record->warehouse_id;

                // Cho kiện về kí gửi
                $data_pack_update['type'] = 'consignment';
                $record->order_type = 'App\Models\Consignment';
                $record->save();
            }
        }

        $data_sync = [
            [
                'id'     => $record->id,
                'params' => $data_pack_update
            ]
        ];

        // Lấy dữ liệu
        $guzzle = new Guzzle;
        $url = env('URL_DONGDONG') . 'webhook/hangve-x329kxm2k2ixk39x92x1911947165';
        $options = [
            'json' => [
                'event_name' => 'update_pack',
                'data'       => $data_sync
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);
    }

    /**
     * @param  OrderPackage  $package
     * @return JsonResponse
     */
    public function getProducts(OrderPackage $package): JsonResponse
    {
        $suppliers = $package->products->groupBy('supplier_id');
        return resSuccessWithinData(new ProductResource($suppliers));
    }

    /**
     * @param  string  $customerId
     * @return JsonResponse
     */
    public function getListVNByCustomerId(string $customerId): JsonResponse
    {
        return resSuccessWithinData(
            OrderPackage::query()->where('customer_id', $customerId)->whereNull('delivery_id')->where(
                'status',
                PackageConstant::STATUS_WAREHOUSE_VN
            )->select('id', 'bill_code', 'order_id', 'weight_real', 'china_shipping_cost', 'shock_proof_cost', 'storage_cost', 'inspection_cost', 'insurance_cost', 'woodworking_cost', 'order_code')->get()
        );
    }

    public function getListVNByCustomerIdV2(string $customerId): JsonResponse
    {
        $dataDB =  OrderPackage::query()->where('customer_id', $customerId)
                                            ->whereNull('delivery_id')
                                            ->where('status',PackageConstant::STATUS_WAREHOUSE_VN)
                                            ->get();

        $data_result = [];
        if (count($dataDB) > 0) {
            foreach ($dataDB as $key => $item) {
                $customer = $item->customer;
                $getCost = (new OrderPackageService())->getCost($item);
                $temp = [
                    'customer_id'         => optional($customer)->id,
                    'customer_name'       => optional($customer)->name,
                    'customer_phone'      => optional($customer)->phone_number,
                    'pack_id'             => $item->id,
                    'bill_code'           => $item->bill_code,
                    'pack_status'         => $item->status,
                    'china_shipping_cost' => $getCost['amount'],
                    'shipping_cost'       => $getCost['amount'],
                    'order_id'            => $item->order_id,
                    'id'                  => $item->id,


                    // Thêm
                    'order_code'       => $item->order_code,
                    'weight_real'      => $item->weight_real,
                    'shock_proof_cost' => $item->shock_proof_cost,
                    'storage_cost'     => $item->storage_cost,
                    'inspection_cost'  => $item->inspection_cost,
                    'insurance_cost'   => $item->insurance_cost,
                    'woodworking_cost' => $item->woodworking_cost,
                    'amount'           => $getCost['amount'],

                ];
                array_push($data_result, $temp);
            }
        }
        return resSuccessWithinData($data_result);
    }

    /**
     * @param  ChangeStatusRequest  $request
     * @return JsonResponse
     */
    public function changeStatus(ChangeStatusRequest $request): JsonResponse
    {
        if ($request->input('status') == PackageConstant::STATUS_RECEIVED_GOODS) {
            $result = ['err' => 'Không dc thao tác chức năng này'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        $packages = OrderPackage::where('id', $request->packages)->where('status', PackageConstant::STATUS_RECEIVED_GOODS)->get();
        if(count($packages)) {
            $result = ['err' => 'Có kiện đã nhận hàng trong danh sách'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        DB::beginTransaction();
        try {
            $dataCheckReceived = [];
            $orderPackages = OrderPackage::query()->findMany($request->packages);

            foreach($orderPackages as $package) {
                $statusOld = data_get($package, 'status');
                if($request->input('status') == PackageConstant::STATUS_RECEIVED_GOODS) {
                    $check = $this->_service->validateSubMoneyAndDoneOrderAfterModifiPack($package);
                    if($check) {
                        $dataCheckReceived[] = $package->bill_code;
                        continue;
                    }
                    $this->_service->subMoneyAndDoneOrderAfterModifiPack($package);
                }
                $package->status = $request->input('status');
                $package->save();
                // update status for order consignment
                $this->_service->updateOrderStatus($package);
                // Thêm noti nếu đổi status
                $this->updatePackageNotiStatus($package, $request);
                if ($statusOld !== $request->input('status')) {
                    if($statusOld == PackageConstant::STATUS_RECEIVED_GOODS) {
                        $this->_service->refundAmount($package);
                    }else {
                        if($request->input('status') == PackageConstant::STATUS_CANCEL) {
                            $this->_service->calculatorWhenCancelPackage($package);
                        }
                    }
                }
            }
            DB::commit();
            return resSuccessWithinData($dataCheckReceived);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

    }
    /**
     * @param  Request $request
     * @return JsonResponse
     */
    public function changeIsMakeUp(Request $request): JsonResponse
    {
        $id = $request->get('id');
        $isMakeUp = $request->get('is_make_up');
        OrderPackage::query()->find($id)->update(['is_make_up' => $isMakeUp]);

        return resSuccess();
    }


    /**
     * @param  string  $ids
     * @return JsonResponse
     */
    public function getChinaCost(string $ids): JsonResponse
    {
        $ids = explode(',', $ids);
        return resSuccessWithinData(OrderPackage::query()->findMany($ids)->sum('china_shipping_cost'));
    }

    public function updatePackageNotiStatus($pack, $params)
    {
        $status = isset($params['status']) ? $params['status'] :  null;

        if (is_null($status)) {
            return;
        }

        $notificationService = \App::make('App\Services\NotificationService');

        $data_pack = [
            'pack_id'         => $pack->id,
            'pack_code'       => $pack->bill_code,
            'pack_status'     => $pack->status,
            'pack_status_txt' => \App\Constants\PackageConstant::STATUSES[$status],
        ];

        $data = [
            'group'           => 3,
            'name'            => 3002,
            'data'            => json_encode($data_pack),
            'time_create'     => time(),
            'status'          => 1
        ];

        $orderDB = $pack->order;

        if ($orderDB) {
            //$staff_care_id      = $orderDB->staff_care_id;
            //$staff_counselor_id = $orderDB->staff_counselor_id;
            $staff_group_id = $orderDB->staff_group_id;

//            if ($staff_care_id) {
//                $notificationService->storeNoti($staff_care_id, $data);
//            }
//            if ($staff_counselor_id) {
//                $notificationService->storeNoti($staff_counselor_id, $data);
//            }
            if ($staff_group_id) {
                $notificationService->storeNoti($staff_group_id, $data);
            }
        }

        $data['customer_id']  = $pack->customer_id;

        $notificationService->store($data);

        // push notification for customer
        $dataNoti = $this->getDataNotiByStatus($pack, $status);
        (new FcmService())->sendNotifyUser([$pack->customer_id], $dataNoti);
    }

    public function getDataNotiByStatus($pack, $status)
    {
        $title = '';
        $body  = '';
        $data  = [
            'screen' => 'PackageDetail',
            'params' => [
                'packId' => $pack->id
            ]
        ];

        switch ($status) {
            case PackageConstant::STATUS_IN_CHINA:
                $title = 'Kiện hàng';
                $body  = 'Kiện '. $pack->bill_code . ' đã về kho TQ';
                break;
            case PackageConstant::STATUS_WAREHOUSE_VN:
                $title = 'Kiện hàng';
                $body  = 'Kiện '. $pack->bill_code . ' đã về kho VN';
                break;
            case PackageConstant::STATUS_RECEIVED_GOODS:
                $title = 'Kiện hàng';
                $body  = 'Kiện '. $pack->bill_code . ' đã nhận hàng';
                break;
        }

        return [
            'title' => $title,
            'body'  => $body,
            'data'  => $data
        ];
    }

    // thêm khách hàng màn kiện
    public function getCustomerPackage()
    {
        $data = request()->all();

        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('code', $data['customer_search'])
                                ->orWhere('phone_number', $data['customer_search'])
                                ->first();

        if ($customer) {
            $result = [
                'customer_has' => 1,
                'id' => optional($customer)->id,
                'name' => optional($customer)->name,
                'phone_number' => optional($customer)->phone_number,
                'warehouse' => optional(optional(optional($customer)->warehouse)->province)->name,
                'warehouse_v2' => optional($customer->warehouse)->only('id', 'custom_name', 'province_id'),
                'province_id' => optional(optional($customer)->warehouse)->province_id,
                'level' => (new ReportCustomerService())->isNewCustomerByCustomerId(
                    optional($customer)->id
                ) ? 'KH Mới' : null,
                'code' => optional($customer)->code
            ];
            return resSuccessWithinData($result);
        }

        $result = [
            'customer_has' => 0,
            'id' => "",
            'name' => "",
            'phone_number' => "",
            'warehouse' => "",
            'level' => "",
            'code' =>"",
        ];
        return resSuccessWithinData($result);
    }

    public function checkBillCodeExist($billCode)
    {
        $data = [
            'isExist' => 0
        ];

        try {
            $check = $this->_service->checkBillCodeExist($billCode);
            $data['isExist'] = $check ? 1 : 0;

            return resSuccessWithinData($data);
        } catch (\Exception $exception) {
            return resError();
        }
    }

    public function export(Request $request)
    {
        $params = $request->all();
        try {
            $time = Carbon::now()->format('YmdHms');
            $fileName = getPathFileExport('package').'/package_'.$time.'.xlsx';
            dispatch(new ExportPackageJob($params, \Auth::user(), $fileName))->onQueue('export-1800');
            \Log::info("Nhân viên có id= ".getCurrentUserId()." đã click xuất excel kiện hàng");
            return resSuccess();
        } catch (\Exception $e) {
            \Log::info($e);
            return resError();
        }
    }

    public function receiveGoods(Request $request)
    {
        $params = $request->all();
        $data = $this->_service->getDataReceiveGoods($params);
        return resSuccessWithinData($data);
    }

    public function updateReceiveGoods(Request $request)
    {
        // Kiểm tra
        $params = $request->all();
        $orderPackIds = data_get($params, 'orderPackIds');
        $validate = $this->validateUpdateReceiveGoods($orderPackIds);
        if ($validate) {
            $result = [
                'error' => $validate
            ];
            return resErrorWithinData($result, null, 422);
        }

        try {
            \DB::beginTransaction();
            $params = $request->all();
            $orderPackIds = data_get($params, 'orderPackIds');
            $filePrintDB =  $this->filePrintService->store($params, FilePrintConstant::KEY_TYPE_PACK_RECEIVE_GOODS);
            foreach($orderPackIds as $id){
                $this->_service->reportStatusTime($id, PackageConstant::STATUS_RECEIVED_GOODS);
                $orderPack = OrderPackage::find($id);
                if($orderPack){
                    OrderPackage::where('id', $id)->update([
                        'status' => PackageConstant::STATUS_RECEIVED_GOODS,
                        'date_done' => Carbon::now(),
                    ]);

                    $orderPack->refresh();

                    \App\Models\Activity::create([
                        'log_name' => 'order_package_status',
                        'content' => PackageConstant::STATUS_RECEIVED_GOODS,

                        'subject_id' => $orderPack->id,
                        'subject_type' => $orderPack->getMorphClass(),

                        'causer_id' => 1,
                        'causer_type' => \App\Models\User::class,
                        'organization_id' =>$orderPack->organization_id,
                        'properties' => json_encode([
                            'event_name' => 'update_receive_goods',

                            'user_dongdong_name' => data_get($params, 'user_dongdong_name'),
                            'user_dongdong_id' => data_get($params, 'user_dongdong_id'),

                            'order_pack_ids' => $orderPackIds,

                            'attributes' => [
                                'status' => PackageConstant::STATUS_RECEIVED_GOODS,
                                'date_done' => optional($orderPack->date_done)->toDateTimeString(),
                            ],
                        ]),
                    ]);
                }

            }

            // Xử lý trừ tiền, hoàn thành đơn khi khách nhận kiện hàng
            $this->subMoneyAndDoneOrderAfterReceiveGood($orderPackIds, $filePrintDB);

            // Cập nhật các kiện bị thất lạc nếu có
            $job = new \App\Jobs\OneWarehouse\SyncStatusPackLost();
            dispatch($job)->delay(now()->addSeconds(10))->onQueue('cron-data-2023');

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
            return resError();
        }
    }

    // Kiểm tra tài khoản khách được phép trừ âm nếu không đủ tiền không
    public function validateUpdateReceiveGoods($orderPackIds)
    {
        if (count($orderPackIds) == 0) {
            return "Không có kiện nào được gửi lên";
        }

        // Check trạng thái hợp lệ
        $orderPackageModel = \App::make('App\Models\OrderPackage');
        $orderPackageFull = $orderPackageModel->whereIn('id', $orderPackIds)->get();
        foreach ($orderPackageFull as $key => $orderPackageDB) {
            if ($orderPackageDB->delivery_id != null) {
                return 'Kiện ' . $orderPackageDB->bill_code . ' hàng đã có giao hàng, vui lòng xóa giao hàng trước khi trả hàng';
            }
            if ($orderPackageDB->status != PackageConstant::STATUS_WAREHOUSE_VN) {
                return 'Kiện ' . $orderPackageDB->bill_code . ' chưa đến kho VN, không thể xử lý';
            }
        }

        // Lấy ra khách hàng xem có cấu hình ưu đãi không
        $customerId = data_get(OrderPackage::whereIn('id', $orderPackIds)->first(), 'customer_id');
        $customerOfferModel = \App::make('App\Models\CustomerOffer');
        $customerOfferDB = $customerOfferModel->where('customer_id', $customerId)->first();

        if ($customerOfferDB && $customerOfferDB->debt == 1) {
            return null;
        }

        // Tính tiền và so sánh
        // Tính tiền và so sánh

        $orderPackageModel = \App::make('App\Models\OrderPackage');
        $orderModel = \App::make('App\Models\Order');
        $consigmentModel = \App::make('App\Models\Consignment');

        // Xử lý các kiện Order (tiền hàng con thiếu + phí ship)
        $orderPackageTypeOrder = $orderPackageModel->whereIn('id', $orderPackIds)
                                    ->where('order_type', OrderPackage::ORDER_TYPE_DEFAULT)
                                    ->get();


        $collection = collect($orderPackageTypeOrder->toArray());
        $grouped = $collection->groupBy('order_id');
        $grouped->all();

        $total_debt_cost = 0;
        $total_all_ship_1 = 0;
        foreach ($grouped as $order_id => $packs) {
            $orderDB = $orderModel->where('id', $order_id)->first();
            // Nếu chưa ghi nhận lấy dc tiền hàng còn thiếu
            if ($orderDB && $orderDB->delivery_pay_id == 0) {
                // Đơn TD đồng bộ thì không làm tròn tiền
                if ($orderDB->thuongdo_id != null && $orderDB->has_edit == 0) {
                    \Log::info('validateUpdateReceiveGoods oh oh');
                    $total_debt_cost += $orderDB->thuongdo_tong_tien_don_hang - $orderDB->thuongdo_tong_tien_tam_ung;
                } else {
                    $total_debt_cost += $orderDB->debt_cost;
                }
            }

            if ($orderDB) {
                // Tiền ship
                $total_all_ship_1 += $packs->sum('amount');
            }
        }

        // Xử lý các kiện không phải Order
        $orderPackageTypeNotOrder = $orderPackageModel->whereIn('id', $orderPackIds)
                                    ->where(function ($q) {
                                        $q->where('order_type', '!=', OrderPackage::ORDER_TYPE_DEFAULT)
                                        ->orWhere('order_type', NULL);
                                    })
                                    ->get();

        $collection = collect($orderPackageTypeNotOrder->toArray());
        $grouped = $collection->groupBy('order_id');
        $grouped->all();

        $total_all_ship_2 = 0;
        foreach ($grouped as $order_id => $packs) {
            $orderDB = $consigmentModel->where('id', $order_id)->first();
            // Tiền ship
            $total_all_ship_2 += $packs->sum('amount');
        }


        // Tổng tiền phải thanh toán:
        $allMoney = $total_debt_cost + $total_all_ship_1 + $total_all_ship_2;

        $customerService = \App::make('App\Services\CustomerService');
        $balance = $customerService->getBalanceAmount($customerId);

        if ($allMoney > round($balance)) {
            \Log::info('validateUpdateReceiveGoods 1: ' . $total_all_ship_1 . '-' . $total_all_ship_2);
            \Log::info('validateUpdateReceiveGoods 2: ' . '-' . $balance);
            \Log::info('validateUpdateReceiveGoods 3: ' . '-' . $allMoney);
            \Log::info('validateUpdateReceiveGoods 3: ' . '-' . $total_debt_cost);
            return 'Bạn không đủ tiền';
        }

        // Check thêm
        if (env('APP_URL') == 'https://api-admin.hangve.com') {
            $orderPackIds_str = implode('_', $orderPackIds);
            $lock = \Cache::lock('validateUpdateReceiveGoods_' . $orderPackIds_str, 300);
            if (!$lock->get()) {
                return 'Đang có tiến trình đang thực hiện, vui lòng thử lại sau 10 phút';
            }
        }

        return null;
    }

    public function subMoneyAndDoneOrderAfterReceiveGood($orderPackIds, $filePrintDB)
    {
        $orderPackageModel = \App::make('App\Models\OrderPackage');
        $orderModel = \App::make('App\Models\Order');
        $consigmentModel = \App::make('App\Models\Consignment');

        // Xử lý các kiện Order (tiền hàng con thiếu + phí ship)
        $orderPackageTypeOrder = $orderPackageModel->whereIn('id', $orderPackIds)
                                    ->where('order_type', OrderPackage::ORDER_TYPE_DEFAULT)
                                    ->get();

        $collection = collect($orderPackageTypeOrder->toArray());
        $grouped = $collection->groupBy('order_id');
        $grouped->all();

        $total_debt_cost = 0;
        $total_all_ship_1 = 0;
        foreach ($grouped as $order_id => $packs) {
            $orderDB = $orderModel->where('id', $order_id)->first();
            // Nếu chưa ghi nhận lấy dc tiền hàng còn thiếu
            if ($orderDB && $orderDB->delivery_pay_id == 0) {
                // Khi thanh toán kiện thì tiền hàng còn thiếu cũng đã được thanh toán. Nên update field này về 0
                $orderDB->remaining_amount =  0;
                $orderDB->save();
                $total_debt_cost += $orderDB->debt_cost;

                // Đánh dấu đã trừ tiền hàng còn thiếu
                $orderDB->delivery_pay_id        = $filePrintDB->id;
                $orderDB->delivery_pay_debt_cost = $orderDB->debt_cost;
                $orderDB->delivery_pay_source    = OrderConstant::DELIVERY_PAY_SOURCE_RECEIVE_GOODS;

                // Đơn TD đồng bộ thì không làm tròn tiền
                if ($orderDB->thuongdo_id != null && $orderDB->has_edit == 0) {
                    $orderDB->delivery_pay_debt_cost = $orderDB->thuongdo_tong_tien_don_hang - $orderDB->thuongdo_tong_tien_tam_ung;
                }

                // Tạo giao dịch trừ tiền hàng còn thiếu
                $this->_service->makeTransactionDebtCost($orderDB, $filePrintDB);

            }

            // Nếu đã nhận 1 phần
            if ($orderDB && $orderDB->delivery_pay_id > 0) {
                // Nếu có update thêm vào đơn
                if ($orderDB->remaining_amount > 0) {
                    $debt_cost_more = $orderDB->debt_cost - $orderDB->delivery_pay_debt_cost;
                    if ($debt_cost_more > 0) {
                        $orderDB->delivery_pay_debt_cost += $debt_cost_more;
                        // Tạo giao dịch trừ tiền hàng còn thiếu
                        $this->_service->makeTransactionDebtCost($orderDB, $filePrintDB, $debt_cost_more);
                        // Khi thanh toán kiện thì tiền hàng còn thiếu cũng đã được thanh toán. Nên update field này về 0
                        $orderDB->remaining_amount =  0;
                    }
                }
            }

            if ($orderDB) {
                // Tiền ship
                $total_all_ship_1 += $packs->sum('amount');
                $orderDB->delivery_pay_shipping_cost += $packs->sum('amount');
                $orderDB->save();
            }

            // Tạo giao dịch trừ tiền ship
            $this->_service->makeTransactionShipingCost($orderDB, $filePrintDB, $packs);

            if ($orderDB) {
                // Kiểm tra xem đã nhận hết kiện chưa, tự động hoàn thành đơn
                // $packagesDB = $orderDB->packagesOrder;
                $packagesDB = $orderDB->packagesOrder->where('status', '!=', PackageConstant::STATUS_CANCEL);
                if (count($packagesDB)) {
                    // Nếu toàn bộ kiện đểu đã về hoàn thành thì cho hoàn thành đơn
                    $flagDoneAll = true;
                    foreach ($packagesDB as $key => $item) {
                        if ($item->status !=  PackageConstant::STATUS_RECEIVED_GOODS) {
                            $flagDoneAll = false;
                            break;
                        }
                    }
                    if ($flagDoneAll) {
                        // \Log::info('Hoàn thành đơn đê subMoneyAndDoneOrderAfterReceiveGood');
                        $orderDB->date_done = Carbon::now();
                        $orderDB->status = OrderConstant::KEY_STATUS_DONE;
                        $orderDB->save();
                    }
                }
            }
        }
        // dump('Tiền hàng còn thiếu: ' . $total_debt_cost);




        // Xử lý các kiện không phải Order
        $orderPackageTypeNotOrder = $orderPackageModel->whereIn('id', $orderPackIds)
                                    ->where(function ($q) {
                                        $q->where('order_type', '!=', OrderPackage::ORDER_TYPE_DEFAULT)
                                        ->orWhere('order_type', NULL);
                                    })
                                    ->get();


        $collection = collect($orderPackageTypeNotOrder->toArray());
        $grouped = $collection->groupBy('order_id');
        $grouped->all();

        $total_all_ship_2 = 0;
        foreach ($grouped as $order_id => $packs) {
            $orderDB = $consigmentModel->where('id', $order_id)->first();
            // Tiền ship
            $total_all_ship_2 += $packs->sum('amount');
            // Tạo giao dịch trừ tiền ship
            $this->_service->makeTransactionShipingCost($orderDB, $filePrintDB, $packs);

            // Cập nhật trạng thái đơn hàng
            if ($orderDB) {
                // Kiểm tra xem đã nhận hết kiện chưa, tự động hoàn thành đơn
                // $packagesDB = $orderDB->orderPackage;
                $packagesDB = $orderDB->orderPackage->where('status', '!=', PackageConstant::STATUS_CANCEL);

                if (count($packagesDB)) {
                    // Nếu toàn bộ kiện đểu đã về hoàn thành thì cho hoàn thành đơn
                    $flagDoneAll = true;
                    foreach ($packagesDB as $key => $item) {
                        if ($item->status !=  PackageConstant::STATUS_RECEIVED_GOODS) {
                            $flagDoneAll = false;
                            break;
                        }
                    }
                    if ($flagDoneAll) {
                        // \Log::info('Hoàn thành đơn đê subMoneyAndDoneOrderAfterReceiveGood kí gửi');
                        $orderDB->date_done = Carbon::now();
                        $orderDB->status = ConsignmentConstant::KEY_STATUS_DONE;
                        $orderDB->save();
                    }
                }
            }
        }
        // dump('Phí ship còn thiếu: ' . ($total_all_ship_1 + $total_all_ship_2));
    }

    public function printWord(Request $request)
    {
        $params = $request->all();
        // Bấm in cũng lưu lại lịch sử
        $this->filePrintService->store($params, FilePrintConstant::KEY_TYPE_PACK_RECEIVE_GOODS);

        $data = $this->_service->getDataPrintPack($params);
        $viewContent = View::make('print.warehouse_pack', $data)->render();
        /*
        Nếu dùng để export word thì dùng chỗ này
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $section->addText($viewContent);
        $fileName = 'demo/order_package' . '-' . now()->toDateString() . '.doc';
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        $objWriter->save($fileName);
        return response()->download($fileName)->deleteFileAfterSend(true);
        */
        return resSuccessWithinData($viewContent);
    }
    public function reportPrint(Request $request)
    {
        $params = $request->all();
        $data = $this->_service->getDataReportPrintPack($params);
        $reportService = app()->make(ReportService::class);
        return $reportService->saveReconciliationWarehouse($data);
    }

    public function exportReceiveGoods(Request $request)
    {
        $params = $request->all();
        $data = $this->_service->getDataPrintPack($params);
//       $viewContent = View::make('print.warehouse_pack', $data)->render();

//        $phpWord = new \PhpOffice\PhpWord\PhpWord();
//        $section = $phpWord->addSection();
//        $section->addText($viewContent);
//        $fileName = 'demo/order_package' . '-' . now()->toDateString() . '.doc';
//        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
//        $objWriter->save($fileName);
//        return response()->download($fileName)->deleteFileAfterSend(true);
        return Excel::download((new PackPrintReceiveGoodsExport($data)), 'PhieuInTraHang_'. time() . '.xlsx');
    }

    /**
     * @param SetOrderCodeRequest $request
     * @param OrderPackage $package
     */
    public function setOrderCode(SetOrderCodeRequest $request, OrderPackage $package)
    {
        try {
            if ($package->order_id) {
                return resSuccess();
            }

            // Logic ngày 23/01/25: Nếu là kiện kí gửi thì ko cần phải set
            if ($package->order_type != 'App\Models\Order') {
                return resError();
            }

            $data = $request->only('order_code', 'orderByCode');
            $this->_service->setOrderCode($package, $data);

            return resSuccess();
        } catch (\Exception $e) {
            return resError($e->getMessage());
        }
    }
    public function notiPackRefund(Request $request)
    {
        $packageModel = $this->_service->_model;
        $code = isset($request->code) ? $request->code :  null;
        if (is_null($code)) {
            return;
        }
        $pack = $packageModel->where('bill_code', $code)->first();

        $notificationService = \App::make('App\Services\NotificationService');
        $notificationService->packNotification($pack, 3003);
    }
    public function setCodeOrderDD(Request $request)
    {
        try {
            $order = Order::query()->where('code', $request->order_code)->first();
            if($order == null) {
                $result = [
                    'error' => 'Mã đơn hàng không tồn tại'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            $options = [
                'json' => [
                    'order'       => $order,
                    'pack_id'    => $request->pack_id
                ]
            ];
            $this->updateOrderCodeDD($options);
            $customerId = $order->customer_id;
            $customer = Customer::where('id', $customerId)->first();
            if ($customer) {
                $result = [
                    'customer_has' => 1,
                    'customer_code' => $customer->code,
                    'customer_name' => $customer->name,
                    'customer_id' => $customer->id,
                    'company_id' => $customer->organization_id,
                    'staff_group_id' => $customer->staff_group_id
                ];
                return resSuccessWithinData($result);
            }

            $result = [
                'customer_has' => 0,
                'customer_code' => "",
                'customer_name' => "",
                'customer_id' => "",
                'company_id' => 0
            ];
            return resSuccessWithinData($result);
        } catch (\Exception $e) {
            return resError($e->getMessage());
        }
    }
    public function updateOrderCodeDD($options)
    {
        $guzzle = new Guzzle;
        $url = env('URL_DONGDONG') . 'pack/update-order-code';
        $guzzle->request('POST', $url, $options);
    }

    public function getCategory()
    {
        return resSuccessWithinData(Category::select('id', 'name')->get());
    }

    public function getReportPackageList()
    {
        try {
            $data = $this->_service->getReportPackageList();
            return resSuccessWithinData($data);
        } catch (\Exception $e) {
            return resErrorWithinData([]);
        }
    }

    public function printMultiple(Request $request)
    {
        $validationRules = [
            'package_ids' => 'required|array',
            'package_ids.*' => 'required|exists:order_package,id',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = request()->all();
        $packageIds = data_get($params, 'package_ids');
        $packages = OrderPackage::whereIn('id', $packageIds)->with(
            [
                'order',
                'customerDelivery',
                'customerDelivery.district',
                'customerDelivery.province',
                'organization',
                'warehouse'
            ]
        )->get();
        foreach($packages as $package) {
            if($package->customer_id == null) {
                $result = [
                    'error' => 'Kiện '.$package->bill_code. ' không có thông tin khách hàng'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }
        $arrayCustomerId = $packages->pluck('customer_id')->toArray();
        if(count(array_unique($arrayCustomerId)) > 1) {
            $result = [
                'error' => 'Chỉ được chọn kiện của 1 khách hàng'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        $data = $this->_service->formatDataPrint($packages);
        return resSuccessWithinData($data);
    }

    public function exportMultiple(Request $request)
    {
        $params = $request->all();
        $packageIds = data_get($params, 'package_ids');
        $packages = OrderPackage::whereIn('id', $packageIds)->with(
            [
                'order',
                'customerDelivery',
                'customerDelivery.district',
                'customerDelivery.province',
                'organization',
                'warehouse'
            ]
        )->get();

        $data = $this->_service->formatDataPrint($packages);
        return Excel::download((new PackPrintWarehouseExport($data)), 'PhieuInKho_'. time() . '.xlsx');
    }
    public function countPackage()
    {
        $count = Cache::get('count_package', '');
        if (!$count) {
            $count = OrderPackage::query()->count();
            Cache::put('count_package', $count, now()->addMinute(5));
        }
        return resSuccessWithinData($count);
    }

    /**
     * @throws GuzzleException
     */
    private function syncPackageToDongDong($payload){
        $syncPackJob = new \App\Jobs\OneWarehouse\SyncPackJob($payload);
        dispatch($syncPackJob)->onQueue('export-1800');
    }

    /**
     * @throws GuzzleException
     */
    public function syncNoteWarehouse($package, $params)
    {
        $data_sync = [
            'id' => $package->id,
            'note' => data_get($params, 'note')
        ];
        $guzzle = new Guzzle;
        $url = $this->dongDongUrl;
        $options = [
            'json' => [
                'event_name' => 'update_note',
                'data' => $data_sync
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $guzzle->request('POST', $url, $options)->getBody()->getContents();
    }

    /**
     * @throws GuzzleException
     */
    public function syncChinaShippingCost($package, $params)
    {
        $data_sync = [
            'id' => $package->id,
            'china_shipping_cost' => data_get($params, 'china_shipping_cost_cny')
        ];
        $guzzle = new Guzzle;
        $url = $this->dongDongUrl;
        $options = [
            'json' => [
                'event_name' => 'china_shipping_cost',
                'data' => $data_sync
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $guzzle->request('POST', $url, $options)->getBody()->getContents();
    }

    /**
     * @throws GuzzleException
     */
    public function syncPackageNumberWarehouse($package, $params)
    {
        $data_sync = [
            'id' => $package->id,
            'package_number' => data_get($params, 'package_number')
        ];
        $guzzle = new Guzzle;
        $url = $this->dongDongUrl;
        $options = [
            'json' => [
                'event_name' => 'update_package_number',
                'data' => $data_sync
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $guzzle->request('POST', $url, $options)->getBody()->getContents();
    }

    public function checkCodeExist(Request $request)
    {
        try {
            $isExist         = $this->_service->checkCodeExist($request->get('code_po'));
            $data['isExist'] = $isExist;

            return resSuccessWithinData($data);
        } catch (\Exception $e) {
            return resErrorWithinData([], 'Server error');
        }
    }

    public function printMultipleAll(Request $request)
    {
        $validationRules = [
            'customer_phone' => 'required|exists:customers,phone_number',
            'status'         => 'nullable',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = request()->all();

        // Lấy ra mã khách hàng
        $customerModel = \App::make('App\Models\Customer');
        $customerDB = $customerModel->where('phone_number', $params['customer_phone'])->first();

        // Lọc ra các kiện
        $packages = OrderPackage::where('customer_id', (int)$customerDB->id)
                    ->orderBy('id', 'desc')
                    ->with(
                        [
                            'order',
                            'customerDelivery',
                            'customerDelivery.district',
                            'customerDelivery.province',
                            'organization',
                            'warehouse'
                        ]);


        if (isset($params['status']) && !is_null($params['status'])) {
            $packages = $packages->where('status', $params['status']);
        }
        $packages = $packages->offset(0)->limit(500)->get();

        $data = $this->_service->formatDataPrint($packages);
        return resSuccessWithinData($data);
    }

    public function exportMultipleAll(Request $request)
    {
        $validationRules = [
            'customer_phone' => 'required|exists:customers,phone_number',
            'status'         => 'nullable',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = request()->all();

        // Lấy ra mã khách hàng
        $customerModel = \App::make('App\Models\Customer');
        $customerDB = $customerModel->where('phone_number', $params['customer_phone'])->first();

        // Lọc ra các kiện
        $packages = OrderPackage::where('customer_id', (int)$customerDB->id)
                    ->orderBy('id', 'desc')
                    ->with([
                        'order',
                        'customerDelivery',
                        'customerDelivery.district',
                        'customerDelivery.province',
                        'organization',
                        'warehouse'
                    ]);


        if (isset($params['status']) && !is_null($params['status'])) {
            $packages = $packages->where('status', $params['status']);
        }
        $packages = $packages->offset(0)->limit(500)->get();

        $data = $this->_service->formatDataPrint($packages);
        return Excel::download((new PackPrintWarehouseExport($data)), 'PhieuInKho_'. time() . '.xlsx');
    }

    public function storeWaitingConfirm(Request $request)
    {
        $params = $request->all();
        $customer = Customer::where('id', data_get($params, 'customer_id'))->first();
        $data = [
            'order_code' => data_get($params, 'order_code'),
            'bill_code' => data_get($params, 'pack_code'),
            'code_warehouse' => data_get($params, 'code'),
            'warehouse_id' => data_get($customer, 'warehouse_id'),
            'category_id' => data_get($params, 'category_id'),
            'customer_id' => data_get($customer, 'id'),
            'weight' => data_get($params, 'weight'),
            'weight_real' => data_get($params, 'weight_real'),
            'volumn_enter' => data_get($params, 'volumn'),
            'length' => data_get($params, 'length'),
            'width' => data_get($params, 'width'),
            'height' => data_get($params, 'height'),
            'package_number' => data_get($params, 'package_number'),
            'delivery_type' => data_get($params, 'delivery_type') == 1 ? 'normal' : 'fast',
            'is_inspection' => data_get($params, 'is_inspection'),
            'is_woodworking' => data_get($params, 'is_woodworking'),
            'is_shock_proof' => data_get($params, 'is_shock_proof'),
            'is_insurance' => data_get($params, 'is_insurance'),
            'is_advanced_inspection' => data_get($params, 'is_advanced_inspection'),
            'staff_order_id' => data_get($customer, 'staff_order_id'),
            'organization_id' => data_get($params, 'company_id'),
            'staff_group_id' => data_get($customer, 'staff_group_id'),
            'status' => PackageConstant::STATUS_IN_GUANGZHOU
        ];
        $order = Order::where('code', data_get($params, 'order_code'))->first();
        if($order) {
            $orderSupplier = OrderSupplier::query()->where(['order_id' => $order->id])->first();
            $data['order_type'] = Order::class;
            $data['order_id'] = $order->id;
            $data['is_inspection'] = $orderSupplier->is_inspection ?? $order->is_inspection;
            $data['is_woodworking'] = $orderSupplier->is_woodworking ?? $order->is_woodworking;
            $data['is_shock_proof'] = $orderSupplier->is_shock_proof ?? $order->is_shock_proof;
            $data['is_advanced_inspection'] = $orderSupplier->is_advanced_inspection ?? $order->is_advanced_inspection;
            $data['staff_group_id'] = $order->staff_group_id;
            $data['staff_order_id'] = $order->staff_order_id;
            $data['warehouse_id'] = $order->warehouse_id;
            $data['official'] = $order->official;
            $data['type_official'] = $order->type_official;
            $data['official_is_partner'] = $order->official_is_partner;
        }
        try {
            DB::beginTransaction();
            $hangveId = data_get($params, 'hangve_id');
            $orderPackage = OrderPackage::find($hangveId);
            if($orderPackage) {
                $orderPackage->customer_id = data_get($customer, 'id');
                $orderPackage->staff_order_id = data_get($customer, 'staff_order_id');
                $orderPackage->warehouse_id = data_get($customer, 'warehouse_id');
                if($order) {
                    $orderSupplier = OrderSupplier::query()->where(['order_id' => $order->id])->first();
                    $orderPackage->order_type = Order::class;
                    $orderPackage->order_id = $order->id;
                    $orderPackage->staff_group_id = $order->staff_group_id;
                    $orderPackage->staff_order_id = $order->staff_order_id;
                    $orderPackage->is_inspection = $orderSupplier->is_inspection ?? $order->is_inspection;
                    $orderPackage->is_woodworking = $orderSupplier->is_woodworking ?? $order->is_woodworking;
                    $orderPackage->is_shock_proof = $orderSupplier->is_shock_proof ?? $order->is_shock_proof;
                    $orderPackage->is_advanced_inspection = $orderSupplier->is_advanced_inspection ?? $order->is_advanced_inspection;
                    $orderPackage->warehouse_id = $order->warehouse_id;
                    $orderPackage->official = $order->official;
                    $orderPackage->type_official = $order->type_official;
                    $orderPackage->official_is_partner = $order->official_is_partner;
                }
                $orderPackage->save();
            }else {
                $orderPackage = OrderPackage::create($data);
            }
            if($customer && $orderPackage) {
                $dateImportCn = data_get($params, 'date_import_cn') ?? date('Y-m-d H:i:s');
                OrderPackageStatusTime::updateOrCreate(
                    ['key' => PackageConstant::STATUS_IN_GUANGZHOU, 'order_package_id' => $orderPackage->id],
                    ['key' => PackageConstant::STATUS_IN_GUANGZHOU, 'order_package_id' => $orderPackage->id, 'created_at' => $dateImportCn]
                );
                Activity::query()->create(
                    [
                        'subject_type' => OrderPackage::class,
                        'subject_id' => $orderPackage->id,
                        'causer_type' => get_class(getCurrentUser()),
                        'causer_id' => getCurrentUser()->id,
                        'log_name' => ActivityConstant::PACKAGE_STATUS,
                        'content' => PackageConstant::STATUS_IN_GUANGZHOU,
                        'created_at' => $dateImportCn
                    ]
                );
                if($customer->service == 1) {
                    $customerService = app()->make(CustomerService::class);
                    $customerService->saveCustomerLabel($customer->id);
                }
                if($customer->last_order_at == null || $customer->last_order_at < $orderPackage->created_at) {
                    $customer->last_order_at = $orderPackage->created_at;
                    $customer->save();
                }
            }

            DB::commit();
            return resSuccessWithinData($orderPackage);
        } catch (Exception $e) {
            DB::rollback();
            \Log::info($e);
            return resError();
        }
    }

    public function listStatus()
    {
        try {
            $data = $this->_service->listStatus();
            return resSuccessWithinData($data);
        } catch (\Exception $e) {
            return resErrorWithinData([], 'Error');
        }
    }
    public function searchByCode(Request $request)
    {
        try {
            $params = $request->all();
            $keyword = data_get($params, 'keyword');
            $column = data_get($params, 'column');
            if(!in_array($column, ['bill_code', 'code_warehouse'])){
                return resSuccessWithinData([]);
            }
            $results = OrderPackage::whereNotNull($column);
            if($keyword) {
                $results->where($column, 'like', '%'.$keyword.'%');
            }
            $results = $results->limit(50)->groupBy($column)->pluck($column);
            return resSuccessWithinData($results);
        } catch (\Exception $e) {
            return resErrorWithinData([], 'Error');
        }
    }

    public function changePricePack(Request $request)
    {
        DB::beginTransaction();
        try {
            $dataChangePrices = $request->all();
            $idPackages = array_column($dataChangePrices,'id');
            if(OrderPackage::query()->whereIn('id', $idPackages)->where('status', PackageConstant::STATUS_RECEIVED_GOODS)->count() > 0) {
                $result = [
                    'error' => 'Kiện đã nhận hàng không được thay đổi phí vận chuyển quốc tế'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            $packageAll = OrderPackage::query()->whereIn('id', $idPackages)->get();
            $results = [];

            foreach($dataChangePrices as $item) {
                if(empty($item)) {
                    continue;
                }
                $package = $packageAll->filter(function($pack) use ($item) {
                    return $pack->id == $item['id'];
                })->first();
                if(empty($package)) {
                    continue;
                }
                // Nếu kiện không ở trạng thái đã nhận hàng và kiện đã được tạo giao hàng. Thì show lỗi luôn
                if(
                    $package->status != PackageConstant::STATUS_RECEIVED_GOODS
                    && $package->delivery_id
                ) {
                    $results[] = [
                        'reason' => 'Không thay đổi được giá kiện khi có đơn giao hàng đang được xử lý',
                        'price_change' => 0,
                        'bill_code' => $package->bill_code ?? '',
                        'code_po' => $package->code_po ?? '',
                    ];
                    continue;
                }

                $isKg = $package->type_quantitative == PackageConstant::WEIGHT;
                if($isKg) {
                    $international_shipping_cost = $item['unit_price'] * $package->weight;
                } else {
                    $international_shipping_cost = $item['unit_price'] * $package->volume;
                }
                if(
                    $package->status == PackageConstant::STATUS_RECEIVED_GOODS
                ) {
                    // Tính chênh lệch để hoàn tiền hoặc trừ ví
                    $priceOld = $package->international_shipping_cost;
                    //Chiết khấu của kiện cũ.
                    $discountPrice = $priceOld * $package->discount_percent / 100;
                    $priceChange = $international_shipping_cost - $priceOld + $discountPrice;
                    $data = '';
                    if($priceChange != 0) {
                        $data = $this->makeTransactionShippingCost($package, $priceChange);
                        if($data) {
                            $results[] = [
                                'reason' => '',
                                'price_change' => number_format($priceChange),
                                'bill_code' => $package->bill_code ?? '',
                                'code_po' => $package->code_po ?? '',
                            ];
                            continue;
                        }
                        $order = Order::query()->where('id', $package->order->id)->first();
                        $order->update(
                            ['delivery_pay_shipping_cost' => $order->delivery_pay_shipping_cost + $priceChange]
                        );
                    }
                    if(empty($data)) {
                        $this->updateOrderAndPackage($package, $item, $international_shipping_cost);
                    }
                } else {
                    $this->updateOrderAndPackage($package, $item, $international_shipping_cost);
                }
            }
                 DB::commit();
            return resSuccessWithinData($results);
        } catch (\Exception $e) {
            DB::rollback();
            // dd($e->getLine(), $e->getFile(), $e->getMessage());
            return resErrorWithinData([], 'Error');
        }
    }

    public function updateOrderAndPackage($package, $item, $international_shipping_cost)
    {
        // Chỉ update phí vc quốc tế
        OrderPackage::query()->where('id', $package->id)->update(
            [
                'international_shipping_cost' => $international_shipping_cost,
                'unit_price' => $item['unit_price'],
                'discount_percent' => 0,
                'discount_cost' => 0
            ]
        );
        // update đơn hàng và kiện hàng
        if($package->order) {
            $total_international_shipping_cost = OrderPackage::query()->where('order_id', $package->order->id)->sum('international_shipping_cost');
            Order::query()->where('id', $package->order->id)->update(['international_shipping_cost' => $total_international_shipping_cost]);
            OrderSupplier::query()
                ->where('order_id', $package->order->id)
                ->update(['international_shipping_cost' => $total_international_shipping_cost]);
        }
        // Lưu lịch sử thay đổi kiện hàng
        $this->setLogShippingCost($package, $package->unit_price, $item['unit_price']);
    }

    public function setLogShippingCost($package, $oldCost, $newCost)
    {
        if($oldCost != $newCost) {
            (new ActivityService())->setLog(
                $package,
                'international_shipping_cost',
                ActivityConstant::PACKAGE_INFO,
                null,
                json_encode(['old' => $oldCost, 'new' => $newCost])
            );
        }
    }

    public function makeTransactionShippingCost($package, $amount)
    {
        $dataError = '';
        $paramsBillCode   = [
            'amount' => ConvertHelper::numericToVND(floatval(abs($amount))),
            'bill_code'   => $package->bill_code,
            'order_code'   => $package->order_code
        ];
        $paramsCodePo   = [
            'amount' => ConvertHelper::numericToVND(floatval(abs($amount))),
            'bill_code'   => $package->code_po,
            'order_code'   => $package->order_code
        ];
        $customerId = $package->customer_id;
        // amount là tiền mới trừ tiền cũ
        if($amount > 0) {
            if($package->bill_code) {
                $msgTrans = trans(
                    "transaction.payment_shipping_cost",
                    $paramsBillCode
                );
                if(!$package->order_code) {
                    $msgTrans = trans(
                        "transaction.payment_shipping_cost_not_order",
                        $paramsBillCode
                    );
                }
            } else {
                // code po luôn có
                $msgTrans = trans(
                    "transaction.payment_shipping_cost_code_po",
                    $paramsCodePo
                );
                if(!$package->order_code) {
                    $msgTrans = trans(
                        "transaction.payment_shipping_cost_code_po_not_order",
                        $paramsCodePo
                    );
                }
            }
            $status = TransactionConstant::STATUS_PURCHASE;
            $isIncrement = false;

            $balance = (new CustomerService())->getBalanceAmount($customerId);
            if($balance < $amount) {
                $dataError = $package;
            }
        } else {
            if($package->bill_code) {
                $msgTrans = trans(
                    "transaction.refund_shipping_cost",
                    $paramsBillCode
                );
                if(!$package->order_code) {
                    $msgTrans = trans(
                        "transaction.refund_shipping_cost_not_order",
                        $paramsBillCode
                    );
                }
            } else {
                // code po luôn có
                $msgTrans = trans(
                    "transaction.refund_shipping_cost_code_po",
                    $paramsCodePo
                );
                if(!$package->order_code) {
                    $msgTrans = trans(
                        "transaction.refund_shipping_cost_code_po_not_order",
                        $paramsCodePo
                    );
                }
            }
            $status = TransactionConstant::STATUS_REFUND;
            $isIncrement = true;
        }

        $transactionService = app()->make('App\Services\TransactionService');
        if(empty($dataError)) {
            if($isIncrement) {
                $transactionService->setTransactionIncrement(
                    abs($amount),
                    $status,
                    $msgTrans,
                    $customerId,
                    $package,
                    ''
                );
            } else {
                $transactionService->setTransactionDecrement(
                    $amount,
                    $status,
                    $msgTrans,
                    $customerId,
                    $package,
                    ''
                );
            }
        }

        return $dataError;
    }

    public function changeStaff($id, Request $request): JsonResponse
    {
        $data = $request->only('staff_group_id');
        try {
            $user = auth()->user();
            $orderPackage = OrderPackage::query()->where('id', $id)->first();
            if($user->hasPermissionName('pack.change-staff-group-status-16', $user) == false
                && $orderPackage->status == PackageConstant::STATUS_RECEIVED_GOODS
            ) {
                return resError('Không thể chuyển nhân viên ở trạng thái đã nhận hàng');
            }
            OrderPackage::query()->where('id', $id)->update($data);
            return resSuccess();
        } catch (\Exception $e) {
            return resError();
        }
    }

    public function updateExchangeRate(Request $request): JsonResponse
    {
        $validationRules = [
            'pack_id' => 'required|exists:order_package,id',
            'exchange_rate' => 'required|numeric|min:2500|max:5500'
        ];
        $validationMessages = [

        ];

        $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $orderPackModel = App::make('App\Models\OrderPackage');
        $orderPackDB = $orderPackModel->where('id', $params['pack_id'])->first();
        if ($orderPackDB->exchange_rate != 0 ) {
            $result = [
                'unit' => 'Kiện đã có tỉ giá, không được phép sửa'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            DB::beginTransaction();
            $orderPackDB->exchange_rate = $params['exchange_rate'];
            $orderPackDB->save();
            DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            DB::rollback();
            return resError($e->getMessage());
        }
    }

    public function updateBillCode(Request $request): JsonResponse
    {
        return resError('Tính năng đang được nâng cấp!');
        $order_code = $request->order_code ?? '';
        if (!$order_code) {
            $validated = Cache::get('validate_time_update_bill_code', '');
            if ($validated) {
                return resError('Tài khoản ' . $validated . ' mới cập nhật mã vận đơn ! Vui lòng cập nhật lại sau 5 phút!');
            }
            Cache::put('validate_time_update_bill_code', Auth::user()->name , now()->addMinutes(5));
        }
        $this->_service->updateBillCode($request->all());
        return resSuccess();
    }

    public function syncDateDone()
    {
        $packages = OrderPackage::where('status', PackageConstant::STATUS_RECEIVED_GOODS)->whereNull('thuongdo_id')
            ->whereNull('date_done')
            ->limit(500)->get();
        \Log::info($packages->count());
        foreach($packages as $package){
            $package->date_done = $package->updated_at;
            $package->save();
        }
        // dispatch(new SyncDateDone())->onQueue('export-1800');
        return resSuccess();

    }

    public function syncDateDoneV2()
    {
        $packages = OrderPackage::where('status', PackageConstant::STATUS_RECEIVED_GOODS)
            ->whereNull('date_done')
            ->whereDate('created_at', '>', '2024-01-01')
            ->get();
        \Log::info($packages->count());
        OrderPackage::where('status', PackageConstant::STATUS_RECEIVED_GOODS)
            ->whereNull('date_done')
            ->whereDate('created_at', '>', '2024-01-01')
            ->update(['date_done' => '2024-10-25 00:00:00']);
        return resSuccess();
    }

    public function changeBillCode(Request $request)
    {
        $validationRules = [
            'pack_id'   => 'required|exists:order_package,id',
            'bill_code' => 'required'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $params = $request->all();
        $validate = $this->handleBeforeChangeBillCode($params);
        if ($validate) {
            $result = [
                'error' => $validate
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();
            $result_check = $this->changeBillCodeAction($params);
            if ($result_check) {
                $result = [
                    'error' => $result_check
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function handleBeforeChangeBillCode($params)
    {
        $orderPackModel = \App::make('App\Models\OrderPackage');

        $orderPackDB = $orderPackModel->where('id', $params['pack_id'])->first();
        // Check phải là kiện Order thì mới dc phép xủ lý
        if ($orderPackDB->order_type != 'App\Models\Order') {
            return 'Không phải kiện order, không thể xử lý';
        }

        // Mã mới trùng mã cũ thì cũng cho té
        if ($orderPackDB->bill_code == $params['bill_code']) {
            return 'Mã kiện không có sự thay đổi';
        }

        $orderPackageModel = \App::make('App\Models\OrderPackage');
        // Check không trùng với đơn kí gửi
        $orderPackageDBCount  = $orderPackageModel->where('bill_code', $params['bill_code'])
                                                ->where('order_type', 'App\Models\Consignment')
                                                ->count();
        if ($orderPackageDBCount > 0) {
            return 'Mã vận đơn ' . $params['bill_code'] . ' đã tồn tại !';
        }

        // Nếu đơn đó đã có 1 kiện trong đơn thì cũng báo lỗi luôn
        $orderPackageDBCount  = $orderPackageModel->where('bill_code', $params['bill_code'])
                                                ->where('order_id', $orderPackDB->order_id)
                                                ->where('order_type', 'App\Models\Order')
                                                ->where('status', '!=', PackageConstant::STATUS_CANCEL)
                                                ->count();

        if ($orderPackageDBCount > 0) {
            return 'Mã vận đơn ' . $params['bill_code'] . ' đã tồn tại !!';
        }

        // Check kiện đã đến kho TQ thì không cho đổi nữa
        $statusArr  = explode("_", $orderPackDB->status);
        $indexStatus = (int) $statusArr[1];
        if ($indexStatus >= 5) {
           return 'Mã vận đơn ' . $params['bill_code'] . ' không được phép sửa do hàng đã đến kho TQ';
        }

        return null;
    }

    public function changeBillCodeAction($params)
    {
        $orderPackModel = \App::make('App\Models\OrderPackage');
        $orderPackDB = $orderPackModel->where('id', $params['pack_id'])->first();

        if ($orderPackDB->bill_code == null) {
            // \Log::info('changeBillCodeAction NULL');
            $this->pushToQuote($orderPackDB, $params);
            $orderPackDB->bill_code = $params['bill_code'];
            $orderPackDB->save();
            if($orderPackDB->bill_code && $orderPackDB->order_type == Order::class) {
                $oneWarehouseService = \App::make('App\Services\OneWarehouseService');
                $oneWarehouseService->syncPackWithOrderOneWarehouse($orderPackDB);
            }
            return null;
        }

        if ($orderPackDB->bill_code != null) {
            // \Log::info('changeBillCodeAction NOTNULL');
            $this->pushToQuote($orderPackDB, $params);
            return $this->_service->syncPackChangeBillCode($orderPackDB, $params['bill_code']);
        }
    }

    public function pushToQuote($orderPackDB, $params)
    {
        $data_push = [
            'hangve_pack_id'    => (int) $orderPackDB->id,
            'bill_code_old'     => $orderPackDB->bill_code,
            'bill_code_new'     => $params['bill_code'],
            'hangve_order_id'   => $orderPackDB->order_id,
            'hangve_order_code' => $orderPackDB->order_code
        ];
        $job = new \App\Jobs\Quote\SyncUpdateBillCodePackOrder($data_push);
        dispatch($job)->delay(now()->addSeconds(5))->onQueue('cron-data-2023');
    }

    public function changeType(Request $request)
    {
        $validationRules = [
            'id' => 'required|exists:order_package,id',
            'type' => 'required',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();
        $id = data_get($params, 'id');
        $type = data_get($params, 'type');
        $orderType = null;
        if($type == 'order') {
            $orderType = Order::class;
        }
        if($type == 'consignment') {
            $orderType = Consignment::class;
        }
        $package = OrderPackage::findOrFail($id);
        $is_change_type = $request->is_change_type ?? '';
        if($package->order_type != null && !$is_change_type) {
            $result = [
                'err' => 'Chỉ được sửa kiện chờ xác nhận'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        $package->update(['order_type' => $orderType]);
        return resSuccess();
    }

    public function getOrderDetailsByPackageIds(Request $request)
    {
        $ids = data_get($request->input('order_package_ids'), []);
        try {
            $data = [];
            $orderPackages = OrderPackage::with(['orderDetails'])->findMany($ids);
            foreach($orderPackages as $orderPackage){
                foreach($orderPackage->orderDetailsPackage as $orderDetailPackage){
                    $orderDetail = $orderDetailPackage->orderDetail;
                    array_push($data, [
                        'id' => $orderDetail->id,
                        'order_package_id' => $orderPackage->id,
                        'bill_code' => $orderPackage->bill_code,
                        'image' => $orderDetail->image,
                        'image_v2' => str_contains($orderDetail->image, 'http') ? $orderDetail->image :  MediaHelper::getURL($orderDetail->image),
                        'name' => $orderDetail->name,
                        'unit_price_cny' => $orderDetail->unit_price_cny,
                        'quantity' => $orderDetailPackage->quantity,
                        'amount_cny' => $orderDetail->amount_cny,
                        'exchange_rate' => optional($orderDetail->order)->exchange_rate,
                        'category' => optional($orderDetail->category)->name
                    ]);
                }
            }
            return resSuccessWithinData($data);
        } catch (\Exception $e) {
            return resErrorWithinData([], $e->getMessage());
        }
    }

    public function checkSharedBillCodeMulti(Request $request)
    {
        $validationRules = [
            'orderPackIds'   => 'required|array',
            'orderPackIds.*' => 'required|exists:order_package,id',
        ];
        $validationMessages = [

        ];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();

        $orderPackModel = \App::make('App\Models\OrderPackage');
        $arr_status = [
            PackageConstant::STATUS_RECEIVED_GOODS,
            PackageConstant::STATUS_CANCEL,
        ];

        $orderPackDBTemps = $orderPackModel->whereIn('id', $params['orderPackIds'])
                                            ->whereNotNull('bill_code')
                                            ->get();
        $pack_codes = \Arr::pluck($orderPackDBTemps, 'bill_code');


        $orderPackDBs = $orderPackModel->whereIn('bill_code', $pack_codes)
                                    ->whereNotIn('status', $arr_status)
                                    ->get();
        $arr_bill_code = [];
        if (count($orderPackDBs) > 0) {
            $arr_bill_code = \Arr::pluck($orderPackDBs, 'bill_code');
        }

        $orderPackDBsCheckInfo = $orderPackModel->whereIn('bill_code', $pack_codes)->get();
        $orderPackDBBillCodes = $orderPackDBsCheckInfo->groupBy('bill_code');
        $code_duplicate_no_info = "";
        $code_no_info = "";

        foreach ($orderPackDBBillCodes as $bill_code => $packages) {

            $hasInfo = $packages->contains(function ($item) {

                $hasWeight = !empty($item->weight) && $item->weight > 0;

                $hasVolume = !empty($item->volume_enter) && $item->volume_enter > 0;

                $hasDimension =
                    !empty($item->width) && $item->width > 0
                    && !empty($item->height) && $item->height > 0
                    && !empty($item->length) && $item->length > 0;

                return $hasWeight || $hasVolume || $hasDimension;
            });

            // Mã trùng nhưng chưa có kg/kích thước
            if ($packages->count() > 1 && !$hasInfo) {
                $code_duplicate_no_info .= $bill_code . ",";
            }

            // Mã không trùng và chưa có kg/kích thước
            if ($packages->count() == 1 && !$hasInfo) {
                $code_no_info .= $bill_code . ",";
            }
        }

        $code_duplicate_no_info = rtrim($code_duplicate_no_info, ",");
        $code_no_info = rtrim($code_no_info, ",");
        if ($code_duplicate_no_info != "") {
            return resSuccessWithinData([
                'status' => 'code_duplicate_no_info',
                'message' => "Các kiện {$code_duplicate_no_info} đang thuộc nhiều đơn khác nhau và không có phí vận chuyển. Vui lòng kiểm tra kĩ trước khi thực hiện thao tác"
            ]);
        }

        if ($code_no_info != "") {
            return resSuccessWithinData([
                'status' => 'code_no_info',
                'message' => "Các kiện {$code_no_info} không thể trả hàng/tạo giao hàng do không có phí vận chuyển."
            ]);
        }



        $code_duplicate = "";
        $counts = array_count_values($arr_bill_code);
        foreach ($counts as $bill_code => $count) {
            if ($count > 1) {
                $code_duplicate .= $bill_code . ",";
            }
        }

        if ($code_duplicate == "") {
            return resSuccessWithinData([
                'status'  => true,
                "message" => null
            ]);
        }


        $code_duplicate = rtrim($code_duplicate, ",");
        return resSuccessWithinData([
            'status'  => false,
            'message' => "Các kiện " . $code_duplicate . " đang thuộc nhiều đơn khác nhau. Vui lòng kiểm tra kĩ trước khi thực hiện thao tác"
        ]);

    }

    public function reportControl(Request $request)
    {
        $params = $request->only('warehouse', 'time');
        $data = $this->_service->reportControl($params);
        return resSuccessWithinData($data);
    }

    public function reportControlTable(Request $request)
    {
        $params = $request->all();
        $data = $this->_service->reportControlTable($params);
        return $data;
    }

    public function commonReportControl()
    {
        $model = \App::make('App\Models\Transporter');
        $transporters = $model->where('country', 'vi')->select('id', 'name')->get()->toArray();
        array_unshift($transporters, ["id" => 0, "name" => "Nhận tại kho"]);
        $data = [
            "transporters" => $transporters,
            "statuses" => PackageConstant::STATUSES,
            "type" => ["Đến kho nhận hàng", "Giao hàng"]
        ];
        return resSuccessWithinData($data);
    }

    public function updatePackLostAndReturnCustomer(Request $request)
    {
        $job = new \App\Jobs\OneWarehouse\SyncStatusPackLost();
        dispatch($job)->delay(now()->addSeconds(10))->onQueue('cron-data-2023');
    }

    public function confirmInfoDD(Request $request)
    {
        $validationRules = [
            'pack_id' => 'required|exists:order_package,id',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();


        try {
            \DB::beginTransaction();
            $packId = data_get($params, 'pack_id');
            $orderPackage = OrderPackage::find($packId);
            if($orderPackage->is_confirm != 1) {
                $result = [
                    'err' => 'Kiện hàng không ở trạng thái chờ xác nhận thông tin'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            $orderPackage->update(['is_confirm' => 0]);
            $job = new \App\Jobs\OneWarehouse\SyncConfirmPackJob($orderPackage);
            dispatch($job)->onQueue('cron-data-2023');
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            return resError();
        }

    }

    public function updateOfficialPartner(Request $request)
    {
        $validationRules = [
            'id' => 'required|exists:order_package,id',
            'official_is_partner' => 'required|in:0,1'
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        DB::beginTransaction();
        try {
            $params = $request->all();
            $id = data_get($params, 'id');
            $officialIsPartner = data_get($params, 'official_is_partner');
            $orderPackage = OrderPackage::findOrFail($id);
            if($orderPackage->status == PackageConstant::STATUS_RECEIVED_GOODS) {
                $result = ['error' => 'Kiện đã nhận hàng không được phép sửa!'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            if($orderPackage->official == 1) {
                $result = ['error' => 'Kiện là bán chính ngạch không thể chọn chính ngạch - Đi bên đối tác'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            $orderPackage->update(['official_is_partner' => $officialIsPartner]);
            DB::commit();
            return resSuccess();
        } catch (\Exception $exception) {
            DB::rollBack();
            return resError();
        }
    }

    public function updateSyncNote(Request $request)
    {
        $validationRules = [
            'id'        => 'required|exists:order_package,id',
            'sync_note' => 'required|in:0,1'
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);

        try {
            DB::beginTransaction();
            $params = $request->all();
            $id       = data_get($params, 'id');
            $syncNote = data_get($params, 'sync_note');

            // Cập nhật trên hệ thống HV
            $orderPackage = OrderPackage::findOrFail($id);
            $orderPackage->update(['sync_note' => $syncNote]);

            // Cập nhật trên hệ thống DD
            $data_sync = [
                'id'        => $orderPackage->id,
                'note'      => $syncNote == 0 ? '' : $orderPackage->note_internal,
                'user_name' => getCurrentUser()->name
            ];
            $payload = [
                'event_name' => 'update_note',
                'data' => $data_sync
            ];
            $this->syncPackageToDongDong($payload);

            // Cập nhật trên hệ thống CN
            $data_sync = [
                'origin_code' => $orderPackage->bill_code,
                'note'        => $syncNote == 0 ? '' : $orderPackage->note_internal,
            ];
            $payload = [
                'event_name' => 'hangve_update_note',
                'data' => $data_sync
            ];
            $this->syncPackageToCN($payload);

            DB::commit();
            return resSuccess();
        } catch (\Exception $exception) {
            DB::rollBack();
            return resError();
        }
    }

    public function updateSyncBlockPackage(Request $request)
    {
        try {
            DB::beginTransaction();
            $params = $request->all();
            $ids = $params['ids'];
            $isBlockPackage = $params['is_block_package'];
            $count = 0;
            $isOfficialSale = false;
            foreach ($ids as $id) {
                // Cập nhật trên hệ thống HV
                $orderPackage = OrderPackage::findOrFail($id);
                if($orderPackage->official == OrderConstant::OFFICIAL_SALE) {
                    $isOfficialSale = true;
                    continue;
                }
                if($orderPackage->order_type != Consignment::class) {
                    continue;
                }
                if(!in_array($orderPackage->status,
                    [PackageConstant::STATUS_PENDING, PackageConstant::STATUS_ON_THE_WAY_CHINA, PackageConstant::STATUS_IN_CHINA])
                ) {
                    continue;
                }
                if($orderPackage->is_block_package == $isBlockPackage) {
                    continue;
                }
                $orderPackage->is_block_package = $isBlockPackage;
                $orderPackage->save();
//                $orderPackage->update(['is_block_package' => $isBlockPackage]);
                // Cập nhật trên hệ thống CN
                $data_sync = [
                    'origin_code' => $orderPackage->bill_code,
                    'is_block_package' => $isBlockPackage,
                ];
                $payload = [
                    'event_name' => 'hangve_block_package',
                    'data' => $data_sync
                ];
                $count++;
                $this->syncPackageToCN($payload);
            }

            DB::commit();

            if($count == 0 && $isOfficialSale) {
                return resError('Không thể chặn xuất kiện hàng đi Trọn gói');
            }
            if($count == 0) {
                return resError('Chặn không thành công');
            }
            $msg = "Chặn thành công $count kiện";
            if($isBlockPackage == 0) {
                $msg = "Bỏ chặn thành công $count kiện";
            }
            return resSuccessWithinData($count, $msg);
        } catch (\Exception $exception) {
            DB::rollBack();
            return resError();
        }
    }

    private function syncPackageToCN($payload){
        $syncPackJob = new \App\Jobs\CN\SyncPackJobCN($payload);
        dispatch($syncPackJob)->onQueue('export-1800');
    }

    public function whInspection(Request $request)
    {
        $auth = getCurrentUser();
        $staffOrderId = $auth->checkUserHasPer('pack.show-edit-all-inspection') ? null : $auth->id;

        // Check quyền xem đơn hoàn thành
        $canViewCompleted = $auth->checkUserHasPer('pack.show-refuns-order-completed');

        $guzzle = new Guzzle;
        $url = env('URL_DONGDONG') . 'pack/pack-inspection';
        $params = [
            'query' => [
                'staff_order_id' => $staffOrderId,
                'bill_code' => request()->input('bill_code'),
                'order_code' => request()->input('order_code'),
                'status' => request()->input('status'),
                'time_import' => request()->input('time_import'),
                'page' => request()->input('page'),
                'per_page' => request()->input('per_page'),
                'staff_group_id' => request()->input('staff_group_id'),
                'staff_order_id_search' => request()->input('staff_order_id_search'),

                'can_view_completed'   => $canViewCompleted ? 1 : 0,
            ]
        ];
        $result = $guzzle->request('GET', $url, $params)->getBody()->getContents();
        $result = json_decode($result, true);
        return $result;
    }

    public function whCountInspection(Request $request)
    {
        $auth = getCurrentUser();
        $staffOrderId = $auth->checkUserHasPer('pack.show-edit-all-inspection') ? null : $auth->id;
        $guzzle = new Guzzle;
        $url = env('URL_DONGDONG') . 'pack/get-status-pack-wait-and-refund';
        $params = [
            'query' => [
                'staff_order_id' => $staffOrderId,
            ]
        ];
        $result = $guzzle->request('GET', $url, $params)->getBody()->getContents();
        $result = json_decode($result, true);
        return $result;
    }

    public function whWaitingConfirm(Request $request)
    {
        $auth = getCurrentUser();
        $staffOrderId = $auth->checkUserHasPer('pack.all-pack.wait-confirm') ? null : $auth->id;
        $guzzle = new Guzzle;
        $url = env('URL_DONGDONG') . 'pack/pack-wait-wh';
        $staffOrderIdInput = request()->input('staff_order_id');
        if($auth->checkUserHasPer('pack.all-pack.wait-confirm') == true) {
            if($staffOrderIdInput) {
                $staffOrderId = $staffOrderIdInput;
            }else {
                $staffOrderId = null;
            }
        }else {
            if($staffOrderIdInput) {
                $staffOrderId = $staffOrderIdInput == $auth->id ? $auth->id : 999999;
            }else {
                $staffOrderId = $auth->id;
            }
        }
        $params = [
            'query' => [
                'staff_order_id' => $staffOrderId,
                'pack_code' => request()->input('pack_code'),
                'status_process' => request()->input('status_process'),
                'time_import' => request()->input('time_import'),
                'page' => request()->input('page'),
                'per_page' => request()->input('per_page')
            ]
        ];
        $result = $guzzle->request('GET', $url, $params)->getBody()->getContents();
        $result = json_decode($result, true);
        return $result;
    }

    public function whInspectionListStatus(Request $request)
    {
        $auth = getCurrentUser();
        $staffOrderId = $auth->checkUserHasPer('pack.show-edit-all-inspection') ? null : $auth->id;
        // Check quyền xem đơn hoàn thành
        $canViewCompleted = $auth->checkUserHasPer('pack.show-refuns-order-completed');
        $guzzle = new Guzzle;
        $url = env('URL_DONGDONG') . 'pack/pack-inspection/list-status';
        $params = [
            'query' => [
                'staff_order_id' => $staffOrderId,
                'bill_code' => request()->input('bill_code'),
                'order_code' => request()->input('order_code'),
                'status' => request()->input('status'),
                'time_import' => request()->input('time_import'),
                'page' => request()->input('page'),
                'per_page' => request()->input('per_page'),
                'staff_group_id' => request()->input('staff_group_id'),
                'staff_order_id_search' => request()->input('staff_order_id_search'),

                'can_view_completed'   => $canViewCompleted ? 1 : 0,
            ]
        ];
        $result = $guzzle->request('GET', $url, $params)->getBody()->getContents();
        $result = json_decode($result, true);
        return $result;
    }

    public function listConfirmQuote(Request $request)
    {
        $guzzle = new Guzzle;
        $url = env('URL_QUOTE') . 'quote/hangve/confirm-quote';
        $params = [
            'query' => [
                'bill_code' => request()->input('bill_code'),
                'customer_phone' => request()->input('customer_phone'),
                'quote_code' => request()->input('quote_code'),
                'page' => request()->input('page'),
                'per_page' => request()->input('per_page')
            ]
        ];
        $result = $guzzle->request('GET', $url, $params)->getBody()->getContents();
        $result = json_decode($result, true);
        return $result;
    }

    public function approveQuote(Request $request)
    {
        $validationRules = [
            'confirm_quote_id' => 'required',
            'fee_percent' => 'required|numeric|min:0',
            'unit_price_kg' => 'required|numeric|min:0',
            'unit_price_m3' => 'required|numeric|min:0'
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = request()->only('confirm_quote_id', 'fee_percent', 'unit_price_kg', 'unit_price_m3');
        try {
            $currenUser = getCurrentUser();
            $data = array_merge($params, [
                'approve_user_id' => $currenUser->id,
                'approve_user_name' => $currenUser->name
            ]);
            $guzzle = new Guzzle;
            $url = env('URL_QUOTE') . 'quote/hangve/approve-quote';
            $options = [
                'json' => $data,
                'verify' => false,
                'http_errors' => true,
            ];
            $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
            $result = json_decode($result, true);
            return resSuccess();
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function approveMultiQuote(Request $request)
    {
        $validationRules = [
            'confirm_quote_ids' => 'required'
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = request()->only('confirm_quote_ids');
        try {
            $currenUser = getCurrentUser();
            $data = array_merge($params, [
                'approve_user_id' => $currenUser->id,
                'approve_user_name' => $currenUser->name
            ]);
            $guzzle = new Guzzle;
            $url = env('URL_QUOTE') . 'quote/hangve/approve-multi-quote';
            $options = [
                'json' => $data,
                'verify' => false,
                'http_errors' => true,
            ];
            $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
            $result = json_decode($result, true);
            return resSuccess();
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function cancelQuote(Request $request)
    {
        $validationRules = [
            'confirm_quote_ids' => 'required|array',
            'confirm_quote_ids.*' => 'required',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $data = request()->only('confirm_quote_ids');
        try {
            $guzzle = new Guzzle;
            $url = env('URL_QUOTE') . 'quote/hangve/cancel-quote';
            $options = [
                'json' => $data,
                'verify' => false,
                'http_errors' => true,
            ];
            $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
            $result = json_decode($result, true);
            return resSuccess();
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function syncFeeFromQuote()
    {
        $this->$this->_service->syncFeeFromQuote();
        return resSuccess();
    }

    public function updateService(Request $request)
    {
        $orderPackage = OrderPackage::where('id', $request->id)
            ->where('order_type', Consignment::class)
            ->first();
        $validationRules = [
            'id' => 'required|exists:order_package,id',
            'service' => 'required|in:insurance,woodworking,shock_proof',
        ];
        if (
            $request->service === 'insurance' &&
            $orderPackage &&
            $orderPackage->is_insurance == 0
        ) {
            $validationRules['order_cost'] = 'required|integer|min:1';
        }
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        try {
            \DB::beginTransaction();
            $params = $request->all();
            $id = $params['id'];
            $service = $params['service'];
            $orderCost = $params['order_cost'] ?? null;
            $orderPackage = OrderPackage::where('id', $id)->where('order_type', Consignment::class)->first();
            if($orderPackage == null) {
                $result = [
                    'error' => 'Chỉ được phép cập nhật dịch vụ cho kiện kí gửi!'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            $auth = getCurrentUser();
            $statuses = [];
            if($auth->hasPermissionName('pack.edit-service-consignment-not-in-stock') == true) {
                $statuses = array_merge($statuses, [PackageConstant::STATUS_PENDING, PackageConstant::STATUS_RECONFIRM, PackageConstant::STATUS_ON_THE_WAY_CHINA]);
            }
            if($auth->hasPermissionName('pack.edit-service-consignment-at-qc') == true) {
                $statuses = array_merge($statuses, [PackageConstant::STATUS_IN_GUANGZHOU]);
            }
            if(!in_array($orderPackage->status, $statuses)) {
                $result = [
                    'error' => 'Bạn không có quyền thực hiện thao tác này!'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            $this->_service->updateService($id, $service, $orderCost);

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function requestChangePrice(Request $request)
    {
        $validationRules = [
            'list_pack' => 'required|array',
            'list_pack.*.id' => 'required|exists:order_package,id',
            'list_pack.*.unit_price' => 'required|numeric|min:1',
            'request_reason' => 'required|string|max:255'
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = request()->only('list_pack', 'request_reason');
        try {
            DB::beginTransaction();
            $listPack = $params['list_pack'];
            $packIds = array_column($listPack, 'id');
            $package = OrderPackage::whereIn('id', $packIds)->where('international_shipping_cost', 0)->first();
            if($package) {
                $result = [
                    'error' => "Không thể yêu cầu đổi đơn giá do mã vận đơn {$package->bill_code} chưa có thông tin trọng lượng"
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            $listStatusValid = [
                PackageConstant::STATUS_PENDING, PackageConstant::STATUS_RECONFIRM, PackageConstant::STATUS_ON_THE_WAY_PINGXIANG, PackageConstant::STATUS_ON_THE_WAY_CHINA,
                PackageConstant::STATUS_IN_PROGRESS, PackageConstant::STATUS_RECEIVED_GOODS, PackageConstant::STATUS_CANCEL, PackageConstant::STATUS_PENDING_LIQUIDATION
            ];
            $packageStatus = OrderPackage::whereIn('id', $packIds)->whereIn('status', $listStatusValid)->first();
            if($packageStatus) {
                $result = [
                    'error' => "Không thể yêu cầu đổi đơn giá do trạng thái của mã vận đơn {$packageStatus->bill_code} không hợp lệ"
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            $checkApproveExist = ApproveUnitPrice::whereIn('order_package_id', $packIds)->where('status', ApproveUnitPriceConstant::APPROVED)->first();
            if($checkApproveExist) {
                $orderPackage = $checkApproveExist->orderPackage;
                if($orderPackage) {
                    $result = [
                        'error' => "Không thể yêu cầu đổi đơn giá do mã vận đơn {$orderPackage->bill_code} có yêu cầu đã duyệt"
                    ];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
            }
            $this->_service->requestChangePrice($params);
            DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \Log::info($e);
            DB::rollback();
            return resError();
        }
    }
}
