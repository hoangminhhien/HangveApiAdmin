<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use GuzzleHttp\Client as Guzzle;
use Carbon\Carbon;
use App\Constants\ChatbizConstant;
use App\Constants\CustomerConstant;
use App\Constants\OrderConstant;
use App\Constants\PackageConstant;
use App\Constants\ViaConstant;
use App\Models\LogWebhook;
use App\Models\OrderPackage;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Warehouse;

class ChatbizController extends Controller
{
    public function __construct()
    {

    }

    public function listStatistic(Request $request)
    {
        // Validate
        $validationRules = [
            'time' => 'required|in:d,w,m,y',
        ];
        $validationMessages = [

        ];
        $validated = $request->validate($validationRules, $validationMessages);

        // Xử lý
        $data = $request->all();
        $time = $request->time;

        $dateRange = $this->perDateRange($time);

        $userChatBizPingModel = \App::make('App\Models\UserChatbizPing');
        $model  = $userChatBizPingModel;


        $result_1 = $model->whereBetween('date', [$dateRange['startDate'], $dateRange['endDate']])->get();

        $collection = collect($result_1->toArray());
        $grouped = $collection->groupBy('user_id');
        $grouped->all();

        $arr_result = [];

        foreach ($grouped as $key_grouped => $group_item) {
            $total_customer = $this->getTotalCustomer($group_item);
            $itemFirst = $group_item[0];

            $temp = [
                'id'                     => $itemFirst['id'],
                'user_code'              => $itemFirst['user_code'],
                'user_name'              => $itemFirst['user_name'],
                'phone_call_out'         => array_sum(\Arr::pluck($group_item, 'phone_call_out')),
                'phone_call_out_success' => array_sum(\Arr::pluck($group_item, 'phone_call_out_success')),
                'phone_call_in'          => array_sum(\Arr::pluck($group_item, 'phone_call_in')),
                'phone_call_in_success'  => array_sum(\Arr::pluck($group_item, 'phone_call_in_success')),

                'zalo_handle'            => array_sum(\Arr::pluck($group_item, 'zalo_handle')),
                'zalo_done'              => array_sum(\Arr::pluck($group_item, 'zalo_done')),
                'zalo_late'              => array_sum(\Arr::pluck($group_item, 'zalo_late')),

                'facebook_handle'        => array_sum(\Arr::pluck($group_item, 'facebook_handle')),
                'facebook_done'          => array_sum(\Arr::pluck($group_item, 'facebook_done')),
                'facebook_late'          => array_sum(\Arr::pluck($group_item, 'facebook_late')),

                'livechat_handle'        => array_sum(\Arr::pluck($group_item, 'livechat_handle')),
                'livechat_done'          => array_sum(\Arr::pluck($group_item, 'livechat_done')),
                'livechat_late'          => array_sum(\Arr::pluck($group_item, 'livechat_late')),

                'total_customer'         => $total_customer
            ];

            array_push($arr_result, $temp);
        }

        return resSuccessWithinData($arr_result);
    }

    public function getTotalCustomer($groups)
    {
        $arr_phone = [];
        foreach ($groups as $key_1 => $userChatbizPingDB) {
            // Phone
            $phone_call_out_list = json_decode($userChatbizPingDB['phone_call_out_list'], true);
            $phone_call_in_list = json_decode($userChatbizPingDB['phone_call_in_list'], true);

            foreach ($phone_call_out_list as $key_1 => $item_1) {
                array_push($arr_phone, $item_1['customer_phone']);
            }
            foreach ($phone_call_in_list as $key_2 => $item_2) {
                array_push($arr_phone, $item_2['customer_phone']);
            }

            // Zalo
            $zalo_handle_list = json_decode($userChatbizPingDB['zalo_handle_list'], true);
            foreach ($zalo_handle_list as $key_3 => $item_3) {
                array_push($arr_phone, $item_3['customer_phone']);
            }
        }

        $count_KH = count(array_unique($arr_phone));
        return $count_KH;
    }

    public static function perDateRange($time)
    {
        $endDate = Carbon::now();

        if ($time == 'd') {
            $startDate =  Carbon::today()->startOfDay();
        }
        if ($time == 'w'){
            $startDate = Carbon::now()->startOfWeek();
        }
        if ($time == 'm'){
            $startDate = Carbon::now()->firstOfMonth();
        }
        if ($time == 'q'){
            $startDate = Carbon::now()->startOfQuarter();
        }
        if ($time == 'y'){
            $startDate = Carbon::now()->startOfYear();
        }

        if ($time == 'd'){
            $startPrevious = Carbon::today()->subDay()->startOfDay();
            $endPrevious = Carbon::today()->subDay()->endOfDay();
        }
        if ($time == 'w'){
            $startPrevious = Carbon::today()->subWeek()->startOfWeek();
            $endPrevious = Carbon::today()->subWeek()->endOfWeek();
        }
        if ($time == 'm'){
            $startPrevious = Carbon::today()->subMonth()->startOfMonth();
            $endPrevious = Carbon::today()->subMonth()->endOfMonth();
        }
        if ($time == 'q'){
            $startPrevious = Carbon::today()->subMonth(3)->startOfQuarter();
            $endPrevious = Carbon::today()->subMonth(3)->endOfQuarter();
        }
        if ($time == 'y'){
            $startPrevious = Carbon::today()->subMonth(12)->startOfYear();
            $endPrevious = Carbon::today()->subMonth(12)->endOfYear();
        }

         $result = array(
            'startDate'     => $startDate->format('Y-m-d H:i:s'),
            'endDate'       => $endDate->format('Y-m-d H:i:s'),
            'startPrevious' => $startPrevious->format('Y-m-d H:i:s'),
            'endPrevious'   => $endPrevious->format('Y-m-d H:i:s'),
        );
        return $result;
    }

    // Log khi nhân viên gọi ra cho khách
    public function logCallOut(Request $request)
    {
        $params = $request->all();

        \Log::info('logCallOut đã xảy ra cho khách: ' .  $params['customer_phone']);

        // Validate
        $validationRules = [
            'customer_phone'       => 'required|string|max:191',
            'customer_phone_count' => 'required|numeric|min:1',
            'customer_channel'     => 'required|string|max:191',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        // Xử lý
        $customerModel = \App::make('App\Models\Customer');
        $customerDB = $customerModel->where('phone_number', $params['customer_phone'])->first();

        try {
            \DB::beginTransaction();
            if ($customerDB) {
                $customerDB->chatbiz_total_call += 1;
                $customerDB->save();
            } else {
                \Log::info('logCallOut đã xảy ra lỗi: ' . $params['customer_phone']);
            }


            \DB::commit();
            return resSuccessWithinData('Thành công');
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function logCallFull($params)
    {
        if ($params['type'] == 'call_out') {
            // Chèn mapping lại nhân viên
            $params['agent_email'] = $this->mappingUser($params['agent_email']);

            // Lấy ra ai là người xử lý
            $userModel = \App::make('App\Models\User');
            $agentDB = $userModel->where('email', $params['agent_email'])->first();

            if (is_null($agentDB)) {
                \Log::info('logCall lỗi không nhân viên sub: ' . $params['agent_email']);
                return;
            }
            try {
                \DB::beginTransaction();

                $data_update = [];
                $timeNow = time();

                $userChatbizPingDB = $this->getOrCreatePing($agentDB);

                if ($userChatbizPingDB->phone_call_out_full_list == null) {
                    $phone_call_out_full_list = [];
                } else {
                    $phone_call_out_full_list = json_decode($userChatbizPingDB->phone_call_out_full_list, true);
                }

                array_push($phone_call_out_full_list, [
                    'time'           => date('Y-m-d H:i:s', $timeNow),
                    'time_int'       => $timeNow,
                    'customer_phone' => $params['customer_phone']
                ]);
                $data_update['phone_call_out_full_list'] = json_encode($phone_call_out_full_list);

                // Lưu lại
                $userChatbizPingDB->fill($data_update)->save();

                \DB::commit();
            } catch (Exception $e) {
                \DB::rollback();
                throw $e;
            }
        }
    }

    public function logCall(Request $request)
    {
        $params = $request->all();
        unset($params['token2000a']);

        // \Log::info('logCall : ' . request()->ip() . ' - ' . json_encode($params, JSON_UNESCAPED_UNICODE));

        // Validate
        $validationRules = [
            'customer_phone' => 'required|string|max:191',
            'agent_email'    => 'required|string|email',
            'type'           => 'required|in:call_in,call_out',
            'miss'           => 'required|in:0,1'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        // Nếu là gọi ra thì cứ lưu full đã
        $this->logCallFull($params);

        // Xử lý
        $customerModel = \App::make('App\Models\Customer');
        $customerDB = $customerModel->where('phone_number', $params['customer_phone'])->first();

        if (is_null($customerDB)) {
            // \Log::info('logCall lỗi không có khách: ' . $params['customer_phone']);
            return resSuccessWithinData([]);
        }

        // Chèn mapping lại nhân viên
        $params['agent_email'] = $this->mappingUser($params['agent_email']);

        // Lấy ra ai là người xử lý
        $userModel = \App::make('App\Models\User');
        $agentDB = $userModel->where('email', $params['agent_email'])->first();

        if (is_null($agentDB)) {
            \Log::info('logCall lỗi không nhân viên: ' . $params['agent_email']);
            return resSuccessWithinData([]);
        }

        try {
            \DB::beginTransaction();

            $userChatbizPingDB = $this->getOrCreatePing($agentDB);

            $data_update = [];
            $timeNow = time();

            if ($params['type'] == 'call_out') {
                if ($params['miss'] == 1) {
                    $data_update['phone_call_out'] = $userChatbizPingDB->phone_call_out + 1;
                }
                if ($params['miss'] == 0) {
                    $data_update['phone_call_out'] = $userChatbizPingDB->phone_call_out + 1;
                    $data_update['phone_call_out_success'] = $userChatbizPingDB->phone_call_out_success + 1;
                }

                $phone_call_out_list = json_decode($userChatbizPingDB->phone_call_out_list, true);
                array_push($phone_call_out_list, [
                    'time'           => date('Y-m-d H:i:s', $timeNow),
                    'time_int'       => $timeNow,
                    'customer_phone' => $params['customer_phone']
                ]);
                $data_update['phone_call_out_list'] = json_encode($phone_call_out_list);
            }

            if ($params['type'] == 'call_in') {
                if ($params['miss'] == 1) {
                    $data_update['phone_call_in'] = $userChatbizPingDB->phone_call_in + 1;
                }
                if ($params['miss'] == 0) {
                    $data_update['phone_call_in'] = $userChatbizPingDB->phone_call_in + 1;
                    $data_update['phone_call_in_success'] = $userChatbizPingDB->phone_call_in_success + 1;
                }

                $phone_call_in_list = json_decode($userChatbizPingDB->phone_call_in_list, true);
                array_push($phone_call_in_list, [
                    'time'           => date('Y-m-d H:i:s', $timeNow),
                    'time_int'       => $timeNow,
                    'customer_phone' => $params['customer_phone']
                ]);
                $data_update['phone_call_in_list'] = json_encode($phone_call_in_list);
            }

            // Lưu lại
            $userChatbizPingDB->fill($data_update)->save();

            // Đếm lại số tổng
            $this->updateTotalCustomer($userChatbizPingDB);

            // \Log::info('logCall thành công: ' .  $params['customer_phone']);

            \DB::commit();
            return resSuccessWithinData('Thành công');
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function updateTotalCustomer($userChatbizPingDB)
    {
        $data = [
            $userChatbizPingDB->toArray()
        ];
        $total_customer = $this->getTotalCustomer($data);

        $userChatbizPingDB->total_customer = $total_customer;
        $userChatbizPingDB->save();
    }

    public function getOrCreatePing($agentDB)
    {
        $userChatbizPingModel = \App::make('App\Models\UserChatbizPing');

        $dataNow = date('Y-m-d', time());
        $userChatbizPingDB = $userChatbizPingModel->where('user_id', $agentDB->id)
                                                ->where('date', $dataNow)
                                                ->first();

        if (is_null($userChatbizPingDB)) {
            $data_user_chatbiz_ping = [
                'user_id'              => $agentDB->id,
                'user_code'            => $agentDB->code,
                'user_name'            => $agentDB->name,
                'date'                 => $dataNow,

                'phone_call_out_list'  => json_encode([]),
                'phone_call_in_list'   => json_encode([]),
                'phone_call_out_full_list'   => json_encode([]),

                'zalo_handle_list'     => json_encode([]),
                'zalo_done_list'       => json_encode([]),
                'zalo_late_list'       => json_encode([]),

                'facebook_handle_list' => json_encode([]),
                'facebook_done_list'   => json_encode([]),
                'facebook_late_list'   => json_encode([]),

                'livechat_handle_list' => json_encode([]),
                'livechat_done_list'   => json_encode([]),
                'livechat_late_list'   => json_encode([]),
            ];
            $userChatbizPingDB = $userChatbizPingModel->create($data_user_chatbiz_ping);
        }

        return $userChatbizPingDB;
    }

    // Log xử lý phần Zalo
    public function logZalo(Request $request)
    {
        $params = $request->all();

        // \Log::info('logZalo : ' . request()->ip() . ' - ' . json_encode($params, JSON_UNESCAPED_UNICODE));

        // Validate
        $validationRules = [
            'customer_phone'  => 'required|string|max:191',
            'agent_email'     => 'required|string|email',
            'type'            => 'required|in:first_send,first_rep,check_miss',
            'conversation_id' => 'required',
            'content'         => 'nullable'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        // Xử lý
        $customerModel = \App::make('App\Models\Customer');
        $customerDB = $customerModel->where('phone_number', $params['customer_phone'])->first();

        if (is_null($customerDB)) {
            // \Log::info('logZalo lỗi không có khách: ' . $params['customer_phone']);
            return resSuccessWithinData([]);
        }

        // Chèn mapping lại nhân viên
        $params['agent_email'] = $this->mappingUser($params['agent_email']);

        // Lấy ra ai là người xử lý
        $userModel = \App::make('App\Models\User');
        $agentDB = $userModel->where('email', $params['agent_email'])->first();

        if (is_null($agentDB)) {
            // \Log::info('logZalo lỗi không nhân viên: ' . $params['agent_email']);
            return resSuccessWithinData([]);
        }

        try {
            \DB::beginTransaction();

            $userChatbizPingDB = $this->getOrCreatePing($agentDB);

            $data_update = [];
            $timeNow = time();

            if ($params['type'] == 'first_send') {
                $data_update['zalo_handle'] = $userChatbizPingDB->zalo_handle + 1;

                $zalo_handle_list = json_decode($userChatbizPingDB->zalo_handle_list, true);
                array_push($zalo_handle_list, [
                    'time'            => date('Y-m-d H:i:s', $timeNow),
                    'time_int'        => $timeNow,
                    'customer_phone'  => $params['customer_phone'],
                    'conversation_id' => $params['conversation_id'],
                ]);
                $data_update['zalo_handle_list'] = json_encode($zalo_handle_list);
            }

            if ($params['type'] == 'first_rep') {
                $data_update['zalo_done'] = $userChatbizPingDB->zalo_done + 1;

                $zalo_done_list = json_decode($userChatbizPingDB->zalo_done_list, true);
                array_push($zalo_done_list, [
                    'time'            => date('Y-m-d H:i:s', $timeNow),
                    'time_int'        => $timeNow,
                    'customer_phone'  => $params['customer_phone'],
                    'conversation_id' => $params['conversation_id'],
                ]);
                $data_update['zalo_done_list'] = json_encode($zalo_done_list);
            }

            // Check trễ, nếu mà trễ mới bắn, không thì thôi bỏ qua
            if ($params['type'] == 'check_miss') {
                $data_update['zalo_late'] = $userChatbizPingDB->zalo_late + 1;

                $zalo_late_list = json_decode($userChatbizPingDB->zalo_late_list, true);
                array_push($zalo_late_list, [
                    'time'            => date('Y-m-d H:i:s', $timeNow),
                    'time_int'        => $timeNow,
                    'customer_phone'  => $params['customer_phone'],
                    'conversation_id' => $params['conversation_id']
                ]);
                $data_update['zalo_late_list'] = json_encode($zalo_late_list);
            }

            // Lưu lại
            $userChatbizPingDB->fill($data_update)->save();

            // Đếm lại số tổng
            $this->updateTotalCustomer($userChatbizPingDB);

            // \Log::info('logZalo thành công: ' .  $params['customer_phone']);

            \DB::commit();
            return resSuccessWithinData('Thành công');
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    // Log xử lý phần Facebook
    public function logFacebook(Request $request)
    {
        $params = $request->all();

        // \Log::info('logFacebook : ' . request()->ip() . ' - ' . json_encode($params, JSON_UNESCAPED_UNICODE));

        // Validate
        $validationRules = [
            'customer_phone'  => 'required|string|max:191',
            'agent_email'     => 'required|string|email',
            'type'            => 'required|in:first_send,first_rep,check_miss',
            'conversation_id' => 'required',
            'content'         => 'nullable'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        // Xử lý
        $customerModel = \App::make('App\Models\Customer');
        $customerDB = $customerModel->where('phone_number', $params['customer_phone'])->first();

        if (is_null($customerDB)) {
            // \Log::info('logFacebook lỗi không có khách: ' . $params['customer_phone']);
            return resSuccessWithinData([]);
        }

        // Chèn mapping lại nhân viên
        $params['agent_email'] = $this->mappingUser($params['agent_email']);

        // Lấy ra ai là người xử lý
        $userModel = \App::make('App\Models\User');
        $agentDB = $userModel->where('email', $params['agent_email'])->first();

        if (is_null($agentDB)) {
            // \Log::info('logFacebook lỗi không nhân viên: ' . $params['agent_email']);
            return resSuccessWithinData([]);
        }

        try {
            \DB::beginTransaction();

            $userChatbizPingDB = $this->getOrCreatePing($agentDB);

            $data_update = [];
            $timeNow = time();

            if ($params['type'] == 'first_send') {
                $data_update['facebook_handle'] = $userChatbizPingDB->facebook_handle + 1;

                $facebook_handle_list = json_decode($userChatbizPingDB->facebook_handle_list, true);
                array_push($facebook_handle_list, [
                    'time'            => date('Y-m-d H:i:s', $timeNow),
                    'time_int'        => $timeNow,
                    'customer_phone'  => $params['customer_phone'],
                    'conversation_id' => $params['conversation_id'],
                ]);
                $data_update['facebook_handle_list'] = json_encode($facebook_handle_list);
            }

            if ($params['type'] == 'first_rep') {
                $data_update['facebook_done'] = $userChatbizPingDB->facebook_done + 1;

                $facebook_done_list = json_decode($userChatbizPingDB->facebook_done_list, true);
                array_push($facebook_done_list, [
                    'time'            => date('Y-m-d H:i:s', $timeNow),
                    'time_int'        => $timeNow,
                    'customer_phone'  => $params['customer_phone'],
                    'conversation_id' => $params['conversation_id'],
                ]);
                $data_update['facebook_done_list'] = json_encode($facebook_done_list);
            }

            // Check trễ, nếu mà trễ mới bắn, không thì thôi bỏ qua
            if ($params['type'] == 'check_miss') {
                $data_update['facebook_late'] = $userChatbizPingDB->facebook_late + 1;

                $facebook_late_list = json_decode($userChatbizPingDB->facebook_late_list, true);
                array_push($facebook_late_list, [
                    'time'            => date('Y-m-d H:i:s', $timeNow),
                    'time_int'        => $timeNow,
                    'customer_phone'  => $params['customer_phone'],
                    'conversation_id' => $params['conversation_id']
                ]);
                $data_update['facebook_late_list'] = json_encode($facebook_late_list);
            }

            // Lưu lại
            $userChatbizPingDB->fill($data_update)->save();

            // Đếm lại số tổng
            $this->updateTotalCustomer($userChatbizPingDB);

            // \Log::info('logFacebook thành công: ' .  $params['customer_phone']);

            \DB::commit();
            return resSuccessWithinData('Thành công');
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }


    // TestBuyder
    public function testBuyder(Request $request)
    {
        $guzzle = new Guzzle;

        $url = 'https://api.buyder.com/open/taobao/get-item?itemId=672991141190';

        $options = [
            'headers' => [
                'X-Token-Auth' => '176ksg2u97cb6qhb5wovryl5iv94v7tpahiuwz2ghjjj6x39weqkf17gbepza58yf',
            ],
            'verify' => false
        ];

        $result = $guzzle->request('GET', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        dd($result);
    }

    public function mappingUser($email_chatbiz)
    {
        // 'mynguyen@thuongdo.com'    => 'nguyenhuyenmy2508@gmail.com',
        // 'hongnt@thuongdo.com'      => 'nguyenhong.neu123@gmail.com',
        // 'trangptc@thuongdo.com'    => 'trangptc1301@gmail.com',
        // 'chiap@thuongdo.com'       => 'anchi2521999@gmail.com',
        // 'duongptt@thuongdo.com'    => 'duong.phamtinx@gmail.com',
        // 'duyenhtm@thuongdo.com'    => 'hoangduyen.030801@gmail.com',
        // 'huuongphung@thuongdo.com' => 'thuyhuong8989@gmail.com',

        $arr_users = [
            'doanquynh2808@gmail.com'          => 'quynhdt@thuongdo.com',
            'trangnguyenncity@gmail.com'       => 'trangkd2@thuongdo.com',
            // 'duongptt@gotech.vn'               => 'duongptt@thuongdo.com',
            // 'anchi2521999@gmail.com'           => 'chiap@thuongdo.com',
            'nguyenkhanhlinh.hubt99@gmail.com' => 'Linhnk@thuongdo.com',
            'thanhbuimg89@gmail.com'           => 'thanhbui@thuongdo.com',
            // 'trangptc1301@gmail.com'           => 'trangptc@thuongdo.com',
            // 'nguyenhong.neu123@gmail.com'      => 'hongnt@thuongdo.com',
            // 'thuyhuong8989@gmail.com'          => 'huuongphung@thuongdo.com',
            'Thuybunn95@gmail.com'             => 'Thuybunn95@gmail.com',
            // 'nguyenhuyenmy250899@gmail.com'    => 'mynguyen@thuongdo.com',
            // 'nguyenhuyenmy2508@gmail.com'      => 'mynguyen@thuongdo.com',
            'nguyenthutra092@gmail.com'        => 'Trantt@thuongdo.com',
            '0nhungnguyen0@gmail.com'          => 'nhungnt@thuongdo.com',
            'luongmytan01@gmail.com'           => 'tanlm@thuongdo.com',
            'Anhanhvan98@gmail.com'            => 'anhntv@thuongdo.com',
            'Huyenthu5277@gmail.com'           => 'huyentt@thuongdo.com',
            // 'hoangduyen.030801@gmail.com'      => 'duyenhtm@thuongdo.com'
        ];

        $arr_users = ChatbizConstant::ARR_MAP_USER_CHATBIZ_HANGVE;

        if (array_key_exists($email_chatbiz, $arr_users)) {
            return $arr_users[$email_chatbiz];
        }

        return $email_chatbiz;
    }

    public function makePass($phoneNumber)
    {
        $phoneStr = strval($phoneNumber);
        $hash = hash('sha256', $phoneStr);
        return substr($hash, 0, 8);
    }

    public function writeLog($params)
    {
        $logWebhookModel = \App::make('App\Models\LogWebhook');
        unset($params['token2000a']);
        $data_log = [
            'direction'   => LogWebhook::LOGWEBHOOK_DIRECTION_IN,
            'log_name'    => 'makeAccountLiveChat',
            'description' => 'ChatbizController',
            'body'        => json_encode($params, JSON_UNESCAPED_UNICODE),
            'from_ip'     => request()->ip()
        ];
        return $logWebhookModel->create($data_log);
    }

    // Log khi nhân viên gọi ra cho khách
    public function makeAccountLiveChat(Request $request)
    {
        $params = $request->all();
        // unset($params['token2000a']);
        // \Log::info('makeAccountLiveChat : ' . request()->ip() . ' - ' . json_encode($params, JSON_UNESCAPED_UNICODE));
        $logDB = $this->writeLog($params);

        $lock = \Cache::lock('makeAccountLiveChat_' . $params['customer_phone'], 60);
        if (!$lock->get()) {
            $logDB->from_ip = $logDB->from_ip . '-duplicate';
            $logDB->save();
            return;
        }

        // Validate
        $validationRules = [
            'domain'         => 'required|in:thuongdo_thuongdo,thuongdo_hangve,thuongdo_hang247,zalo_fb',
            'source'         => 'nullable|in:1,2,3',
            'customer_phone' => 'required|numeric',
            'customer_name'  => 'required|string|max:191',
            'agent_email'  => 'required'
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();

        // Xử lý
        $customerModel = \App::make('App\Models\Customer');
        $customerDB = $customerModel->where('phone_number', $params['customer_phone'])->first();

        if (!is_null($customerDB)) {
            return resSuccessWithinData([
                'create_success' => false
            ]);
        }

        $create_success = false;

        // Chèn mapping lại nhân viên
        $params['agent_email'] = $this->mappingUser($params['agent_email']);

        try {
            \DB::beginTransaction();

            if (is_null($customerDB)) {
                $userModel = \App::make('App\Models\User');

                $staff_group_id = null;

                $userDB = $userModel->where('email', $params['agent_email'])->first();

                if (is_null($userDB)) {
                    $userGroupModel = \App::make('App\Models\UserGroup');
                    // Chia random cho đứa nào đó
                    $monthYear = date('m/Y');
                    $userGroupDB = $userGroupModel->where('month', $monthYear)
                                                    ->where('is_get_customer', 1)
                                                    ->inRandomOrder()
                                                    ->first();
                    if (!is_null($userGroupDB)) {
                        $staff_group_id = (int) $userGroupDB->user_id;
                    }
                } else {
                    $staff_group_id = (int) $userDB->id;
                }

                // Tạo tài khoản
                $source = CustomerConstant::SOURCE_HV;
                if ($params['domain'] == 'thuongdo_thuongdo') {
                    $source = CustomerConstant::SOURCE_TD;
                }
                if ($params['domain'] == 'thuongdo_hang247') {
                    $source = CustomerConstant::SOURCE_NH;
                }

                // Nếu là bắn từ bên Zalo, FB sang thì bỏ qua nguồn
                if ($params['domain'] == 'zalo_fb') {
                    $source = CustomerConstant::SOURCE_TD;
                }

                // Nguồn tạo ra hội thoại
                $chatbiz_source = CustomerConstant::CHATBIZ_SOURCE_LIVE_CHAT;
                $via = null;
                if (isset($params['source'])) {
                    $chatbiz_source = $params['source'];

                    if ($chatbiz_source == CustomerConstant::CHATBIZ_SOURCE_ZALO_PERSONAL) {
                        $via = ViaConstant::KEY_ZALO_PERSONAL;
                    }
                }


                $data_customer = [
                    'name'                    => $params['customer_name'],
                    'email'                   => $params['customer_phone'] . '@gmail.com',
                    'phone_number'            => $params['customer_phone'],
                    'staff_group_id'          => $staff_group_id ? $staff_group_id : null,
                    'staff_group_time_assign' => $staff_group_id ? date('Y-m-d H:i:s', time()) : null,
                    'password'                => \Hash::make($this->makePass($params['customer_phone'])),
                    'source'                  => $source,
                    'creator_id'              => 1, // SysAdmin hệ thống
                    'organization_id'         => 1,
                    'level'                   => 1,
                    'point_level'             => 25,
                    'point_way'               => 20,
                    'service'                 => 99,
                    'chatbiz_source'          => $chatbiz_source,
                    'via'                     => $via,
                ];
                $customerDB = $customerModel->create($data_customer);
                $create_success = true;

                if (!is_null($staff_group_id)) {
                    // Thêm log chuyển nhân viên
                    $notificationService = \App::make('App\Services\NotificationService');
                    $data_noti = [
                        'customer_name'  => $data_customer['name'],
                        'customer_email' => $data_customer['email'],
                        'customer_id'    => (int)$customerDB->id,
                    ];
                    $data = [
                        'group'           => 1,
                        'name'            => 1002,
                        'data'            => json_encode($data_noti),
                        'time_create'     => time(),
                        'status'          => 1
                    ];
                    $notificationService->storeNoti($data_customer['staff_group_id'], $data);
                }
            }

            \DB::commit();
            return resSuccessWithinData([
                'create_success' => $create_success
            ]);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }


    public function fakeLogCallOut(Request $request)
    {
        // Validate
        $validationRules = [
            'agent_email'    => 'required|exists:users,email',
            'customer_phone' => 'required|numeric',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();

        $params['type'] = 'call_out';

        if (env('APP_URL') == 'http://localhost:18018' || env('APP_URL') == 'https://api-admin-v2.hangve.com') {
            $this->logCallFull($params);
        }

        return resSuccessWithinData([
            'create_success' => 'OK'
        ]);
    }

    public function checkStatus(Request $request)
    {
        $validationRules = [
            'name' => 'required',
            'code' => 'required',
        ];
        $validationMessages = [

        ];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();

        $status = 'Mã ' . $params['code'] . ' không tồn tại trên hệ thống';

        if ($params['name'] == 'check_order_status') {
            $orderDB = Order::query()->where('code', $params['code'])->first();
            if (!is_null($orderDB)) {
                $statusTemp = [];
                $statusTemp['order_status'] = OrderConstant::STATUSES[$orderDB->status];
                $warehouseDB = Warehouse::query()->where('id', $orderDB->warehouse_id)->first();
                if ($warehouseDB) {
                    $statusTemp['kho_tra_hang'] = $warehouseDB->address;
                }

                $orderPackages = $orderDB->packagesOrder;
                if (count($orderPackages) > 0) {
                    $statusTemp['total_packages'] = count($orderPackages);
                    $arr_string = [];
                    foreach ($orderPackages as $key => $pack) {
                        if ($pack['bill_code']) {
                            $temp = [
                                'pack_code' => $pack['bill_code'],
                                'pack_status' => PackageConstant::STATUSES[$pack->status],
                            ];
                            array_push($arr_string, $temp);
                        }
                    }
                    if (count($arr_string)) {
                        $statusTemp['packages'] = $arr_string;
                    }
                }

                $status = $statusTemp;
            }
        }

        if ($params['name'] == 'check_bill_status') {
            $packDB = OrderPackage::query()->where('bill_code', $params['code'])->first();
            if (!is_null($packDB)) {
                $status = PackageConstant::STATUSES[$packDB->status];

                $statusTemp = [];
                $statusTemp['pack_code'] = $params['code'];
                $statusTemp['pack_status'] = PackageConstant::STATUSES[$packDB->status];

                $status = $statusTemp;
            } else {

                $orderDB = Order::query()->where('code', $params['code'])->first();
                if (!is_null($orderDB)) {
                    $statusTemp = [];
                    $statusTemp['order_status'] = OrderConstant::STATUSES[$orderDB->status];
                    $warehouseDB = Warehouse::query()->where('id', $orderDB->warehouse_id)->first();
                    if ($warehouseDB) {
                        $statusTemp['kho_tra_hang'] = $warehouseDB->address;
                    }

                    $orderPackages = $orderDB->packagesOrder;
                    if (count($orderPackages) > 0) {
                        $statusTemp['total_packages'] = count($orderPackages);
                        $arr_string = [];
                        foreach ($orderPackages as $key => $pack) {
                            if ($pack['bill_code']) {
                                $temp = [
                                    'pack_code' => $pack['bill_code'],
                                    'pack_status' => PackageConstant::STATUSES[$pack->status],
                                ];
                                array_push($arr_string, $temp);
                            }
                        }
                        if (count($arr_string)) {
                            $statusTemp['packages'] = $arr_string;
                        }
                    }

                    $status = $statusTemp;
                }
            }
        }

        if ($params['name'] == 'check_address_warehouse_cn') {
            $status = 'Không có địa chỉ kho TQ';

            $customerDB = Customer::query()->where('phone_number', $params['code'])->first();
            if (!is_null($customerDB)) {
                $status = 'Hàng tiểu ngạch thì anh chị gửi kho Quảng Châu: ';
                $status .= 'Địa chỉ nhận hàng : 地址: 广东省 广州市 白云区 石井街道 凰岗村 凤鸣路117号F1档 ' . $customerDB->code . ' 咚咚国际店菜鸟驿站. ';
                $status .= 'Người nhận : 收货人: 邓有礼转 ' . $customerDB->code . ' 埋头. ';
                $status .= 'Mã bưu điện : 邮政编码: 510430. ';
                $status .= 'Số điện thoại : 电话: +86-18926668611.';

                $status .= '------- Hàng chính ngạch anh chị gửi kho Bằng Tường: ';
                $status .= 'Địa chỉ nhận hàng : 地址：广西壮族自治区凭祥市南山王府红木一仓通A档口 ' . $customerDB->code . ' 仓. ';
                $status .= 'Người nhận : 收货人: 陶小月 ' . $customerDB->code . ' 埋头. ';
                $status .= '邮政编码: 532600. ';
                $status .= 'Số điện thoại : 电话: 18878742572.';
            }
        }

        return resSuccessWithinData([
            'status' => $status
        ]);
    }
}

