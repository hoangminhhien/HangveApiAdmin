<?php

namespace App\Services\Customer;

use App\Constants\NotifyConstant;
use App\Http\Resources\Customer\ChangeStaffRequestResource;
use App\Http\Resources\PaginateJsonResource;
use App\Models\ChangeStaffRequest;
use App\Models\Customer;
use App\Models\Staff;
use App\Services\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChangeStaffRequestService implements Service
{
    protected string $_resource = ChangeStaffRequestResource::class;

    /**
     * @param  array  $params
     * @return JsonResponse
     */
    public function changeStaffRequests(array $params): JsonResponse
    {
        $perPage = $params["per_page"] ?? 15;
        $code = $params["code"] ?? null;
        $data = ChangeStaffRequest::query()->with(["customer", "staffGroup"]);
        if ($code) {
            $data = $data->whereHas('customer', function ($q) use ($code) {
                return $q->where('customers.code', "like", "%$code%")
                            ->orWhere('customers.phone_number', $code);
            });
        }
        $data = $data->paginate($perPage);
        return resSuccessWithinData(new PaginateJsonResource($data, $this->_resource));
    }

    public function deleteChangeStaffRequests($id): JsonResponse
    {
        try {
            $data = ChangeStaffRequest::query()->where("id", $id)->first();
            if ($data->delete()) {
                return resSuccess("Xóa yêu cầu thay đổi nhân viên hỗ trợ thành công");
            }
            return resError("Lỗi xảy ra khi xóa yêu cầu thay đổi nhân viên hỗ trợ");
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return resError($exception->getMessage());
        }
    }

    public function updateChangeStaffRequests(array $params, $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $message = "Lỗi xảy ra khi cập nhật dữ liệu";
            $type = $params["type"] ?? null;
            $staffGroupId = $params["staff_group_id"] ?? null;
            $data = ChangeStaffRequest::query()->where("id", $id)->first();
            if (!$data) {
                return resError("Dữ liệu không còn tồn tại trong hệ thống");
            }
            if (!$staffGroupId && !$type) {
                return resError("Dữ liệu gửi cập nhật không hợp lệ");
            }
            if ($staffGroupId) {
                $data->staff_group_id = $staffGroupId;
                $data->status = ChangeStaffRequest::STATUS_PENDING;
                $message = "Gán nhân viên tiếp nhận thành công";
            }
            if ($type) {
                if (!$data->staff_group_id) {
                    return resError("Bạn hay chọn nhân viên kinh doanh trước khi xác nhận");
                }

                $dataCustomer = Customer::query()->where("id", $data->customer_id)->first();
                if ($data->staff_group_id == $dataCustomer->staff_group_id) {
                    return resSuccess("Nhân viên kinh doanh này đã cập nhật trước đó");
                }
                // Lưu lại nhân viên kinh doanh cũ
                $data->staff_group_id_old = $dataCustomer->staff_group_id;
                // Cập nhật nhân viên kinh doanh mới cho khách hàng
                $dataCustomer->staff_group_id = $data->staff_group_id;
                $dataCustomer->save();

                $data->status = ChangeStaffRequest::STATUS_APPROVED;
                $message = "Cập nhật nhân viên mới thành công";

                // Gửi notification cho nhân viên khi được gán khách hàng mới
                $notificationService = \App::make('App\Services\NotificationService');
                $keyGroup = NotifyConstant::KEY_GROUP_CUSTOMER;
                $keyName  = NotifyConstant::KEY_NAME_CUSTOMER_CHANGE_STAFF;
                $dataNotification = [
                    'customer_name'  => $dataCustomer->name,
                    'customer_email' => $dataCustomer->email,
                    'customer_id'    => $dataCustomer->id,
                ];
                $notificationService->sendNoti($keyGroup, $keyName, $data->staff_group_id, $dataNotification);

                // Gửi notification cho khách hàng khi thay đổi nhân viên hỗ trợ
                $keyGroup = NotifyConstant::KEY_GROUP_COMPLAIN;
                $staffNew = Staff::query()->where("id", $data->staff_group_id)->first();
                $dataNotificationCustomer = [
                    'content'  => "Nhân viên hỗ trợ của bạn đã được thay đổi." . ($staffNew ? " Nhân viên tiếp nhận là: " . $staffNew->name : "")
                ];
                $notificationService->sendNotificationCustomer($keyGroup, $keyName, $data->customer_id, $dataNotificationCustomer);
            }
            $data->save();
            DB::commit();
            return resSuccess($message);
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception);
            return resError($exception->getMessage());
        }
    }

}
