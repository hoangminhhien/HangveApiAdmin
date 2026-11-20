<?php

namespace App\Http\Controllers;

use App\Http\Resources\Refund\RefundFeedbackResource;
use App\Http\Resources\Refund\RefundDetailResource;
use App\Http\Resources\Refund\RefundResource;
use App\Constants\RefundConstant;
use App\Constants\OrderConstant;
use App\Http\Resources\OnlyValueKeyResource;
use App\Models\Order;
use App\Models\RefundFromXadal;
use App\Models\ReportCustomer;
use App\Models\Refund;
use App\Models\RefundPayment;
use App\Models\RefundFeedback;
use App\Models\Staff;
use App\Services\FcmTokenService;
use App\Services\OrderService;
use Carbon\Carbon;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\ActivityService;
use App\Models\Fine;
use App\Constants\FineConstant;
use Illuminate\Support\Facades\Log;

class RefundController extends Controller
{
    public function __construct(RefundService $service)
    {
        $this->_service = $service;
    }

    public function store(): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__);
        $params = request()->all();

        // Check
        $order_code = $params['order_code'];
        $amount_vn = $params['amount_vn'];
        $result_check = $this->checkBiggerTotalAmountOrder($order_code, $amount_vn);

        if ($result_check) {
            $result = ['error' => 'Tổng tiền hoàn đã vượt quá tổng tiền cọc'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        return resSuccessWithinData($this->_service->store(request()->all()));
    }

    // Kiểm tra số tiền hoàn có lớn hơn tổng tiền của đơn hay không
    // $money_add có thể âm hoặc dương
    public function checkBiggerTotalAmountOrder($order_code, $money_add)
    {
        // Tìm xem nó là đơn nào
        $orderModel = \App::make('App\Models\Order');
        $orderDB = $orderModel->where('code', $order_code)->first();
        //$totalAmount = $orderDB->getTotalAmount();
        $totalAmount = $orderDB->deposit_cost;

        // Xem nó đã hoàn bao tiền
        $refundModel = \App::make('App\Models\Refund');
        $refunds = $refundModel->where('order_code', $order_code)
                                    ->where('status', '<>', RefundConstant::KEY_STATUS_CANCEL)
                                    ->get();

        $refund_ids = \Arr::pluck($refunds, 'id');
        $totalRefund = $refundModel->where('order_code', $order_code)
                ->where('status', '<>', RefundConstant::KEY_STATUS_CANCEL)
                ->sum(\DB::raw('IF(total_refund_cny<>0,total_refund_cny*exchange_rate,IF(total_refund_vn<>0,total_refund_vn,amount_vn))'));

        // Xem tiền hoàn thêm
        $refundPaymentModel = \App::make('App\Models\RefundPayment');
        $totalRefundPayment = $refundPaymentModel->whereIn('refund_id', $refund_ids)->sum('amount');


        // Thêm số tiền mới vào thì nó là bao nhiêu
        $totalRefundNew = $totalRefund + $totalRefundPayment + $money_add;

        // Kiểm tra
        if ($totalRefundNew > $totalAmount) {
            return true;
        }
        return false;
    }

    /**
     * @param  RefundModifiesRequest  $request
     * @param  Refund  $refund
     * @return mixed
     */
    public function modifies(RefundModifiesRequest $request, Refund $refund)
    {
        try {
            $refund->update($request->all());

            return resSuccessWithinData(new RefundResource($refund));
        } catch(Exception $e) {
            throw $e;
        }
    }

    public function update(string $id): JsonResponse
    {
        try {
            \DB::beginTransaction();
            $data = request()->only([
                'note',
                'file',
                'amount_cn',
                'amount_vn',
                'exchange_rate',
                'type',
                'status'
            ]);
            $status = request()->get('status');

            if($status == RefundConstant::KEY_STATUS_CANCEL) {
                $this->updateNotiWhenChangeStatus($status, $id);
            } else {
                // Check kiểm tra số tiền update có bị lớn hơn tổng tiền ko
                $refund = $this->_service->getDetail($id);
                // Số tiền chênh lệch sau khi update
                $money_diff = $data['amount_vn'] - $refund->amount_vn;

                $result_check = $this->checkBiggerTotalAmountOrder($refund->order_code, $money_diff);

                if ($result_check) {
                    $result = ['error' => 'Tổng tiền hoàn sau khi chỉnh sửa đã vượt quá tổng tiền đơn'];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }

                $this->updateNotiWhenUpdate($id);
            }
            \DB::commit();

            return resSuccessWithinData($this->_service->update($id, $data));
        } catch(Exception $e) {
            \DB::rollBack();
            throw $e;
        }

    }

    public function updateStatusProcess(string $id): JsonResponse
    {
        $data = request()->only([
            'status'
        ]);
        $status = request()->get('status');
        // Chỉ có TBP giao dịch quốc tế thì mới được quyền chuyển trạng thái
        if($status && request()->get('is_process') == true && !$this->checkUserInternationalTrade()) {
            return resSuccessWithinData('');
        }
        if(
            $status && request()->get('is_process') == true
        ) {
            $this->updateNotiWhenChangeStatus($status, $id);
        }

        return resSuccessWithinData($this->_service->update($id, $data));
    }

    private function checkUserInternationalTrade()
    {
        $userOnline = \Auth::user();
        // Lấy tất cả user thuộc bộ phận quan hệ quốc tế.
        $internationalTrade = $this->_service->getInternationalTrade();

        return in_array($userOnline->id, $internationalTrade);
    }

    public function updateNotiWhenChangeStatus(string $status, string $id)
    {
        $refund = $this->_service->getDetail($id);
        $orderCode = $refund->order_code;
        $userCreateRefund = $refund->staff_id;

        $arrayUserId = [
            \Auth::user()->id,
            $userCreateRefund
        ];
        // Lấy tất cả user thuộc bộ phận kế toán.
        $accountant = $this->_service->getAccountant();
        // Lấy tất cả user thuộc bộ phận quan hệ quốc tế.
        $internationalTrade = $this->_service->getInternationalTrade();
        $arrayUserId = array_merge($arrayUserId, $accountant, $internationalTrade);

        $notificationService = \App::make('App\Services\NotificationService');

        $statusText = RefundConstant::STATUSES[$status];
        if($status != RefundConstant::KEY_STATUS_CANCEL) {
            $dataContent = [
                'content'     => 'Hoàn tiền cho đơn hàng ' . $orderCode . ' chuyển sang trạng thái ' . $statusText
            ];
        } else {
            $userOnline = \Auth::user();
            $staffName = $userOnline->name;
            $dataContent = [
                'content'     => $staffName . ' đã hủy hoàn tiền đơn hàng ' . $orderCode
            ];
        }

        if(!empty($arrayUserId)) {
            foreach(array_unique($arrayUserId) as $userId) {
                $data = [
                    'group'           => 10,
                    'name'            => 10001,
                    'data'            => json_encode($dataContent),
                    'time_create'     => time(),
                    'status'          => 1
                ];

                $notificationService->storeNoti($userId, $data);
            }
        }
    }

    public function updateNotiWhenUpdate(string $id)
    {
        $refund = $this->_service->getDetail($id);
        $orderCode = $refund->order_code;
        $userCreateRefund = $refund->staff_id;

        $arrayUserId = [
            \Auth::user()->id,
            $userCreateRefund
        ];
        // Lấy tất cả user thuộc bộ phận kế toán.
        $accountant = $this->_service->getAccountant();
        // Lấy tất cả user thuộc bộ phận quan hệ quốc tế.
        $internationalTrade = $this->_service->getInternationalTrade();
        $arrayUserId = array_merge($arrayUserId, $accountant, $internationalTrade);

        $notificationService = \App::make('App\Services\NotificationService');
        $userOnline = \Auth::user();
        $staffName = $userOnline->name;

        if(!empty($arrayUserId)) {
            foreach(array_unique($arrayUserId) as $userId) {
                $dataContent = [
                    'content'     => 'Hoàn tiền cho đơn hàng ' . $orderCode . ' mới được ' . $staffName . ' cập nhật'
                ];
                $data = [
                    'group'           => 10,
                    'name'            => 10001,
                    'data'            => json_encode($dataContent),
                    'time_create'     => time(),
                    'status'          => 1
                ];

                $notificationService->storeNoti($userId, $data);
            }
        }
    }

    public function getStatus(): JsonResponse
    {
        $data = $this->countStatus();

        return resSuccessWithinData($data);
    }

    private function countStatus()
    {
        $statues = RefundConstant::STATUSES;
        $countAll = Refund::select(DB::raw('count(*) as count'), 'status')
            ->groupBy('status')
            ->get();
        $dataCountAll = [];
        $dataCount = [];

        if(!empty($countAll)) {
            $dataCountAll = $countAll->pluck('count','status')->toArray();
            $dataCount = $countAll->pluck('count')->toArray();
        }
        $count = array_sum($dataCount);
        $dataAll = [];
        foreach($statues as $key => $status) {
            $dataAll[] = [
                'name' => $status,
                'value' => $key,
                'color' => RefundConstant::STATUSES_COLOR[$key],
                'count' => !empty($dataCountAll) && isset($dataCountAll[$key]) ? $dataCountAll[$key] : 0
            ];
        }

        $dataTextAll[] = [
            'name' => 'Tất cả',
            'value' => '',
            'color' => '#0688ee',
            'count' => $count,
        ];

        return array_merge($dataTextAll, $dataAll);
    }

    public function detail(string $id): JsonResponse
    {
        return resSuccessWithinData(new RefundDetailResource(Refund::query()->with(['order'])->find($id)));
    }
    /**
     * @param  string  $id
     * @return JsonResponse
     * @throws \Throwable
     */
    public function storeFeedback(string $id)
    {

        try {
            \DB::beginTransaction();
            $comment = '';
            if($content = request()->input('content')) {
                $comment = RefundFeedback::query()->create(
                    [
                        'refund_id' => $id,
                        'cause_id' => getCurrentUserId(),
                        'cause_type' => Staff::class,
                        'content' => $content,
                    ]
                );
                $this->updateNotiWhenAddFeedback($content, $id);
            }
            $result = Refund::query()->with('order')->where('id', $id)->first();
            if($result && $result->status == RefundConstant::KEY_STATUS_PENDING){
                $result->status = RefundConstant::KEY_STATUS_PROCESS;
                $result->save();
            }
            if(request()->input('confirmRefund') == true && $orderCode = request()->input('order_code')) {
                if($result->status != RefundConstant::KEY_STATUS_DONE) {

                    // Nếu hoàn tiền có tiền hàng thực tế thì cập nhật tiền hàng thức tế cho đơn luôn
                    if($result->order_cost_real > 0) {
                        $order = $result->order;
                        if($order) {
                            $orderCost = $result->order_cost_real;
                            $order->orderSuppliers()->update(['order_cost_real' => $orderCost]);
                            $order->update(['order_cost_real' => $orderCost ? $orderCost * $order->exchange_rate : 0]);
                        }
                    }
                    $this->updateNotiWhenChangeStatus(RefundConstant::KEY_STATUS_DONE, $id);
                    Refund::query()->where('id', $id)->update(['status' => RefundConstant::KEY_STATUS_DONE]);
                    $order = Order::query()->where('code', $orderCode)->first();
                    $customerId = $order->customer_id;

                    $amount  = $result->total_amount;
                    $content = 'Hoàn tiền thừa ' . number_format($amount) . 'vnđ' . ' Cho đơn hàng mã: ' . $orderCode . '. Vì 1 số thay đổi';
                    $this->updateNotiLogic($customerId, $content);
                    $source = $result;

                    (new \App\Services\TransactionService())->setTransactionIncrement(
                        $amount,
                        \App\Constants\TransactionConstant::STATUS_REFUND,
                        $content,
                        $customerId,
                        $source,
                        $order
                    );

                    ReportCustomer::query()->where('customer_id', $customerId)->increment('discount_amount', $amount);

                    // Thêm cập nhật các thông tin vào đơn hàng vào đơn hàng
                    $refundService = \App::make('App\Services\RefundService');
                    $refundService->updateOrderAndOrderDetail($result);
                    // Log lại lịch sử hoàn tiền trong chi tiết đơn
                    (new ActivityService())->setOrderLog(
                        $order,
                        trans(
                            "activity.order_refund",
                            ['user_name' => data_get(getCurrentUser(), 'name'), 'amount' => number_format($amount) . ' vnđ']
                        ),
                        $order->id,
                    );
                    if(request()->input('done_fine') == 1) {
                        $fineController = \App::make('App\Http\Controllers\FineController');
                        $fines = Fine::query()->where('order_code', $orderCode)->where('status', '!=', FineConstant::KEY_STATUS_DONE)->get();
                        foreach($fines as $fine) {
                            $fine->status = FineConstant::KEY_STATUS_DONE;
                            $fine->save();
                            // Thêm noti
                            $fineController->updateFineNotiStatus($fine);
                        }
                    }
                }

            }
            \DB::commit();

            return resSuccessWithinData($comment);
        } catch(Exception $e) {
            \DB::rollBack();
            throw $e;
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


    public function updateNotiWhenAddFeedback($content, $id)
    {
        $refund = $this->_service->getDetail($id);
        $orderCode = $refund->order_code;
        $userCreateRefund = $refund->staff_id;
        $arrayUserId = [
            $userCreateRefund
        ];
        // Lấy tất cả user thuộc bộ phận kế toán.
        $accountant = $this->_service->getAccountant();
        // Lấy tất cả user thuộc bộ phận quan hệ quốc tế.
        $internationalTrade = $this->_service->getInternationalTrade();
        $arrayUserId = array_merge($arrayUserId, $accountant, $internationalTrade);

        $notificationService = \App::make('App\Services\NotificationService');
        $userOnline = \Auth::user();
        $staffName = $userOnline->name;

        if(!empty($arrayUserId)) {
            foreach(array_unique($arrayUserId) as $userId) {
                // loại bỏ người tạo feedback
                if ($userId != $userOnline->id) {
                    $dataContent = [
                        'content'     =>  'Ý kiến từ nhân viên ' . $staffName . ': ' . $content
                    ];
                    $data = [
                        'group'           => 10,
                        'name'            => 10001,
                        'data'            => json_encode($dataContent),
                        'time_create'     => time(),
                        'status'          => 1
                    ];

                    $notificationService->storeNoti($userId, $data);
                }
            }
        }
    }

    public function storePayment(string $id)
    {
        $params = request()->all();
        // Check kiểm tra số tiền update có bị lớn hơn tổng tiền ko
        $refund = $this->_service->getDetail($id);
        $result_check = $this->checkBiggerTotalAmountOrder($refund->order_code, $params['amount']);

        if ($result_check) {
            $result = ['error' => 'Tổng tiền hoàn sau khi thêm  đã vượt quá tổng tiền đơn'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();
            $comment = RefundPayment::query()->create(
                [
                    'refund_id' => $id,
                    'amount' => request()->input('amount'),
                    'note' => request()->input('note'),
                ]
            );
            $orderCode = request()->get('order_code');
            $order = Order::query()->where('code', $orderCode)->first();
            $customerId = $order->customer_id;

            $amount = request()->input('amount');
            $content = 'Hoàn tiền thừa ' . number_format($amount) . 'vnđ' . ' Cho đơn hàng mã: ' . $orderCode . '. Vì 1 số thay đổi';
            $result = Refund::query()->where('id', $id)->first();
            $source = $result;

            (new \App\Services\TransactionService())->setTransactionIncrement(
                $amount,
                \App\Constants\TransactionConstant::STATUS_REFUND,
                $content,
                $customerId,
                $source,
                $order
            );
            $this->updateNotiWhenAddPayment($orderCode, $id);
            $this->updateNotiForCustomerWhenAddPayment($content, $customerId);
            ReportCustomer::query()->where('customer_id', $customerId)->increment('discount_amount', $amount);
            \DB::commit();

            return resSuccessWithinData($comment);
        } catch(Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    public function updateNotiForCustomerWhenAddPayment($content, $customerId)
    {
        $data_customer = [
            'content'  => $content
        ];
        $notificationService = \App::make('App\Services\NotificationService');

        $data = [
            'group'           => 8,
            'name'            => 8013,
            'data'            => json_encode($data_customer),
            'time_create'     => time(),
            'customer_id'     => $customerId,
            'status'          => 1
        ];

        $notificationService->store($data);
    }

    public function updateNotiWhenAddPayment(string $orderCode, string $id)
    {
        $refund = $this->_service->getDetail($id);
        $orderCode = $refund->order_code;
        $userCreateRefund = $refund->staff_id;

        $arrayUserId = [
            \Auth::user()->id,
            $userCreateRefund
        ];
        // Lấy tất cả user thuộc bộ phận kế toán.
        $accountant = $this->_service->getAccountant();
        // Lấy tất cả user thuộc bộ phận quan hệ quốc tế.
        $internationalTrade = $this->_service->getInternationalTrade();
        $arrayUserId = array_merge($arrayUserId, $accountant, $internationalTrade);

        $notificationService = \App::make('App\Services\NotificationService');
        $userOnline = \Auth::user();
        $staffName = $userOnline->name;

        if(!empty($arrayUserId)) {
            foreach(array_unique($arrayUserId) as $userId) {
                $dataContent = [
                    'content'     => $staffName . ' đã thêm hoàn tiền cho đơn hàng ' . $orderCode
                ];
                $data = [
                    'group'           => 10,
                    'name'            => 10001,
                    'data'            => json_encode($dataContent),
                    'time_create'     => time(),
                    'status'          => 1
                ];

                $notificationService->storeNoti($userId, $data);
            }
        }
    }
    public function destroy(string $id): JsonResponse
    {
        $msg = \App::make('App\Models\RefundFeedback')->where('id', $id)->first();

        if ($msg && Carbon::now()->subMinutes(5) > Carbon::parse($msg->created_at)) {
            $result = ['err' => 'Hết hạn, không thể xóa tin nhắn này!'];

            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        if ($msg->cause_id != app('auth')->user()->id) {
            $result = ['err' => 'Không thể xóa tin nhắn của người khác'];

            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            $RefundFeedbackService = \App::make('App\Services\RefundFeedbackService');
            $RefundFeedbackService->destroy($id);

            return resSuccess();
        } catch (Exception $e) {
            throw $e;
        }
    }
    public function getFeedback(string $id): JsonResponse
    {
        return resSuccessWithinData(
            new RefundFeedbackResource(
                RefundFeedback::query()->where('refund_id', $id)->get()
            )
        );
    }

    public function storeMessage(): ?array
    {
        return [
            'order_code.exists' => 'Mã đơn hàng không tồn tại'
        ];
    }

    public function storeRequest(): array
    {
        return [
            'order_code'    => 'required|string|max:255|exists:orders,code',
            'amount_cn'     => 'required|numeric|max:10000000000',
            'amount_vn'     => 'required|numeric|max:10000000000',
            'exchange_rate' => 'required|numeric|max:10000000000',
        ];
    }

    public function refund(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            $ids = data_get($params, 'ids');
            $content = data_get($params, 'content');
            if (!$ids || !count($ids)) {
                return resError('Missing param ids!');
            }
            if (!trim($content)) {
                return resError('Vui lòng nhập ý kiến hoàn tiền!');
            }
            $this->_service->refundMulti($ids, $content);
            DB::commit();
            return resSuccess();
        } catch(\Exception $e) {
            DB::rollBack();
            return resError($e->getMessage());
        }
    }

    // Lưu hoàn tiền, logic mới
    public function storeV2(Request $request): JsonResponse
    {
        // Validate
        $validationRules = [
            'order_id' => 'required|exists:orders,id',
            'files' => 'required|array',
            'china_shipping_cost' => 'required|numeric|min:0',
            'list_order_detail' => 'required|array',
            'list_order_detail.*.id' => 'required|exists:order_details,id',
            'list_order_detail.*.name' => 'required',
            'list_order_detail.*.link' => 'required',
            /*'list_order_detail.*.image' => 'required',*/
            /*'list_order_detail.*.classification' => 'required',*/
            'list_order_detail.*.unit_price_cny' => 'required|numeric|min:0',
            'list_order_detail.*.quantity' => 'required|numeric|min:0',
            'list_order_detail.*.note' => 'nullable|max:500',
            'list_order_detail.*.unit_price_cny_new' => 'required|numeric|min:0',
            'list_order_detail.*.quantity_new'=> 'required|numeric|min:0',
        ];
        $validationMessages = [

        ];
        $request->validate($validationRules, $validationMessages);
        $params = request()->all();
        if(data_get($params, 'total_refund_cny') && data_get($params, 'total_refund_vn')) {
            $result = [
                'error' => 'Tổng tiền hoàn chỉ được nhập tiền tệ hoặc VND'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Kiểm tra order_detail phải chính xác
        $validate = $this->checkOrderDetailTrue($params);
        if ($validate) {
            $result = [
                'error' => $validate
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check tại 1 thời điểm chỉ có 1 lệnh hoàn được xử lý
        $arr_status = [
            RefundConstant::KEY_STATUS_PENDING,
            RefundConstant::KEY_STATUS_PROCESS,
        ];

        $refundModel = App::make('App\Models\Refund');
        $refundDB = $refundModel->where('order_id', $params['order_id'])
                                ->whereIn('status', $arr_status)
                                ->get();
        if (count($refundDB) > 0) {
            $result = [
                'error' => 'Đơn này đã được tạo lệnh hoàn tiền, Vui lòng xử lý lệnh cũ trước khi tạo lệnh mới'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Đơn ở trạng thái đặt hàng trở đi mới được tạo lệnh hoàn tiền

        // Tính toán số tiền sẽ hoàn
        $arr_fee_refund = $this->getFeeRefund($params);
        if (data_get($params, 'total_refund_cny')) {
            $result_check = $this->checkBiggerTotalAmountOrder($arr_fee_refund['order_code'], data_get($params, 'total_refund_cny') * data_get($params, 'exchange_rate'));
            // dd($result_check);
            if ($result_check) {
                $result = ['error' => 'Tổng tiền hoàn đã vượt quá tổng tiền cọc'];
                // Logic ngày 2-11-2024 yêu cầu bỏ check chỗ Tổng tiền hoàn đã vượt quá tổng tiền cọc
                // return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        } else if(data_get($params, 'total_refund_vn')) {
            $result_check = $this->checkBiggerTotalAmountOrder($arr_fee_refund['order_code'], data_get($params, 'total_refund_vn'));
            if ($result_check) {
                $result = ['error' => 'Tổng tiền hoàn đã vượt quá tổng tiền cọc'];
                // Logic ngày 2-11-2024 yêu cầu bỏ check chỗ Tổng tiền hoàn đã vượt quá tổng tiền cọc
                // return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        } else {
             // Nếu số tiền hoàn < 0 thì báo lỗi
            if ($arr_fee_refund['total_money_refund'] <= 0) {
                $result = [
                    'error' => 'Số tiền hoàn không hợp lệ'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }

            if ($arr_fee_refund['sub_china_shipping_cost'] < 0) {
                $result = [
                    'error' => 'Số tiền ship nội địa hoàn lại đang nhiều hơn tiền ship nội lưu trong đơn'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }

            // Check (Logic cũ)
            $result_check = $this->checkBiggerTotalAmountOrder($arr_fee_refund['order_code'], $arr_fee_refund['total_money_refund_vnd']);
            if ($result_check) {
                $result = ['error' => 'Tổng tiền hoàn đã vượt quá tổng tiền cọc'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        // Xử lý
        try {
            DB::beginTransaction();
            $result = $this->storeRefundAction($params, $arr_fee_refund);
            $orderService = new OrderService();
            Log::info('order_id_refund ' . $params['order_id']);
            $orderService->refundFromXadal($params['order_id'], 'refund');
            DB::commit();
            return resSuccessWithinData($result);
        } catch (\Exception $e) {
            DB::rollback();
            return resError($e->getMessage());
        }
    }

    public function checkOrderDetailTrue($params)
    {
        $flag     = true; // Mọi thứ đều hợp lệ
        $flagCode = "";
        $orderDetailModel = \App::make('App\Models\OrderDetail');
        foreach ($params['list_order_detail'] as $key => $order_detail) {
            $orderDetailDB = $orderDetailModel->where('id', $order_detail['id'])->first();
            if ($orderDetailDB->order_id != $params['order_id']) {
                $flag = false;
                $flagCode = $orderDetailDB->name;
                break;
            }
        }
        if (!$flag) {
            return "Chi tiết sp không đúng";
        }
        return null;
    }

    public function getFeeRefund($params): array
    {
        $total_money_item = 0; // Tổng tiền sản phẩm
        $total_fee_service = 0; // Tổng tiền phí dịch vụ
        $total_fee_inspection = 0; // Tổng tiền phí kiểm đếm
        $total_quantity_old = 0; // Tổng sp cũ
        $total_quantity_new = 0; // Tổng sp cũ

        $orderModel = App::make('App\Models\Order');
        $orderDB = $orderModel->where('id', $params['order_id'])->first();

        // Tỉ giá
        $exchange_rateDB = $orderDB->exchange_rate;
        $order_percentDB = $orderDB->order_percent;

        // Tính tiền sản phẩm sẽ hoàn
        foreach ($params['list_order_detail'] as $key => $order_detail) {
            $total_quantity_old += $order_detail['quantity'];
            $total_quantity_new += $order_detail['quantity_new'];
            $total_money_old = $order_detail['unit_price_cny'] * $order_detail['quantity'];
            $total_money_new = $order_detail['unit_price_cny_new'] * $order_detail['quantity_new'] ;
            $total_sub = $total_money_old - $total_money_new;

            $total_money_item += $total_sub;
            if ($orderDB->status == OrderConstant::KEY_STATUS_ORDERED) {
                $total_fee_service += $total_sub * $order_percentDB / 100;
            }
        }

        // Tính phí kiểm đếm
        if ($orderDB->is_inspection) {
            $accountingService = App::make('App\Services\AccountingService');
            $inspection_old = $accountingService->getInspectionCost($total_quantity_old);
            $inspection_new = $accountingService->getInspectionCost($total_quantity_new);
            $total_fee_inspection = $inspection_old - $inspection_new;
        }

        // Tính phí kiểm đếm công thức mới
        if ($orderDB->is_inspection) {
            // Lấy ra
            $orderSupplier = $orderDB->orderSuppliers->first();
            $inspection_old = $orderSupplier->inspection_cost; // Giá trị kiểm đếm cũ
            $price_inspection_per_one_item = $inspection_old / $total_quantity_old; // Đơn giá kiểm đếm với mỗi sản phẩm
            $total_fee_inspection = ($total_quantity_old - $total_quantity_new) * $price_inspection_per_one_item; // Số tiền hoàn lại
        }


        // Tổng số tiền sẽ hoàn
        $total_money_refund = 0;
        $total_money_refund_vnd = 0;
        if ($orderDB->status == OrderConstant::KEY_STATUS_ORDERED) {
            $total_money_refund = $total_money_item + $params['china_shipping_cost']
                                + $total_fee_service
                                + round($total_fee_inspection / $exchange_rateDB);

            $total_money_refund_vnd = ($total_money_item + $params['china_shipping_cost'] + $total_fee_service) * $exchange_rateDB
                                    + $total_fee_inspection;
        }
        if ($orderDB->status == OrderConstant::KEY_STATUS_DONE) {
            $total_money_refund = $total_money_item  + $params['china_shipping_cost'];
            $total_money_refund_vnd = $total_money_refund * $exchange_rateDB ;
        }

        // Kiểm tra số tiền china_shipping_cost
        $totalChinaShippingCost = 0;
        $sub_china_shipping_cost = $orderDB->china_shipping_cost
            - $totalChinaShippingCost
            - $params['china_shipping_cost'];

        return [
            'total_money_item' => $total_money_item, // Tổng tiền đơn hàng
            'total_fee_service' => $total_fee_service, // Phí dịch vụ
            'total_fee_inspection' => $total_fee_inspection, // Phí kiểm đếm
            'total_money_refund' => $total_money_refund,
            'total_money_refund_vnd' => $total_money_refund_vnd,
            'order_code' => $orderDB->code,
            'customer_id' => $orderDB->customer_id ?? 0,
            'order_percent' => $orderDB->order_percent,
            'exchange_rate' => $exchange_rateDB,
            'order_status' => $orderDB->status,
            'sub_china_shipping_cost' => $sub_china_shipping_cost
        ];
    }

    public function storeRefundAction($params, $arr_fee_refund): bool
    {
        $userOnline = \Auth::user();
        $refundModel = App::make('App\Models\Refund');
        $data_refund = [
            'organization_id'      => (int) $userOnline->organization_id,
            'staff_id'             => (int) $userOnline->id,
            'order_id'             => $params['order_id'],
            'order_code'           => $arr_fee_refund['order_code'],
            'order_percent'        => $arr_fee_refund['order_percent'],
            'order_status'         => $arr_fee_refund['order_status'],
            'amount_cn'            => $arr_fee_refund['total_money_refund'],
            'total_money_item'     => $arr_fee_refund['total_money_item'],
            'total_fee_service'    => $arr_fee_refund['total_fee_service'],
            'total_fee_inspection' => $arr_fee_refund['total_fee_inspection'],
            'exchange_rate'        => $arr_fee_refund['exchange_rate'],
            'amount_vn'            => $arr_fee_refund['total_money_refund_vnd'],
            'files'                => json_encode($params['files']),
            'order_detail_change'  => json_encode($params['list_order_detail'],JSON_UNESCAPED_UNICODE),
            'china_shipping_cost'  => $params['china_shipping_cost'],
            'note'                 => $params['note'],
            'status'               => RefundConstant::KEY_STATUS_PENDING,
            'total_refund_cny'     => data_get($params, 'total_refund_cny', 0),
            'total_refund_vn'      => data_get($params, 'total_refund_vn'),
            'order_cost_real_old'      => data_get($params, 'order_cost_real_old'),
            'order_cost_real'      => data_get($params, 'order_cost_real')
        ];
        $refundModel->create($data_refund);
        return true;
    }

    // Cập nhật hoàn tiền, logic mới
    public function updateV2(Request $request)
    {
        // Validate
        $validationRules = [
            'refund_id'                              => 'required|exists:refunds,id',
            'order_id'                               => 'required|exists:orders,id',
            'files'                                  => 'required|array',
            'china_shipping_cost'                    => 'required|numeric|min:0',

            'list_order_detail'                      => 'required|array',
            'list_order_detail.*.id'                 => 'required|exists:order_details,id',
            'list_order_detail.*.name'               => 'required',
            'list_order_detail.*.link'               => 'required',
            // 'list_order_detail.*.image'              => 'required',
            // 'list_order_detail.*.classification'     => 'required',
            'list_order_detail.*.unit_price_cny'     => 'required|numeric|min:0',
            'list_order_detail.*.quantity'           => 'required|numeric|min:0',
            'list_order_detail.*.note'               => 'nullable|max:191',
            'list_order_detail.*.unit_price_cny_new' => 'required|numeric|min:0',
            'list_order_detail.*.quantity_new'       => 'required|numeric|min:0',
        ];
        $validationMessages = [

        ];
        $validated = $request->validate($validationRules, $validationMessages);

        $params = request()->all();
        if(data_get($params, 'total_refund_cny') && data_get($params, 'total_refund_vn')) {
            $result = [
                'error' => 'Tổng tiền hoàn chỉ được nhập tiền tệ hoặc VND'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $refundModel = \App::make('App\Models\Refund');
        $refundDB = $refundModel->where('id', $params['refund_id'])->first();

        // Kiểm tra trùng order_id ko
        if ($refundDB->order_id != $params['order_id']) {
            $result = [
                'error' => 'ID đơn hàng không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check trạng thái refund
        $arr_status = [
            RefundConstant::KEY_STATUS_DONE,
            RefundConstant::KEY_STATUS_CANCEL,
        ];

        if (in_array($refundDB->status, $arr_status)) {
            $result = ['money' => 'Trạng thái lệnh hoàn không hợp lệ'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Kiểm tra order_detail phải chính xác
        $validate = $this->checkOrderDetailTrue($params);
        if ($validate) {
            $result = [
                'error' => $validate
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Xử lý
        try {
            \DB::beginTransaction();

            // Xóa cái lệnh cũ đi
            $refundDB->delete();

            // Tính toán số tiền sẽ hoàn
            $arr_fee_refund = $this->getFeeRefund($params);
            if(data_get($params, 'total_refund_cny')) {
                $result_check = $this->checkBiggerTotalAmountOrder($arr_fee_refund['order_code'], data_get($params, 'total_refund_cny') * data_get($params, 'exchange_rate'));
                if ($result_check) {
                    $result = ['error' => 'Tổng tiền hoàn đã vượt quá tổng tiền cọc'];
                    // return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
            }else if(data_get($params, 'total_refund_vn')) {
                $result_check = $this->checkBiggerTotalAmountOrder($arr_fee_refund['order_code'], data_get($params, 'total_refund_vn'));
                if ($result_check) {
                    $result = ['error' => 'Tổng tiền hoàn đã vượt quá tổng tiền cọc'];
                    // return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
            }else {
                // Nếu số tiền hoàn < 0 thì báo lỗi
                if ($arr_fee_refund['total_money_refund'] <= 0) {
                    $result = [
                        'error' => 'Số tiền hoàn không hợp lệ'
                    ];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }

                if ($arr_fee_refund['sub_china_shipping_cost'] < 0) {
                    $result = [
                        'error' => 'Số tiền ship nội địa hoàn lại đang nhiều hơn tiền ship nội lưu trong đơn'
                    ];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }

                // Check (Logic cũ)
                $result_check = $this->checkBiggerTotalAmountOrder($arr_fee_refund['order_code'], $arr_fee_refund['total_money_refund_vnd']);
                if ($result_check) {
                    $result = ['error' => 'Tổng tiền hoàn đã vượt quá tổng tiền cọc'];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
            }

            // Thêm mới lại
            $result = $this->storeRefundAction($params, $arr_fee_refund);
            $orderService = new OrderService();
            Log::info('order_id_refund ' . $params['order_id']);
            $orderService->refundFromXadal($params['order_id'], 'refund');
            \DB::commit();
            return resSuccessWithinData($result);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    // Lịch sử hoàn tiền
    public function historyV2(Request $request)
    {
        // Validate
        $validationRules = [
            'refund_id' => 'required|exists:refunds,id',
        ];
        $validationMessages = [

        ];
        $validated = $request->validate($validationRules, $validationMessages);

        $params = request()->all();

        $refundModel = \App::make('App\Models\Refund');
        $refundDBs = $refundModel->where('id', $params['refund_id'])->get();

        $data_result = [];
        foreach ($refundDBs as $key => $item) {
            // Đếm tổng số sp
            $total_quantity_old   = 0; // Tổng sp cũ
            $total_quantity_new   = 0; // Tổng sp mới
            $order_detail_changeDB = json_decode($item->order_detail_change, true);
            if(is_array($order_detail_changeDB)) {
                foreach ($order_detail_changeDB as $key => $order_detail) {
                    $total_quantity_old += $order_detail['quantity'];
                    $total_quantity_new += $order_detail['quantity_new'];
                }
            }

            $temp = [
                'created_at'           => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : null,
                'total_money_item'     => $item->total_money_item, // Tiền hàng (tệ)
                'total_fee_service'    => $item->total_fee_service, // Phí dịch vụ (tệ)
                'total_fee_inspection' => $item->total_fee_inspection, // Phí kiểm đếm (vnd)
                'china_shipping_cost'  => $item->china_shipping_cost, // Phí vận chuyển nội địa (tệ)
                'exchange_rate'        => $item->exchange_rate, // Tỉ giá
                'amount_cn'            => $item->total_amount_cny, // Tổng tất cả tiền hoàn (tệ)
                'amount_vn'            => $item->total_amount, // Tổng tất cả tiền hoàn (vnd),
                'total_item'           => $total_quantity_old - $total_quantity_new, // Tổng số sp đã hoàn
            ];

            array_push($data_result, $temp);
        }
        return resSuccessWithinData($data_result);
    }

    public function feedbackMulti(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $params = $request->all();
            $ids = data_get($params, 'ids');
            $content = data_get($params, 'content');
            if (!$ids || !count($ids)) {
                return resError('Missing param ids!');
            }
            if (!trim($content)) {
                return resError('Vui lòng nhập ý kiến!');
            }
            $this->_service->feedbackMulti($ids, $content);
            DB::commit();
            return resSuccess();
        } catch(\Exception $e) {
            DB::rollBack();
            return resError($e->getMessage());
        }
    }
}
