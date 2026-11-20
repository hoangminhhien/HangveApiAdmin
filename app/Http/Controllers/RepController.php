<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Services\RepService;
use App\Constants\MessageConstant;
use App\Constants\RepConstant;
use App\Models\Order;
use App\Models\Rep;

use App\Services\FcmTokenService;

class RepController extends Controller
{
    public function __construct(RepService $service)
    {
        $this->_service = $service;
    }

    public function listMessage(Request $request)
    {
        $validationRules = [
            'chat_id' => 'required|exists:chat_ais,id',
            'page'    => 'required|numeric|min:0'
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $userOnline = \Auth::user();

        // Check xem có đúng hội thoại đứa đang login cầm hay không
        $chatModel = \App::make('App\Models\Chat');
        $chatDB = $chatModel->where('id', $params['chat_id']);

        // Nếu không có quyền thì chỉ fetch theo chính tài khoản cúa nó thôi
        $viewAll = $userOnline->checkUserHasPer('live_chat.view-full-list');
        if (!$viewAll) {
            $chatDB = $chatDB->where('user_id', $userOnline->id);
        }

        $chatDB  = $chatDB->first();

        if (is_null($chatDB)) {
            $result = [
                'error' => 'Hội thoại không tồn tại'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Nếu Ok
        $repModel = \App::make('App\Models\Rep');
        $per_page = 20;

        $model = $repModel->where('chat_id', $params['chat_id']);

        $model = $model->orderBy('id', 'desc');
        $messageDBs = $model->paginate($per_page);

        $_paginateResource = \App\Http\Resources\PaginateJsonResource::class;
        $_resource = \App\Http\Resources\Rep\RepResource::class;
        return resSuccessWithinData(new $_paginateResource($messageDBs, $_resource));
    }

    public function storeMessageV2(Request $request)
    {
        $validationRules = [
            'chat_id'   => 'required|exists:chat_ais,id',
            'type'      => 'required|in:1,2,3,4,5,6,7,8',
            'content'   => 'required|max:2000',
            'parent_id' => 'nullable',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        if (isset($params['parent_id']) == false) {
            $params['parent_id'] = 0;
        }

        $userOnline = \Auth::user();

        // Check xem có đúng hội thoại đứa đang login cầm hay không
        $chatModel = \App::make('App\Models\Chat');
        $chatDB = $chatModel->where('id', $params['chat_id']);
                                // ->where('user_id', $userOnline->id)
                                // ->first();

        $viewAll = $userOnline->checkUserHasPer('chat_ai.view-full-list');
        if (!$viewAll) {
            $chatDB = $chatDB->where('user_id', $userOnline->id);
        }
        $chatDB = $chatDB->first();


        if (is_null($chatDB)) {
            $result = ['error' => 'Hội thoại không tồn tại'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }


        if ($params['parent_id'] > 0) {
            $repParentDB = Rep::query()->where('id', $params['parent_id'])
                                    ->where('type', RepConstant::TYPE_OPTION)
                                    ->first();
            if ($repParentDB) {
                $arr_rep = json_decode($repParentDB->content, true);
                if ($arr_rep['attribute_name'] != $chatDB->attribute_name) {
                    $result = ['error' => 'Không được phép chọn lại thuộc tính này'];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
            }
        }


        try {
            \DB::beginTransaction();
            $repAi = $this->_service->storeRep($params, $chatDB, $userOnline);
            if ($repAi == null) {
                $result = ['error' => 'AI đang không phản hồi'];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            \DB::commit();
            $repAi = new \App\Http\Resources\Rep\RepResource($repAi);
            return resSuccessWithinData($repAi);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function storeMessage(Request $request)
    {
        $validationRules = [
            'chat_id'   => 'required|exists:chat_ais,id',
            'type'      => 'required|in:1,2,3,4,5,6,7,8',
            'content'   => 'required|max:2000',
            'parent_id' => 'nullable|exists:rep_ais,id',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $userOnline = \Auth::user();

        // Check xem có đúng hội thoại đứa đang login cầm hay không
        $chatModel = \App::make('App\Models\Chat');
        $chatDB = $chatModel->where('id', $params['chat_id'])
                                ->where('user_id', $userOnline->id)
                                ->first();

        if (is_null($chatDB)) {
            $result = ['error' => 'Hội thoại không tồn tại'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        try {
            \DB::beginTransaction();
            // Nếu người dùng nhắn không phải tiếng Việt thì báo lõi
            $sendVN = $this->checkSendVN($params);
            if ($sendVN) {
                $messageDB = $this->handlerStoreMessage($chatDB, $userOnline, $params);
            } else {
                $messageDB = $this->errorSentDiffVN($chatDB, $userOnline, $params);
            }
            \DB::commit();
            $messageDB = new \App\Http\Resources\Rep\RepResource($messageDB);
            return resSuccessWithinData($messageDB);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function checkSendVN($params)
    {
        $content = $params['content'];
        if ($params['type'] == RepConstant::TYPE_ITEM) {
            $arr_content = json_decode($params['content'], true);
            $content = $arr_content['name'];
        }
        $result_check = $this->isVietnamese($content);
        return $result_check;
    }

    public function isVietnamese($str) {
        // Biểu thức chính quy kiểm tra tiếng Việt có dấu
        $pattern = '/[áàảãạâấầẩẫậăắằẳẵặéèẻẽẹêếềểễệíìỉĩịóòỏõọôốồổỗộơớờởỡợúùủũụưứừửữựýỳỷỹỵđĐ0123456789.abcdefghijklmnopqrstuvwxyz.ABCDEFGHIJKLMNOPQRSTUVWXYZ]/u';
        return preg_match($pattern, $str);
    }

    public function errorSentDiffVN($chatDB, $userOnline, $params)
    {
        $repModel = \App::make('App\Models\Rep');

        if (isset($params['parent_id']) == false) {
            $params['parent_id'] = 0;
        }

        $data_rep = [
            'organization_id' => $userOnline->organization_id,
            'chat_id'         => (int) $chatDB->id,
            'source'          => RepConstant::SOURCE_USER,
            'type'            => $params['type'],
            'content'         => $params['content'],
            'parent_id'       => $params['parent_id'],
            'clear'           => RepConstant::CLEAR_ACTIVE,
        ];
        $repDB = $repModel->create($data_rep);

        $data_rep = [
            'organization_id' => $userOnline->organization_id,
            'chat_id'         => (int) $chatDB->id,
            'source'          => RepConstant::SOURCE_AI,
            'type'            => RepConstant::TYPE_TEXT,
            'content'         => 'Hãy cung cấp thông tin sản phẩm bằng tiếng việt',
            'parent_id'       => 0,
            'clear'           => RepConstant::CLEAR_ACTIVE,
        ];
        $repDB = $repModel->create($data_rep);

        $chatDB->time_last_send = date('Y-m-d H:i:s');
        $chatDB->content_last_send = 'Hãy cung cấp thông tin sản phẩm bằng tiếng việt';
        $chatDB->save();

        return $repDB;
    }

    public function handlerStoreMessage($chatDB, $userOnline, $params)
    {
        $repModel = \App::make('App\Models\Rep');

        if (isset($params['parent_id']) == false) {
            $params['parent_id'] = 0;
        }

        $data_rep = [
            'organization_id' => $userOnline->organization_id,
            'chat_id'         => (int) $chatDB->id,
            'source'          => RepConstant::SOURCE_USER,
            'type'            => $params['type'],
            'content'         => $params['content'],
            'parent_id'       => $params['parent_id']
        ];
        $repDB = $repModel->create($data_rep);

        // Cập nhật thời gian chat cuối
        $chatDB->time_last_send = date('Y-m-d H:i:s');
        $chatDB->content_last_send = $params['content'];
        $chatDB->content_last_send = $this->makeContentMessageLast($userOnline, $params);

        if ($params['type'] == RepConstant::TYPE_ITEM) {
            $chatDB->rep_id_new = $repDB->id;
            $chatDB->summary = json_encode([], JSON_UNESCAPED_UNICODE);
            $chatDB->options = json_encode([], JSON_UNESCAPED_UNICODE);
        }
        $chatDB->save();

        // Check xem người dùng có yêu cầu gợi ý sản phẩm ra không
        $showSugget = false;
        $keywordsSuggest = [
            'có những',
            'những loại',
            'loại nào',
            'có loại nào',
            'có mấy loại',
            'có mấy dạng',
            'có những dạng',
            'có những gì',
            'có gì',
            'có gì vậy',
            'gợi ý',
            'gợi ý giúp',
            'cho tôi xem',
            'cho xem',
            'xem các',
            'xem loại',
            'option',
            'options',
            'list',
            'danh sách',
            'các loại',
            'các dạng',
            'các lựa chọn',
            'lựa chọn nào',
            'có những'
        ];

        $message = mb_strtolower($params['content']);
        foreach ($keywordsSuggest as $k) {
            if (str_contains($message, $k)) {
                $showSugget = true;
            }
        }

        $options = json_decode($chatDB->options, true);
        if ($showSugget == true && count($options) > 0) {
            $optionString = implode(' - ', $options);
            $aiRep = "Các giá trị có thể chọn là : " . $optionString ;
        } else {
            // Check xem có cần hỏi thuế, chính sách không
            $hsService  = \App::make('App\Services\HsService');
            $aiRep = $hsService->autoAiCheck($chatDB->id);
            if ($aiRep == null) {
                // Bắn sang AI để nó trả lời
                $hsService  = \App::make('App\Services\HsService');
                $aiRep = $hsService->autoAiHs($chatDB->id);
            }
        }

        // AI Phản hồi
        $data_ai = [
            'organization_id' => $userOnline->organization_id,
            'chat_id'         => (int) $chatDB->id,
            'source'          => RepConstant::SOURCE_AI,
            'type'            => RepConstant::TYPE_TEXT,
            'content'         => $aiRep,
            'parent_id'       => $params['parent_id']
        ];

        $aiDB = $repModel->create($data_ai);

        return $aiDB;
    }

    public function makeContentMessageLast($userOnline, $params)
    {
        $type = $params['type'];
        if ($type == RepConstant::TYPE_IMAGE) {
            return $userOnline->name . ' đã gửi 1 ảnh';
        }
        if ($type == RepConstant::TYPE_VIDEO) {
            return $userOnline->name . ' đã gửi 1 video';
        }
        if ($type == RepConstant::TYPE_VOTE) {
            return $userOnline->name . ' đã đánh giá hội thoại ' . $params['content'] . ' sao';
        }
        if ($type == RepConstant::TYPE_ITEM) {
            $arr_content = json_decode($params['content'], true);
            return $userOnline->name . ' đã gửi 1 sản phẩm: ' . $arr_content['name'];
        }
        if ($type == RepConstant::TYPE_PDF) {
            return $userOnline->name . ' đã gửi 1 file PDF';
        }
        if ($type == RepConstant::TYPE_WORD) {
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
