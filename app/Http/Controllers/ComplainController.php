<?php

namespace App\Http\Controllers;
use App\Constants\ComplainConstant;
use App\Constants\DepartmentConstant;
use App\Http\Requests\Complain\ComplainModifiesRequest;
use App\Http\Resources\Complain\ComplainCommentResource;
use App\Http\Resources\Complain\ComplainDetailResource;
use App\Http\Resources\Complain\ComplainAutoMessageResource;
use App\Http\Resources\Complain\ListCommentResource;
use App\Http\Resources\ComplainResource;
use App\Http\Resources\ListResource;
use App\Http\Resources\OnlyIdNameResource;
use App\Models\Complain;
use App\Models\ComplainFeedback;
use App\Models\ComplainAutoMessage;
use App\Models\ComplainType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ReportUserOrderedCustomer;
use App\Models\Solution;
use App\Models\Staff;
use App\Services\GroupService;
use App\Services\OrderDetailService;
use App\Services\UserService;
use App\Services\FcmTokenService;
use Carbon\Carbon;
use App\Services\ComplainService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Exports\ComplainExport;
use Maatwebsite\Excel\Facades\Excel;

class ComplainController extends Controller
{
    public function __construct(ComplainService $service)
    {
        $this->_service = $service;
    }

    public function modifies(ComplainModifiesRequest $request, Complain $complain): JsonResponse
    {
        try {
            DB::beginTransaction();
            if($status = $request->get('status')) {
                $statusNew = $status;
                $statusOld = $complain->status;
                if ($statusNew != $statusOld) {
                    $payload['user_id'] = $complain->customer_id ?? 0;
                    $fcmTokenService = app()->make(FcmTokenService::class);
                    $title = 'Thay đổi trạng thái khiếu nại';
                    $body = 'Khiếu nại cho đơn hàng ' . $complain->order->code . ' được chuyển sang trạng thái ' . ComplainConstant::STATUSES[$statusNew] ?? '';
                    $dataSent = [
                        'type' => 'complains',
                        'body' => (string) $complain->id ?? 0
                    ];
                    $fcmTokenService->sendMessageForApp($payload, $title, $body, $dataSent);
                }
                $this->updateNotification($complain, $status);
            }
            if($solutionId = $request->get('solution_id')) {
                // update giải pháp và số tiền
                $this->updateNotiInDetail($complain, $solutionId, $request->get('compensation') ?? 0);
            }
            $complain->update($request->all());
            DB::commit();

            return resSuccessWithinData(new ComplainResource($complain));

        } catch(\Exception $e) {
            DB::rollBack();
            return resError($e->getMessage());
        }
    }

    private function updateNotification($complainResource, $status)
    {
        $notificationService = App::make('App\Services\NotificationService');

        $data_customer = [
            'complain_id' => $complainResource ? $complainResource->id : "",
            'status' => $status,
            'status_txt' => ComplainConstant::STATUSES[$status],
            'order_code' => $complainResource && $complainResource->order ? $complainResource->order->code : ''
        ];
        $data = [
            'group' => 5,
            'name' => 5004,
            'data' => json_encode($data_customer),
            'time_create' => time(),
            'customer_id' => $complainResource && $complainResource->customer ? $complainResource->customer->id : "",
            'status' => 1
        ];

        $notificationService->store($data);
    }

    private function updateNotiInDetail($complainResource, $solutionId, $compensation)
    {
        $notificationService = \App::make('App\Services\NotificationService');
        $solutionModel = \App::make('App\Models\Solution');
        $solution = $solutionModel->where('id', $solutionId)->first();
        $solutionText = $solution->name ?? '';
        $solutionCode = $solution->code ?? '';
        $data_customer = [
            'compensation'          => $compensation,
            'solution_id'           => $solutionId,
            'solution_code'         => $solutionCode,
            'solution_txt'          => $solutionText,
            'order_code'            => $complainResource && $complainResource->order ? $complainResource->order->code : '',
            'complain_id'           => $complainResource ? $complainResource->id : "",
        ];
        $data = [
            'group'           => 5,
            'name'            => 5005,
            'data'            => json_encode($data_customer),
            'time_create'     => time(),
            'customer_id'     => $complainResource && $complainResource->customer ? $complainResource->customer->id : "",
            'status'          => 1
        ];

        $notificationService->store($data);
    }

    public function detail(string $id): JsonResponse
    {
        $complains = Complain::with(['orderDetails', 'order', 'solution', 'customer'])->find($id);
        return resSuccessWithinData(new ComplainDetailResource($complains));
    }

    public function getFeedback(string $id, string $type): JsonResponse
    {
        return $this->getFeedbackByIdAndType($id, $type);
    }

    /**
     * @param  string  $id
     * @param  string  $type
     * @return JsonResponse
     */
    private function getFeedbackByIdAndType(string $id, string $type): JsonResponse
    {
        return resSuccessWithinData(
            new ListCommentResource(
                ComplainFeedback::query()->where(['complain_id' => $id, 'type' => $type])->get(),
                $type,
                ComplainCommentResource::class
            )
        );
    }

    /**
     * @throws BindingResolutionException
     */
    public function storeFeedback(string $id): JsonResponse
    {
        $comment = ComplainFeedback::query()->create(
            [
                'complain_id' => $id,
                'cause_id' => getCurrentUserId(),
                'cause_type' => Staff::class,
                'content' => request()->input('content'),
                'type' => request()->input('type'),
            ]
        );

        if (request()->input('type') == 'private') {
            // Thêm noti
            $complainModel = App::make('App\Models\Complain');
            $complain = $complainModel->where('id', $id)->first();
            $content = request()->input('content');
            $this->updateComplainNotiLogic($complain, $content);
            $this->sendRealtimeToUser($complain);
        }
        if (request()->input('type') == 'public') {
             // Thêm noti cho khách hàng
            $complainModel = App::make('App\Models\Complain');
            $complain = $complainModel->where('id', $id)->first();
            $content = request()->input('content');
            $this->updateNotiWhenChat($complain, $content);
            $this->sendRealtimeToCustomer($complain);

            $payload['user_id'] = $complain->customer_id ?? 0;
            $fcmTokenService = app()->make(FcmTokenService::class);
            $title = 'Phản hồi đơn khiếu nại khiếu nại';
            $body = 'Bạn có phản hổi của đơn khiếu nại cho đơn hàng ' . $complain->order->code;
            $dataSent = [
                'type' => 'complains',
                'body' => (string) $complain->id
            ];
            $fcmTokenService->sendMessageForApp($payload, $title, $body, $dataSent);
        }

        return resSuccessWithinData($comment);
    }

    public function sendRealtimeToCustomer($complain)
    {
        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('id', $complain->customer_id)->first();

        if ($customer) {
            $cData = [
                'channel'  => 'complain-info',
                'to_code' => $customer->code,
            ];
            $redis = \LRedis::connection();
            $redis->publish('pubsub', json_encode($cData));
        }
    }

    public function sendRealtimeToUser($complain)
    {
        $userModel = \App::make('App\Models\User');

        $arr_code = [];

        $staff_care       = $userModel->where('id', $complain->staff_care_id)->first();
        $staff_counselor  = $userModel->where('id', $complain->staff_counselor_id)->first();
        $staff_order      = $userModel->where('id', $complain->staff_order_id)->first();
        $staff_complain   = $userModel->where('id', $complain->staff_complain_id)->first();
        $staff_management = $userModel->where('id', $complain->staff_management_id)->first();
        $staff_admin      = $userModel->where('email', 'admin@thuongdo.com')->first();

        if ($staff_care) {
            array_push($arr_code, $staff_care->code);
        }
        if ($staff_counselor) {
            array_push($arr_code, $staff_counselor->code);
        }
        if ($staff_order) {
            array_push($arr_code, $staff_order->code);
        }
        if ($staff_complain) {
            array_push($arr_code, $staff_complain->code);
        }
        if ($staff_management) {
            array_push($arr_code, $staff_management->code);
        }
        if ($staff_admin) {
            array_push($arr_code, $staff_admin->code);
        }

        if (count($arr_code)) {
            $redis = \LRedis::connection();
            foreach ($arr_code as $key => $code) {
                $cData = [
                    'channel'  => 'complain-info-private',
                    'to_code' => $code,
                ];
                $redis->publish('pubsub', json_encode($cData));
            }
        }
    }

    private function updateNotiWhenChat($complain, $content)
    {
         $notificationService = \App::make('App\Services\NotificationService');

        $data_complain = [
            'order_code'            => $complain->order->code,
            'complain_id'           => $complain->id,
            'content'               => $content
        ];
        $data = [
            'group'           => 5,
            'name'            => 5006,
            'data'            => json_encode($data_complain),
            'time_create'     => time(),
            'customer_id'     => $complain->customer_id,
            'status'          => 1
        ];

        $notificationService->store($data);
    }

    public function updateComplainNotiLogic($complain, $content)
    {
        $staff_order_id     = $complain->staff_order_id;
        $staff_complain_id  = $complain->staff_complain_id;
        $staff_group_id     = $complain->staff_group_id;

        $notificationService = \App::make('App\Services\NotificationService');

        $orderDB = $complain->order;

        $userOnline = \Auth::user();

        // Loại bỏ noti nếu chính nó là người care
        if ($userOnline->id == $staff_order_id) {
            $staff_order_id = null;
        }
        if ($userOnline->id == $staff_complain_id) {
            $staff_complain_id = null;
        }
        if ($userOnline->id == $staff_group_id) {
            $staff_group_id = null;
        }


        $data_complain = [
            'complain_id'    => $complain->id,
            'order_id'       => $orderDB->id,
            'order_code'     => $orderDB->code,
            'content'        => $content,
            'user_chat_id'   => $userOnline->id,
            'user_chat_name' => $userOnline->name,
        ];


        $data = [
            'group'           => 5,
            'name'            => 5003,
            'data'            => json_encode($data_complain),
            'time_create'     => time(),
            'status'          => 1
        ];

        if ($staff_order_id) {
            $notificationService->storeNoti($staff_order_id, $data);
        }
        if ($staff_complain_id) {
            $notificationService->storeNoti($staff_complain_id, $data);
        }
        if ($staff_group_id) {
            $notificationService->storeNoti($staff_group_id, $data);
        }
    }

    /**
     * @param  string  $id
     * @param  string  $type
     * @return JsonResponse
     */
    public function seen(string $id, string $type): JsonResponse
    {
        ComplainFeedback::query()->where(['complain_id' => $id, 'type' => $type])->update(['is_seen' => true]);
        return resSuccess();
    }

    // thêm nv khiếu nại
    public function changeStaffComplain(Complain $complain): JsonResponse
    {
        try {
            \DB::beginTransaction();

            $complain->update(
                request()->only(['staff_complain_id'])
            );

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }
    public function destroy(string $id): JsonResponse
    {
        $msg = \App::make('App\Models\ComplainFeedback')->where('id', $id)->first();

        if ($msg && Carbon::now()->subMinutes(5) > Carbon::parse($msg->created_at)) {
            $result = ['err' => 'Hết hạn, không thể xóa tin nhắn này!'];

            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        if ($msg->cause_id != app('auth')->user()->id) {
            $result = ['err' => 'Không thể xóa tin nhắn của người khác'];

            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            $complainFeedbackService = \App::make('App\Services\ComplainFeedbackService');
            $complainFeedbackService->destroy($id);

            return resSuccess();
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getAutoMessage(): JsonResponse
    {
        $complainAutoMessageService = \App::make('App\Services\ComplainAutoMessageService');

        return $complainAutoMessageService->pagination($perPage = request()->get('per_page') ?? 15);
    }
    /**
     * @param  Request  $request
     * @return mixed
     */
    public function modifiesAutoMessage(Request $request, $id)
    {
        try {
            ComplainAutoMessage::query()->findOrFail($id)->update(
                $request->all()
            );
            $autoMessage = ComplainAutoMessage::query()->find($id);

            return resSuccessWithinData(new ComplainAutoMessageResource($autoMessage));
        } catch(Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    /**
     * @return JsonResponse
     * @throws \Throwable
     */
    public function storeAutoMessage(Request $request)
    {
        $autoMessage = ComplainAutoMessage::query()->create(
            [
                'staff_id' => getCurrentUserId(),
                'content' => request()->input('content'),
                'title' => request()->input('title'),
            ]
        );
        return resSuccessWithinData($autoMessage);
    }

    public function destroyAutoMessage(string $id): JsonResponse
    {
        try {
            $complainAutoMessageService = \App::make('App\Services\ComplainAutoMessageService');
            $complainAutoMessageService->destroy($id);

            return resSuccess();
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function export(Request $request)
    {
        try {
            $params    = $request->all();
            $data      = $this->_service->export($params);
            return Excel::download(new ComplainExport($data), 'complain.xlsx');
        } catch (\Exception $e) {
            \Log::info($e);
            return resError();
        }
    }

    public function getTypes(): JsonResponse
    {
        return resSuccessWithinData(new ListResource(ComplainType::query()->get(), OnlyIdNameResource::class));
    }

    public function getSolutions(): JsonResponse
    {
        return resSuccessWithinData(new ListResource(Solution::query()->get(), OnlyIdNameResource::class));
    }

    public function storeMessage(): ?array
    {
        return [];
    }

    public function storeRequest(): array
    {
        return [
            'complain_type_id' => 'required|exists:complain_type,id',
            'solution_id' => 'required|exists:solutions,id',
            'products' => ['required', 'array'],
            'products.*.order_detail_id' => 'required|exists:order_details,id',
            'products.*.images' => 'array',
            'images_bill' => 'array',
            'images_bill.*' => 'string',
            'images_received' => 'array',
            'images_received.*' => 'string',
        ];
    }

    public function store(): JsonResponse
    {
        $userService = new UserService();
        $this->throwValidationAndAction(__FUNCTION__);
        $params = request()->all();
        $params['order_id'] = $orderId = request()->orderId;
        $order = Order::query()->find($orderId);
        $customer = Customer::query()->where('id', $order->customer_id)->first();
        $params['organization_id'] = Auth::user()->organization_id;
        $params['customer_id'] = $order->customer_id;
        $params['staff_order_id'] = $order->staff_order_id ?? $userService->getStaffAssign(
                ReportUserOrderedCustomer::class
            );
        $params['staff_complain_id'] = $this->getUserComplain();
        $groupService = new GroupService();
        // $staffGroupId = $groupService->getStaffForOrder($customer);
        $params['staff_group_id'] = data_get($order, 'staff_group_id');
        $params['status'] = ComplainConstant::KEY_STATUS_PENDING;
        $complain = Complain::query()->create($params);

        // Đếm KN
        $this->updateCountNotiOfSupplier($complain->order_id);

        (new OrderDetailService())->insertComplainImage($params, $complain);
        $this->_service->insertImages($complain->id, $params['images_bill'], $params['images_received']);
        return resSuccessWithinData($complain);

    }

    // Đếm lại tổng số KN
    public function updateCountNotiOfSupplier($order_id)
    {
        $orderSupplierModel = \App::make('App\Models\OrderSupplier');
        $one = $orderSupplierModel->where('order_id', $order_id)->first();

        if ($one) {
            $supplierModel = \App::make('App\Models\Supplier');
            $supplier = $supplierModel->where('id', $one->supplier_id)->first();

            if ($supplier) {
                $complain_number = $supplier->complain_number == null ? 0 : $supplier->complain_number;
                $complain_number += 1;

                $supplier->complain_number = $complain_number;
                $supplier->save();
            }
        }
    }

    public function getUserComplain()
    {
        $departmentRepo = \App::make('App\Models\Department');
        $userRepo = \App::make('App\Models\User');
        $organization_id = getOrganization();

        $departmentComplain = $departmentRepo->where('type', DepartmentConstant::TYPE_COMPLAIN)->first();
        $users = $userRepo
            ->select('id', 'is_circle_order', 'reset_staff_allow')
            ->where('department_id', $departmentComplain->id)
            ->where('status', 1)
            ->where('reset_staff_allow', 1)
            ->where('organization_id', $organization_id)
            ->orderBy('id')
            ->get();

        $userIdCurrent = '';
        foreach($users as $user) {
            if($user->is_circle_order) {
                $userIdCurrent = $user->id;
                break;
            }
        }
        // Lấy user tiếp theo sẽ được gán
        $userComplain = '';
        if($userIdCurrent) {
            $userComplain = $users->filter(function($item) use ($userIdCurrent) {
                return $item->id > $userIdCurrent;
            })->first();
        }

        if(empty($userComplain)) {
            $userComplain = $users->first();
        }

        if ($userComplain) {
            $userRepo->where('is_circle_order', 1)
                ->where('department_id', $departmentComplain->id)
                ->where('organization_id', $organization_id)
                ->update(['is_circle_order' => 0]);
            $userRepo->where('id', $userComplain->id)->update(['is_circle_order' => 1]);
            return $userComplain->id;
        }
        return null;
    }
}
