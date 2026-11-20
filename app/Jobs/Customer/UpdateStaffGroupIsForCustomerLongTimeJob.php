<?php

namespace App\Jobs\Customer;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Models\UserChatbizPing;
use Carbon\Carbon;
use App\Models\Consignment;
use App\Constants\OrderConstant;
use App\Models\Activity;
use Illuminate\Support\Facades\DB;
use App\Services\CustomerService;
use App\Services\OrderService;
use App\Constants\PackageConstant;
use Illuminate\Support\Facades\Cache;

class UpdateStaffGroupIsForCustomerLongTimeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {   
        // KH lâu chưa lên đơn quá 3 tháng và dc đã dc gán lại sau 3 tháng
        $numberLastMonth = 3;
        $lastMonth = Carbon::now()->subMonths($numberLastMonth);
        $timestampLastMonth = strtotime($lastMonth);
        $longTimeOrder2020 = Carbon::createFromFormat('Y', 2020)->startOfyear();
        $user = \Auth::user() ?? User::query()->withoutGlobalScope(
            OrganizationScope::class
        )->first();
        $userGroupIds = $this->getUserGroup();
        if(count($userGroupIds) == 0) {
            \Log::info('Không tìm thấy nhân viên nào để gán');    
            return;
        }
        if (count(Cache::get('calculatorCustomerLongTimeOrder', [])) == 0) {
            Cache::put('calculatorCustomerLongTimeOrder', $userGroupIds);
        }
        $cacheUserIds = Cache::get('calculatorCustomerLongTimeOrder', []);
        \Log::info('-------------Start update staff_group_id of customer-----------');
        \Log::info($cacheUserIds);
        Customer::query()
            ->where(DB::raw("IF(customers.last_order_at IS NOT NULL, customers.last_order_at, customers.created_at)"), '<', $lastMonth)
            ->where(DB::raw("IF(customers.staff_group_time_assign IS NOT NULL, customers.staff_group_time_assign, customers.created_at)"), '<', $lastMonth)
            ->where('customers.status', 1)
            ->whereNotNull('customers.staff_group_id')
            ->where(DB::raw("IF(customers.last_order_at IS NULL OR customers.last_order_at < customers.created_at, customers.created_at, customers.last_order_at)"), '>', $longTimeOrder2020)
            ->whereDoesntHave('packages', function ($q) use ($lastMonth){
                $q->whereBetween('created_at', [$lastMonth, Carbon::now()])
                    ->where(function ($subQuery) {
                        $subQuery->whereNull('order_type')
                            ->orWhere('order_type', Consignment::class);
                    });
            })
            ->chunk(500, function($customers) use ($user, $userGroupIds, $cacheUserIds, $timestampLastMonth)
            {
                foreach($customers as $key => $customer) {
                    $data = UserChatbizPing::where('user_id', $customer->staff_group_id)
                                            ->whereNotNull('phone_call_out_full_list')
                                            ->whereRaw("phone_call_out_full_list LIKE BINARY ?", ['%' . $customer->phone_number . '%'])->get();
                    $checkExist = false;
                    foreach($data as $item) {
                        // Logic check xem có cuộc gọi ra ở chatbiz trong 3 tháng lại đây hay không
                        $callOutList = json_decode($item->phone_call_out_full_list);
                        foreach($callOutList as $itemPhone) {
                            if(data_get($itemPhone, 'customer_phone') == $customer->phone_number && data_get($itemPhone, 'time_int') > $timestampLastMonth) {
                                $checkExist = true;
                                break;
                            }
                        }
                        if($checkExist == true) {
                            break;
                        }
                    }
                    if($checkExist == true) {
                        continue;
                    }
                    // Có cuộc gọi ra thì bỏ qua khách hàng
                    if(count($cacheUserIds) == 0) {
                        Cache::put('calculatorCustomerLongTimeOrder', $userGroupIds);
                        $cacheUserIds = Cache::get('calculatorCustomerLongTimeOrder', []);
                    }
                    $staffGroupId = $cacheUserIds[0];
                    if($customer->staff_group_id != $staffGroupId) {
                        array_shift($cacheUserIds);
                        Cache::put('calculatorCustomerLongTimeOrder', array_values($cacheUserIds));
                        $params = [
                            'staff_group_id' => $staffGroupId,
                            'staff_group_id_old' => $customer->staff_group_id
                        ];
                        $this->updateStaffLogic($customer, $params, $user->id);
                        if($customer->staff_group_id != $staffGroupId) {
                            $this->pushNotiChangeCustomer($customer, $staffGroupId);
                        }
                        $customer->update([
                            'staff_group_id' => $staffGroupId,
                            'staff_group_time_assign' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            });
        \Log::info('-------------End Update staff_group_id of customer-----------');       
    }

    private function updateStaffLogic($customer, $params, $auth = null)
    {
        $user = User::find($auth);
        $staff_group_id = $params['staff_group_id'] ?? null;
        $logService = \App::make('App\Services\ActivityService');
        if ($staff_group_id) {
            // Lấy hết đơn hàng cũ của khách trừ đơn hoàn thành ra
            $orderRepo = \App::make('App\Models\Order');
            $orderOld = $orderRepo
                ->with('packages')
                ->where('staff_group_id', '!=', $staff_group_id)
                ->where('status', '!=', OrderConstant::KEY_STATUS_DONE)
                ->where('customer_id', $customer->id)
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

                    // send noti change order for staff care, counselor
                    $orderService->sendNotiOrder($item);

                }
            }

            // Đổi hết hội thoại cho người mới
            if ($params['staff_group_id'] != $params['staff_group_id_old']) {
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

    private function pushNotiChangeCustomer($customer, $staffGroupId)
    {
        // Thêm log chuyển nhân viên
        $notificationService = \App::make('App\Services\NotificationService');

        $data_customer = [
            'customer_name'  => $customer->name,
            'customer_email' => $customer->email,
            'customer_id'    => $customer->id,
        ];
        $data = [
            'group'           => 1,
            'name'            => 1002,
            'data'            => json_encode($data_customer),
            'time_create'     => time(),
            'status'          => 1
        ];

        $notificationService->storeNoti($staffGroupId, $data);
    }

    private function getUserGroup(): array
    {
        $query = User::query();
        $query = $query->whereExists(function ($query){
                $query->select("users_group.id")
                    ->from('users_group')
                    ->whereRaw('users_group.user_id = users.id')
                    ->where('users_group.is_get_customer', 1)
                    ->where('users_group.month', date('m/Y'));
            })
            ->where('users.status', 1)
            ->select('users.id');
        return $query->inRandomOrder()->pluck('id')->toArray();
    }
}
