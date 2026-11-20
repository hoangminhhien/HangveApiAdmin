<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Services\ChatService;
use App\Constants\PackageConstant;
use App\Constants\ConversationConstant;
use App\Constants\DepartmentConstant;
use App\Constants\RepConstant;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Rep;
use App\Models\User;
use App\Models\NameTran;
use App\Models\Supplier;

use GuzzleHttp\Exception\RequestException;

class ChatController extends Controller
{
    public function __construct(ChatService $service)
    {
        $this->_service = $service;
    }

    public function storeV2(Request $request)
    {
        $validationRules = [
            'order_force' => 'nullable',
            'order_id'    => 'required_without:order_force|exists:orders,id',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $customerModel = \App::make('App\Models\Customer');

        $userOnline = \Auth::user();
        $result_check = $this->handleBeforeStoreV2($params, $userOnline);

        if(!is_array($result_check)) {
            $result = ['error' => $result_check];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();
            $chatDB = $this->handlerStoreV2($userOnline, $result_check);

            $result_result = new \App\Http\Resources\Chat\ChatResource($chatDB);
            \DB::commit();
            return resSuccessWithinData($result_result);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function handleBeforeStoreV2($params, $userOnline)
    {
        if (isset($params['order_force']) && $params['order_force'] == true) {
            return [
                'order_id'   => 0,
                'order_code' => null,
            ];
        }
        $orderDB = Order::query()->where('id', $params['order_id'])->first();
        return [
            'order_id'   => $params['order_id'],
            'order_code' => $orderDB->code,
        ];
    }

    public function handlerStoreV2($userOnline, $result_check)
    {
        // Kiểm tra xem đã có hội thoại chưa
        $chatModel = \App::make('App\Models\Chat');
        $chatDB = $chatModel->where('user_id', (int)$userOnline->id)
                            ->where('order_id', $result_check['order_id'])
                            ->first();

        if (is_null($chatDB)) {
            $data_more = [
                'organization_id'   => (int) $userOnline->organization_id,
                'user_id'           => (int) $userOnline->id,
                'user_code'         => $userOnline->code,
                'user_name'         => $userOnline->name,
                'user_email'        => $userOnline->email,
                'user_image'        => $userOnline->avatar_origin,
                'time_last_send'    => date('Y-m-d H:i:s', time()),
                'content_last_send' => 'Hãy cung cấp thông tin sản phẩm để tra cứu mã HS code',
                'summary'           => json_encode([], JSON_UNESCAPED_UNICODE),
                'options'           => json_encode([], JSON_UNESCAPED_UNICODE),
                'items'             => json_encode([], JSON_UNESCAPED_UNICODE),
                'items_cache'       => json_encode([], JSON_UNESCAPED_UNICODE),
                'item_ids'          => json_encode([], JSON_UNESCAPED_UNICODE),
                'rep_id_new'        => 0,
            ];

            $data_conversation = array_merge($result_check, $data_more);
            $chatDB = $chatModel->create($data_conversation);

            // Tạo luôn 1 tin nhắn đầu tiên
            $rep_data = [
                'organization_id' => (int) $userOnline->organization_id,
                'chat_id'         => (int) $chatDB->id,
                'source'          => RepConstant::SOURCE_AI,
                'type'            => RepConstant::TYPE_TEXT,
                'content'         => 'Hãy cung cấp thông tin sản phẩm để tra cứu mã HS code',
                'parent_id'       => 0,
            ];
            $repDB = Rep::query()->create($rep_data);

        } else {
            $chatDB->time_last_send = date('Y-m-d H:i:s', time());
            $chatDB->save();
        }

        return $chatDB;
    }

    public function checkConversationNull(Request $request)
    {
        $conversationModel = \App::make('App\Models\Conversation');
        $time = time() - 3 * 3600;
        $time_text = date('Y-m-d H:i:s', $time);
        $conversationDBs = $conversationModel->whereNull('content_last_send')
                                            ->where('created_at', '>', $time_text)
                                            ->get();

        if (count($conversationDBs) > 0) {
            // \Log::info('checkConversationNull: ' . count($conversationDBs));
            foreach ($conversationDBs as $key => $conversationDB) {
                $conversationDB->delete();
            }
        }
    }

    public function storeSearchNull(Request $request)
    {
        $validationRules = [
            'content' => 'required',
            'check'   => 'required|in:0,1'
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $userOnline = \Auth::user();

        $orderModel = \App::make('App\Models\Order');
        $packModel  = \App::make('App\Models\OrderPackage');

        $orderOrPackDB = null;
        $data_check = [
            'type' => 1
        ];

        $orderOrPackDB = $orderModel->where('code', $params['content'])
                            ->where('staff_group_id', $userOnline->id)
                            ->first();

        if (is_null($orderOrPackDB)) {
            $orderOrPackDB = $packModel->where('bill_code', $params['content'])
                            ->where('staff_group_id', $userOnline->id)
                            ->first();
            if (!is_null($orderOrPackDB)) {
                $data_check['type'] = 2;
                $data_check['order_package_id'] = $orderOrPackDB->id;
                $data_check['customer_id'] = $orderOrPackDB->customer_id;
            }
        } else {
            $data_check['order_id'] = $orderOrPackDB->id;
            $data_check['customer_id'] = $orderOrPackDB->customer_id;
        }

        if ($params['check'] == 1) {
            $has_order_or_pack = true;
            if (is_null($orderOrPackDB)) {
                $has_order_or_pack = false;
            }
            return resSuccessWithinData([
                'has_order_or_pack' => $has_order_or_pack
            ]);
        }

        $customerModel = \App::make('App\Models\Customer');
        $userOnline = $customerModel->where('id', $data_check['customer_id'])->first();

        $result_check = $this->handleBeforeStoreV2($data_check, $userOnline);

        try {
            \DB::beginTransaction();
            $conversationDB = $this->handlerStoreV2($userOnline, $result_check);

            $result_result = new \App\Http\Resources\Conversation\ConversationResource($conversationDB);
            \DB::commit();
            return resSuccessWithinData($result_result);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function changeAgent(Request $request)
    {
        $validationRules = [
            'conversation_id' => 'required|exists:conversations,id',
            'user_id'         => 'required|exists:users,id',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $userModel = \App::make('App\Models\User');
        $departmentModel = \App::make('App\Models\Department');
        $conversationModel = \App::make('App\Models\Conversation');

        $departmentDB = $departmentModel->where('type', DepartmentConstant::TYPE_BUSINESS)->first();
        $userDB = $userModel->where('id', $params['user_id'])
                            ->where('department_id', $departmentDB->id)
                            ->where('status', 1)
                            ->first();

        if(is_null($userDB)) {
            $result = [
                'error' => 'Nhân viên không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $conversationDB = $conversationModel->where('id', $params['conversation_id'])->first();

        if ($conversationDB->user_id == $params['user_id']) {
            $result = [
                'error' => 'Nhân viên không phù hợp'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();

            // Chuyển nhượng
            $userOldDB = $userModel->where('id', $conversationDB->user_id)->first();

            $conversationDB->user_id    = $userDB->id;
            $conversationDB->user_code  = $userDB->code;
            $conversationDB->user_name  = $userDB->name;
            // $conversationDB->user_email = $userDB->email;
            $conversationDB->user_image = $userDB->avatar_origin;

            $conversationDB->time_last_send = date('Y-m-d H:i:s');
            $conversationDB->time_last_send_admin = date('Y-m-d H:i:s');
            $conversationDB->save();

            // Cập nhật số chưa đếm
            if ($conversationDB->unread_admin > 0) {
                // Chuyển số chưa đợc từ người cũ sang người mới
                // $userOldDB->total_chat_unread -= 1; // Tp070125
                // $userOldDB->save();

                // $userDB->total_chat_unread += 1; // Tp070125
                // $userDB->save();
            } else {
                $conversationDB->unread_admin = 1;
                $conversationDB->save();

                // $userDB->total_chat_unread += 1; // Tp070125
                // $userDB->save();
            }

            \DB::commit();

            $result_result = new \App\Http\Resources\Conversation\ConversationResource($conversationDB);
            $result_result['user_code_old'] = $userOldDB->code;
            return resSuccessWithinData($result_result);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function getItemInOrder(Request $request)
    {
        $validationRules = [
            'order_id'         => 'required',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();

        $data_result = [];
        $orderDetails = OrderDetail::query()->where('order_id', $params['order_id'])->orderBy('id', 'ASC')->get();
        if (count($orderDetails) > 0) {
            // Check xem dịch chưa, chưa dịch thì dịch ra
            $nameTranDBs = NameTran::query()->where('order_id', $params['order_id'])->count();
            if ($nameTranDBs == 0) {
                try {
                    $arr_tran_diff = \Arr::pluck($orderDetails, 'name');
                    $translateService = \App::make('App\Services\GoogleTranslateService');
                    $data_translate_vi = $translateService->translateSearchText($arr_tran_diff, 'vi');

                    foreach ($orderDetails as $key => $odtemp) {
                        $temp = [
                            'order_id' => $odtemp->order_id,
                            'order_detail_id' => (int)$odtemp->id,
                            'name_vi' => $data_translate_vi[$key]['translatedText'],
                        ];
                        NameTran::create($temp);
                    }
                } catch (RequestException $e) {
                    \Log::info('Lỗi: ' . json_encode($e, JSON_UNESCAPED_UNICODE));
                }
            }

            $groupeds = $orderDetails->groupBy('supplier_id');

            foreach ($groupeds as $key_1 => $orderDetailTemps) {
                $data_temp = [];
                foreach ($orderDetailTemps as $key_2 => $orderDetai) {
                    $temp = [
                        'id'             => (int) $orderDetai->id,
                        'name'           => $orderDetai->nameTran ? $orderDetai->nameTran->name_vi : $orderDetai->name,
                        'link'           => $orderDetai->link,
                        'note'           => $orderDetai->note,
                        'classification' => $orderDetai->classification,

                        'image' => $orderDetai->getImage(),

                        'quantity'       => $orderDetai->quantity,
                        'unit_price_cny' => $orderDetai->unit_price_cny,
                        'amount_cny'     => $orderDetai->amount_cny,
                    ];
                    array_push($data_temp, $temp);
                }

                $temp = [
                    'supplier_id' => $key_1,
                    'arr_items' => $data_temp,
                ];
                $supplierDB = Supplier::query()->where('id', $key_1)->first();
                if ($supplierDB) {
                    $temp['supplier_name'] = $supplierDB->name;
                }
                array_push($data_result, $temp);
            }
        }
        return resSuccessWithinData($data_result);
    }

    public function getListUserChat(Request $request)
    {
        $validationRules = [
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();

        $data_result = [];
        $userDBs = User::query()->where('status', 1)
                            ->where('id', '<>', 1)
                            ->where('dongdong_id', 0)
                            ->whereNull('deleted_at')
                            ->get();

        if (count($userDBs) > 0) {
            foreach ($userDBs as $key => $userDB) {
                $temp = [
                    'id' => (int) $userDB->id,
                    'name' => $userDB->name,
                ];
                array_push($data_result, $temp);
            }
        }
        return resSuccessWithinData($data_result);
    }

    public function proxyImage(Request $request)
    {
        $url = $request->get('url');

        if (!$url) {
            abort(404);
        }

        $content = file_get_contents($url);

        return response($content)
            ->header('Content-Type', 'image/png')
            ->header('Access-Control-Allow-Origin', '*');
    }
}
