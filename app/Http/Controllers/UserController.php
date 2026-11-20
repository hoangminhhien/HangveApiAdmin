<?php

namespace App\Http\Controllers;

use App\Constants\UserConstant;
use App\Http\Requests\Staff\StaffChangePasswordRequest;
use App\Http\Resources\ListResource;
use App\Http\Resources\Role\RoleResource;
use App\Interfaces\Validation\StoreValidationInterface;
use App\Interfaces\Validation\UpdateValidationInterface;
use App\Models\Staff;
use App\Models\Notification as NotificationModel;

use App\Services\MediaService;
use App\Services\OrderService;
use App\Services\PermissionService;
use App\Services\StaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Notification;
use App\Notifications\PushNotiAdmin;

use NotificationChannels\WebPush\WebPushMessage;

use LRedis;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Order;
use DB;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\ComplainConstant;
use App\Models\User;

use App\Constants\DepartmentConstant;
use App\Services\ConfigService;

class UserController extends Controller implements StoreValidationInterface, UpdateValidationInterface
{
    public function __construct(StaffService $service)
    {
        $this->_service = $service;
    }

    public function pagination(): JsonResponse
    {
        $data = request()->all();
        $per_page = request()->get('per_page', 25);

        $userRepo = \App::make('App\Models\User');

        $name          = request()->name;
        $email         = request()->email;
        $department_id = request()->department_id;
        $phone_number = request()->phone_number;
        $role_id       = request()->role_id;
        $status       = data_get($data, 'status');

        $model  = $userRepo->withCurrentGroup()->with(['organization', 'roles']);

        if (!is_null($name)) {
            $model = $model->where('users.name', 'like', "%{$name}%");
        }
        if (!is_null($email)) {
            $model = $model->where('users.email', 'like', "%{$email}%");
        }
        if (!is_null($department_id)) {
            $model = $model->where('users.department_id', $department_id);
        }
        if (!is_null($phone_number)) {
            $model = $model->where('users.phone_number', $phone_number);
        }
        if (!is_null($status)) {
//            $model = $model->where('status', $status);
            $model = $model->where('users.status', $status);
        }

        if (!is_null($role_id)) {
            $model = $model->whereHas('roles', function($role) use($role_id) {
                $role->where('id', '=', $role_id);
            });
        }

        $model = $model->orderBy('users.created_at', 'desc');
        $users = $model->paginate($per_page);

        $_paginateResource = \App\Http\Resources\PaginateJsonResource::class;
        $_resource = \App\Http\Resources\User\UserRealResource::class;
        return resSuccessWithinData(new $_paginateResource($users, $_resource));
    }

    /**
     * @return mixed
     */
    public function me()
    {
        /** @var Staff $staff */
        $staff = Auth::user()->load(['permissions', 'department', 'roles', 'userExternal', 'organization' => function($q){
            return $q->select('id', 'name');
        }]);
        $permissions = $staff->getAllPermissions();

        $data = $staff->only(
            'id',
            'name',
            'code',
            'email',
            'avatar',
            'phone_number',
            'total_noti',
            'id_taobao',
            'user_name_taobao',
            'password_taobao',
            'total_chat_unread',
            'xadal_user_code',
            'xadal_user_password',
        );
        $data['department'] = optional($staff->department)->only('id', 'name', 'type');
        $data['roles'] = (new ListResource($staff->roles, RoleResource::class));
        $data['permissions'] = $permissions;
        // noti mới
        $data['total_noti'] = $this->countNotification($data['id']);
        $data['total_chat_unread'] = $staff->getCountConversationUnread();

        $data['user_external'] = $staff->userExternal->isNotEmpty() ?
            $this->getUserExternal($staff->userExternal) : [];
        $data['organization'] = $staff->organization;
        $data['inspection'] = json_decode((new ConfigService())->getValueByKey('inspection'));
        return resSuccessWithinData($data);
    }

    public function getUserExternal($data) {
        $userExternals = [];

        foreach ($data as $item) {
            if (!$item->is_active) {
                continue;
            }

            $userExternals[$item->source] = [
                'source'    => $item->source,
                'user_name' => $item->user_name,
                'is_active' => $item->is_active
            ];
        };

        return $userExternals;
    }

    // total noti mới
    public function countNotification($userid)
    {
        $countAll = DB::table('notifications')
            ->select(DB::raw('count(*) as count'), 'group')
            ->where('user_id', $userid)
            ->where('time_seen', 0)
            ->groupBy('group')
            ->get();

        $data = [
            'all' => 0,
            'customer' => 0, //1
            'order' => 0, //2
            'package' => 0, //3
            'delivery' => 0, //4
            'complain' => 0, // 5
            'fine' => 0, //6
            'cart' => 0, //7
            'finance' => 0, //8
            'endow' => 0, //9
            'refund' => 0, // 10
            'file' => 0, // 11
            'important' => 0, // 13
            'orderstatusreconfirm' => 0, // 14
            'consignment' => 0, // 15
        ];

        $notifications = [
            NotificationModel::CUSTOMER => 'customer', // 1
            NotificationModel::ORDER    => 'order', // 2
            NotificationModel::PACKAGE  => 'package', //3
            NotificationModel::DELIVERY => 'delivery', //4
            NotificationModel::COMPLAIN => 'complain', //5
            NotificationModel::FINE     => 'fine', //6
            NotificationModel::CART     => 'cart', //7
            NotificationModel::FINANCE  => 'finance', //8
            NotificationModel::ENDOW    => 'endow', //9
            NotificationModel::REFUND   => 'refund',  // 10
            NotificationModel::FILE     => 'file', //11
            NotificationModel::IMPORTANT     => 'important', //11
            NotificationModel::ORDERSTATUSRECONFIRM     => 'orderstatusreconfirm', //14
            NotificationModel::CONSIGNMENT     => 'consignment', //15
        ];

        $excludeFromAll = ['orderstatusreconfirm', 'consignment'];
        foreach($countAll as $value) {
            if (!in_array($value->group, array_keys($notifications))) {
                continue;
            }

//            $data['all']                        += $value->count;
            $data[$notifications[$value->group]] = $value->count;

            $key = $notifications[$value->group];
            if (!in_array($key, $excludeFromAll)) {
                $data['all'] += $value->count;
            }
        };

        return $data;
    }

    public function store(): JsonResponse
    {
        $result = $this->_service->store(request()->all());
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }
        return resSuccessWithinData($result);
    }

    public function storeMessage(): array
    {
        return [];
    }

    public function storeRequest(): array
    {
        return [
            'name' => 'required|max:255',
            'email' => 'required|max:255|email|unique:users,email',
            'phone_number' => 'required|max:10|unique:users,phone_number',
            'password' => 'required|max:255',
            'department_id' => 'required|max:255|exists:departments,id',
            'role_id' => 'required|max:255|exists:roles,id',
            'organization_id' => 'required|exists:organizations,id'
        ];
    }

    public function updateMessage(): array
    {
        return [];
    }

    public function updateRequest(string $id): array
    {
        return [
            'name' => 'required|max:255',
            'email' => [
                'required',
                'max:255',
                'email',
                Rule::unique('users')->ignore($id),
            ],
            'phone_number' => [
                'required',
                'max:10',
                Rule::unique('users')->ignore($id),
            ],
            'password' => 'max:255',
            'organization_id' => 'required|exists:organizations,id',
            'role_id' => 'required|max:255|exists:roles,id'
        ];
    }

    /**
     * @param  Staff  $user
     * @return JsonResponse
     */
    public function activeUser(User $user): JsonResponse
    {
        // if($user->status == UserConstant::STATUS_ACTIVE){
        //     $orders = $user->orderStaff()->whereNotIn('status', [OrderConstant::KEY_STATUS_DONE, OrderConstant::KEY_STATUS_CANCEL])->count();
        //     if($orders > 0){
        //         return resError('Nhân viên phải xử lý hết đơn mới có thể ngưng tài khoản của nhân viên', 400);
        //     }
        // }
        $user->update(['status' => (int)!$user->status]);

        // Xóa luôn token
        $result = DB::table('personal_access_tokens')
                                ->where('tokenable_type', 'App\Models\User')
                                ->where('tokenable_id', (int)$user->id)
                                ->delete();

        return resSuccessWithinData($user);
    }

    public function uploadAvt(): JsonResponse
    {
        $attachment = (new MediaService())->singleFileV2(request()->file('file'));
        Auth::user()->update(['avatar' => data_get($attachment, 'path')]);
        return resSuccess();
    }

    /**
     * @param  StaffChangePasswordRequest  $request
     * @return JsonResponse
     */
    public function changePassword(StaffChangePasswordRequest $request): JsonResponse
    {
        $user = Auth::user();
        if (!Hash::check( $request->input('password'), $user->password)) {
            return resError(trans('staff.password_invalid'));
        }
        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        // Xóa luôn token
        $result = DB::table('personal_access_tokens')
                                ->where('tokenable_type', 'App\Models\User')
                                ->where('tokenable_id', (int)$user->id)
                                ->delete();

        return resSuccess();
    }

    public function changeAccountXadal(Request $request): JsonResponse
    {
        $user = Auth::user();
        $user->xadal_user_code = $request->xadal_user_code ?? '';
        $user->xadal_user_password = $request->xadal_user_password ?? '';
        $user->save();
        return resSuccessWithinData(Auth::user());
    }

    public function update(string $id): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__, $id);

        $data = request()->all();

        // Lấy ra role cần cập nhật
        $role = \App\Models\Role::where('id', $data['role_id'])->first();

        // Lấy ra user cần cập nhất
        $userModel = \App::make('App\Models\User');
        $user = $userModel->where('id', $id)->first();

        if (!$user->hasRole($role)) {
            $name = '99999999999999';
            $roleOfUser = $user->roles;
            if (count($roleOfUser)) {
                $roleOfUser = $user->roles[0];
                $name = \Str::slug($roleOfUser->display_name);
            }

            $orderStatus = [
                \App\Constants\OrderConstant::KEY_STATUS_DONE,
                \App\Constants\OrderConstant::KEY_STATUS_CANCEL,
            ];

            $packStatus = [
                \App\Constants\PackageConstant::STATUS_RECEIVED_GOODS,
                \App\Constants\PackageConstant::STATUS_CANCEL,
            ];

            if ($name == 'nhan-vien-tu-van') {
                $customerRepo = \App::make('App\Models\Customer');
                $customerCareCount = $customerRepo->where('staff_counselor_id', $id)->count();
                if ($customerCareCount) {
                    $result = [ 'err' => 'Không được cập nhật vai trò. Vẫn còn khách hàng đang được nhân viên này tư vấn.'];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }

                // Check bên đơn
                $orderRepo = \App::make('App\Models\Order');
                $orderCareCount = $orderRepo->where('staff_counselor_id', $id)
                                            ->whereIn('status', $orderStatus)
                                            ->count();
                if ($orderCareCount) {
                    $result = [ 'err' => 'Không được cập nhật vai trò. Vẫn còn đơn hàng đang được nhân viên tư vấn.'];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }

                // Check bên kiện
                $orderPackageRepo = \App::make('App\Models\OrderPackage');

                $orderPackageCareCount = $orderPackageRepo->where('staff_counselor_id', $id)
                                                          ->whereIn('status', $packStatus)
                                                          ->count();
                if ($orderPackageCareCount) {
                    $result = [ 'err' => 'Không được cập nhật vai trò. Vẫn còn kiện hàng đang được nhân viên này xử lý.'];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
            }

            if ($name == 'nhan-vien-cham-soc') {
                $customerRepo = \App::make('App\Models\Customer');
                $customerCareCount = $customerRepo->where('staff_care_id', $id)->count();
                if ($customerCareCount) {
                    $result = [ 'err' => 'Không được cập nhật vai trò. Vẫn còn khách hàng đang được nhân viên này chăm sóc.'];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }

                // Check bên đơn
                $orderRepo = \App::make('App\Models\Order');
                $orderCareCount = $orderRepo->where('staff_care_id', $id)
                                            ->whereIn('status', $orderStatus)
                                            ->count();
                if ($orderCareCount) {
                    $result = [ 'err' => 'Không được cập nhật vai trò. Vẫn còn đơn hàng đang được nhân viên tư vấn.'];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }

                // Check bên kiện
                $orderPackageRepo = \App::make('App\Models\OrderPackage');

                $orderPackageCareCount = $orderPackageRepo->where('staff_care_id', $id)
                                                            ->whereIn('status', $packStatus)
                                                            ->count();
                if ($orderPackageCareCount) {
                    $result = [ 'err' => 'Không được cập nhật vai trò. Vẫn còn kiện hàng đang được nhân viên này xử lý.'];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
            }

            if ($name == 'nhan-vien-bao-gia') {
                // Check bên đơn
                $orderRepo = \App::make('App\Models\Order');
                $orderCareCount = $orderRepo->where('staff_quotation_id', $id)
                                            ->whereIn('status', $orderStatus)
                                            ->count();

                if ($orderCareCount) {
                    $result = [ 'err' => 'Không được cập nhật vai trò. Vẫn còn đơn hàng đang được nhân viên báo giá.'];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
            }

            if ($name == 'nhan-vien-dat-hang') {
                // Check bên đơn
                $orderRepo = \App::make('App\Models\Order');
                $orderCareCount = $orderRepo->where('staff_order_id', $id)
                                            ->whereIn('status', $orderStatus)
                                            ->count();

                if ($orderCareCount) {
                    $result = [ 'err' => 'Không được cập nhật vai trò. Vẫn còn đơn hàng đang được nhân viên đặt hàng.'];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
            }

            if ($name == 'nhan-vien-khieu-nai') {
                $complainStatus = [
                    \App\Constants\ComplainConstant::KEY_STATUS_DONE,
                    \App\Constants\ComplainConstant::KEY_STATUS_CANCEL,
                ];

                $complainRepo = \App::make('App\Models\Complain');
                $complainCareCount = $complainRepo->where('staff_complain_id', $id)
                                                  ->whereIn('status', $complainStatus)
                                                  ->count();
                if ($complainCareCount) {
                    $result = [ 'err' => 'Không được cập nhật vai trò. Vẫn còn khiếu nại đang được nhân viên này xử lý.'];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
            }
        }

        try {
            \DB::beginTransaction();

            $result = $this->_service->update($id, request()->all());

            \DB::commit();

            return resSuccessWithinData($result);

            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $data = request()->all();

        // Check care khách hàng
        $customerRepo = \App::make('App\Models\Customer');
        $customerCareCount = $customerRepo->where('staff_group_id', $id)->count();

        if ($customerCareCount) {
            $result = [ 'err' => 'Không được xóa. Vẫn còn khách hàng đang được nhân viên này chăm sóc.'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check bên đơn hàng
        $orderRepo = \App::make('App\Models\Order');

        $orderCareCount = $orderRepo->whereNotIn('status', [OrderConstant::KEY_STATUS_DONE])->where('staff_group_id', $id)->count();
        if ($orderCareCount) {
            $result = [ 'err' => 'Còn tồn đơn hàng chưa xử lý xong, vui lòng kiểm tra !'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check bên kiện
        $orderPackageRepo = \App::make('App\Models\OrderPackage');

        $orderPackageCareCount = $orderPackageRepo->whereNotIn('status', [PackageConstant::STATUS_RECEIVED_GOODS, PackageConstant::STATUS_CANCEL])->where('staff_group_id', $id)->count();
        if ($orderPackageCareCount) {
            $result = [ 'err' => 'Còn tồn kiện hàng chưa xử lý xong, vui lòng kiểm tra!'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check khiếu nại
        $complainRepo = \App::make('App\Models\Complain');
        $complainCareCount = $complainRepo->whereNotIn('status', [ComplainConstant::KEY_STATUS_DONE, ComplainConstant::KEY_STATUS_CANCEL])->where('staff_group_id', $id)->count();
        if ($complainCareCount) {
            $result = [ 'err' => 'Còn tồn khiếu nại chưa xử lý xong, vui lòng kiểm tra!'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check bên nộp phạt
        $fineRepo = \App::make('App\Models\Fine');

        $fineCareCount = $fineRepo->where('user_id', $id)->count();

        if ($fineCareCount) {
            $result = [ 'err' => 'Không được xóa. Vẫn còn phiếu phạt chưa giải quyết xong '];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();

            $this->_service->destroy($id);
            // dd('1321');
            \DB::commit();

            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function saveTokenNoti(Request $request)
    {
        $this->validate($request,[
            'endpoint'    => 'required',
            'keys.auth'   => 'required',
            'keys.p256dh' => 'required'
        ]);

        try {
            \DB::beginTransaction();

            $endpoint = $request->endpoint;
            $token = $request->keys['auth'];
            $key = $request->keys['p256dh'];
            $user = Auth::user();
            $user->updatePushSubscription($endpoint, $key, $token);

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function sentNotiTest(Request $request)
    {
        $userOnline = \Auth::user();
        $notificationService = \App::make('App\Services\NotificationService');
        $data_order = [
            'order_code'  => 1,
            'order_id'    => 1,
        ];
        $data = [
            'group'           => 2,
            'name'            => 2001, // Thay đổi nhân viên
            'data'            => json_encode($data_order),
            'time_create'     => time(),
            'status'          => 1
        ];

        $params = $request->all();

        $notificationService->storeNoti($params['user_id'], $data);

        // $userOnline->notify(new PushNotiAdmin('Hay quá', 'Chào mừng bạn đến với bình nguyên vô tận'));

        // Notification::send($userOnline,new PushNotiAdmin);
    }


    public function saveTokenNotiClient(Request $request)
    {
        $this->validate($request,[
            'endpoint'    => 'required',
            'keys.auth'   => 'required',
            'keys.p256dh' => 'required',
            'email'       => 'required|exists:customers,email'
        ]);

        try {
            \DB::beginTransaction();

            $endpoint = $request->endpoint;
            $token = $request->keys['auth'];
            $key = $request->keys['p256dh'];
            $customerModel = \App::make('App\Models\Customer');
            $customerDB = $customerModel->where('email', $request->email)->first();
            $customerDB->updatePushSubscription($endpoint, $key, $token);

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function sentNotiTestClient(Request $request)
    {
        $customerModel = \App::make('App\Models\Customer');
        $userOnline = $customerModel->where('email', 'nhung30@gmail.com')->first();

        $notificationService = \App::make('App\Services\NotificationService');
        $data_order = [
            'order_code'  => 1,
            'order_id'    => 1,
        ];
        $data = [
            'group'           => 2,
            'name'            => 2001, // Thay đổi nhân viên
            'data'            => json_encode($data_order),
            'time_create'     => time(),
            'status'          => 1
        ];

        $params = $request->all();

        $notificationService->storeNotificationCustomer($userOnline->id, $data);
    }

    /**
     * @param  Staff  $user
     * @return JsonResponse
     */
    public function updateResetStaffStatus(Staff $user): JsonResponse
    {
        try {
            $user->update(['reset_staff_allow' => !$user->reset_staff_allow]);

            return resSuccess();
        } catch (\Exception $e) {
            return resError();
        }
    }

    public function waitingDeposit(Request $request, OrderService $orderService): JsonResponse
    {
        $params = $request->all();
        $res = $orderService->waitingDeposit($params);
        return resSuccessWithinData($res);
    }

    public function waitingDepositTotal(Request $request, OrderService $orderService): JsonResponse
    {
        $params = $request->all();
        /*$res = Cache::get('waiting_deposit_total', []);*/
        $res = $orderService->totalWaitingDeposit($params);
        /*if (empty($res)) {
            $res = $orderService->totalWaitingDeposit($params);
            Cache::put('waiting_deposit_total', $res, now()->addMinutes(10));
        }*/

        return resSuccessWithinData($res);
    }

    public function ordered(Request $request, OrderService $orderService): JsonResponse
    {
        $params = $request->all();
        $res = $orderService->ordered($params);
        return resSuccessWithinData($res);
    }

    public function orderedTotal(Request $request, OrderService $orderService): JsonResponse
    {
        $params = $request->all();
        /*$res = Cache::get('ordered_total', []);*/
        $res = $orderService->totalOrdered($params);
        /*if (empty($res)) {
            $res = $orderService->totalOrdered($params);
            Cache::put('ordered_total', $res, now()->addMinutes(10));
        }*/

        return resSuccessWithinData($res);
    }

    public function listUserOrdered(): JsonResponse
    {
        // Logic mới lấy theo nhân viên trong phòng ban
        $departmentRepo = App::make('App\Models\Department');
        $userRepo = App::make('App\Models\User');

        $departmentQuote = $departmentRepo->where('type', DepartmentConstant::TYPE_ORDER)->first();

        $users = $userRepo->where('department_id', $departmentQuote->id)
                        ->where('status', 1)
                        ->where('reset_staff_allow', 1)
                        ->get();
        return resSuccessWithinData($users);
    }

    public function getStaffListByType(Request $request): JsonResponse
    {
        try {
            $type = $request->get("type", null);
            if (!$type) {
                return resError("The type field is required");
            }
            return resSuccessWithinData($this->_service->getStaffByDepartmentTypes([$type]));
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return resError($exception->getMessage());
        }
    }

    public function removeConversationIdCurrent(Request $request)
    {
        // try {
        //     \DB::beginTransaction();
        //     $userOnline = \Auth::user();
        //     $userOnline->conversation_id_current = 0;
        //     $userOnline->save();
        //     \DB::commit();
        //     return resSuccess();
        // } catch (Exception $e) {
        //     \DB::rollback();
        //     throw $e;
        // }

        $user_id = (int) \Auth::id();
        User::where('id', $user_id)
                ->where('conversation_id_current', '>', 0)
                ->update([
                    'conversation_id_current' => 0,
                ]);
        return resSuccess();
    }

    public function reSyncCountBagAndStatus(Request $request)
    {
        $origin_code      = $request->get("origin_code", null);
        $order_package_id = $request->get("order_package_id", null);

        if (is_null($origin_code) || is_null($order_package_id)) {
            return;
        }

        $job = new \App\Jobs\CN\SyncCountBagAndStatus($origin_code, $order_package_id);
        dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
    }
}
