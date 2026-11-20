<?php

namespace App\Http\Controllers;

use App\Constants\CustomerConstant;
use App\Constants\NotifyConstant;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Helpers\PaginateHelper;
use App\Http\Requests\ChargeMoneyRequest;
use App\Http\Requests\CustomerChangeStaffToMultipleRequest;
use App\Http\Requests\CustomerChangeStatusRequest;
use App\Http\Requests\CustomerOfferRequest;
use App\Http\Requests\CustomerUpdateSomethingRequest;
use App\Http\Resources\Customer\CustomerOfferEndowResource;
use App\Http\Resources\Customer\CustomerResource;
use App\Http\Resources\Note\NoteResource;
use App\Http\Resources\PaginateJsonResource;
use App\Interfaces\Validation\StoreValidationInterface;
use App\Interfaces\Validation\UpdateValidationInterface;
use App\Jobs\SentSmsToCustomerJob;
use App\Models\Bank;
use App\Models\LogPing;
use App\Models\Customer;
use App\Models\CustomerConfirmClose;
use App\Models\CustomerLabel;
use App\Models\CustomerOffer;
use App\Models\Label;
use App\Models\Order;
use App\Models\SmsLog;
use App\Models\User;
use App\Models\LogAutoPay;
use App\Services\ActivityService;
use App\Services\ConfigService;
use App\Services\Customer\ChangeStaffRequestService;
use App\Services\CustomerConfirmCloseService;
use App\Services\CustomerLogEndowService;
use App\Services\CustomerService;
use App\Services\FcmService;
use App\Services\FcmTokenService;
use App\Services\ReportService;
use App\Services\SmsService;
use App\Services\SmsZaLoService;
use App\Services\SyncChargeAppService;
use App\Services\UserService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Exports\CustomerExport;
use App\Jobs\ExportExcelJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Database\Eloquent\Model;
use App\Exports\CustomerTransactionExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\CustomerNote;
use App\Models\CustomerLogEndow;
use App\Jobs\ExportCustomerV2;
use App\Http\Requests\ChargeFromAppRequest;
use App\Http\Resources\ListResource;
use App\Jobs\Order\UpdateOrderPercentJob;
use Illuminate\Support\Facades\Hash;
use App\Constants\ViaConstant;

use App\Exports\CustomerDebtExport;



/**
 * Class CustomerController
 * @package App\Http\Controllers
 */
class CustomerController extends Controller implements StoreValidationInterface, UpdateValidationInterface
{
    protected $activityService;
    protected $changeStaffRequestService;
    protected $syncChargeAppService;

    public function __construct(
        CustomerService $service,
        ActivityService $activityService,
        ChangeStaffRequestService $changeStaffRequestService,
        SyncChargeAppService $syncChargeAppService
    )
    {
        $this->_service        = $service;
        $this->activityService = $activityService;
        $this->changeStaffRequestService = $changeStaffRequestService;
        $this->syncChargeAppService = $syncChargeAppService;
    }
    public function pagination(): JsonResponse
    {
        $request = request();
        if (!empty($request->is_close_customer)) {
            $customerService = app()->make(CustomerService::class);
            $customerService->closeCustomer();
        }
        return $this->_service->pagination(PaginateHelper::getPerPage());
    }
    public function storeMessage(): array
    {
        return [];
    }

    public function storeRequest(): array
    {
        return [
            'name' => 'required|max:255',
            'phone_number' => 'required|max:13|unique:customers',
            'email' => 'required|max:255|email|unique:customers',
            'password' => 'required|max:255',
            'bod' => 'nullable|date_format:Y-m-d',
            'warehouse_id' => 'required|max:255|exists:warehouses,id',
            'address' => 'nullable|string|max:255',
            'staff_care_id' => 'nullable|exists:users,id',
            'staff_counselor_id' => 'nullable|exists:users,id',
            'staff_group_id' => 'nullable|exists:users,id',
            'service' => 'required|in:0,1',
            'facebook_url' => 'url|max:255',
            'skype_url' => 'url|max:255',
            'customer_introduce_id' => 'nullable|exists:customers,code',
            'discount_by_kg' => 'nullable|numeric|min:0',
            'discount_by_m3' => 'nullable|numeric|min:0',
        ];
    }

    public function updateMessage(): array
    {
        return [
            'service.required' => 'Vui lòng chọn dịch vụ',
            'service.in' => 'Vui lòng chọn dịch vụ',
            'source.in' => 'Vui lòng chọn nguồn',
        ];
    }

    /**
     * @param  string  $id
     * @return array
     */
    public function updateRequest(string $id): array
    {
        return [
            'name' => 'required|max:255',
            'phone_number' => [
                'required',
                'max:13',
                // Rule::unique('customers')->ignore($id),
            ],
            'email' => [
                'required',
                'max:255',
                'email',
                Rule::unique('customers')->ignore($id),
            ],
            'password' => 'nullable|max:255',
            'bod' => 'nullable|date_format:Y-m-d',
            'warehouse_id' => 'required|max:255|exists:warehouses,id',
            'address' => 'nullable|string|max:255',
            'staff_care_id' => 'nullable|exists:users,id',
            // 'staff_counselor_id' => 'nullable|exists:users,id',
            'staff_group_id' => 'nullable|exists:users,id',
            'service' => 'required|in:0,1',
            'source' => 'required|in:1,2',
            'facebook_url' => 'url|max:255',
            'skype_url' => 'url|max:255',
            'customer_introduce_id' => 'nullable|exists:customers,code',
            'discount_by_kg' => 'nullable|numeric|min:0',
            'discount_by_m3' => 'nullable|numeric|min:0',
        ];
    }

    public function update(string $id): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__, $id);

        // Validate thêm
        $params = request()->all();
        $result_check = $this->checkPhoneNumber($id, $params);
        if(!is_null($result_check)) {
            return res422($result_check);
        }

        return resSuccessWithinData($this->_service->update($id, request()->all()));
    }

    public function checkPhoneNumber($id, $data)
    {
        $customerModel = \App::make('App\Models\Customer');
        $customerUpdate = $customerModel->where('id', $id)->first();

        // Nếu đổi sdt khác số điện thoại của nó thì check xem có ai trùng không
        if ($customerUpdate->phone_number != $data['phone_number']) {
            $customerDiff = $customerModel->where('phone_number', $data['phone_number'])
                                          ->where('id', '<>', $id)
                                          ->first();
            if (!is_null($customerDiff)) {
                return 'Số điện thoại đã bị trùng, không để cập nhật';
            }
        }

        // Nếu toàn bộ các số tk khác trùng số này mà hủy kích hoạt thì cho qua, nếu không báo lỗi
        $customerDiffs = $customerModel->where('phone_number', $data['phone_number'])
                                    ->where('id', '<>', $id)
                                    ->get();
        if (count($customerDiffs) > 0) {
            $canNext = true;
            foreach ($customerDiffs as $key => $customerDiff) {
                if ($customerDiff->status == 1) {
                    $canNext = false;
                    break;
                }
            }

            if ($canNext == false) {
                return 'Số điện thoại đã bị trùng';
            }
        }
        return null;
    }

    /**
     * @return JsonResponse
     */
    public function changeStaffRequests(Request $request): JsonResponse
    {
        return $this->changeStaffRequestService->changeStaffRequests($request->all());
    }

    /**
     * @return JsonResponse
     */
    public function deleteChangeStaffRequests($id): JsonResponse
    {
        return $this->changeStaffRequestService->deleteChangeStaffRequests($id);
    }

    /**
     * @return JsonResponse
     */
    public function updateChangeStaffRequests(Request $request, $id): JsonResponse
    {
        return $this->changeStaffRequestService->updateChangeStaffRequests($request->all(), $id);
    }

    /**
     * @return JsonResponse
     */
    public function reports(): JsonResponse
    {
        return resSuccessWithinData((new ReportService())->reports(Customer::class));
    }

    /**
     * @param  CustomerUpdateSomethingRequest  $request
     * @param  Customer  $customer
     * @return JsonResponse
     */
    public function updateSomething(CustomerUpdateSomethingRequest $request, Customer $customer): JsonResponse
    {
        return $this->prepareUpdate($customer);
    }

    /**
     * @param  CustomerChangeStatusRequest  $request
     * @param  Customer  $customer
     * @return JsonResponse
     */
    public function changeStatus(CustomerChangeStatusRequest $request, Customer $customer): JsonResponse
    {
        return $this->prepareUpdate($customer);
    }

    /**
     * @param  Customer  $customer
     * @return JsonResponse
     */
    private function prepareUpdate(Customer $customer): JsonResponse
    {
        try {
            DB::beginTransaction();
            $params = request()->all();
            $staffGroupId = isset($params['staff_group_id']) ? $params['staff_group_id'] : null;
            if($staffGroupId && $this->_service->checkPerStaffGroupId()) {
                return resError('Bạn không có quyền thay đổi nhân viên quản lý', 403);
            }
            if($staffGroupId && $staffGroupId != $customer->staff_group_id) {
                $queryCustomer = Customer::query();
                $existCustomer = $this->_service->getCustomerWaitingForCare($queryCustomer)->where('id', $customer->id)->first();
                if($existCustomer) {
                    // Không được tự gán NV kinh doanh ở màn KH chờ chăm sóc
                    $result = ['err' => 'Khách hàng này thuộc phân loại Chờ chăm sóc. Vui lòng gán nhãn hợp lệ !'];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
                // Tự động gán lại nếu nó gán lại staff_care_id,staff_counselor_id
                $this->_service->updateStaffLogic($customer, $params);

                // Tự động gán lại hội thoại cho người mới
                $this->updateConversationLogic($customer, $params);

                // Thêm noti khi cập nhật staff_care_id,staff_counselor_id
                $this->_service->updateNotiLogic($customer, $params);
                $params['staff_group_id'] = $staffGroupId;
                $params['staff_group_time_assign'] = date('Y-m-d H:i:s');
            }
            if (!empty($params['customer_reason_inactive_id'])) {
                if (
                    CustomerConfirmClose::query()
                        ->where('customer_id', $customer->id)
                        ->whereIn('status', [
                            CustomerConfirmClose::STATUS_WAITING_CONFIRM,
                            CustomerConfirmClose::STATUS_CONFIRMED
                        ])
                        ->exists()
                ) {
                    return resError('Tài khoản đã được tạo yêu cầu Đóng tài khoản!', 500);
                }
                $payload = [
                    'customer_id' => $customer->id,
                    'user_create_id' => Auth::user()->id,
                    'note_id' => $params['customer_reason_inactive_id'],
                ];
                $customerConfirmCloseService = app()->make(CustomerConfirmCloseService::class);
                $customerConfirmCloseService->store($payload);
                DB::commit();
                return resSuccess();
            }

            $oldStatus = $customer->status;
            $oldVia = $customer->via;
            $oldBusinessType = $customer->business_type;
            $status = isset($params['status']) ? $params['status'] :  null;
            if ($status == 1) {
                $customer->customer_reason_inactive_id = null;
            }
            $customer->update($params);
            $this->logOpenAccount($customer, $oldStatus, $customer->status);
            // Log thay đổi via / business_type
            $this->logUpdateViaAndBusinessType(
                $customer,
                $oldVia,
                $oldBusinessType
            );
            DB::commit();
            return resSuccessWithinData($customer);
        } catch (\Exception $e) {
            DB::rollback();
            return resError($e->getMessage());
        }
    }

    public function logOpenAccount(Customer $customer, int $oldStatus, int $newStatus): void
    {
        if ($oldStatus == CustomerConstant::STATUS_INACTIVE && $newStatus == CustomerConstant::STATUS_ACTIVE) {
            $user = getCurrentUser();

            $content = sprintf('%s đã mở tài khoản.', $user->name ?? 'Người dùng không xác định');

            $dataActivities = [[
                'subject_type'    => get_class($customer),
                'subject_id'      => $customer->id,
                'causer_type'     => get_class($user),
                'causer_id'       => $user->id,
                'log_name'        => 'customer_log',
                'content'         => $content,
                'object_id'       => $customer->id,
                'properties'      => null,
                'organization_id' => $user->organization_id ?? getOrganization(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]];

            (new ActivityService())->setCustomerLogs($dataActivities);
        }
    }

    public function logUpdateViaAndBusinessType(
        Customer $customer,
        $oldVia,
        $oldBusinessType
    ): void {
        $user = getCurrentUser();
        $map = [
            'via' => [
                'old'     => $oldVia,
                'new'     => $customer->via,
                'labels'  => ViaConstant::STATUSES,
                'message' => '%s đã đổi nguồn tương tác từ %s sang %s',
            ],
            'business_type' => [
                'old'     => $oldBusinessType,
                'new'     => $customer->business_type,
                'labels'  => CustomerConstant::CUSTOMER_BUSINESS_TYPE,
                'message' => '%s đã đổi loại hình từ %s sang %s',
            ],
        ];
        $logs = [];
        foreach ($map as $item) {
            if ($item['old'] == $item['new']) {
                continue;
            }
            $logs[] = $this->buildCustomerLog(
                $customer,
                $user,
                sprintf(
                    $item['message'],
                    $user->name ?? '',
                    $item['labels'][$item['old']] ?? $item['old'] ?? '—',
                    $item['labels'][$item['new']] ?? $item['new'] ?? '—'
                )
            );
        }
        if ($logs) {
            (new ActivityService())->setCustomerLogs($logs);
        }
    }
    private function buildCustomerLog(Customer $customer, $user, string $content): array
    {
        return [
            'subject_type'    => get_class($customer),
            'subject_id'      => $customer->id,
            'causer_type'     => get_class($user),
            'causer_id'       => $user->id,
            'log_name'        => 'customer_log',
            'content'         => $content,
            'object_id'       => $customer->id,
            'properties'      => null,
            'organization_id' => $user->organization_id ?? getOrganization(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ];
    }

    public function updateConversationLogic($customer, $params)
    {
        // Nếu có đổi staff_group_id thì mới xử lý
        if (isset($params['staff_group_id'])) {
            if ($params['staff_group_id'] != $customer->staff_group_id) {
                $conversationModel = \App::make('App\Models\Conversation');
                $userModel = \App::make('App\Models\User');
                $conversationDBs = $conversationModel->where('customer_id', (int) $customer->id)
                                                    ->get();
                if (count($conversationDBs)) {
                    foreach ($conversationDBs as $key => $conversationDB) {
                        // Nhân viên
                        $userDB = $userModel->where('id', $params['staff_group_id'])->first();
                        $userOldDB = $userModel->where('id', $conversationDB->user_id)->first();

                        // Lưu thông tin
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
            }
        }
    }


    /**
     * @param  CustomerChangeStaffToMultipleRequest  $request
     * @return JsonResponse
     */
    public function changeStaff(CustomerChangeStaffToMultipleRequest $request): JsonResponse
    {
        try {
            \DB::beginTransaction();

            $customers = Customer::query()->whereIn('id', $request->input('customers'))->get();
            if ($customers) {
                $params  = request()->all();
                foreach ($customers as $key => $customer) {
                    $staffGroupId             = isset($params['staff_group_id']) ? $params['staff_group_id'] : null;

                    if($staffGroupId && $staffGroupId != $customer->staff_group_id) {
                        // Tự động gán lại nếu nó gán lại staff_care_id,staff_counselor_id
                        $this->_service->updateStaffLogic($customer, $params);

                        // Tự động gán lại hội thoại cho người mới
                        $this->updateConversationLogic($customer, $params);

                        $customer->staff_group_id = $staffGroupId;
                        $customer->staff_group_time_assign = date('Y-m-d H:i:s');
                        $customer->save();
                    }

                }
            }

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    /**
     * detail: show detail of customer
     * @param  string  $id
     * @return JsonResponse
     */
    public function detail(string $id): JsonResponse
    {
        $customer = Customer::query()->with([
                'staffGroup' => function($a){
                    return $a->select("id", "name");
                },
                'deliveryDefault',
                'deliveryDefault.ward',
                'deliveryDefault.district',
                'deliveryDefault.province',
            ])->findOrFail($id);
        return resSuccessWithinData(new CustomerResource($customer));
    }

    public function updateOffer(CustomerOfferRequest $request, string $id = null): JsonResponse
    {
        DB::beginTransaction();
        try {
            $customer = CustomerOffer::query()->findOrFail($id);
            $customerId = data_get($customer, 'customer_id');
            $offerRate = data_get($customer, 'offer_rate');
            $consignmentRate = data_get($customer, 'consignment_rate');
            $depositRate = data_get($customer, 'deposit_rate');
            $levelRate = data_get($customer, 'level_rate');
            $transWeightHnFee = data_get($customer, 'trans_weight_hn_fee');
            $transWeightHcmFee = data_get($customer, 'trans_weight_hcm_fee');
            $transVolumnHnFee = data_get($customer, 'trans_volumn_hn_fee');
            $transVolumnHcmFee = data_get($customer, 'trans_volumn_hcm_fee');

            $cn_transWeightHnFee = data_get($customer, 'cn_trans_weight_hn_fee');
            $cn_transWeightHcmFee = data_get($customer, 'cn_trans_weight_hcm_fee');
            $cn_transVolumnHnFee = data_get($customer, 'cn_trans_volumn_hn_fee');
            $cn_transVolumnHcmFee = data_get($customer, 'cn_trans_volumn_hcm_fee');

            $debt = data_get($customer, 'debt');
            $data = $request->all();
            $paramOfferRate = data_get($data, 'offer_rate') ? data_get($data, 'offer_rate') : 0;
            $fcmTokenService = app()->make(FcmTokenService::class);
            if($offerRate != data_get($data, 'offer_rate')) {
                $this->_service->createLogEndow($customerId, $offerRate, $paramOfferRate, 'offer_rate');
                $this->setContentCustomerOfferSentNotificationToApp(
                    $customerId, $offerRate, data_get($data, 'offer_rate'), 'offer_rate', $fcmTokenService
                );
                dispatch(new UpdateOrderPercentJob($customerId, $paramOfferRate))->onQueue('export-1800');
            }
            if($depositRate != data_get($data, 'deposit_rate')) {
                $paramDepositRate = data_get($data, 'deposit_rate') ? data_get($data, 'deposit_rate') : 0;
                $this->setContentCustomerOfferSentNotificationToApp(
                    $customerId, $depositRate, data_get($data, 'deposit_rate'), 'deposit_rate', $fcmTokenService
                );
                $this->_service->createLogEndow($customerId, $depositRate, $paramDepositRate, 'deposit_rate');
            }
            /* % đặt cọc đơn ký gửi */
            if($consignmentRate != data_get($data, 'consignment_rate')) {
                $paramConsignmentRate = data_get($data, 'consignment_rate')
                    ? data_get($data, 'consignment_rate')
                    : 0;
                $this->_service->createLogEndow($customerId, $depositRate, $paramConsignmentRate, 'consignment_rate');
            }
            if($levelRate != data_get($data, 'level_rate')) {
                $paramLevelRate = data_get($data, 'level_rate') ? data_get($data, 'level_rate') : 0;
                if(!data_get($data, 'level_rate')) {
                    //update lại
                    $customerLevelReCalculate = $this->_service->updateLevelCustomer($customerId, true);
                    $customerOffer = CustomerOffer::query()->findOrFail($id);
                    $levelRateOld = $customerOffer->level_rate;
                    //update lại level user cho đơn hàng chờ xử lý, chờ đặt cọc
                    // Nếu level khác level rate thì mới chạy

                    if($levelRateOld != $customerLevelReCalculate) {
                        $offerRate = $paramOfferRate;
                        dispatch(new UpdateOrderPercentJob($customerId, $offerRate))->onQueue('export-1800');
                    }
                } else {
                    $customerLevelCurrent = $this->_service->updateLevelCustomer($customerId);
                    if($paramLevelRate < $customerLevelCurrent) {
                        return resError(
                            'Không thể cập nhật level thấp hơn level hiện tại. Level của khách hàng đang là ' . $customerLevelCurrent
                        );
                    }
                    Customer::query()->where('id', $customerId)->update(['level' => $customerLevelCurrent]);
                }
                $this->_service->createLogEndow($customerId, $levelRate, $paramLevelRate, 'level_rate');
                $this->setContentCustomerOfferSentNotificationToApp(
                    $customerId, $levelRate, $paramLevelRate, 'level_rate', $fcmTokenService
                );
            }
            if($transWeightHnFee != data_get($data, 'trans_weight_hn_fee')) {
                $paramWeightHn = data_get($data, 'trans_weight_hn_fee') ? data_get($data, 'trans_weight_hn_fee') : 0;
                $this->_service->createLogEndow($customerId, $transWeightHnFee, $paramWeightHn, 'trans_weight_hn_fee');
                $this->setContentCustomerOfferSentNotificationToApp(
                    $customerId, $transWeightHnFee, $paramWeightHn, 'trans_weight_hn_fee', $fcmTokenService
                );
            }
            if($transWeightHcmFee != data_get($data, 'trans_weight_hcm_fee')) {
                $paramWeightHcm = data_get($data, 'trans_weight_hcm_fee') ? data_get($data, 'trans_weight_hcm_fee') : 0;
                $this->_service->createLogEndow($customerId, $transWeightHcmFee, $paramWeightHcm, 'trans_weight_hcm_fee');
                $this->setContentCustomerOfferSentNotificationToApp(
                    $customerId, $transWeightHcmFee, $paramWeightHcm, 'trans_weight_hcm_fee', $fcmTokenService
                );
            }
            if($transVolumnHnFee != data_get($data, 'trans_volumn_hn_fee')) {
                $paramVolumnHn = data_get($data, 'trans_volumn_hn_fee') ? data_get($data, 'trans_volumn_hn_fee') : 0;
                $this->_service->createLogEndow($customerId, $transVolumnHnFee, $paramVolumnHn, 'trans_volumn_hn_fee');
                $this->setContentCustomerOfferSentNotificationToApp(
                    $customerId, $transVolumnHnFee, $paramVolumnHn, 'trans_volumn_hn_fee', $fcmTokenService
                );
            }
            if($transVolumnHcmFee != data_get($data, 'trans_volumn_hcm_fee')) {
                $paramVolumnHcm = data_get($data, 'trans_volumn_hcm_fee') ? data_get($data, 'trans_volumn_hcm_fee') : 0;
                $this->_service->createLogEndow($customerId, $transVolumnHcmFee, $paramVolumnHcm, 'trans_volumn_hcm_fee');
                $this->setContentCustomerOfferSentNotificationToApp(
                    $customerId, $transVolumnHcmFee, $paramVolumnHcm, 'trans_volumn_hcm_fee', $fcmTokenService
                );
            }
            if($debt != data_get($data, 'debt')) {
                $this->_service->createLogEndow($customerId, $debt, data_get($data, 'debt'), 'debt');
            }

            // Phần bên Chính ngạch
            if($cn_transWeightHnFee != data_get($data, 'cn_trans_weight_hn_fee')) {
                $paramWeightHn = data_get($data, 'cn_trans_weight_hn_fee') ? data_get($data, 'cn_trans_weight_hn_fee') : 0;
                $this->_service->createLogEndow($customerId, $cn_transWeightHnFee, $paramWeightHn, 'cn_trans_weight_hn_fee');
                $this->setContentCustomerOfferSentNotificationToApp(
                    $customerId, $cn_transWeightHnFee, $paramWeightHn, 'cn_trans_weight_hn_fee', $fcmTokenService
                );
            }
            if($cn_transWeightHcmFee != data_get($data, 'cn_trans_weight_hcm_fee')) {
                $paramWeightHcm = data_get($data, 'cn_trans_weight_hcm_fee') ? data_get($data, 'cn_trans_weight_hcm_fee') : 0;
                $this->_service->createLogEndow($customerId, $cn_transWeightHcmFee, $paramWeightHcm, 'cn_trans_weight_hcm_fee');
                $this->setContentCustomerOfferSentNotificationToApp(
                    $customerId, $cn_transWeightHcmFee, $paramWeightHcm, 'cn_trans_weight_hcm_fee', $fcmTokenService
                );
            }
            if($cn_transVolumnHnFee != data_get($data, 'cn_trans_volumn_hn_fee')) {
                $paramVolumnHn = data_get($data, 'cn_trans_volumn_hn_fee') ? data_get($data, 'cn_trans_volumn_hn_fee') : 0;
                $this->_service->createLogEndow($customerId, $cn_transVolumnHnFee, $paramVolumnHn, 'cn_trans_volumn_hn_fee');
                $this->setContentCustomerOfferSentNotificationToApp(
                    $customerId, $cn_transVolumnHnFee, $paramVolumnHn, 'cn_trans_volumn_hn_fee', $fcmTokenService
                );
            }
            if($cn_transVolumnHcmFee != data_get($data, 'cn_trans_volumn_hcm_fee')) {
                $paramVolumnHcm = data_get($data, 'cn_trans_volumn_hcm_fee') ? data_get($data, 'cn_trans_volumn_hcm_fee') : 0;
                $this->_service->createLogEndow($customerId, $cn_transVolumnHcmFee, $paramVolumnHcm, 'cn_trans_volumn_hcm_fee');
                $this->setContentCustomerOfferSentNotificationToApp(
                    $customerId, $cn_transVolumnHcmFee, $paramVolumnHcm, 'cn_trans_volumn_hcm_fee', $fcmTokenService
                );
            }

            $this->updateNotiForUpdateOffer($customer, $data);
            $offer = $customer->update($data);
            $customerOffer = CustomerOffer::query()->findOrFail($id);
            if(
                $customerOffer->trans_weight_hn_fee > 0
                || $customerOffer->trans_weight_hcm_fee > 0
                || $customerOffer->trans_weight_hp_fee > 0
                || $customerOffer->trans_volumn_hn_fee > 0
                || $customerOffer->trans_volumn_hcm_fee > 0
                || $customerOffer->trans_volumn_hp_fee > 0

                || $customerOffer->cn_trans_weight_hn_fee > 0
                || $customerOffer->cn_trans_weight_hcm_fee > 0
                || $customerOffer->cn_trans_volumn_hn_fee > 0
                || $customerOffer->cn_trans_volumn_hcm_fee > 0
            ) {
                Customer::query()->where('id', $customerOffer->customer_id)->update(['is_endow_ship' => 1]);
            } else {
                Customer::query()->where('id', $customerOffer->customer_id)->update(['is_endow_ship' => 0]);
            }

            DB::commit();
            return resSuccessWithinData($offer);
        } catch(\Exception $e) {
            DB::rollBack();
            return resError($e->getMessage());
        }
    }

    /**
     * @throws BindingResolutionException
     */
    private function setContentCustomerOfferSentNotificationToApp($customerId, $valueOld, $valueNew, $type, $fcmTokenService)
    {
        $payload['user_id'] = $customerId;
        $valueOld = $valueOld ?? 0;
        $valueNew = $valueNew ?? 0;
        $title = 'Thay đổi ưu đãi khách hàng';
        switch ($type) {
            case 'deposit_rate':
                if ($valueOld != 0 && $valueNew != 0) {
                    $body = '% Đặt cọc được thay đổi từ ' . $valueOld . '% sang ' . $valueNew . '%';
                } else if ($valueOld != 0 && $valueNew == 0) {
                    $body = 'Ưu đãi Đặt cọc ' . $valueOld . '% đã bị hủy';
                } else if ($valueOld == 0 && $valueNew != 0) {
                    $body = 'Thêm mới ưu đãi Đặt cọc ' . $valueNew . '%';
                } else {
                    $body = '';
                }
                break;
            case 'level_rate':
                if ($valueOld != 0 && $valueNew != 0) {
                    $body = 'Cấp độ được thay đổi từ ' . $valueOld . ' sang ' . $valueNew;
                } else if ($valueOld != 0 && $valueNew == 0) {
                    $body = 'Cấp độ ' . $valueOld . ' đã bị hủy';
                } else if ($valueOld == 0 && $valueNew != 0) {
                    $body = 'Thêm mới Cấp độ ' . $valueNew;
                } else {
                    $body = '';
                }
                break;
            case 'trans_weight_hn_fee':
                if ($valueOld != 0 && $valueNew != 0) {
                    $body = 'Phí vận chuyển HN (/kg) thay đổi từ ' . number_format($valueOld) . 'vnd sang ' . number_format($valueNew) . 'vnd';
                } else if ($valueOld != 0 && $valueNew == 0) {
                    $body = 'Phí vận chuyển HN (/kg) ' . number_format($valueOld) . ' đã bị hủy';
                } else if ($valueOld == 0 && $valueNew != 0) {
                    $body = 'Thêm mới Phí vận chuyển HN (/kg) ' . number_format($valueNew);
                } else {
                    $body = '';
                }
                break;
            case 'trans_weight_hcm_fee':
                if ($valueOld != 0 && $valueNew != 0) {
                    $body = 'Phí vận chuyển HCM (/kg) thay đổi từ ' . number_format($valueOld) . 'vnd sang ' . number_format($valueNew) . 'vnd';
                } else if ($valueOld != 0 && $valueNew == 0) {
                    $body = 'Phí vận chuyển HCM (/kg) ' . number_format($valueOld) . ' đã bị hủy';
                } else if ($valueOld == 0 && $valueNew != 0) {
                    $body = 'Thêm mới Phí vận chuyển HCM (/kg) ' . number_format($valueNew);
                } else {
                    $body = '';
                }
                break;
            case 'trans_volumn_hn_fee':
                if ($valueOld != 0 && $valueNew != 0) {
                    $body = 'Phí vận chuyển HN (/m3) thay đổi từ ' . number_format($valueOld) . 'vnd sang ' . number_format($valueNew) . 'vnd';
                } else if ($valueOld != 0 && $valueNew == 0) {
                    $body = 'Phí vận chuyển HN (/m3) ' . number_format($valueOld) . ' đã bị hủy';
                } else if ($valueOld == 0 && $valueNew != 0) {
                    $body = 'Thêm mới Phí vận chuyển HN (/m3) ' . number_format($valueNew);
                } else {
                    $body = '';
                }
                break;
            case 'trans_volumn_hcm_fee':
                if ($valueOld != 0 && $valueNew != 0) {
                    $body = 'Phí vận chuyển HCM (/m3) thay đổi từ ' . number_format($valueOld) . 'vnd sang ' . number_format($valueNew) . 'vnd';
                } else if ($valueOld != 0 && $valueNew == 0) {
                    $body = 'Phí vận chuyển HCM (/m3) ' . number_format($valueOld) . ' đã bị hủy';
                } else if ($valueOld == 0 && $valueNew != 0) {
                    $body = 'Thêm mới Phí vận chuyển HCM (/m3) ' . number_format($valueNew);
                } else {
                    $body = '';
                }
                break;
            case 'offer_rate':
                if ($valueOld != 0 && $valueNew != 0) {
                    $body = '% Đặt hàng được thay đổi từ ' . $valueOld . '% sang ' . $valueNew . '%';
                } else if ($valueOld != 0 && $valueNew == 0) {
                    $body = 'Ưu đãi Đặt hàng ' . $valueOld . '% đã bị hủy';
                } else if ($valueOld == 0 && $valueNew != 0) {
                    $body = 'Thêm mới ưu đãi Đặt hàng ' . $valueNew . '%';
                } else {
                    $body = '';
                }
                break;
            default:
                $body = '';
        }
        if ($body) {
            $dataSent = [
                'type' => 'customer_offers',
                'body' => 'Thay đổi ưu đãi khách hàng'
            ];
            Log::info('setContentCustomerOfferSentNotificationToApp TYPE : ' . $type . ' BODY ' . $body);
            $fcmTokenService->sendMessageForApp($payload, $title, $body, $dataSent);
        }
    }

    private function updateNotiForUpdateOffer($customer, $request)
    {
        $notificationService = \App::make('App\Services\NotificationService');

        $data = [
            'group'           => 9,
            'name'            => 9001,
            'time_create'     => time(),
            'customer_id'     => $customer->customer_id,
            'status'          => 1
        ];

        if($customer->deposit_rate != $request['deposit_rate']) {
            $data_deposit_rate['deposit_rate'] = [
                'old' => $customer->deposit_rate,
                'new' => $request['deposit_rate']
            ];
            $data['data'] = json_encode($data_deposit_rate);
            $notificationService->store($data);
        }

        if($customer->offer_rate != $request['offer_rate']) {
            $data_offer_rate['offer_rate'] = [
                'old' => $customer->offer_rate,
                'new' => $request['offer_rate']
            ];
            $data['data'] = json_encode($data_offer_rate);
            $notificationService->store($data);
        }

        if($customer->trans_volumn_hcm_fee != $request['trans_volumn_hcm_fee']) {
            $data_trans_volumn_hcm_fee['trans_volumn_hcm_fee'] = [
                'old' => $customer->trans_volumn_hcm_fee,
                'new' => $request['trans_volumn_hcm_fee']
            ];
            $data['data'] = json_encode($data_trans_volumn_hcm_fee);
            $notificationService->store($data);
        }

        if($customer->trans_volumn_hn_fee != $request['trans_volumn_hn_fee']) {
            $data_trans_volumn_hn_fee['trans_volumn_hn_fee'] = [
                'old' => $customer->trans_volumn_hn_fee,
                'new' => $request['trans_volumn_hn_fee']
            ];
            $data['data'] = json_encode($data_trans_volumn_hn_fee);
            $notificationService->store($data);
        }

        if($customer->trans_volumn_hp_fee != $request['trans_volumn_hp_fee']) {
            $data_trans_volumn_hp_fee['trans_volumn_hp_fee'] = [
                'old' => $customer->trans_volumn_hp_fee,
                'new' => $request['trans_volumn_hp_fee']
            ];
            $data['data'] = json_encode($data_trans_volumn_hp_fee);
            $notificationService->store($data);
        }
        if($customer->trans_weight_hcm_fee != $request['trans_weight_hcm_fee']) {
            $data_trans_weight_hcm_fee['trans_weight_hcm_fee'] = [
                'old' => $customer->trans_weight_hcm_fee,
                'new' => $request['trans_weight_hcm_fee']
            ];
            $data['data'] = json_encode($data_trans_weight_hcm_fee);
            $notificationService->store($data);
        }
        if($customer->trans_weight_hn_fee != $request['trans_weight_hn_fee']) {
            $data_trans_weight_hn_fee['trans_weight_hn_fee'] = [
                'old' => $customer->trans_weight_hn_fee,
                'new' => $request['trans_weight_hn_fee']
            ];
            $data['data'] = json_encode($data_trans_weight_hn_fee);
            $notificationService->store($data);
        }
        if($customer->trans_weight_hp_fee != $request['trans_weight_hp_fee']) {
            $data_trans_weight_hp_fee['trans_weight_hp_fee'] = [
                'old' => $customer->trans_weight_hp_fee,
                'new' => $request['trans_weight_hp_fee']
            ];
            $data['data'] = json_encode($data_trans_weight_hp_fee);
            $notificationService->store($data);
        }
    }

    public function createOffer(CustomerOfferRequest $request): JsonResponse
    {
        try {
            $params = $data = $request->all();
            $customerId = data_get($params, 'customer_id');
            if(data_get($params, 'offer_rate')) {
                $paramOfferRate = data_get($data, 'offer_rate') ? data_get($data, 'offer_rate') : 0;
                $this->_service->createLogEndow($customerId, 0, $paramOfferRate, 'offer_rate');
                dispatch(new UpdateOrderPercentJob($customerId, $paramOfferRate))->onQueue('export-1800');
            }
            if(data_get($params, 'deposit_rate')) {
                $paramDepositRate = data_get($data, 'deposit_rate') ? data_get($data, 'deposit_rate') : 0;
                $this->_service->createLogEndow($customerId, 0, $paramDepositRate, 'deposit_rate');
            }
            if(data_get($params, 'level_rate')) {
                $paramLevelRate = data_get($data, 'level_rate') ? data_get($data, 'level_rate') : 0;
                $this->_service->createLogEndow($customerId, 0, $paramLevelRate, 'level_rate');
            }
            if(data_get($params, 'trans_weight_hn_fee')) {
                $paramWeightHn = data_get($data, 'trans_weight_hn_fee') ? data_get($data, 'trans_weight_hn_fee') : 0;
                $this->_service->createLogEndow($customerId, 0, $paramWeightHn, 'trans_weight_hn_fee');
            }
            if(data_get($params, 'trans_weight_hcm_fee')) {
                $paramWeightHcm = data_get($data, 'trans_weight_hcm_fee') ? data_get($data, 'trans_weight_hn_fee') : 0;
                $this->_service->createLogEndow($customerId, 0, $paramWeightHcm, 'trans_weight_hcm_fee');
            }
            if(data_get($params, 'trans_volumn_hn_fee')) {
                $paramVolumnHn = data_get($data, 'trans_volumn_hn_fee') ? data_get($data, 'trans_volumn_hn_fee') : 0;
                $this->_service->createLogEndow($customerId, 0, $paramVolumnHn, 'trans_volumn_hn_fee');
            }
            if(data_get($params, 'trans_volumn_hcm_fee')) {
                $paramVolumnHcm = data_get($data, 'trans_volumn_hcm_fee') ? data_get($data, 'trans_volumn_hcm_fee') : 0;
                $this->_service->createLogEndow($customerId, 0, $paramVolumnHcm, 'trans_volumn_hcm_fee');
            }
            if(data_get($params, 'debt')) {
                $this->_service->createLogEndow($customerId, 0, data_get($params, 'debt'), 'debt');
            }
            $offer = CustomerOffer::query()->create($request->all());

            return resSuccessWithinData($offer);
        } catch (\Exception $e) {
            return resError();
        }
    }

    public function getHistoryChangeStaff(Request $request, $customer_id)
    {
        $data = $request->all();
        $per_page = $request->get('per_page', 25);

        $activityModel = \App::make('App\Models\Activity');

        $model  = $activityModel;

        $model = $model->where('subject_id', $customer_id);

        $model = $model->where('log_name', 'customer_staff_change_log');
        $model = $model->orderBy('created_at', 'desc');
        $activity = $model->paginate($per_page);

        $_paginateResource = PaginateJsonResource::class;
        $_resource = \App\Http\Resources\Activity\ActivityTrashResource::class;
        return resSuccessWithinData(new $_paginateResource($activity, $_resource));
    }

    public function getCustomer()
    {
        $data = request()->all();

        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('code', $data['customer_search'])
                                ->first();

        if ($customer) {
            $result = [
                'customer_has'  => 1,
                'customer_code' => $customer->code,
                'customer_name' => $customer->name,
                'customer_id' => $customer->id,
            ];
            return resSuccessWithinData($result);
        }

        $result = [
            'customer_has'  => 0,
            'customer_code' => "",
            'customer_name' => "",
            'customer_id' => "",
        ];
        return resSuccessWithinData($result);
    }

    public function getCustomerWithCondition()
    {
        $data = request()->all();

        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('code', $data['customer_search'])
            ->orWhere('phone_number', $data['customer_search'])
            ->first();
        if($customer == null) {
            return resError("Khách hàng không tồn tại");
        }

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
    }

    public function updateCustomerCareCategory(Request $request)
    {
        $validationRules = [
            'customer_id'       => 'required|exists:customers,id',
            'arr_category_id'   => 'nullable|array',
            'arr_category_id.*' => 'nullable|exists:categories,id',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $data = $request->all();
        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('id', $data['customer_id'])->where('status', 1)->first();

        $customer->customer_categories()->sync($data['arr_category_id']);

        return resSuccess();
    }

    public function countCustomerWaithAssign(Request $request)
    {
        $customerRepo = \App::make('App\Models\Customer');
        $customerCareCount = $customerRepo->whereNull('staff_care_id')
                                    ->where('reset_staff', 'counselor')
                                    ->count();

        $customerCounselorCount = $customerRepo->whereNull('staff_counselor_id')
                                    ->where('reset_staff', 'care')
                                    ->count();

        $data_result = [
            'customerCareCount'      => $customerCareCount,
            'customerCounselorCount' => $customerCounselorCount,
        ];

        return resSuccessWithinData($data_result);
    }

    public function debt(Request $request)
    {
        $params = $request->all();
        $per_page = !empty($params['per_page']) ? $params['per_page'] : 15;

        $query = $this->buildDebtQuery($params);


        $totalBalance = DB::query()
            ->fromSub($query, 'sub')
            ->sum('balance_amount');

        $customers = $query
            ->groupBy('customers.id')
            ->orderBy('report_customers.balance_amount')
            ->paginate($per_page);

        $data = $customers->toArray();
        $data['total_balance_amount'] = $totalBalance;

        return resSuccessWithinData($data);
    }


    private function buildDebtQuery($params)
    {
        $timeCheck = null;
        if (!empty($params['time_blance_negative_last'])) {
            $time = $params['time_blance_negative_last'];
            $now = time();

            if ($time == 1) {
                $timeCheck = [
                    'start' => date('Y-m-d H:i:s', $now - 7 * 86400),
                    'end' => date('Y-m-d H:i:s', $now),
                ];
            } elseif ($time == 2) {
                $timeCheck = [
                    'start' => date('Y-m-d H:i:s', $now - 14 * 86400),
                    'end' => date('Y-m-d H:i:s', $now - 7 * 86400),
                ];
            } elseif ($time == 3) {
                $timeCheck = [
                    'start' => date('Y-m-d H:i:s', $now - 30 * 86400),
                    'end' => date('Y-m-d H:i:s', $now - 14 * 86400),
                ];
            } elseif ($time == 4) {
                $timeCheck = [
                    'start' => date('Y-m-d H:i:s', $now - 3650 * 86400),
                    'end' => date('Y-m-d H:i:s', $now - 30 * 86400),
                ];
            }
        }

        return Customer::select([
            'customers.id as customer_id',
            'customers.code as customer_code',
            'customers.name as customer_name',
            'users.name as user_name',
            'report_customers.balance_amount',
            DB::raw('SUM(orders.order_cost + orders.order_fee - orders.deposit_cost) AS order_missing'),
            DB::raw('SUM(order_supplier.international_shipping_cost) AS total_shipping_cost'),
            DB::raw('SUM(orders.order_cost + orders.order_fee - orders.deposit_cost + order_supplier.international_shipping_cost) AS total_amount')
        ])
            ->leftJoin('users', function ($join) {
                $join->on('users.id', '=', 'customers.staff_group_id');
            })
            ->join('report_customers', 'report_customers.customer_id', 'customers.id')
            ->leftJoin('orders', function($join){
                $join->on('orders.customer_id', '=', 'customers.id')
                    ->whereIn('orders.status', [
                        OrderConstant::KEY_STATUS_DEPOSITED,
                        OrderConstant::KEY_STATUS_WAIT_TO_PAY,
                        OrderConstant::KEY_STATUS_ORDERED,
                        OrderConstant::KEY_STATUS_RECONFIRM
                    ]);
            })
            ->leftJoin('order_supplier', 'order_supplier.order_id', 'orders.id')

            ->when(!empty($params['code']), fn($q) => $q->where('customers.code', $params['code']))
            ->when(!empty($params['phone']), fn($q) => $q->where('customers.phone_number', $params['phone']))
            ->when(!empty($params['name_manager']), fn($q) => $q->where('users.name', 'like', '%' . $params['name_manager'] . '%'))
            ->when(!empty($params['warehouse_id']), fn($q) => $q->where('customers.warehouse_id', $params['warehouse_id']))

            ->when(!empty($timeCheck), function($q) use ($timeCheck){
                $q->whereDate('report_customers.blance_negative_last', '<', $timeCheck['end'])
                    ->whereDate('report_customers.blance_negative_last', '>=', $timeCheck['start']);
            })

            ->where('report_customers.balance_amount', '<', -5000)
            ->groupBy('customers.id');
    }

    public function exportDebt(Request $request)
    {
        try {
            $params = $request->all();

            $data = $this->buildDebtQuery($params)
                ->orderBy('report_customers.balance_amount')
                ->get();

            return Excel::download(new CustomerDebtExport($data), 'debt.xlsx');

        } catch (\Exception $e) {
            \Log::error($e);
            return resError();
        }
    }

    public function forceDelete()
    {
        try {
            Artisan::call('customer-trash:delete');

            return resSuccess();
        } catch (\Exception $e) {
           return resError($e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $params = $request->all();
        try {
            $time = Carbon::now()->format('YmdHms');
            $fileName = getPathFileExport('customer').'/customer_'.$time.'.xlsx';
            dispatch(new ExportCustomerV2($params, \Auth::user(), $fileName))->onQueue('export-1800');
            \Log::info("Nhân viên có id= ".getCurrentUserId()." đã click xuất excel khách hàng");
            return resSuccess();
        } catch (\Exception $e) {
            \Log::info($e);
            return resError();
        }
    }

    public function exportTransaction($customerId, Request $request){
        try {
            $params = $request->all();
            $data = $this->_service->exportTransaction($customerId, $params);
            return Excel::download(new CustomerTransactionExport( $data), 'customer_transaction.xlsx');
        } catch (\Exception $e) {
            \Log::info($e);
            return resError();
        }
    }

    public function checkPush(Request $request)
    {
        try {
            $userIds  = $request->input('user_id');
            $dataPush = $request->only('title', 'body', 'data');

            return (new FcmService())->sendNotifyUser($userIds, $dataPush);
        } catch (\Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function note(Request $request)
    {
        try {
            $customer_id = $request->input('customer_id');
            $content = $request->input('content');
            $type = $request->input('type');

            if ($type == null) {
                $type = CustomerNote::CUSTOMER_NOTE_TYPE_2; // Mặc định là của bên màn đơn hàng
            }
            CustomerNote::create([
                'customer_id' => $customer_id,
                'content' => $content,
                'user_id' => getCurrentUserId(),
                'type' => $type
            ]);
            return resSuccess();
        } catch (\Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function getNote(Request $request)
    {
        try {
            $customer_id = $request->get('customer_id');
            if (!$customer_id) {
                return resSuccessWithinData([]);
            }

            return resSuccessWithinData(new ListResource($this->_service->getNoteByCustomer($customer_id), NoteResource::class));
        } catch (\Exception $e) {
            return resErrorWithinData([], $e->getMessage());
        }
    }

    public function deleteCustomerNote(Request $request, $customer_note_id): JsonResponse
    {
        $this->_service->deleteCustomerNote($customer_note_id);
        return resSuccess();
    }

    public function moveVND($content)
    {
        // + VND 895,000\nTai khoan Thanh toan: 2082229999\nSo du: VND 18,927,754\nHV 3014917
        $content = str_replace(" VND ","", $content);
        return $content;
    }

    public function addSpace($text)
    {
        // Cái mã nó dính liền thì phải
        // Regex: HV + (6 số) hoặc (5 ký tự chữ/số)
        $pattern = '/\bHV(\d{6}|[A-Za-z0-9]{5})\b/i';
        // Thay bằng "HV " + phần sau
        $result = preg_replace($pattern, 'HV $1', $text);

        return $result;
    }

    public function movePhay($input)
    {
        // NamABank: TK 0876161488 nop 35,000,000VND luc 16:42 19/09/2025. So du 622,044,581VND.ND: hv 306595, ma gd 100000013566179
        $output = preg_replace('/(\d{6}|[A-Za-z0-9]{5}),/', '$1 ', $input);
        return $output;
    }

    public function changeHz($input)
    {
        // NamABank: TK 0876161488 nop 1,000,000VND luc 09:39 17/09/2025. So du 402,833,834VND.ND: mbvcb.10955854221.5260bftvg2bhvkmy.hv 146118.ct tu 0561000561000
        return preg_replace_callback(
            '/\.[A-Za-z0-9]*hv[A-Za-z0-9]*\./',
            function ($matches) {
                return str_replace("hv", "hz", $matches[0]);
            },
            $input
        );
    }

    public function changeHZZ($input)
    {
        // VietinBank:15\/12\/2025 13:39|TK:62655868|GD:+10,000,000VND|SDC:230,180,309VND|ND:CT DEN:126T25C0PGHVUWYD MBVCB.12156197323.331825.HV 072063.CT tu 101508950~
        return preg_replace_callback(
            '/(CT DEN:)(\S*HV\S*)/',
            function ($matches) {
                return $matches[1] . str_replace('HV', 'hz', $matches[2]);
            },
            $input
        );
    }

    /**
     * @param ChargeFromAppRequest $request
     * @return JsonResponse
     */
    public function chargeFromApp(ChargeFromAppRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $content = $request->input('content');
            $content = $this->moveVND($content);
            $content = $this->addSpace($content);
            $content = $this->movePhay($content);
            $content = $this->changeHz($content);
            $content = $this->changeHZZ($content);
            $bankName = $request->input('bank_name');
            $statusSync = CustomerConstant::STATUS_PENDING;
            $transactionCode = $this->_service->getTransactionCode($content);
            list($codeFromMsg, $amount, $bankChargeCode, $chargeContent) = $this->getCodeAmountFromMsg($content);
            $bank = $this->_service->getBankByShortName($bankName);

            $fundService = \App::make('App\Services\FundService');
            $bank_number = $fundService->getBankNumberInContent($content);
            if (is_null($bank_number)) {
                $msg = 'Không tìm thấy số tài khoản';
                $dataSync = $this->getDataSync($bank, $amount, $content, $statusSync, $msg);
                return resSuccessWithinData($dataSync, $msg);
            }

            if (!$codeFromMsg || !$bankChargeCode || !$amount) {
                $msg = 'Cú pháp nạp không đúng';
                $this->_service->saveFundTransaction($amount, null, $bank, $content);
                $dataSync = $this->getDataSync($bank, $amount, $content, $statusSync, $msg);
                return resSuccessWithinData($dataSync, $msg);
            }

            $this->_service->saveHistory($transactionCode, $chargeContent, $content);
            if (!$codeFromMsg) {
                $msg = 'Cú pháp nạp không đúng';
                $this->_service->saveFundTransaction($amount, null, $bank, $content);
                $dataSync = $this->getDataSync($bank, $amount, $content, $statusSync, $msg);
                return resSuccessWithinData($dataSync, $msg);
            }

            $userByCode = $this->_service->getUserByCode($codeFromMsg);
            if (!$userByCode) {
                $msg = 'Không tìm thấy khách hàng '. $codeFromMsg;
                $this->_service->saveFundTransaction($amount, $userByCode, $bank, $content);
                $dataSync = $this->getDataSync($bank, $amount, $content, $statusSync, $msg);
                return resSuccessWithinData($dataSync, $msg);
            }
            if (!$bank) {
                $msg = 'Không tìm thấy ngân hàng : '. $bankName;
                $this->_service->saveFundTransaction($amount, $userByCode, $bank, $content);
                $dataSync = $this->getDataSync($bank, $amount, $content, $statusSync, $msg);
                return resSuccessWithinData($dataSync, $msg);
            }

            // $this->_service->chargeFromApp($amount, $userByCode, $bank, $content);
            // Logic ngày 14-01-2025: Chuyển phần nạp tiền vào queue để chạy, không chạy trực tiếp lệnh trên
            $job = new \App\Jobs\Deposit\ChargeApp($amount, $userByCode, $bank, $content);
            dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');

            $dataSync = $this->getDataSync($bank, $amount, $content, CustomerConstant::STATUS_SUCCESS,
                'Nạp tiền thành công');
            $config = env('SEND_SMS_ORDER_PACKAGE', '');
            if ($config == 'PRODUCT') {
                $phone_number = $userByCode->phone_number ?? 0;
                $phone_number = convertPhoneNumber($phone_number);
                $user_name = $userByCode->name ?? '';
                $params = [
                    "phone_number" => $phone_number,
                    "template_id" => SmsLog::ZALO_TEMPLATE_ID_NAP_TIEN_THANH_CONG,
                    "message" => "Xin chào $user_name. Chúng tôi đã ghi nhận quý khách đã nạp tiền thành công",
                    "template_data" => [
                        "price" => $amount,
                        "name" => $user_name,
                        "ten_khach_hang" => $user_name,
                        "payment" => 'Chuyển khoản',
                        "ngay_giao_dich" => date('d/m/Y'),
                        "ma_don_hang" => $codeFromMsg,
                    ]
                ];
                $response = (new SmsZaLoService($params))
                    ->getAccessToken()
                    ->validate()
                    ->sent()
                    ->getResult();
                if ($response['code'] == 500) {
                    // Log::info('SMS ZaLo Error Nạp Tiền Thành Công ' . $response['message'] . $phone_number);
                    $array = [
                        'phone_number' => $userByCode->phone_number ?? 0,
                        'user_code' => $userByCode->code ?? '',
                        'log_type' => SmsLog::LOG_TYPE_SMS_RECHARGE,
                        'message' => 'QK vua nap thanh cong ' . number_format($amount) .'VND vao TK. Dang nhap xem chi tiet. Cu phap nap tien: HV '.$userByCode->code.' , Xin cam on!'
                    ];
                    $jobs = new SentSmsToCustomerJob($array);
                    dispatch($jobs)->onQueue('sent-sms-customer');
                } else {
                    // Log::info('SMS ZaLo Success Nạp Tiền Thành Công!' . $phone_number);
                    SmsLog::query()->create([
                        'phone_number' => $phone_number,
                        'content' => $params['message'],
                        'user_code' => $userByCode->code ?? '',
                        'sms_source' => SmsLog::SMS_SOURCE_ZALO,
                        'log_type' => SmsLog::LOG_TYPE_SMS_RECHARGE,
                        'response_status' => 0
                    ]);
                }
            }
            return resSuccessWithinData($dataSync, "Nạp tiền tài khoản ". $codeFromMsg ." thành công");
        } catch (\Exception $exception) {
            Log::info('Nạp tiền thất bại: '. $exception->getMessage());
            $dataSync = $this->getDataSync($bank, $amount, $content, CustomerConstant::STATUS_FAIL,
                $exception->getMessage());
            return resSuccessWithinData($dataSync, "Nạp tiền thất bại!");
        }
    }

    private function getDataSync(Bank $bank = null, $amount = null, $content, $status, $note = 'Fail')
    {
        return [
            'bank_id'         => $bank->bank_id ?? null,
            'money'           => $amount ?? null,
            'content_message' => $content,
            'status'          => $status,
            'note'            => $note
        ];
    }

    private function syncDataCharge(Bank $bank = null, $amount = null, $content, $status, $note = 'Fail')
    {
        try {
            $dataSync = [
                'bank_id'         => $bank->bank_id ?? null,
                'money'           => $amount ?? null,
                'content_message' => $content,
                'status'          => $status,
                'note'            => $note
            ];
            //dd($dataSync, 111);
            $this->syncChargeAppService->sync($dataSync);
        } catch (\Exception $e) {
            //dd($e->getMessage());
        }
    }

    protected function getCodeAmountFromMsg(string $msg) {
        $code           = '';
        $amount         = 0;
        $bankChargeCode = '';
        $chargeContent  = '';

        if (!$msg) {
            return [$code, $amount, $bankChargeCode, $chargeContent];
        }

        $chargeQRPrefix = $this->_service->getListChargePrefix();
        if ($chargeQRPrefix->isEmpty()) {
            return [$code, $amount, $bankChargeCode, $chargeContent];
        }

        $amount        = $this->_service->getAmountCharge($msg);
        $chargeContent = $this->_service->getChargeContent($msg, $chargeQRPrefix);
        //dd($amount, $chargeContent);

        $arrMsg = explode(' ', $chargeContent);
        if (!count($arrMsg)) {
            return [$code, $amount, $bankChargeCode, $chargeContent];
        }

        $chargeQRPrefixArr = $chargeQRPrefix->toArray();
        if (!in_array($arrMsg[0], $chargeQRPrefixArr) && !in_array(strtoupper($arrMsg[0]), $chargeQRPrefixArr)) {
            return [$code, $amount, $bankChargeCode, $chargeContent];
        }


        $bankChargeCode = strtoupper($arrMsg[0]);
        $code           = !empty($arrMsg[1]) ? $arrMsg[1] : '';

        return [$code, $amount, $bankChargeCode, $chargeContent];
    }

    /**
     * @param ChargeFromAppRequest $request
     * @return JsonResponse
     */
    public function chargeMoney(ChargeMoneyRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $data    = $request->only('content', 'amount', 'customer_id');

            $customer = Customer::findOrFail($data['customer_id']);

            $this->_service->chargeMoney($data, $customer);

            return resSuccess("Nạp tiền thành công");
        } catch (\Exception $exception) {
            //dd($exception->getMessage(), $exception->getFile(), $exception->getLine());
            return resError("Nạp tiền không thành công");
        }

    }

    public function smsZaLoKienHangVeVietNam(Request $request, CustomerService $customerService): JsonResponse
    {
        $customer_id = $request->customer_id ?? 0;
        $params['customer_id'] = $customer_id;
        $customerService->smsZaLoKienHangVeVietNam($params);
        return resSuccess();
    }

    /**
     * @return JsonResponse
     * @throws \Throwable
     */
    public function getByLevel(): JsonResponse
    {
        $this->throwAction(__FUNCTION__);
        return $this->_service->pagination(PaginateHelper::getPerPage());
    }

    public function initTotalOrders()
    {
        try {
            $this->_service->initTotalOrders();

            return resSuccess();
        } catch (\Exception $exception) {
            //dd($exception->getMessage(), $exception->getFile(), $exception->getLine());
            return resError($exception->getMessage());
        }
    }

    public function initTotalOrdersV2()
    {
        try {
            $this->_service->initTotalOrdersV2();

            return resSuccess();
        } catch (\Exception $exception) {
            //dd($exception->getMessage(), $exception->getFile(), $exception->getLine());
            return resError($exception->getMessage());
        }
    }

    public function initTotalPackagesV2()
    {
        try {
            $this->_service->initTotalPackagesV2();

            return resSuccess();
        } catch (\Exception $exception) {
            //dd($exception->getMessage(), $exception->getFile(), $exception->getLine());
            return resError($exception->getMessage());
        }
    }

    public function initTotalPackagesOfOrderV2()
    {
        try {
            $this->_service->initTotalPackagesOfOrderV2();

            return resSuccess();
        } catch (\Exception $exception) {
            //dd($exception->getMessage(), $exception->getFile(), $exception->getLine());
            return resError($exception->getMessage());
        }
    }

    public function withTrashed()
    {
        return $this->_service->withTrashed();
    }

    public function updateLabel(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'label_id' => 'required|array',
                'customer_id' => 'required|array'
            ]);
            $customers = $request->input('customer_id');
            $labelIds = $request->input('label_id');

            if(count($labelIds) > 2) {
                return resError('Không thể chọn nhiều hơn 2 nhãn');
            }
            $check = false;
            if(count($labelIds) == 2) {
                $labelIdSave = Label::query()->whereIn('id', $labelIds)->pluck('code')->toArray();
                $labelAccept1 = [
                    Label::ORDERED,
                    Label::CONSIGNMENT_ORDERED
                ]; // Mảng đã lên đơn và đã lên đơn ký gửi
                $labelAccept2 = [
                    Label::DA_DAT_HANG,
                    Label::CONSIGNMENT_ORDERED
                ]; // Mảng đã đặt hàng và đã lên đơn ký gửi

                $diff11 = array_diff($labelIdSave, $labelAccept1);
                $diff12 = array_diff($labelAccept1, $labelIdSave);

                $diff21 = array_diff($labelIdSave, $labelAccept2);
                $diff22 = array_diff($labelAccept2, $labelIdSave);

                if((empty($diff11) && empty($diff12)) || (empty($diff21) && empty($diff22))) {
                    $check = true;
                }
                if(!$check) {
                    return resError('Chỉ được phép lưu 1 nhãn');
                }
            }
            if(count($labelIds) == 1 || $check) {
                foreach ($customers as $customer) {
                    CustomerLabel::query()->where('customer_id', $customer)->delete();
                    foreach ($labelIds as $labelId) {
                        $labelCheck = Label::query()->where('id', $labelId)->first();
                        if(data_get($labelCheck, 'code') == 'DANG_FOLLOW') {
                            $queryCustomer = Customer::query();
                            $existCustomer = $this->_service->getCustomerWaitingForCare($queryCustomer)->where('id', $customer)->first();
                            if($existCustomer) {
                                $existCustomer->update([
                                    'staff_group_id' => getCurrentUserId(),
                                    'staff_group_time_assign' => date('Y-m-d H:i:s')
                                ]);
                            }
                        }
                        CustomerLabel::query()->create(
                            ['customer_id' => $customer, 'label_id' => $labelId],
                        );

                        $params['label_id'] = $labelId;
                        $customerUpdate = Customer::query()->where('id', $customer)->first();
                        $customerUpdate->update($params);
                    }
                }
                $checkAutoCloseAccount = Label::whereIn('code', [
                    'KHONG_CO_NHU_CAU',
                    'MAT_HANG_KHONG_NHAP_DUOC',
                    'KHONG_TUONG_TAC_DUOC'
                ])->whereIn('id', $labelIds)->count();
                if($checkAutoCloseAccount > 0) {
                    Artisan::call("customer:auto-close-account-long-time-order");
                }
            }
            DB::commit();
            return resSuccess();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function logOffer($customerId, CustomerLogEndowService $customerLogEndowService): JsonResponse
    {
        $params = request()->all();
        $params['customer_id'] = $customerId;
        $results = $customerLogEndowService->index($params);
        return resSuccessWithinData($results);
    }

    public function getSupplier(Request $request)
    {
        try {
            $customerId  = $request->input('customer_id');

            $data = $this->_service->getSupplierByCustomer($customerId);
            return resSuccessWithinData($data);
        } catch (\Exception $e) {
            return resErrorWithinData([], $e->getMessage());
        }
    }

    public function initCountOrder()
    {
        dispatch(new \App\Jobs\ReportCustomer\UpdateCountOrder())->onQueue('export-1800');
        return resSuccess();
    }

    public function updateIsEndowShip()
    {
        CustomerOffer::query()
        ->chunk(100, function ($customerOffers) {
            foreach($customerOffers as $customerOffer) {
                if(
                    $customerOffer->trans_weight_hn_fee > 0
                || $customerOffer->trans_weight_hcm_fee > 0
                || $customerOffer->trans_weight_hp_fee > 0
                || $customerOffer->trans_volumn_hn_fee > 0
                || $customerOffer->trans_volumn_hcm_fee > 0
                || $customerOffer->trans_volumn_hp_fee > 0)
                {
                    Customer::query()->where('id', $customerOffer->customer_id)->update(['is_endow_ship' => 1]);
                }
            }
        });

        return true;

    }

    public function updateCustomerLabel()
    {
        Customer::query()->whereNotNull('label_id')->chunk(1000, function ($customers) {
            foreach ($customers as $customer) {
                CustomerLabel::query()->updateOrCreate([
                    'customer_id' => $customer->id,
                    'label_id'    => $customer->label_id,
                ]);
            }
        });
        return true;
    }

    public function assignStaff(Request $request)
    {
        try {
            DB::beginTransaction();
            $params = $request->only('staff_group_id', 'phone_number');
            $data = $this->_service->assignStaff($params);
            DB::commit();

            return resSuccess($data);
        } catch (\Exception $e) {
            DB::rollBack();
            return resError($e->getMessage(), $e->getCode());
        }
    }

    public function assignAllStaff(Request $request): JsonResponse
    {
        $validationRules = [
            'staff_group_id_old' => 'required|exists:customers,id',
            'staff_group_id_new'   => 'required|array',
            'staff_group_id_new.*' => 'required|exists:customers,id',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        try {
            DB::beginTransaction();
            $params = $request->only('staff_group_id_old', 'staff_group_id_new');
            $this->_service->assignAllStaff($params);
            DB::commit();

            return resSuccess('Đang trong tiến trình xử lý chuyển khách hàng cho nhân viên mới');
        } catch (\Exception $e) {
            DB::rollBack();
            return resError($e->getMessage(), $e->getCode());
        }
    }
    public function countCustomerByStaff(Request $request): JsonResponse
    {
        try {
            $params = $request->only('staff_group_id');
            $count = Customer::query()->where('staff_group_id', $params['staff_group_id'])->count();

            return resSuccessWithinData($count);
        } catch (\Exception $e) {
            DB::rollBack();
            return resError($e->getMessage(), $e->getCode());
        }
    }

    public function runCommandCustomerLongTime(): JsonResponse
    {
        try {
            Artisan::call('update:staff-group-id-by-customer-longtime');

            return resSuccess('OK');
        } catch (\Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function syncLastOrderAtByOrder(): JsonResponse
    {
        try {
            $customers = Customer::query()
                ->whereHas('order', function($q){
                    $q->whereColumn('created_at', '>' , 'customers.last_order_at');
                })->with('order')
                ->limit(1000)
                ->get();
            foreach($customers as $customer) {
                $timeOrderMax = $customer->order->max('created_at');
                $customer->last_order_at = $timeOrderMax;
                $customer->save();
            }
            return resSuccess('OK');
        } catch (\Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function listCustomerConfirmClose(
        Request $request,
        CustomerConfirmCloseService $customerConfirmCloseService
    ): JsonResponse
    {
        $params = $request->all();
        $results = $customerConfirmCloseService->index($params);
        return resSuccessWithinData($results);
    }

    public function updateStatusCustomerConfirmClose(
        Request $request,
        CustomerConfirmCloseService $customerConfirmCloseService,
        $id
    ): JsonResponse
    {
        DB::beginTransaction();
        try {
            $customerConfirmCloseService->update($id, $request->all());
            DB::commit();
            return resSuccess();
        } catch (\Exception $exception) {
            DB::rollBack();
            return resError($exception->getMessage());
        }
    }

    public function updateStatusCustomerConfirmCloseMany(
        Request $request,
        CustomerConfirmCloseService $customerConfirmCloseService
    ): JsonResponse
    {
        DB::beginTransaction();
        try {
            if (empty($request->ids)) {
                return resError('Không có bản ghi nào được chọn!');
            }
            if (empty($request->status) || !in_array($request->status, [
                    CustomerConfirmClose::STATUS_CONFIRMED,
                    CustomerConfirmClose::STATUS_CANCEL
                ])) {
                return resError('Trạng thái không hợp lệ!');
            }
            $customerConfirmCloseService->updateCloseMany($request->all());
            DB::commit();
            return resSuccess();
        } catch (\Exception $exception) {
            DB::rollBack();
            return resError($exception->getMessage());
        }
    }

    public function changePasswordByPhoneNumber(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string|exists:customers,phone_number',
            'password' => 'required|string|max:255'
        ]);
        $phoneNumber = $request->input('phone_number');
        $password = $request->input('password');
        $customer = Customer::where('phone_number', $phoneNumber)->first();
        $customer->password = Hash::make($password);
        $customer->save();
        return resSuccess();
    }

    public function cancelOrderWaitingDeposit3Month(Request $request)
    {
        $customerService = \App::make('App\Services\CustomerService');
        $customerService->getOrderWaitingDeposit3Month();
    }

    public function cancelOrderWaitingDeposit3MonthV2(Request $request)
    {
        $arr_code = [
            '3197400001',
            '2991780026',
            '2417620267',
            '1378750009',
            '3150690003',
            '0366520114',
            '2991780027',
            '3087990002',
            '3197460001',
            '2901590010',
            '3166710045',
            '1017280126',
            '3104500204',
            '3176770010',
            '3176770009',
            '3147900053',
            '1160580317',
            '2844970027',
            '3078390121',
            '3197400002',
            '3078390120',
            '0068890054',
            '0068890053',
            '3027020005',
            '3078390124',
            '3078390122',
        ];

        foreach ($arr_code as $key => $code) {
            $this->cancelOrderWaitingDeposit3MonthAction($code);
        }
    }

    public function cancelOrderWaitingDeposit3MonthAction($code)
    {
        try {
            \DB::beginTransaction();
            $orderModel = \App::make('App\Models\Order');
            $orderDB = $orderModel->where('code', $code)->first();

            if ($orderDB->status == OrderConstant::KEY_STATUS_WAITING_DEPOSIT) {
                \Log::info('cancelOrderWaitingDeposit3MonthAction: run: ' . (int) $orderDB->id . '-' . $code);
                // Cập nhật
                $content = 'Đơn hàng bị hủy tự động do đã ở trạng thái Chờ đặt cọc quá 3 tháng..';
                $orderDB->status = OrderConstant::KEY_STATUS_CANCEL;
                $orderDB->reason_cancel = $content;
                $orderDB->staff_cancel_id = 1;
                $orderDB->save();
            }

            \DB::commit();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function waitingForCare()
    {
        return $this->_service->waitingForCare(PaginateHelper::getPerPage());
    }

    public function countWaitingForCare()
    {
        $query = Customer::query();
        $count = $this->_service->getCustomerWaitingForCare($query)->count();
        return resSuccessWithinData($count);
    }

    public function runJobtWaitingForCare()
    {
        $job = new \App\Jobs\Customer\UpdateStaffGroupForCustomerWaitingCareJob();
        dispatch($job)->onQueue('export-1800');
        return resSuccess();
    }

    public function runJobAutoCloseAccount()
    {
        Artisan::call("customer:auto-close-account-long-time-order");
        return resSuccess();
    }

    public function runJobSyncLastOrderAtPack()
    {
        $job = new \App\Jobs\Customer\SyncLastOrderAtPackJob();
        dispatch($job)->onQueue('export-1800');
        return resSuccess();
    }

    public function runJobUpdateStaffGroupLongTime()
    {
        Artisan::call("update:staff-group-id-by-customer-longtime");
        return resSuccess();
    }

    public function pushMoney9Pay(Request $request)
    {
        $request->validate([
            'account_number' => 'required|in:MB969000383242,MB969000383677',
            'amount'         => 'required|numeric|min:1',
            'customer_code'  => 'required|string|max:20',
            'id_pay'         => 'required|string|max:50',
            'content'        => 'required|string|max:255',
            'date_pay'       => 'required',
            'token'          => 'required'
        ]);

        $params = $request->all();

        // \Log::info('pushMoney9Pay: ' . $params['content']);

        $date = new \DateTime($params['date_pay']);
        $date->setTimezone(new \DateTimeZone('Asia/Ho_Chi_Minh'));
        $params['date_pay'] = $date->format('Y-m-d H:i:s');

        $token = $params['token'];
        if ($token != env('TOKEN_9PAY', 'Myh2817xn7116x9112')) {
            return res422('Token không hợp lệ');
        }

        $dataPush = $this->validatePushMoney9Pay($params);

        if ($dataPush['message'] != null) {
            return res422($dataPush['message']);
        }

        try {
            if ($dataPush['userByCode'] == null) {
                    $this->_service->push9PayActionNull($params['amount'], $dataPush['userByCode'], $params['account_number'], $dataPush['id_pay'], $dataPush['content']);
            } else {
                $this->_service->push9PayAction($params['amount'], $dataPush['userByCode'], $params['account_number'], $dataPush['id_pay']);
            }

            // Gửi tin nhắn
            $this->sentSMS($dataPush['userByCode'], $params['amount']);

            return resSuccessWithinData([
                'status' => 'Thành công'
            ]);
        } catch (Exception $e) {

        }
    }

    public function sentSMS($userByCode, $amount)
    {
        $config = env('SEND_SMS_ORDER_PACKAGE', '');
        if ($config == 'PRODUCT') {
            $phone_number = $userByCode->phone_number ?? 0;
            if ($phone_number == 0) {
                \Log::info('Không có số điện thoại, đá luôn');
                return;
            }
            $phone_number = convertPhoneNumber($phone_number);
            $user_name = $userByCode->name ?? '';
            $params = [
                "phone_number" => $phone_number,
                "template_id" => SmsLog::ZALO_TEMPLATE_ID_NAP_TIEN_THANH_CONG,
                "message" => "Xin chào $user_name. Chúng tôi đã ghi nhận quý khách đã nạp tiền thành công !!!",
                "template_data" => [
                    "price" => $amount,
                    "name" => $user_name,
                    "ten_khach_hang" => $user_name,
                    "payment" => 'Chuyển khoản',
                    "ngay_giao_dich" => date('d/m/Y'),
                    "ma_don_hang" => $userByCode->code,
                ]
            ];
            // \Log::info('sentSMS start');
            $response = (new SmsZaLoService($params))
                ->getAccessToken()
                ->validate()
                ->sent()
                ->getResult();
            // \Log::info('sentSMS end');
            if ($response['code'] == 500) {
                Log::info('Zalo lỗi: ' . $response['message'] . $phone_number);
                $array = [
                    'phone_number' => $userByCode->phone_number ?? 0,
                    'user_code' => $userByCode->code ?? '',
                    'log_type' => SmsLog::LOG_TYPE_SMS_RECHARGE,
                    'message' => 'QK vua nap thanh cong ' . number_format($amount) .'VND vao TK. Dang nhap xem chi tiet. Cu phap nap tien: HV '.$userByCode->code.' , Xin cam on !!!'
                ];
                $jobs = new SentSmsToCustomerJob($array);
                dispatch($jobs)->onQueue('sent-sms-customer');
            } else {
                Log::info('Zalo thành công: ' . $phone_number);
                SmsLog::query()->create([
                    'phone_number' => $phone_number,
                    'content' => $params['message'],
                    'user_code' => $userByCode->code ?? '',
                    'sms_source' => SmsLog::SMS_SOURCE_ZALO,
                    'log_type' => SmsLog::LOG_TYPE_SMS_RECHARGE,
                    'response_status' => 0
                ]);
            }
        }
    }

    public function moveLower($str)
    {
        // hv041G2
        $result = preg_replace('/^hv([A-Za-z0-9]+)$/', 'HV $1', $str);
        return $result;
    }

    public function moveHVDich($input)
    {
        // MBVCB.13669216431.602456.hv 086327.CT tu 0021000444121 CAO THI GIANG toi 96311300015448636 HV DICH V
        $input = str_ireplace('HV DICH', '', $input);
        $input = trim($input);
        return $input;
    }

    public function moveHVDoubleSpace($str)
    {
        // MBVCB.13684997505.833358.HV  168969.CT tu 0931004185682 NGUYEN VAN LAM toi 96311300015449099 TDG DIC
        $str = preg_replace('/\s+/', ' ', $str);
        return $str;
    }

    public function moveNameStick($str)
    {
        // HV 5376C Trace 628710
        $str = str_replace('Trace', 'aTrace', $str);
        return $str;
    }


    public function validatePushMoney9Pay($params)
    {
        $id_pay = 0;
        $message = null;
        $data_log_auto_push            = $params;
        $data_log_auto_push['status']  = 0;
        $data_log_auto_push['from_ip'] = request()->ip();


        // Tự động lấy code của khách hàng
        $content = $params['content'];
        $content = $this->moveVND($content);
        $content = $this->addSpace($content);
        $content = $this->movePhay($content);
        $content = $this->changeHz($content);
        $content = $this->changeHZZ($content);
        $content = $this->moveLower($content);
        $content = $this->moveHVDich($content);
        $content = $this->moveHVDoubleSpace($content);
        $content = $this->moveNameStick($content);
        $content = 'T ' . $content;
        list($codeFromMsg, $amount, $bankChargeCode, $chargeContent) = $this->getCodeAmountFromMsg($content);
        $data_log_auto_push['customer_code'] = $codeFromMsg;
        $params['customer_code'] = $codeFromMsg;

        $logAutoPush = LogAutoPay::query()->create($data_log_auto_push);

        $id_pay  = (int) $logAutoPush->id;
        $message = null;

        $userByCode = $this->_service->getUserByCode($params['customer_code']);

        // Check xem có id_pay bị trùng không
        $autoPayDB = LogAutoPay::query()->where('id_pay', $params['id_pay'])->where('status', 1)->first();
        if (!is_null($autoPayDB)) {
            $message = 'ID Pay đã bị trùng';
        }

        $contentSave = 'Tài khoản ' . $params['account_number'] . ' nhận số tiền ' . number_format($params['amount'],0,",",".") . ' lúc: ' . $params['date_pay'] .   ' với nội dung: ' . $params['content'];

        return [
            'id_pay'  => $id_pay,
            'message' => $message,
            'userByCode' => $userByCode,
            'content' => $contentSave,
        ];
    }

    public function pushLog9Pay(Request $request)
    {
        $request->validate([
            'token' => 'required'
        ]);

        $params = $request->all();

        $token = $params['token'];
        if ($token != env('TOKEN_9PAY', 'Myh2817xn7116x9112')) {
            return res422('Token không hợp lệ');
        }

        // Lấy ra log cuối cùng:
        $re_scan = false;
        $lastLog = LogPing::latest('id')->first();
        if ($lastLog && $lastLog->user_id > 0) {
            $re_scan = true;
        }

        $data_log = [
            'source' => LogPing::SOURCE_9PAY,
            'type' => LogPing::TYPE_LOG,
            'time' => date('Y-m-d H:i')
        ];

        $logPingDB = LogPing::query()->create($data_log);

        return resSuccessWithinData([
            're_scan' => $re_scan
        ]);
    }

    public function getByPhoneNumber(Request $request)
    {
        $validationRules = [
            'phone_number'       => 'required',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $phoneNumber = $request->input('phone_number');
        $customer = Customer::query()->where('phone_number', $phoneNumber)->first();
        $weightFee = 0;
        $volumnFee = 0;
        if($customer) {
            $offer = $customer->offer;
            $warehouse = $customer->warehouse;
            if($offer && $warehouse) {
                // 28 - ID tỉnh Hà Nội
                if($warehouse->province_id == 28) {
                    $weightFee = $offer->cn_trans_weight_hn_fee;
                    $volumnFee = $offer->cn_trans_volumn_hn_fee;
                }
                // 43 - ID tỉnh HCM
                if($warehouse->province_id == 43) {
                    $weightFee = $offer->cn_trans_weight_hcm_fee;
                    $volumnFee = $offer->cn_trans_volumn_hcm_fee;
                }
            }
        }
        return resSuccessWithinData([
            'weight_fee' => $weightFee,
            'volumn_fee' => $volumnFee
        ]);
    }
}
