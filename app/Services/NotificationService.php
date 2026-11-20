<?php


namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Notification;
use App\Http\Resources\Notification\NotificationResource;
use App\Http\Resources\Notification\NotificationListResource;
use App\Http\Resources\Notification\NotificationPaginateResource;

use Illuminate\Http\JsonResponse;

use App\Notifications\PushNotiAdmin;

use LRedis;

use GuzzleHttp\Client as Guzzle;

class NotificationService extends BaseService
{
    protected string $_paginateResource = NotificationPaginateResource::class;
    protected string $_listResource = NotificationListResource::class;
    protected string $_resource = NotificationResource::class;

    public function pagination(int $perPage): JsonResponse
    {
        // $this->throwModel();
        // $userOnline = \Auth::user();
        // $data = $this->_model->newQuery()->where('user_id', $userOnline->id)->paginate($perPage);
        // return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));

        $this->throwModel();
        $userOnline = \Auth::user();
        $group = request()->get('group');
        $data = $this->_model->newQuery()
            ->where('user_id', $userOnline->id)
            ->when($group, function($q) use ($group) {
                return $q->where('group', $group);
            })
            ->paginate($perPage);
        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }

    public function store(array $data)
    {
        $this->throwModel();
        $userOnline = \Auth::user();

        $data['organization_id'] = $userOnline->organization_id ?? 0;
        $data['status'] = 1;

        return $this->_model->create($data);
    }

    public function storeNotiChangeExchangeRate(array $data)
    {
        $this->throwModel();
        $data['status'] = 1;

        return $this->_model->create($data);
    }

    public function storeNoti($user_id, $data)
    {
        $userModel  = new User();
        $user = $userModel->newQuery()->where('id', $user_id)->first();
        if ($user) {
            // Thêm tắt thông tin
            $data['user_id'] = $user_id;
            $data['organization_id'] = $user->organization_id ?? 0;

            // Tạo noti
            $noti = $this->_model->newQuery()->create($data);

            // Cập nhật tổng noti chưa đọc cho user dc gán
            // $user->total_noti += 1;
            // $user->save();

            // Đẩy về con Redis
            $cData = [
                'channel'  => 'noti-admin',
                'user_code' => $user->code,
                'group' => $data['group'] ??  '',
            ];
            $redis = LRedis::connection();
            $redis->publish('pubsub', json_encode($cData));

            // Thêm noti
            // $user->notify(new PushNotiAdmin($noti->getNameText()));
            $job = new \App\Jobs\SendAdminNoti($user, $noti->getContent());
            dispatch($job)->delay(now()->addSeconds(3))->onQueue('sent-noti-app');
        }
    }

    /**
     * @param int $keyGroup
     * @param int $keyName
     * @param string $userId
     * @param array $data
     */
    public function sendNoti(int $keyGroup, int $keyName, string $userId, array $data)
    {
        $dataNoti = [
            'group'           => $keyGroup,
            'name'            => $keyName,
            'data'            => json_encode($data),
            'time_create'     => time(),
            'status'          => 1
        ];

        $this->storeNoti($userId, $dataNoti);
    }

    public function storeNotificationCustomer($customerId, $data, $no_sign = false)
    {
        $customer = Customer::query()->where('id', $customerId)->first();
        if ($customer) {
            // Thêm tắt thông tin
            $data["customer_id"] = $customer->id;
            $data['user_id'] = $customer->staff_group_id ?? null;
            $data['organization_id'] = $customer->organization_id;

            if ($no_sign == true) {
                $data['user_id'] = null;
            }

            // Tạo noti
            $notification = $this->_model->newQuery()->create($data);

            // Đẩy về con Redis
            $cData = [
                'channel' => 'noti-admin',
                'user_code' => $customer->code,
            ];
            $redis = LRedis::connection();
            $redis->publish('pubsub', json_encode($cData));

            // Thêm notification
            $job = new \App\Jobs\SendAdminNoti($customer, $notification->getContentCustomer());
            dispatch($job)->delay(now()->addSeconds(3))->onQueue('sent-noti-app');
        }
    }

    /**
     * @param  int  $keyGroup
     * @param  int  $keyName
     * @param  string  $customerId
     * @param  array  $data
     */
    public function sendNotificationCustomer(int $keyGroup, int $keyName, string $customerId, array $data)
    {
        $dataNotification = [
            'group' => $keyGroup,
            'name' => $keyName,
            'data' => json_encode($data),
            'time_create' => time(),
            'status' => 1
        ];

        $this->storeNotificationCustomer($customerId, $dataNotification);
    }

    public function packNotification($pack, $name)
    {
        $data_pack = [
            'pack_id' => $pack->id,
            'pack_code' => $pack->bill_code,
        ];
        $data = [
            'group' => 3,
            'name' => $name,
            'data' => json_encode($data_pack),
            'time_create' => time(),
            'status' => 1
        ];
        $orderDB = $pack->order;
        if ($orderDB) {
            $staff_order_id = $orderDB->staff_order_id;
            if ($staff_order_id) {
                $this->storeNoti($staff_order_id, $data);
            }
        }
    }

    public function pushStatusToQuote($orderPackage)
    {
        // Lấy dữ liệu
        $guzzle = new Guzzle;
        $url = env('URL_QUOTE') . 'webhook/hangve-m2329k1947165msh';
        $options = [
            'json' => [
                'event_name' => 'quote_update_quote_clean',
                'data' => [
                    'hangve_pack_id'     => (int) $orderPackage->id,
                    'hangve_pack_status' => $orderPackage->status,
                ],
                'token2000a'         => env('TOKEN_SYNC_DD_HV')
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result['code'] == 200) {
        } else {
            \Log::info("pushStatusToOne err " . $orderPackage->id);
        }
    }

    public function syncAddPackOrderAction($orderPackage)
    {
        if ($orderPackage->bill_code == null || $orderPackage->bill_code == '') {
            \Log::info("syncAddPackOrderAction chưa có mã vận đơn " . $orderPackage->id);
            return;
        }
        // Lấy dữ liệu
        $guzzle = new Guzzle;
        $url = env('URL_QUOTE') . 'webhook/hangve-m2329k1947165msh';
        $options = [
            'json' => [
                'event_name' => 'hangve_add_pack_order',
                'data' => [
                    'hangve_pack_id'        => (int) $orderPackage->id,
                    'hangve_pack_bill_code' => $orderPackage->bill_code,
                    'hangve_order_id'       => $orderPackage->order_id,
                    'hangve_order_code'     => $orderPackage->order_code,
                ],
                'token2000a'         => env('TOKEN_SYNC_DD_HV')
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result['code'] == 200) {
        } else {
            \Log::info("syncAddPackOrderAction err " . $orderPackage->id);
        }
    }

    public function syncUpdateBillCodePackOrder($data_push)
    {
        // Lấy dữ liệu
        $guzzle = new Guzzle;
        $url = env('URL_QUOTE') . 'webhook/hangve-m2329k1947165msh';
        $options = [
            'json' => [
                'event_name' => 'hangve_update_bill_code_pack_order',
                'data'       => $data_push,
                'token2000a' => env('TOKEN_SYNC_DD_HV')
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $log = [
            'event_name' => 'hangve_update_bill_code_pack_order',
            'data'       => $data_push,
        ];
        // \Log::info(json_encode($log));

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result['code'] == 200) {
        } else {
            \Log::info("syncUpdateBillCodePackOrder err " . json_encode($result));
        }
    }

    public function reSyncCallOutChatbiz($dateCurrent)
    {
        $guzzle = new Guzzle;
        $url = 'https://api-v2.chatbiz.io/get-call-out-by-day-311025-xt9aq7mlpz';
        $options = [
            'json' => [
                'date'       => $dateCurrent,
                'token2000a' => env('TOKEN_SYNC_DD_HV')
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $result = $guzzle->request('GET', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && $result['status_code'] == 200) {
            $data_call = $result['data'];
            if (count($data_call) > 0) {
                $grouped = [];
                foreach ($data_call as $item) {
                    $email = $item['agent_email'];
                    $grouped[$email][] = $item;
                }

                foreach ($grouped as $key_call => $arr_call) {
                    if ($key_call != 'hanguyennd19@gmail.com') {
                        continue;
                    }

                    dump($key_call);
                    dd($arr_call);
                }
            }
        }
    }
}
