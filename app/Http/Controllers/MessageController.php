<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Services\MessageService;
use App\Constants\MessageConstant;
use App\Models\Order;

use App\Services\FcmTokenService;

class MessageController extends Controller
{
    public function __construct(MessageService $service)
    {
        $this->_service = $service;
    }

    public function listMessage(Request $request)
    {
        $validationRules = [
            'conversation_id' => 'required|exists:conversations,id',
            'page'            => 'required|numeric|min:0'
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $userOnline = \Auth::user();

        // Check xem có đúng hội thoại đứa đang login cầm hay không
        $conversationModel = \App::make('App\Models\Conversation');
        $conversaionDB = $conversationModel->where('id', $params['conversation_id']);
                                // ->where('user_id', $userOnline->id)
                                // ->first();

        // Nếu không có quyền thì chỉ fetch theo chính tài khoản cúa nó thôi
        $viewAll = $userOnline->checkUserHasPer('live_chat.view-full-list');
        if (!$viewAll) {
            $conversaionDB = $conversaionDB->where('user_id', $userOnline->id);
        }

        $conversaionDB  = $conversaionDB->first();

        if (is_null($conversaionDB)) {
            $result = [
                'error' => 'Hội thoại không tồn tại'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Reset số đếm
        if ($conversaionDB->unread_admin > 0) {
            // Số tổng
            // $userOnline->total_chat_unread -= $conversaionDB->unread_admin;
            // $userOnline->save();

            // Người không có quyền xem all mới được reset lại số đếm
            if ($userOnline->id == $conversaionDB->user_id) {
                // Theo đơn
                $conversaionDB->unread_admin = 0;
                $conversaionDB->save();

                // Đếm logic mới
                // $userOnline->total_chat_unread = $userOnline->getCountConversationUnread(); // Tp070125
                // $userOnline->save();
            }
        }

        // Lưu hội thoại nào đang được xem
        $userOnline->conversation_id_current = (int) $conversaionDB->id;
        $userOnline->save();

        // Nếu Ok
        $messageModel = \App::make('App\Models\Message');
        $per_page = 20;

        $model = $messageModel->where('conversation_id', $params['conversation_id']);

        $model = $model->orderBy('id', 'desc');
        $messageDBs = $model->paginate($per_page);

        $_paginateResource = \App\Http\Resources\PaginateJsonResource::class;
        $_resource = \App\Http\Resources\Message\MessageResource::class;
        return resSuccessWithinData(new $_paginateResource($messageDBs, $_resource));
    }

    public function storeMessage(Request $request)
    {
        $validationRules = [
            'conversation_id' => 'required|exists:conversations,id',
            'type'            => 'required|in:1,2,3,4,5,6,7,8',
            'content'         => 'required|max:500',
            'parent_id'       => 'nullable|exists:messages,id',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $userOnline = \Auth::user();

        // Check xem có đúng hội thoại đứa đang login cầm hay không
        $conversationModel = \App::make('App\Models\Conversation');
        $conversaionDB = $conversationModel->where('id', $params['conversation_id'])
                                ->where('user_id', $userOnline->id)
                                ->first();

        if (is_null($conversaionDB)) {
            $result = [
                'error' => 'Hội thoại không tồn tại'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        if ($conversaionDB->unread_client > 50) {
            $result = [
                'error' => 'Bạn không thể gửi thêm tin nhắn'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }


        try {
            \DB::beginTransaction();
            $messageDB = $this->handlerStoreMessage($conversaionDB, $userOnline, $params);
            \DB::commit();
            return resSuccessWithinData($messageDB);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }
    public function handlerStoreMessage($conversaionDB, $userOnline, $params)
    {
        $messageModel = \App::make('App\Models\Message');

        if (isset($params['parent_id']) == false) {
            $params['parent_id'] = 0;
        }

        $data_message = [
            'organization_id' => $userOnline->organization_id,
            'conversation_id' => (int) $conversaionDB->id,
            'source'          => MessageConstant::SOURCE_USER,
            'type'            => $params['type'],
            'content'         => $params['content'],
            'parent_id'       => $params['parent_id']
        ];
        $messageDB = $messageModel->create($data_message);

        // Cập nhật thời gian chat cuối
        $conversaionDB->time_last_send = date('Y-m-d H:i:s');
        $conversaionDB->time_last_send_admin = date('Y-m-d H:i:s');
        $conversaionDB->content_last_send = $params['content'];
        $conversaionDB->content_last_send = $this->makeContentMessageLast($userOnline, $params);
        $conversaionDB->unread_client += 1;

        // Không cần lưu số đếm khi nó đang đứng trong hội thoại
        $customerDB = $conversaionDB->customer;
        if ($customerDB->conversation_id_current == (int) $conversaionDB->id) {
            $conversaionDB->unread_client -= 1;
        }

        $conversaionDB->conversation_config_id = 2; // Đang xử lý
        $conversaionDB->save();

        // Tăng số đếm cho bên user client
        // $customerDB = $conversaionDB->customer;
        if ($conversaionDB->unread_client == 1) {
            $customerDB->total_chat_unread += 1;
            $customerDB->save();
        }

        // Đẩy queue check đổi trạng thái tự động
        $conversationConfigDB =  $this->getConversationConfig(0);
        $data_last_send_old = [
            'time_last_send_client' => $conversaionDB->time_last_send_client,
            'time_last_send_admin' => $conversaionDB->time_last_send_admin,
        ];
        $job = new \App\Jobs\LiveChat\AutoChangeStatusConversation(
            $data_message['conversation_id'], $conversationConfigDB, $data_last_send_old
        );
        dispatch($job)->delay(now()->addSeconds($conversationConfigDB->time ?? 10))->onQueue('auto-conversation');


        // Gửi noti trình duyệt
        if ($customerDB->conversation_id_current != (int) $conversaionDB->id) {
            $contentNoti = $conversaionDB->user_name . ' đã gửi: ' . $params['content'];
            if ($params['type']  != MessageConstant::TYPE_TEXT) {
                $contentNoti =  $this->makeContentMessageLast($userOnline, $params);
            }
            $job = new \App\Jobs\SendNotiLiveChat($customerDB, $contentNoti);
            dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-live-chat');


            // Đẩy noti cho app
            $payload['user_id'] = $conversaionDB->customer_id ?? 0;
            $fcmTokenService = app()->make(FcmTokenService::class);
            $title = 'Hàng Về có tin nhắn mới !';
            $body = 'Bạn có tin nhắn mới từ ' . $userOnline->name;
            $body = 'Nhấn để để xem ngay';
            $body = $contentNoti;
            $dataSent = [
                'type' => 'livechat',
                'body' => (string) $conversaionDB->id
            ];
            $fcmTokenService->sendMessageForApp($payload, $title, $body, $dataSent);
        }

        return [
            'total_chat_unread' => $customerDB->total_chat_unread,
            'message_id'        => (int)$messageDB->id,
            'content'           => $messageDB->content
        ];

        return $messageDB;
    }

    public function makeContentMessageLast($userOnline, $params)
    {
        $type = $params['type'];
        if ($type == MessageConstant::TYPE_IMAGE) {
            return $userOnline->name . ' đã gửi 1 ảnh';
        }
        if ($type == MessageConstant::TYPE_VIDEO) {
            return $userOnline->name . ' đã gửi 1 video';
        }
        if ($type == MessageConstant::TYPE_VOTE) {
            return $userOnline->name . ' đã đánh giá hội thoại ' . $params['content'] . ' sao';
        }
        if ($type == MessageConstant::TYPE_ITEM) {
            return $userOnline->name . ' đã gửi 1 sản phẩm';
        }
        if ($type == MessageConstant::TYPE_PDF) {
            return $userOnline->name . ' đã gửi 1 file PDF';
        }
        if ($type == MessageConstant::TYPE_WORD) {
            return $userOnline->name . ' đã gửi 1 file Word';
        }
        return $params['content'];
    }


    public function checkAfterTimeMessage(Request $request)
    {
        $validationRules = [
            'conversation_id' => 'required|exists:conversations,id',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        // Check xem có đúng hội thoại đứa đang login cầm hay không
        $conversationModel = \App::make('App\Models\Conversation');
        $conversaionDB = $conversationModel->where('id', $params['conversation_id'])->first();

        try {
            \DB::beginTransaction();
            $conversationConfigDB = $this->getConversationConfig(0);
            if (!is_null($conversationConfigDB)) {
                $data_last_send_old = [
                    'time_last_send_client' => $conversaionDB->time_last_send_client,
                    'time_last_send_admin' => $conversaionDB->time_last_send_admin,
                ];
                $this->checkAfterTimeMessageAction($conversaionDB, $conversationConfigDB, $data_last_send_old);
            }
            \DB::commit();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function checkAfterTimeMessageAction($conversaionDB, $conversationConfigDB, $data_last_send_old)
    {
        // Nếu lệch thời gian cũ thì té luôn không cần check
        if (
            $data_last_send_old['time_last_send_admin'] != $conversaionDB->time_last_send_admin ||
            $data_last_send_old['time_last_send_client'] != $conversaionDB->time_last_send_client
        ) {
            return;
        }
        // Check bình thường

        $time_check = $this->getTimeCheck($conversaionDB, $conversationConfigDB);

        $diff_admin  = strtotime($time_check) - strtotime($conversaionDB->time_last_send_admin);
        $diff_client = strtotime($time_check) - strtotime($conversaionDB->time_last_send_client);
        $int_time    = $conversationConfigDB->time;

        if ($diff_admin == $int_time && $diff_client >= $int_time) {
            // Set trạng thái tự động
            $conversaionDB->conversation_config_id = $conversationConfigDB->id;
            $conversaionDB->save();

            // Đẩy queue tiếp theo
            // $conversationConfigDB = $this->getConversationConfig($int_time);
            // if (!is_null($conversationConfigDB)) {
            //     $this->checkAfterTimeMessageAction($conversaionDB, $conversationConfigDB);
            // }
        }
    }

    public function getConversationConfig($time)
    {
        $conversationConfigModel = \App::make('App\Models\ConversationConfig');
        $conversationConfigDB = $conversationConfigModel->where('time', '>', $time)
                                                        ->where('id', '>', 2)
                                                        ->orderBy('time', 'asc')
                                                        ->first();
        return $conversationConfigDB;
    }

    public function getTimeCheck($conversaionDB, $conversationConfigDB)
    {
        $time_check_next = strtotime($conversaionDB->time_last_send_admin) + $conversationConfigDB->time;
        $time_check_next = date('Y-m-d H:i:s', $time_check_next);

        return $time_check_next;
    }

    public function editMessage(Request $request)
    {
        $validationRules = [
            'message_id' => 'required|exists:messages,id',
            'content'    => 'required|max:500',
            'type'       => 'required|in:1,2,3,4,5,6,7,8',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $userOnline = \Auth::user();

        $messageModel = \App::make('App\Models\Message');
        $messageDB = $messageModel->where('id', $params['message_id'])
                                ->where('source', MessageConstant::SOURCE_USER)
                                ->first();

        if (is_null($messageDB)) {
            $result = [
                'error' => 'Tin nhắn không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Check xem có đúng hội thoại đứa đang login cầm hay không
        $conversationModel = \App::make('App\Models\Conversation');
        $conversaionDB = $conversationModel->where('id', $messageDB->conversation_id)
                                ->where('user_id', $userOnline->id)
                                ->first();

        if (is_null($conversaionDB)) {
            $result = [
                'error' => 'Hội thoại không tồn tại'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();

            $messageDB->content = $params['content'];
            $messageDB->type = $params['type'];
            if (
                $params['type'] == MessageConstant::TYPE_RECALL &&
                $messageDB->content == $conversaionDB->content_last_send
            ) {
                $conversaionDB->content_last_send = 'Tin nhắn đã được thu hồi';
                $conversaionDB->save();
            }
            $messageDB->save();

            \DB::commit();
            return resSuccessWithinData([]);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }
}
