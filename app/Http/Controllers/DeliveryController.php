<?php

namespace App\Http\Controllers;

use App\Constants\DeliveryConstant;
use App\Constants\FilePrintConstant;
use App\Exports\DeliveryPrintWarehouseExport;
use App\Helpers\ConvertHelper;
use App\Http\Requests\Delivery\ModifiesRequest;
use App\Http\Requests\Delivery\NoteWarehouseRequest;
use App\Http\Resources\Delivery\DeliveryResource;
use App\Http\Resources\Delivery\ListDetailResource;
use App\Http\Resources\Delivery\PrintDeliveryResource;
use App\Http\Resources\Delivery\PrintExWarehouseResource;
use App\Interfaces\Validation\StoreValidationInterface;
use App\Jobs\SentSmsToCustomerJob;
use App\Models\Delivery;
use App\Models\CustomerDelivery;
use App\Models\Order;
use App\Models\SmsLog;
use App\Rules\PackagesWithSameCustomer;
use App\Services\DeliveryService;
use App\Services\FcmService;
use App\Services\SmsZaLoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

use App\Constants\PackageConstant;

use App\Exports\DeliveryCod\DeliveryCodTemplate;

use App\Imports\DeliveryCodImport;
use Illuminate\Http\Request;

use App\Constants\OrderConstant;
use App\Constants\ConsignmentConstant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

use App\Services\TransactionService;
use App\Models\Fund;

class DeliveryController extends Controller implements StoreValidationInterface
{

    public function __construct(DeliveryService $service)
    {
        $this->_service = $service;
    }

    public function storeMessage(): ?array
    {
        return [];
    }

    public function storeRequest(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'transporter_id' => 'required|exists:transporters,id',
            'payment' => 'required|in:' . implode(',', array_keys(DeliveryConstant::PAYMENTS)),
            'date' => 'nullable|date:Y-m-d|after_or_equal:' . date('Y-m-d'),
            'customer_delivery_id' => 'required|exists:customer_deliveries,id',
            'note' => 'string|max:500',
            'packages' => ['required', 'array', new PackagesWithSameCustomer()],
            'packages.*' => 'required|exists:order_package,id',
            'force_create_delivery' => 'nullable|boolean'
        ];
    }

    protected function getAttributes(): array
    {
        return [
            'customer_id' => 'Khách hàng',
            'transporter_id' => 'Hình thức vận chuyển',
            'payment' => 'Phương thức thanh toán',
            'date' => 'Ngày giao',
            'customer_delivery_id' => 'Địa chỉ giao hàng',
            'note' => 'Ghi chú',
            'packages' => 'Kiện hàng',
            'packages.*' => 'Kiện hàng',
            'force_create_delivery' => 'Đồng ý thanh toán trước',
        ];
    }

    public function store(): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__);

        // Xử lý
        $orderPackModel = \App::make('App\Models\OrderPackage');
        $params = request()->all();
        $packages = $params['packages'];

        if (
            isset($params['transporter_id']) &&
            $params['transporter_id'] == 2 &&
            $params['payment'] === 'cod'
        ) {
            $result = ['Không áp dụng thu COD cho đơn giao Viettel. Vui lòng chọn lại !'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        if (
            isset($params['transporter_id']) &&
            $params['transporter_id'] == 4 &&
            $params['payment'] === 'cod'
        ) {
            $result = ['Không áp dụng thu COD cho đơn giao hàng tiết kiệm. Vui lòng chọn lại !'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        // Check kiện hủy hoặc có giao hàng thì không được tạo giao hàng
        foreach ($packages as $key => $item) {
            $packDB = $orderPackModel->where('id', $item)->first();

            // Check điều kiện
            if ($packDB->status != PackageConstant::STATUS_WAREHOUSE_VN) {
                $result = ['err' => 'Kiện ' . $packDB->code . ' đang không ở trạng thái đến kho VN, không thể tạo giao hàng'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }

            if ($packDB->delivery_id) {
                $result = ['err' => 'Kiện ' . $packDB->code . ' đã có giao hàng'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        // Check giới hạn cho phép trừ âm ví khi phiếu giao thanh toán bằng Ví điện tử
        if ($params['payment'] === DeliveryConstant::PAYMENT_E_WALLET) {
            $checkEcode = $this->_service->checkEcode($params['packages'], $params['customer_delivery_id']);
            if ($checkEcode) {
                // return res422('Không thể tạo giao hàng do ví khách không được âm quá 20,000đ. Vui lòng nạp thêm tiền để tiếp tục thanh toán!');
            }
        }

        // Check thêm địa chỉ phải đúng của khách hàng
        $customerDeliveryDB = CustomerDelivery::query()->where('id', $params['customer_delivery_id'])->first();
        if ($customerDeliveryDB->customer_id != $params['customer_id']) {
            $result = ['err' => 'Địa chỉ khách hàng không đúng, vui lòng kiểm tra lại'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Xem có force_create_delivery: đồng ý trả tiền luôn khi không có hàng về đủ chưa

        $force_create_delivery = isset($params['force_create_delivery']) ? $params['force_create_delivery'] : false;

        // Nếu không đồng ý thì validate
        if ($force_create_delivery == false) {
            // Check logic theo 2 Flag
            $packsDB = $orderPackModel->whereIn('id', $packages)->get();
            $collection = collect($packsDB);
            $grouped = $collection->groupBy('order_id');
            $grouped->all();

            $flag1 = false; // Toàn bộ kiện đã về VN
            $flag2 = false; // Phải chọn toàn bộ kiện đã về VN

            // Check các kiện còn lại cũng phải ở trạng thái:
            // - Khác trạng thái hủy
            // - Không tính kiện đã giao hàng

            foreach ($grouped as $order_id => $items) {
                $packAllDB = $orderPackModel->where('order_id', $order_id)
                                            ->where('status', '<>', PackageConstant::STATUS_CANCEL)
                                            ->whereNull('delivery_id')
                                            ->get();
                $orderCodeErr = $items[0]->order_code;
                foreach ($packAllDB as $key_1 => $item_done) {
                    if ($item_done->status != PackageConstant::STATUS_WAREHOUSE_VN) {
                        $flag1 = true;
                        break;
                    }
                }
                // Thêm điều kiện $order_id != "" vì nếu là kiện kí gửi sẽ k thuộc đơn hàng nào nên bỏ qua check này
                if (count($items) != count($packAllDB) && $order_id != "") {
                    $flag2 = true;
                    break;
                }
            }

            if ($flag1) {
                $result = ['err' => 'Các kiện của đơn ' . $orderCodeErr . ' chưa về hết, vui lòng thanh toán tiền hàng còn thiếu để tạo giao hàng'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            // Bỏ logic tích hết kiện đã về VN
            // if ($flag2) {
            //     $result = ['err' => 'Các kiện của đơn ' . $orderCodeErr . ' cần phải được tích chọn hết để giao hàng'];
            //     return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            // }
        }

        // Nếu đồng ý thì check nó phải chọn hết các kiện đã về VN
        // Bò logic này hôm 22-10-2024
        // if ($force_create_delivery) {
        //     $packsDB = $orderPackModel->whereIn('id', $packages)->get();
        //     $collection = collect($packsDB);
        //     $grouped = $collection->groupBy('order_id');
        //     $grouped->all();

        //     $flag = false; // Phải chọn toàn bộ kiện đã về VN

        //     foreach ($grouped as $order_id => $items) {
        //         $packAllDB = $orderPackModel->where('order_id', $order_id)
        //                                     ->where('status', PackageConstant::STATUS_WAREHOUSE_VN)
        //                                     ->whereNull('delivery_id')
        //                                     ->get();
        //         $orderCodeErr = $items[0]->order_code;
        //         if (count($items) != count($packAllDB)) {
        //             $flag = true;
        //             break;
        //         }
        //     }
        //     if ($flag) {
        //         $result = ['err' => 'Các kiện của đơn ' . $orderCodeErr . ' mà đã về VN cần phải được tích chọn hết để giao hàng'];
        //         return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        //     }
        // }


        // Thực hiện
        try {
            DB::beginTransaction();
            $result = $this->_service->store($params);
            // Đánh dấu số tiền hàng còn thiếu và phí ship đã thu vào luôn đơn
            // $this->updateDebtCostAndShippingCostToOrder($result, true);
            /*$orderIds = $orderPackModel->select('id', 'order_id')->whereIn('id', $packages)->pluck('order_id')->toArray();
            $orders = Order::query()
                ->select('id', 'code', 'customer_id')
                ->whereIn('id', $orderIds)
                ->with('customer')
                ->get();*/
            $phone_number = $result->customer->phone_number ?? 0;
            $phone_number = convertPhoneNumber($phone_number);
            $user_name = $result->customer->name ?? '';
            $user_code = $result->customer->code ?? '';
            $order_code = $result->code ?? '';
            $params = [
                "phone_number" => $phone_number,
                "template_id" => SmsLog::ZALO_TEMPLATE_ID_THONG_BAO_GIAO_HANG,
                "message" => "Xin chào $user_name, đơn giao hàng có mã $order_code đã được tạo thành công",
                "template_data" => [
                    "name" => $user_name,
                    "order_code" => $order_code,
                ]
            ];
            // Log::info('Giao hang ' . json_encode($params));
            $response = (new SmsZaLoService($params))
                ->getAccessToken()
                ->validate()
                ->sent()
                ->getResult();
            if ($response['code'] == 500) {
                // Log::info('SMS ZaLo Error Tạo Giao Hàng ' . $response['message'] . $phone_number);
            }
            else
            {
                // Log::info('SMS ZaLo Success Tạo Giao Hàng!' . $phone_number);
                SmsLog::query()->create([
                    'phone_number' => $phone_number,
                    'content' => $params['message'],
                    'user_code' => $user_code,
                    'sms_source' => SmsLog::SMS_SOURCE_ZALO,
                    'log_type' => SmsLog::LOG_TYPE_SMS_THONG_BAO_GIAO_HANG,
                    'response_status' => 0
                ]);
            }
            DB::commit();
            return resSuccessWithinData($result);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function updateMessage(): array
    {
        return [];
    }

    /**
     * @param ModifiesRequest $request
     * @param Delivery $delivery
     * @return JsonResponse
     */
    public function modifies(ModifiesRequest $request, Delivery $delivery): JsonResponse
    {
        try {
            $params = $request->all();

            list($canRemove, $msg) = $this->checkCanRemovePack($delivery, $params);
            if (!$canRemove) {
                return resErrorWithinData($msg, 'Có lỗi xảy ra', 422);
            }

            if ($delivery->status == DeliveryConstant::KEY_STATUS_DONE) {
                $result_err = ['error' => 'Giao hàng đã hoàn thành, không thể thao tác'];
                return resErrorWithinData($result_err, 'Có lỗi xảy ra', 422);
            }

            // Check chuyển trạng thái phải lần lượt theo thứ tự
            $check_status = $this->checkChangeStatusDelivery($delivery, $params);
            if ($check_status) {
                $result_err = ['error' => $check_status];
                return resErrorWithinData($result_err, 'Có lỗi xảy ra', 422);
            }


            // Nếu update sang hoàn thành thì check xem có kiện dc trả hàng k, để cảnh bao
            // Logic ngày 26-11-2024: Đổi check từ hoàn thành sang đang giao hàng là check luôn rồi
            if (data_get($params, 'status') == DeliveryConstant::KEY_STATUS_RUN) {
                $packagesDBs = $delivery->packages;
                $flagDone = false; // Không có kiện nào dc trả
                $flagCode = ""; //
                foreach ($packagesDBs as $key => $pack) {
                    if ($pack->status == PackageConstant::STATUS_RECEIVED_GOODS) {
                        $flagDone = true;
                        $flagCode = $pack->bill_code;
                        break;
                    }
                }
                if ($flagDone) {
                    $result_err = ['error' => 'Kiện hàng ' . $flagCode . ' đã chuyển trạng thái Đã nhận hàng, vui lòng check lại!'];
                    return resErrorWithinData($result_err, 'Có lỗi xảy ra', 422);
                }
            }

            // Check số dư phát
            if ($delivery->payment === DeliveryConstant::PAYMENT_E_WALLET) {
                $canSubMoneyNow = $this->checkCanSubMoneyNow($delivery, $params);
                if ((data_get($params, 'status') == DeliveryConstant::KEY_STATUS_RUN || $canSubMoneyNow)) {
                    $checkFullEcode = $this->_service->checkFullEcode($delivery);
                    if ($checkFullEcode['err']) {
                        $moneyErr = number_format($checkFullEcode['moneyCheck'],0,".",".");
                        return res422('Không thể tạo giao hàng do ví khách không được âm quá ' .$moneyErr.'đ. Vui lòng nạp thêm tiền để tiếp tục thanh toán!');
                    }
                }
            }

            // Check gửi quá nhanh
            if ($delivery->time_next_action != null) {
                $time_next_db = strtotime($delivery->time_next_action);
                $time_now = time();
                if ($time_next_db > $time_now) {
                    $result_err = ['error' => 'Hệ thống đang xử lý, vui lòng thao tác lại sau!'];
                    return resErrorWithinData($result_err, 'Có lỗi xảy ra', 422);
                }
            }
            $time_next = time() + 30;
            $time_next_action = date('Y-m-d H:i:s', $time_next);
            $delivery->time_next_action = $time_next_action;
            $delivery->save();

            return DB::transaction(
                function () use ($delivery, $request, $params) {
                    $deliveryCostBegin = $delivery->delivery_cost;
                    $isDeliveryCostPaidBegin = $delivery->is_delivery_cost_paid;
                    $params['is_delivery_cost_paid'] = is_numeric(
                        $params['is_delivery_cost_paid']
                    ) ? $params['is_delivery_cost_paid'] : null;

                    // Logic ngày 24/09/2024:
                    $params['is_delivery_cost_paid'] = null; // Không được phép thanh toán tiền nữa

                    // Logic ngày 28-11-2024:
                    $canSubMoneyNow = $this->checkCanSubMoneyNow($delivery, $params);
                    if (in_array(data_get($params, 'status'), [DeliveryConstant::KEY_STATUS_RUN, DeliveryConstant::KEY_STATUS_DONE]) && $delivery->customer_balance_old == 0) {
                        $customerService  = \App::make('App\Services\CustomerService');
                        $params['customer_balance_old'] = $customerService->getBalanceAmount($delivery->customer_id);
                    }

                    $delivery->update($params);

                    // Nếu hoành thành giao hàng thì thực hiện trừ tiền
                    // Logic ngày 26-11-2024: Đổi check từ hoàn thành sang đang giao hàng là trừ tiền luôn (không đổi trạng thái kiện)
                    // Logic ngày 28-11-2024: Nếu đổi từ Chờ xử lý, đang xử lý sang hoàn thành luôn thì cũng trừ luôn
                    // Logic ngày 27-01-2026: Ngắt hàm này vì nó đã trừ ở bước tạo đơn rồi
                    $dateCheck = $this->_service->dateCheck($delivery);
                    if ((data_get($params, 'status') == DeliveryConstant::KEY_STATUS_RUN || $canSubMoneyNow) && $dateCheck == false) {
                        $packagesDBs = $delivery->packages;
                        $packages = \Arr::pluck($packagesDBs, 'id');
                        (new TransactionService())->purchaseDelivery($delivery, $packages);
                    }

                    // Hủy giao hàng
                    if (data_get($params, 'status') == DeliveryConstant::KEY_STATUS_CANCEL) {
                        // Logic ngày 27/01/2026 cho hoàn lại tiền
                        $this->_service->backMoneyWhenDestroyOrCancelDelivery($delivery, 'cancel');

                        $delivery->packages()->update([
                            'delivery_id' => null,
                            'is_delivery' => 0
                        ]);
                    }

                    if ($request->has('note') && ($note = $request->input('note'))) {
                        $this->_service->storeNote($delivery->id, $note);
                    }
                    if (data_get($params, 'is_delivery_cost_paid') == 1 && $deliveryCostBegin > 0) {
                        $diffCost = (int)data_get($params, 'delivery_cost') - (int)$deliveryCostBegin;
                        if ($diffCost > 0) {
                            // Thanh toán khi thay doi delivery_cost
                            $this->_service->deductionDeliveryCost($delivery, $diffCost);
                        }
                        if ($diffCost < 0) {
                            // Hoan tien khi thay doi delivery_cost
                            $this->_service->refundDeliveryCost($delivery, abs($diffCost));
                        }
                    };
                    if (data_get($params, 'is_delivery_cost_paid') != $isDeliveryCostPaidBegin) {
                        if ($delivery->is_delivery_cost_paid == 1) {
                            // Giao dich thanh toán
                            $this->_service->deductionDeliveryCost($delivery, data_get($delivery, 'delivery_cost'));
                        } else {
                            // Giao dich hoan tien
                            $this->_service->refundDeliveryCost($delivery, data_get($delivery, 'delivery_cost'));
                        }
                    }
                    $this->refund($delivery);

                    // Thêm noti
                    $this->updateDeliveryNotiStatus($delivery, $params);

                    // Cập nhật trạng thái đang giao hàng nếu có
                    $this->changeStatusToRun($delivery);

                    // Cập nhật trạng thái kiện đã nhận hàng nếu giao hàng hoàn thành
                    $this->changeStatusPackageOfDeliveryDone($delivery);

                    // Cập nhật số tiền hàng còn thiếu + phí ship đã nhận dc nếu đơn hoàn thành
                    $this->updateDebtCostAndShippingCostToOrder($delivery);

                    // Cập nhật các kiện bị thất lạc nếu có
                    $job = new \App\Jobs\OneWarehouse\SyncStatusPackLost();
                    dispatch($job)->delay(now()->addSeconds(10))->onQueue('cron-data-2023');

                    return resSuccessWithinData(new DeliveryResource($delivery));
                }
            );
        } catch (\Exception $exception) {
            Log::info(
                'Lỗi chuyển trạng thái kiện hàng '
                . $exception->getMessage() . 'dòng ' . $exception->getLine() . $exception->getFile()
            );
            return resError();
        }
    }

    public function checkCanSubMoneyNow($delivery, $params)
    {
        // Nếu ở 2 trạng thái chờ xử lý và đang xử và trạng thái chọn tiếp theo là hoàn thành
        $arr_status = [
            DeliveryConstant::KEY_STATUS_PENDING,
            DeliveryConstant::KEY_STATUS_PROCESS
        ];
        if (in_array($delivery->status, $arr_status) && data_get($params, 'status') == DeliveryConstant::KEY_STATUS_DONE) {
            return true;
        }
         return false;
    }

    public function changeStatusToRun($delivery)
    {
        if ($delivery->status == DeliveryConstant::KEY_STATUS_RUN) {
            $packOfDelivery = $delivery->packages;
            if (count($packOfDelivery) > 0) {
                // Đang giao hàng các kiện
                foreach ($packOfDelivery as $key => $pack) {
                    if ($pack->status != PackageConstant::STATUS_IN_PROGRESS) {
                        $pack->status = PackageConstant::STATUS_IN_PROGRESS;
                        $pack->save();
                    }
                }
            }
        }
    }

    public function checkChangeStatusDelivery($delivery, $params)
    {
        // Nếu trạng thái nó không đổi thì không cần check
        if ($delivery->status == $params['status']) {
            return null;
        }
        // Muốn đổi về chờ xử lý chỉ khi nó ở đang xử lý
        if ($params['status'] == DeliveryConstant::KEY_STATUS_PENDING) {
            if ($delivery->status != DeliveryConstant::KEY_STATUS_PROCESS) {
                return "Trạng thái không phải là đang xử lý, không thể sang chờ xử lý";
            }
        }
        // Muốn đổi sang đang xử lý thì chỉ có trạng thái Chờ xử lý mới dc đổi
        if ($params['status'] == DeliveryConstant::KEY_STATUS_PROCESS) {
            if ($delivery->status != DeliveryConstant::KEY_STATUS_PENDING) {
                return "Trạng thái không phải là chờ xử lý, không thể sang đang xử lý";
            }
        }
        // Muốn đổi sang đang giao hàng chỉ khi chờ xử lý hoặc đang xử lý
        if ($params['status'] == DeliveryConstant::KEY_STATUS_RUN) {
            $arr_status_can_run = [
                DeliveryConstant::KEY_STATUS_PENDING,
                DeliveryConstant::KEY_STATUS_PROCESS
            ];
            if (!in_array($delivery->status, $arr_status_can_run)) {
                return "Trạng thái không phải là chờ xử lý hoặc đang xử lý, không thể sang đang giao hàng";
            }
        }
        // Nếu muốn đổi sang hoàn thành thì trạng thái kiện phải là đang giao hàng
        // Logic 28-11-2024: Cho phép cả Chờ xử lý vs đang xử lý cũng có thể đổi
        if ($params['status'] == DeliveryConstant::KEY_STATUS_DONE) {
            $arr_status_can_run = [
                DeliveryConstant::KEY_STATUS_PENDING,
                DeliveryConstant::KEY_STATUS_PROCESS,
                DeliveryConstant::KEY_STATUS_RUN,
            ];
            if (!in_array($delivery->status, $arr_status_can_run)) {
                return "Trạng thái không phải là chờ xử lý, đang xử lý hoặc đang giao hàng, không thể hoàn thành";
            }
        }
        // Nếu muốn đổi sang Hủy thì trạng thái phải là chờ xử lý hoặc đang xử lý
        if ($params['status'] == DeliveryConstant::KEY_STATUS_CANCEL) {
            $arr_status_can_cancel = [
                DeliveryConstant::KEY_STATUS_PENDING,
                DeliveryConstant::KEY_STATUS_PROCESS
            ];
            if (!in_array($delivery->status, $arr_status_can_cancel)) {
                return "Trạng thái không phải là chờ xử lý hoặc đang xử lý, không thể hủy";
            }
        }
    }

    // Hàm này bỏ, thay bằng hàm phía dưới
    public function updateDebtCostAndShippingCostToOrderV2($delivery)
    {
        // import_cod: là dùng ở màn Import đối soát mới thực hiện xử lý, mặc định sẽ chỉ có trừ ví thì mới xử lý
        if ($delivery->status == DeliveryConstant::KEY_STATUS_DONE) {
            $orderDB = $delivery->order;

            // Nếu chưa ghi nhận lấy dc tiền hàng còn thiếu
            if ($orderDB->delivery_pay_id == 0) {
                $orderDB->delivery_pay_id        = $delivery->id;
                $orderDB->delivery_pay_debt_cost = $orderDB->debt_cost;
            }

            // Tính toán lại số tiền shiping đã nhận được
            $deliveryOfOrder = $orderDB->deliveries->where('status', DeliveryConstant::KEY_STATUS_DONE);
            $total_shipping_cost = $deliveryOfOrder->sum('shipping_cost');

            $orderDB->delivery_pay_shipping_cost = $total_shipping_cost;
            $orderDB->save();
        }
    }

    public function updateDebtCostAndShippingCostToOrder($delivery, $forceNow = false)
    {
        $orderPackModel = \App::make('App\Models\OrderPackage');
        $deliveryModel = \App::make('App\Models\Delivery');

        // Logic ngày 03/01/2025 sẽ cập nhật luôn từ trạng thái Đang giao hàng luôn
        // Logic ngày 27/01/2025 mặc định lúc tạo đơn là trừ luôn tiền nên sẽ đánh dấu luôn

        if ($delivery->status == DeliveryConstant::KEY_STATUS_DONE || $delivery->status == DeliveryConstant::KEY_STATUS_RUN || $forceNow == true) {
            $orderDBs = $delivery->orders;

            if (count($orderDBs) > 0) {
                foreach ($orderDBs as $key => $orderDB) {
                    // Nếu chưa ghi nhận lấy dc tiền hàng còn thiếu
                    if ($orderDB->delivery_pay_id == 0) {
                        $orderDB->delivery_pay_id        = $delivery->id;
                        $orderDB->delivery_pay_debt_cost = $orderDB->debt_cost;
                        $orderDB->debt_cost_pending = $delivery->status == DeliveryConstant::KEY_STATUS_RUN ? 1 : 0;
                        if ($forceNow == true) {
                            $orderDB->debt_cost_pending = 1;
                        }
                    }

                    // Tính toán lại số tiền shiping đã nhận được
                    $total_shipping_cost = 0;
                    $orderPackagesDB = $orderDB->packagesOrder->where('status', PackageConstant::STATUS_RECEIVED_GOODS);
                    if (count($orderPackagesDB) > 0 ) {
                        $total_shipping_cost = $orderPackagesDB->sum('amount');
                    }
                    // if ($forceNow == true) {
                    //     $orderPackagesDB_1 = $orderDB->packagesOrder->where('status', PackageConstant::STATUS_RECEIVED_GOODS)->whereNull('delivery_id');
                    //     $orderPackagesDB_2 = $orderDB->packagesOrder->whereNotNull('delivery_id');

                    //     $total_shipping_cost = $orderPackagesDB_1->sum('amount');
                    //     $total_shipping_cost += $orderPackagesDB_2->sum('amount');
                    // }

                    $orderDB->delivery_pay_shipping_cost = $total_shipping_cost;
                    $orderDB->save();
                }
            }
        }
    }

    public function changeStatusPackageOfDeliveryDone($delivery)
    {
        if ($delivery->status == DeliveryConstant::KEY_STATUS_DONE) {
            $packOfDelivery = $delivery->packages;
            if (count($packOfDelivery) > 0) {
                // Hoàn thành các kiện
                foreach ($packOfDelivery as $key => $pack) {
                    // $_POST['disable_observer_package'] = true;
                    if ($pack->status != PackageConstant::STATUS_RECEIVED_GOODS) {
                        $pack->date_done = date('Y-m-d H:i:s', time());
                        $pack->status = PackageConstant::STATUS_RECEIVED_GOODS;
                        $pack->save();
                    }
                }

                // Bóc tách các đơn của giao hàng
                $arr_order_ids       = [];
                $arr_consignment_ids = [];
                foreach ($packOfDelivery as $key => $pack) {
                    if ($pack->order_type == 'App\Models\Order') {
                        array_push($arr_order_ids, $pack->order_id);
                    }
                    if ($pack->order_type == 'App\Models\Consignment') {
                        array_push($arr_consignment_ids, $pack->order_id);
                    }
                }
                $arr_order_ids = array_unique($arr_order_ids);
                $arr_consignment_ids = array_unique($arr_consignment_ids);

                // Tìm đơn của kiện để hoàn thành nó
                $orderPackModel   = \App::make('App\Models\OrderPackage');
                $orderModel       = \App::make('App\Models\Order');
                $consignmentModel = \App::make('App\Models\Consignment');

                foreach ($arr_order_ids as $key => $order_id) {
                    $packagesDB = [];
                    if($order_id) {
                        $packagesDB = $orderPackModel->where('order_type', 'App\Models\Order')
                            ->where('order_id',  $order_id)
                            ->where('status', '!=', PackageConstant::STATUS_CANCEL)
                            ->get();
                    }
                    if (!empty($packagesDB)) {
                        // Nếu toàn bộ kiện đểu đã về hoàn thành thì cho hoàn thành đơn
                        $flagDoneAll = true;
                        foreach ($packagesDB as $key => $item) {
                            if ($item->status !=  PackageConstant::STATUS_RECEIVED_GOODS) {
                                $flagDoneAll = false;
                                break;
                            }
                        }
                        if ($flagDoneAll) {
                            // \Log::info('Hoàn thành đơn đê');
                            $orderDB = $orderModel->where('id', $order_id)->first();
                            if ($orderDB) {
                                $orderDB->date_done = Carbon::now();
                                $orderDB->status = OrderConstant::KEY_STATUS_DONE;
                                $orderDB->save();
                            }
                        }
                    }
                }

                foreach ($arr_consignment_ids as $key => $order_id) {
                    $packagesDB = [];
                    if($order_id) {
                        $packagesDB = $orderPackModel->where('order_type', 'App\Models\Consignment')
                            ->where('order_id',  $order_id)
                            ->where('status', '!=', PackageConstant::STATUS_CANCEL)
                            ->get();
                    }
                    if (!empty($packagesDB)) {
                        // Nếu toàn bộ kiện đểu đã về hoàn thành thì cho hoàn thành đơn
                        $flagDoneAll = true;
                        foreach ($packagesDB as $key => $item) {
                            if ($item->status !=  PackageConstant::STATUS_RECEIVED_GOODS) {
                                $flagDoneAll = false;
                                break;
                            }
                        }
                        if ($flagDoneAll) {
                            // \Log::info('Hoàn thành đơn đê v2');
                            $orderDB = $consignmentModel->where('id', $order_id)->first();
                            if ($orderDB) {
                                $orderDB->date_done = Carbon::now();
                                $orderDB->status = ConsignmentConstant::KEY_STATUS_DONE;
                                $orderDB->save();
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param string $id
     * @return mixed
     */
    public function update(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $params = request()->all();

            $delivery = Delivery::find($id);

            // Nếu có tính năng add thì cũng tạm ngắt k cho thực hiện
            $package_add = isset($params['packages']['add']) ? $params['packages']['add'] : null;
            if ($package_add) {
                \Log::info('app/Http/Controllers/DeliveryController.php update stop');
                return resErrorWithinData("Có lỗi", 'Có lỗi xảy ra', 422);
            }

            list($canRemove, $msg) = $this->checkCanRemovePack($delivery, $params);
            if (!$canRemove) {
                return resErrorWithinData($msg, 'Có lỗi xảy ra', 422);
            }

            $result = $this->_service->update($id, $params);

            \DB::commit();
            return $result;
        } catch (\Exception $e) {
            \DB::rollback();
            return resError();
        }
    }

    public function checkCanRemovePack(Delivery $delivery, $params)
    {
        $canRemove = true;
        $msg       = [];

        $orderPackModel = \App::make('App\Models\OrderPackage');

        $packagesByDelivery = $delivery->packages;
        $grouped            = $packagesByDelivery->groupBy('order_id')->all();

        $orderPackCount = [];
        foreach ($grouped as $order_id => $items) {
            $orderPackCount[$order_id] = count($items);
        }

        $packages = !empty($params['packages']) ? $params['packages'] : [];;
        if (empty($packages['remove']) || !count($packages['remove'])) {
            return [$canRemove, $msg];
        }

        $packRemoves = $orderPackModel->whereIn('id', $packages['remove'])->get();

        foreach ($packRemoves as $packRemove) {
            // kiểm tra nếu kiện thuộc đơn hàng mà có từ 2 kiện trở lên đã về VN thì không cho xoá
            if (!empty($orderPackCount[$packRemove->order_id]) && $orderPackCount[$packRemove->order_id] > 1) {
                $canRemove = false;
                $msg       = [
                    'err' => 'Các kiện của đơn ' . $packRemove->order_code . ' mà đã về VN cần phải được tích chọn hết để giao hàng'
                ];
                break;
            }
        }

        return [$canRemove, $msg];
    }

    public function updateDeliveryNotiStatus($delivery, $params)
    {
        $status = isset($params['status']) ? $params['status'] :  null;

        if (is_null($status)) {
            return;
        }

        $notificationService = \App::make('App\Services\NotificationService');

        $data_delivery = [
            'delivery_id'         => $delivery->id,
            'delivery_code'       => $delivery->code,
            'delivery_postcode'   => $delivery->postcode,
            'delivery_status'     => $delivery->status,
            'delivery_status_txt' => \App\Constants\DeliveryConstant::STATUSES[$status],
        ];


        $data = [
            'group'           => 4,
            'name'            => 4002,
            'data'            => json_encode($data_delivery),
            'time_create'     => time(),
            'status'          => 1
        ];

        $orderDB = $delivery->order;

        if ($orderDB) {
            $staff_care_id      = $orderDB->staff_care_id;
            $staff_counselor_id = $orderDB->staff_counselor_id;
            if ($staff_care_id) {
                $notificationService->storeNoti($staff_care_id, $data);
            }
            if ($staff_counselor_id) {
                $notificationService->storeNoti($staff_counselor_id, $data);
            }
        }

        // push notification for customer
        $dataNoti = $this->getDataNotiByStatus($delivery, $status);
        (new FcmService())->sendNotifyUser([$delivery->customer_id], $dataNoti);
    }

    public function getDataNotiByStatus($delivery, $status)
    {
        $title = '';
        $body  = '';
        $data  = [
            'screen'     => 'DeliveryDetail',
            'params'     => [
                'deliveryId' => $delivery->id
            ]
        ];

        switch ($status) {
            case DeliveryConstant::KEY_STATUS_DONE:
                $title = 'Giao hàng';
                $body  = 'Giao hàng '. $delivery->code . ' đã hoàn thành';
                break;
        }

        return [
            'title' => $title,
            'body'  => $body,
            'data'  => $data
        ];
    }

    /**
     * @param Delivery $delivery
     */
    private function refund(Delivery $delivery)
    {
        $refund = json_decode($delivery->refund, true);
        if ($delivery->status === DeliveryConstant::KEY_STATUS_DONE) {
            if ($refund && $refund['amount'] > 0) {
                $this->_service->refundDelivery(
                    $delivery,
                    $refund['amount'],
                    __(
                        'transaction.delivery_refund',
                        [
                            'name' => getCurrentUser()->name,
                            'bill' => implode(',', array_filter($refund['bill_code'])),
                            'amount' => ConvertHelper::numericToVND($refund['amount'])
                        ]
                    )
                );
            }

            if ($delivery->extend_cost > 0) {
                $this->_service->refundDelivery(
                    $delivery,
                    $delivery->extend_cost,
                    __(
                        'transaction.delivery_extend_cost',
                        [
                            'name' => getCurrentUser()->name,
                            'amount' => ConvertHelper::numericToVND($delivery->extend_cost)
                        ]
                    )
                );
            }
        }
    }

    public function printDelivery(Delivery $delivery): JsonResponse
    {
        return resSuccessWithinData(new PrintDeliveryResource($delivery));
    }

    public function printExWarehouse(Delivery $delivery): JsonResponse
    {
        $filePrintService = \App::make('App\Services\FilePrintService');

        $delivery->load(['customer:id,name,phone_number', 'packages', 'warehouse']);
        $orderPackageIds = $delivery->packages()->pluck('id')->toArray();
        $params['orderPackIds'] = $orderPackageIds;
        $filePrintService->store($params, FilePrintConstant::KEY_TYPE_PACK_DELIVERY);

        return resSuccessWithinData(new PrintExWarehouseResource($delivery));
    }

    public function getXlsx(Delivery $delivery)
    {
        return Excel::download((new DeliveryPrintWarehouseExport($delivery)), 'PhieuInKho_' . $delivery->code . '_' . time() . '.xlsx');
    }

    public function storeNote(NoteWarehouseRequest $request, string $id): JsonResponse
    {
        (new DeliveryService())->storeNote($id, $request->input('note'));
        return resSuccess();
    }


    public function downloadFileTemplateCod(\Request $request)
    {
        try {
            (new DeliveryCodTemplate())->store('excel/delivery_cod.xlsx', 'local');

            // download
            $file = storage_path('app/excel/delivery_cod.xlsx');

            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
            return response()->download($file, 'delivery_cod.xlsx', $headers);

        } catch (\Exception $e) {
            // dd($e->getMessage());
            return resError($e->getMessage());
        }
    }

    public function submitFileTemplateCod(Request $request)
    {
        $params = $request->all();
        $data_delivery_cods = (new DeliveryCodImport)->toArray(request()->file('file'));

        $validate = $this->validateDataExcel($data_delivery_cods[0]);

        if ($validate) {
            return resError($validate);
        }

        try {
            \DB::beginTransaction();
            // $this->updateDeliveryCodeExcel($data_delivery_cods[0]);
            // $this->updateDeliveryCodeExcelV2($data_delivery_cods[0]); // Logic mới ngày 15-09-2024
            $this->updateDeliveryCodeExcelV3($data_delivery_cods[0]); // Logic mới ngày 26-11-2024
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function validateDataExcel($data_delivery_cods)
    {
        $deliveryModel = \App::make('App\Models\Delivery');
        $fundModel = \App::make('App\Models\Fund');
        $arr_postcode = [];
        foreach ($data_delivery_cods as $key => $delivery) {
            if ($key == 0) {
                continue;
            }

            $postcode  = $delivery['postcode'];
            $money     = $delivery['money'];
            $fund_name = $delivery['fund_name'];

            if (is_null($postcode) && is_null($money) && is_null($fund_name)) {
                continue;
            }

            if (is_null($money)) {
                return 'Giao hàng dòng số ' . ($key + 1) . ' có số tiền không được để trống';
            }
            if (is_null($postcode)) {
                return 'Giao hàng dòng số ' . ($key + 1) . ' có mã bưu kiện không không được để trống';
            }
            if (is_null($fund_name)) {
                // return 'Giao hàng dòng số ' . ($key + 1) . ' có tên quỹ không được để trống';
            }

            if (!is_numeric($money)) {
                return 'Giao hàng dòng số ' . ($key + 1) . ' số tiền không không đúng định dạng';
            }

            if ($money < 0) {
                return 'Giao hàng dòng số ' . ($key + 1) . ' số tiền không được nhỏ hơn 0';
            }

            if (is_null($fund_name) && $money > 0) {
                return 'Giao hàng dòng số ' . ($key + 1) . ' có tên quỹ không được để trống';
            }

            // Check có tồn tại đơn hàng tương ứng ko
            $deliveryDB = $deliveryModel->where('carrier_code', $postcode)->first();
            if (is_null($deliveryDB)) {
                return 'Mã bưu kiện ' . $postcode . ' không tồn tại trên hệ thống';
            }

            // Check trạng thái hợp lệ hay không
            if ($deliveryDB->status == DeliveryConstant::KEY_STATUS_DONE) {
                return 'Mã bưu kiện ' . $postcode . ' đã hoàn thành, không thể xử lý tiếp';
            }

            // Check phải đang giao hàng mới được phép import
            if ($deliveryDB->status != DeliveryConstant::KEY_STATUS_RUN) {
                return 'Mã bưu kiện ' . $postcode . ' chưa ở trạng thái đang giao hàng, không thể xử lý tiếp';
            }

            // Check số tiền phải thu có khốp không
            $amount_out = $deliveryDB->amount_out;
            if ($amount_out != $money) {
                // return 'Mã bưu kiện ' . $postcode . ' có số tiền cần thu không khớp: ' . $amount_out;
            }

            // Check số tiền fee_missing (Số tiền thu)
            $amount_out = $deliveryDB->fee_missing;
            if ($amount_out != $money) {
                return 'Mã bưu kiện ' . $postcode . ' có số tiền thu không khớp: ' . $amount_out;
            }

            if ($money > 0) {
                // Check tên quỹ phải là quỹ VN
                $fundDB = $fundModel->where('name', $fund_name)->first();
                if (is_null($fundDB)) {
                    return 'Ngân hàng' . $fund_name . ' không tồn tại';
                }
                if ($fundDB->unit_currency != Fund::VND_UNIT_CURRENCY) {
                    return 'Ngân hàng' . $fund_name . 'có loại tiền tệ không hợp lệ';
                }
            }

            // Push vào mảng check trùng
            array_push($arr_postcode, $postcode);
        }

        // Check thêm xem list bị trùng mã không
        $counts = array_count_values($arr_postcode);
        foreach ($counts as $key => $count) {
            if ($count > 1) {
                return 'Mã bưu kiện ' . $key . ' đã bị nhập 2 lần, vui lòng kiểm tra lại';
            }
        }
        return null;
    }

    public function updateDeliveryCodeExcel($data_delivery_cods)
    {
        $deliveryModel    = \App::make('App\Models\Delivery');
        $orderModel       = \App::make('App\Models\Order');
        $customerService  = \App::make('App\Services\CustomerService');
        $transportsModel  = \App::make('App\Models\Transporter');
        $fundModel        = \App::make('App\Models\Fund');
        $fundTypePayModel = \App::make('App\Models\FundTypePay');
        $fundTransactionService  = \App::make('App\Services\FundTransactionService');

        $arr_min = [];

        $countNextTime = 0;
        foreach ($data_delivery_cods as $key => $delivery) {
            if ($key == 0) {
                continue;
            }

            $postcode  = $delivery['postcode'];
            $money     = $delivery['money'];
            $fund_name = $delivery['fund_name'];

            if (is_null($postcode) && is_null($money) && is_null($fund_name)) {
                continue;
            }

            if ($money < 0) {
                continue;
            }

            // Push vào mảng phụ
            array_push($arr_min, $delivery);

            $deliveryDB = $deliveryModel->where('carrier_code', $postcode)->first();

            // 1. Cập nhật về đã giao hàng
            $deliveryDB->status = DeliveryConstant::KEY_STATUS_DONE;
            $deliveryDB->save();

            // 2. Cập nhật các kiện của đơn giao về trạng thái hoàn thành
            $orderPackagesDB = $deliveryDB->orderPackages;
            $order_ids = [];
            if (count($orderPackagesDB) > 0) {
                $order_ids = \Arr::pluck($orderPackagesDB, 'order_id');
                foreach ($orderPackagesDB as $key_pack => $packDB) {
                    $packDB->status = PackageConstant::STATUS_RECEIVED_GOODS;
                    $packDB->save();
                }
            }

            // 3. Cập nhật trang thái của đơn hàng (nếu tất cả các kiện Đã nhận hàng) -> Đã hoàn thành
            $orderDBs = $orderModel->whereIn('id', $order_ids)->get();

            if (count($orderDBs)) {
                foreach ($orderDBs as $key_order => $orderDB) {
                    $packOfOrder = $orderDB->packages;
                    // Check toàn bộ mà đã hoàn thành
                    if (count($packOfOrder)) {
                        $flagUpdateOrder = true;
                        foreach ($packOfOrder as $key_pack => $packDB) {
                            if ($packDB->status != PackageConstant::STATUS_RECEIVED_GOODS) {
                                $flagUpdateOrder = false;
                                break;
                            }
                        }

                        if ($flagUpdateOrder) {
                            $orderDB->status = OrderConstant::KEY_STATUS_DONE;
                            $orderDB->date_done = Carbon::now();
                            $orderDB->save();
                        }
                    }
                }
            }

            // 4. Màn Khách hàng/ chi tiết: Cộng tiền COD vào số dư ví+  Thêm lịch sử giao dịch: Nạp tiền ---
            $customerId = $deliveryDB->customer_id;
            // $balanceOld = $customerService->getBalanceAmount($customerId);
            // Nạp tiền vào ví khách
            $amount = $deliveryDB->amount_out;
            $amount = $money; // Logic mới lấy luôn tiền nó nhập vào
            $content = 'Hoàn tiền COD của mã giao hàng ' . $deliveryDB->code;
            $customer_id = $customerId;
            $soure = $deliveryDB;

            $countNextTime = $countNextTime +1;
            $_POST['postion_6308270688'] = $countNextTime; // Sau nghiên cứu ko dùng cái này nữa

            (new \App\Services\TransactionService())->setTransactionIncrement(
                $amount,
                \App\Constants\TransactionConstant::STATUS_REFUND,
                $content,
                $customer_id,
                $soure
            );
        }

        // 5. Màn Lịch sử thu chi tiền việt: Thêm giao dịch: Nạp tiền --- + nôp vào quỹ tiền ảo + thêm note: Nạp COD Viettel, tiền đã về VTB ngày
        $collection = collect($arr_min);
        $grouped    = $collection->groupBy('fund_name');
        $grouped->all();

        foreach ($grouped as $fund => $delivery) {
            // Tìm ra các đơn vị vận chuyển đối soát
            $postcode_ids = \Arr::pluck($delivery, 'postcode');

            $deliveryFull = $deliveryModel->whereIn('carrier_code', $postcode_ids)->get();


            $transporter_ids = \Arr::pluck($deliveryFull, 'transporter_id');

            $transporterDBs = $transportsModel->whereIn('id', $transporter_ids)->get();
            $transporterNameArr = \Arr::pluck($transporterDBs, 'name');
            $transporterNameText = implode(",", $transporterNameArr);
            $textTransation = 'Nạp COD ' . $transporterNameText;

            // Tìm ra quỹ cộng tiền
            $fundDB = $fundModel->where('name', $fund)->first();


            if (!is_null($fundDB)) {
                $fundTypePayDB = $fundTypePayModel->where('code', 10)->first();
                // Số tiền cộng thêm
                $amountAdd = 0;
                foreach ($deliveryFull as $key => $item) {
                    $amountAdd += $item->amount_out;
                }
                // Số tiền cộng thêm Logic mới 25-07-2024
                $amountAdd = 0;
                foreach ($delivery as $key_2024 => $item_2024) {
                    $amountAdd += $item_2024['money'];
                }

                $data_fund = [
                    'type_object'      => 3, // Nội bộ
                    'type_pay'         => 1, // Khoản thu
                    'fund_type_pay_id' => $fundTypePayDB->id, // Thu tiền code,
                    'fund_id'          => $fundDB->id,
                    'money'            => $amountAdd,
                    'note'             => $textTransation,
                    'lock'             => 1
                ];
                $result = $fundTransactionService->store($data_fund);

                $fundDB = $fundModel->where('name', $fund)->first();
            }
        }

        // 6. Cập nhật tiền hàng còn thiếu + tiền ship vào đơn
        $this->updateDebtCostAndShippingCostToOrder($deliveryDB);
    }


    public function updateDeliveryCodeExcelV2($data_delivery_cods)
    {
        $deliveryModel    = \App::make('App\Models\Delivery');
        $orderModel       = \App::make('App\Models\Order');
        $customerService  = \App::make('App\Services\CustomerService');
        $transportsModel  = \App::make('App\Models\Transporter');
        $fundModel        = \App::make('App\Models\Fund');
        $fundTypePayModel = \App::make('App\Models\FundTypePay');
        $fundTransactionService  = \App::make('App\Services\FundTransactionService');
        $customerModel       = \App::make('App\Models\Customer');

        $arr_min = [];

        $countNextTime = 0;
        foreach ($data_delivery_cods as $key => $delivery) {
            if ($key == 0) {
                continue;
            }

            $postcode  = $delivery['postcode'];
            $money     = $delivery['money'];
            $fund_name = $delivery['fund_name'];

            if (is_null($postcode) && is_null($money) && is_null($fund_name)) {
                continue;
            }

            if ($money < 0) {
                continue;
            }

            // Push vào mảng phụ
            array_push($arr_min, $delivery);

            $deliveryDB = $deliveryModel->where('carrier_code', $postcode)->first();

            // 1. Cập nhật về đã giao hàng
            $deliveryDB->status = DeliveryConstant::KEY_STATUS_DONE;
            $deliveryDB->save();

            // 2. Cập nhật các kiện của đơn giao về trạng thái hoàn thành
            $orderPackagesDB = $deliveryDB->orderPackages;
            $order_ids = [];
            if (count($orderPackagesDB) > 0) {
                $order_ids = \Arr::pluck($orderPackagesDB, 'order_id');
                foreach ($orderPackagesDB as $key_pack => $packDB) {
                    $packDB->status = PackageConstant::STATUS_RECEIVED_GOODS;
                    $packDB->save();
                }
            }

            // 3. Cập nhật trang thái của đơn hàng (nếu tất cả các kiện Đã nhận hàng) -> Đã hoàn thành
            $orderDBs = $orderModel->whereIn('id', $order_ids)->get();

            if (count($orderDBs)) {
                foreach ($orderDBs as $key_order => $orderDB) {
                    $packOfOrder = $orderDB->packages;
                    // Check toàn bộ mà đã hoàn thành
                    if (count($packOfOrder)) {
                        $flagUpdateOrder = true;
                        foreach ($packOfOrder as $key_pack => $packDB) {
                            if ($packDB->status != PackageConstant::STATUS_RECEIVED_GOODS) {
                                $flagUpdateOrder = false;
                                break;
                            }
                        }

                        if ($flagUpdateOrder) {
                            $orderDB->status = OrderConstant::KEY_STATUS_DONE;
                            $orderDB->date_done = Carbon::now();
                            $orderDB->save();
                        }
                    }
                }
            }

            // 4. Màn Khách hàng/ chi tiết: Cộng tiền COD vào số dư ví+  Thêm lịch sử giao dịch: Nạp tiền ---
            $customerId = $deliveryDB->customer_id;
            // $balanceOld = $customerService->getBalanceAmount($customerId);
            // Nạp tiền vào ví khách
            $amount = $deliveryDB->amount_out;
            $amount = $money; // Logic mới lấy luôn tiền nó nhập vào
            $content = 'Hoàn tiền COD của mã giao hàng ' . $deliveryDB->code;
            $content = 'Nạp COD';
            $customer_id = $customerId;
            $soure = $deliveryDB;

            $countNextTime = $countNextTime +1;
            $_POST['postion_6308270688'] = $countNextTime; // Sau nghiên cứu ko dùng cái này nữa

            (new \App\Services\TransactionService())->setTransactionIncrement(
                $amount,
                \App\Constants\TransactionConstant::STATUS_REFUND,
                $content,
                $customer_id,
                $soure
            );

            // 5. Màn Lịch sử thu chi tiền việt:
            $fundDB        = $fundModel->where('name', $fund_name)->first();
            $fundTypePayDB = $fundTypePayModel->where('code', 10)->first();
            $customerDB    = $customerModel->where('id', $deliveryDB->customer_id)->first();
            $data_fund = [
                'type_object'      => 2, // Khách hàng
                'customer_code'    => $customerDB->code,
                'type_pay'         => 1, // Khoản thu
                'fund_type_pay_id' => $fundTypePayDB->id, // Thu tiền code,
                'fund_id'          => $fundDB->id,
                'money'            => $money,
                'note'             => 'Nạp COD',
                'lock'             => 1
            ];
            $result = $fundTransactionService->store($data_fund);
        }

        // 6. Cập nhật tiền hàng còn thiếu + tiền ship vào đơn
        $this->updateDebtCostAndShippingCostToOrder($deliveryDB);
    }

    public function updateDeliveryCodeExcelV3($data_delivery_cods)
    {
        $deliveryModel    = \App::make('App\Models\Delivery');
        $orderModel       = \App::make('App\Models\Order');
        $customerService  = \App::make('App\Services\CustomerService');
        $transportsModel  = \App::make('App\Models\Transporter');
        $fundModel        = \App::make('App\Models\Fund');
        $fundTypePayModel = \App::make('App\Models\FundTypePay');
        $fundTransactionService  = \App::make('App\Services\FundTransactionService');
        $customerModel       = \App::make('App\Models\Customer');

        $arr_min = [];

        foreach ($data_delivery_cods as $key => $delivery) {
            if ($key == 0) {
                continue;
            }

            $postcode  = $delivery['postcode'];
            $money     = $delivery['money'];
            $fund_name = $delivery['fund_name'];

            if (is_null($postcode) && is_null($money) && is_null($fund_name)) {
                continue;
            }

            if ($money < 0) {
                continue;
            }

            $deliveryDB = $deliveryModel->where('carrier_code', $postcode)->first();

            \Log::info('updateDeliveryCodeExcelV3: ' . $postcode);

            // 1. Cập nhật về đã giao hàng
            $deliveryDB->status = DeliveryConstant::KEY_STATUS_DONE;
            $deliveryDB->amount_import = $money;
            $deliveryDB->save();

            // 3. Cập nhật trang thái của đơn hàng (nếu tất cả các kiện Đã nhận hàng) -> Đã hoàn thành
            $this->changeStatusPackageOfDeliveryDone($deliveryDB);

            // 6. Cập nhật tiền hàng còn thiếu + tiền ship vào đơn
            $this->updateDebtCostAndShippingCostToOrder($deliveryDB);

            // Nếu số tiền bằng 0 thì bỏ qua 2 logic số 4 và 5
            if ($money == 0) {
                continue;
            }

            // 4. Màn Khách hàng/ chi tiết: Cộng tiền COD vào số dư ví+  Thêm lịch sử giao dịch: Nạp tiền ---
            $customerId = $deliveryDB->customer_id;
            // $balanceOld = $customerService->getBalanceAmount($customerId);
            // Nạp tiền vào ví khách
            $amount = $deliveryDB->amount_out;
            $amount = $money; // Logic mới lấy luôn tiền nó nhập vào
            $content = 'Hoàn tiền COD của mã giao hàng ' . $deliveryDB->code;
            $content = 'Nạp COD tiền giao hàng';
            $customer_id = $customerId;
            $soure = $deliveryDB;

            (new \App\Services\TransactionService())->setTransactionIncrement(
                $amount,
                \App\Constants\TransactionConstant::STATUS_REFUND,
                $content,
                $customer_id,
                $soure
            );

            // 5. Màn Lịch sử thu chi tiền việt:
            $fundDB        = $fundModel->where('name', $fund_name)->first();
            $fundTypePayDB = $fundTypePayModel->where('code', 10)->first();
            $customerDB    = $customerModel->where('id', $deliveryDB->customer_id)->first();
            $data_fund = [
                'type_object'      => 2, // Khách hàng
                'customer_code'    => $customerDB->code,
                'customer_id'      => $customerDB->id,
                'customer_name'    => $customerDB->name,
                'type_pay'         => 1, // Khoản thu
                'fund_type_pay_id' => $fundTypePayDB->id, // Thu tiền code,
                'fund_id'          => $fundDB->id,
                'money'            => $money,
                'note'             => 'Nạp COD',
                'lock'             => 1
            ];
            $result = $fundTransactionService->store($data_fund);
        }
    }

    public function updatePrint(Request $request)
    {
        $validationRules = [
            'delivery_id'  => 'required|exists:deliveries,id',
            'fee_ship_out' => 'nullable|numeric|min:0',
            'fee_missing'  => 'nullable|numeric|min:0',
            'note_print'   => 'nullable'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        // Tìm ra giao hàng
        $delieveryModel = \App::make('App\Models\Delivery');
        $delieveryDB = $delieveryModel->where('id', $params['delivery_id'])->first();


        if ($delieveryDB->status == DeliveryConstant::KEY_STATUS_DONE) {
            $result_err = ['error' => 'Giao hàng đã hoàn thành, không thể thao tác'];
            return resErrorWithinData($result_err, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();
            if (isset($params['fee_missing'])) {
                $delieveryDB->fee_missing = $params['fee_missing'];
                $delieveryDB->save();
            }

            if (isset($params['fee_ship_out'])) {
                $delieveryDB->fee_ship_out = $params['fee_ship_out'];
                $delieveryDB->save();
            }

            if (isset($params['note_print'])) {
                $delieveryDB->note_print = $params['note_print'];
                $delieveryDB->save();
            }

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }
    // vẫn thao tác được ở trạng thái hoàn thành
    public function modifiesStatusDone(ModifiesRequest $request, Delivery $delivery): JsonResponse
    {
        try {
            return DB::transaction(
                function () use ($delivery, $request) {
                    if ($request->has('note') && ($note = $request->input('note'))) {
                        $this->_service->storeNote($delivery->id, $note);
                        $delivery->update(['note'=> $note]);
                    }
                    return resSuccessWithinData(new DeliveryResource($delivery));
                }
            );
        } catch (\Exception $exception) {
            return resError();
        }
    }

    public function updateNote(Request $request, $id)
    {
        $delivery = Delivery::findOrFail($id);
        $validationRules = [
            'carrier_code' => 'nullable|unique:deliveries,carrier_code,' . $id,
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->only('note', 'carrier_code','user_dongdong_id');
        $note = data_get($params, 'note');
        if ($note && ($note != $delivery->note)) {
            $delivery->note = $note;
            $this->_service->storeNote($delivery->id, $note, $params);
        }
        $delivery->carrier_code = data_get($params, 'carrier_code');
        $delivery->save();
        return resSuccess();
    }

    public function destroy(string $id): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__, $id);

        try {
            \DB::beginTransaction();

            $this->_service->destroy($id);

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }

    }

    public function export(Request $request)
    {
        return $this->_service->export($request);
    }
}
