<?php


namespace App\Services;


use App\Constants\RefundConstant;
use App\Constants\RoleConstant;
use App\Constants\TransactionConstant;
use App\Http\Resources\Refund\RefundResource;
use App\Models\Refund;
use App\Models\Order;
use App\Models\ReportCustomer;
use App\Models\Role;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use App\Services\ActivityService;
use App\Models\Staff;
use App\Models\RefundFeedback;
use App\Models\Fine;
use App\Constants\FineConstant;

class RefundService extends BaseService
{
    protected string $_resource = RefundResource::class;

    /**
     * @param int $perPage
     * @return JsonResponse
     */
    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();

        //$perService = \App::make('App\Services\PerService');

        $query = $this->_model->newQuery();
        $query->with('staff', 'order.customer', 'refund_feedback', 'order.refunds');

        $query = $query->orderBy('created_at', 'desc');

        //$query = $perService->getConditionByUserLogin($query);
        $data = $query->paginate($perPage);

        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }

    public function store(array $data)
    {
        $this->throwModel();
        $userOnline = \Auth::user();

        $data['organization_id'] = $userOnline->organization_id;
        $data['staff_id'] = $userOnline->id;
        $order = Order::query()->where('code', $data['order_code'])->first();
        $data['order_id'] = optional($order)->id;
        $data['status'] = RefundConstant::KEY_STATUS_PENDING;
        unset($data['created_at']);
        // tạo noti cho
        $this->updateNoti($data['order_code']);

        return $this->_model->create($data);
    }

    public function updateNoti($orderCode)
    {
        $arrayUserId = [
            \Auth::user()->id
        ];
        // Lấy tất cả user thuộc bộ phận kế toán.
        $accountant = $this->getAccountant();
        // Lấy tất cả user thuộc bộ phận quan hệ quốc tế.
        $internationalTrade = $this->getInternationalTrade();
        $arrayUserId = array_merge($arrayUserId, $accountant, $internationalTrade);

        $notificationService = \App::make('App\Services\NotificationService');

        if(!empty($arrayUserId)) {
            foreach(array_unique($arrayUserId) as $userId) {
                $dataContent = [
                    'content'     => 'Hoàn tiền cho đơn hàng ' . $orderCode . ' mới được tạo'
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

    public function getAccountant()
    {
        $roleAccountant = Role::query()->where('name', 'like', '%' . RoleConstant::ACCOUNTANT_ROLE . '%')->first();
        $accountant = [];

        if(!empty($roleAccountant)) {
            $accountant = $roleAccountant->users->pluck('id')->toArray();
        }

        return $accountant;
    }

    public function getInternationalTrade()
    {

        $roleInternationalTrade = Role::query()->where('name', 'like', '%'. RoleConstant::INTERNATIONAL_TRADE . '%')->first();
        $internationalTrade = [];

        if(!empty($roleInternationalTrade)) {
            $internationalTrade = $roleInternationalTrade->users->pluck('id')->toArray();
        }

        return $internationalTrade;
    }

    public function getDetail(string $id)
    {
        return $this->_model->newQuery()->findOrFail($id);
    }

    /**
     * @throws BindingResolutionException
     */
    public function refundMulti(array $ids, $feedback): bool
    {
        if (!count($ids)) {
            return true;
        }
        $arr_status = [
            RefundConstant::KEY_STATUS_PENDING,
            RefundConstant::KEY_STATUS_PROCESS,
        ];
        $refunds = $this->_model->newQuery()->with('order')->find($ids)->whereIn('status', $arr_status);

        $notificationService = \App::make('App\Services\NotificationService');
        $fineController    = \App::make('App\Http\Controllers\FineController');

        // Lấy tất cả user thuộc bộ phận kế toán.
        $accountant = $this->getAccountant();
        // Lấy tất cả user thuộc bộ phận quan hệ quốc tế.
        $internationalTrade = $this->getInternationalTrade();
        $orderCodeArray = [];
        foreach ($refunds as $refund) {
            
            // Nếu hoàn tiền có tiền hàng thực tế thì cập nhật tiền hàng thức tế cho đơn luôn
            if($refund->order_cost_real > 0) {
                $order = $refund->order;
                if($order) {
                    $orderCost = $refund->order_cost_real;
                    $order->orderSuppliers()->update(['order_cost_real' => $orderCost]);
                    $order->update(['order_cost_real' => $orderCost ? $orderCost * $order->exchange_rate : 0]);
                }
            }
            RefundFeedback::query()->create(
                [
                    'refund_id' => $refund->id,
                    'cause_id' => getCurrentUserId(),
                    'cause_type' => Staff::class,
                    'content' => $feedback,
                ]
            );
            $orderCode  = $refund->order_code;
            $order      = $refund->order;
            $customerId = $order->customer_id;
            $orderCodeArray[$orderCode] = [
                'order_code' => $orderCode,
                'customer_id' => $customerId
            ];
            // update refund status
            $refund->status = RefundConstant::KEY_STATUS_DONE;
            $refund->save();
            // create noti, transaction
            // Nếu mà tổng hoàn tiền mà nhập tay thì lấy số tiền đó hoàn lại
            $amount  = $refund->total_amount;
            $content = 'Hoàn tiền thừa ' . number_format($amount) . 'vnđ' . ' Cho đơn hàng mã: ' . $orderCode .
                '. Vì 1 số thay đổi';
            $this->updateNotiLogic($notificationService, $customerId, $content);
            $source = $refund;
            (new \App\Services\TransactionService())->setTransactionIncrement(
                $amount,
                TransactionConstant::STATUS_REFUND,
                $content,
                $customerId,
                $source,
                $order
            );
            // update discount amount for report customer
            ReportCustomer::query()->where('customer_id', $customerId)->increment('discount_amount', $amount);
            // send noti
            $this->updateNotiWhenChangeStatus($notificationService, RefundConstant::KEY_STATUS_DONE, $refund,
                $accountant, $internationalTrade);

            // Cập nhật lại đơn hàng
            $this->updateOrderAndOrderDetail($refund);
            // Log lại lịch sử hoàn tiền trong chi tiết đơn
            (new ActivityService())->setOrderLog(
                $order,
                trans(
                    "activity.order_refund",
                    ['user_name' => data_get(getCurrentUser(), 'name'), 'amount' => number_format($amount) . ' vnđ']
                ),
                $order->id,
            );
            if(request()->input('done_fine') == 1 && $refund->order_code) {
                $fines = Fine::query()->where('order_code', $refund->order_code)->where('status', '!=', FineConstant::KEY_STATUS_DONE)->get();
                foreach($fines as $fine) {
                    $fine->status = FineConstant::KEY_STATUS_DONE;
                    $fine->save();
                    // Thêm noti
                    $fineController->updateFineNotiStatus($fine);
                }
            }
        }
        $fcmTokenService = app()->make(FcmTokenService::class);
        foreach ($orderCodeArray as $sent) {
            $payload['user_id'] = $sent['customer_id'];
            $title = 'Hoàn tiền đơn hàng';
            $body = 'Bạn có hoàn tiền cho đơn hàng ' . $sent['order_code'];
            $dataSent = [
                'type' => 'refunds',
                'body' => (string) $sent['order_code']
            ];
            $fcmTokenService->sendMessageForApp($payload, $title, $body, $dataSent);
        }
        return true;
    }

    public function updateOrderAndOrderDetail($refund)
    {
        if ($refund->total_refund_cny > 0 ) {
            return;
        }

        $orderModel       = \App::make('App\Models\Order');
        $orderSupModel    = \App::make('App\Models\OrderSupplier');
        $orderDetailModel = \App::make('App\Models\OrderDetail');

        $orderDB       = $orderModel->where('id', $refund->order_id)->first();
        $orderSupDB    = $orderSupModel->where('order_id', $orderDB->id)->first();
        $orderDetailDB = $orderDetailModel->where('order_id', $orderDB->id)->get();

        $order_cost_new          = $orderDB->order_cost - $refund->total_money_item * $refund->exchange_rate; // Tiền hàng
        $order_fee_new           = $orderDB->order_fee - $refund->total_fee_service * $refund->exchange_rate; // Tiền phí
        $inspection_cost_new     = $orderDB->inspection_cost - $refund->total_fee_inspection;                 // Kiểm đếm
        $china_shipping_cost_new = $orderDB->china_shipping_cost - $refund->china_shipping_cost;              // Ship TQ

        $deposit_cost_new = $orderDB->deposit_cost - $refund->amount_vn;

        // Cập nhật vào đơn hàng và nhà cung cấp
        $data_update = [
            'order_cost'          => $order_cost_new,
            'order_fee'           => $order_fee_new,
            'inspection_cost'     => $inspection_cost_new,
            'china_shipping_cost' => $china_shipping_cost_new,
            'deposit_cost'        => $deposit_cost_new,
        ];
        $orderDB->fill($data_update)->save();
        $orderSupDB->fill($data_update)->save();

        // Cập nhật chi tiết đơn
        $order_detail_change = json_decode($refund->order_detail_change, true);
        foreach ($order_detail_change as $key => $order_detail) {
            $orderDetailDB = $orderDetailModel->where('id', $order_detail['id'])->first();

            if ($orderDetailDB) {
                $orderDetailDB->unit_price_cny = $order_detail['unit_price_cny_new'];
                $orderDetailDB->quantity       = $order_detail['quantity_new'];
                $orderDetailDB->note           = $order_detail['note'];
                $orderDetailDB->amount_cny     = $order_detail['unit_price_cny_new'] * $order_detail['quantity_new'];
                $orderDetailDB->save();
            }
        }
    }

    private function updateNotiLogic($notificationService, $customerId, $content)
    {
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

    public function updateNotiWhenChangeStatus($notificationService, string $status, Refund $refund, array $accountant,
        array $internationalTrade)
    {
        $orderCode        = $refund->order_code;
        $userCreateRefund = $refund->staff_id;

        $arrayUserId = [
            \Auth::user()->id,
            $userCreateRefund
        ];

        $arrayUserId = array_merge($arrayUserId, $accountant, $internationalTrade);

        $statusText = RefundConstant::STATUSES[$status];
        $dataContent = [
            'content' => 'Hoàn tiền cho đơn hàng ' . $orderCode . ' chuyển sang trạng thái ' . $statusText
        ];

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

    public function feedbackMulti(array $ids, $feedback)
    {
        $refunds = $this->_model->newQuery()->with('order')->find($ids);
        foreach ($refunds as $refund) {
            $refund->refund_feedback()->create([
                'cause_id' => getCurrentUserId(),
                'cause_type' => Staff::class,
                'content' => $feedback,
            ]);
            // update refund status
            if($refund->status == RefundConstant::KEY_STATUS_PENDING){
                $refund->status = RefundConstant::KEY_STATUS_PROCESS;
                $refund->save();
            }
        }
    }
}
