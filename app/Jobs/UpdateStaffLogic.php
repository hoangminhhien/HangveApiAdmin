<?php

namespace App\Jobs;

use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateStaffLogic implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $customer;
    protected $params;
    protected $userId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($customer, $params, $userId)
    {
        $this->customer = $customer;
        $this->params = $params;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $user = '';
        if($this->userId) {
            $user = User::find($this->userId);
        }
        $params = $this->params;
        $staff_group_id = $params['staff_group_id'] ?? null;
        $logService = \App::make('App\Services\ActivityService');
        if ($staff_group_id) {
            // Lấy hết đơn hàng cũ của khách trừ đơn hoàn thành ra
            $orderRepo = \App::make('App\Models\Order');
            $orderOld = $orderRepo
                ->with('packages')
                ->where('staff_group_id', '!=', $staff_group_id)
                ->where('status', '!=', OrderConstant::KEY_STATUS_DONE)
                ->where('customer_id', $this->customer->id)
                ->get();
            $packService = \App::make('App\Services\OrderPackageService');
            $orderService = app()->make(OrderService::class);
            if (count($orderOld)) {
                foreach ($orderOld as $item) {
                    $content = 'Cập nhật nhân viên quản lý';
                    $logService->setChangeStaffOrderLog($item, $content, $item->staff_group_id, $staff_group_id, $user);
                    $item->staff_group_id = $staff_group_id;
                    $isSave = $item->save();
                    if ($isSave) {
                        // update staff for package
                        $packageIds = $item->packages
                            ->whereNotIn('status',
                                [PackageConstant::STATUS_RECEIVED_GOODS, PackageConstant::STATUS_CANCEL]
                            )
                            ->pluck('id');
                        $packService->updateStaffForOrderPackage($packageIds, null, null, $staff_group_id);
                    }
                    if(!isset($params['is_assign_all'])) {
                        // send noti change order for staff care, counselor
                        $orderService->sendNotiOrder($item);
                    }

                }
            }

            // Thêm log chuyển nhân viên
            if ($staff_group_id) {
                $content = 'Cập nhật nhân viên kinh doanh';
                $logService->setChangeStaffCustomerLog($this->customer, $content, $this->customer->staff_group_id, $staff_group_id, $user);
            }

            // Đổi hết hội thoại cho người mới
            if (isset($params['staff_group_id_old'])) {
                if ($params['staff_group_id'] != $params['staff_group_id_old']) {
                    $conversationModel = \App::make('App\Models\Conversation');
                    $userModel = \App::make('App\Models\User');
                    $conversationDBs = $conversationModel->where('customer_id', (int) $this->customer->id)
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
            } else {
                // \Log::info('Tèo staff_group_id_old');
            }
        }
    }
}
