<?php


namespace App\Services;


use App\Constants\CustomerConstant;
use App\Constants\DeliveryConstant;
use App\Constants\TransactionConstant;
use App\Helpers\ConvertHelper;
use App\Helpers\PaginateHelper;
use App\Helpers\RandomHelper;
use App\Http\Resources\Conversation\ConversationResource;
use App\Http\Resources\ListResource;
use App\Models\CustomerDelivery;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\Order;
use App\Models\OrderPackage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

use App\Constants\PackageConstant;


use App\Models\Chat;
use App\Models\Rep;
use App\Constants\RepConstant;
use GuzzleHttp\Client as Guzzle;

class HsService extends BaseService
{
    protected string $_resource = MessageResource::class;

    public function autoAiHs($chat_id)
    {
        $message = 'Có lỗi xảy ra khi AI trả lời';

        $chatModel = \App::make('App\Models\Chat');
        $chatDB = $chatModel->where('id', $chat_id)->first();

        // Lấy ra lịch sử cuối
        $historyItems = $this->getHistoryItems($chat_id, $chatDB);

        // Bắn về AI
        $guzzle = new Guzzle;
        $options = [
            'json' => [
                'content'        => $chatDB->content_last_send,
                'arr_attributes' => json_decode($chatDB->summary, true),
                'arr_options'    => json_decode($chatDB->options, true),
                'arr_rep_old'    => $historyItems,
                'token176a'      => $this->makeToken()
            ],
            'verify' => false
        ];
        if (env('APP_URL') == 'http://localhost:18018') {
            \Log::info('autoAiHs');
            \Log::info(json_encode($options['json'], JSON_UNESCAPED_UNICODE));
        }
        $result = $guzzle->request('POST', $this->getUrlAi() . '/api/v1/hs-push-hs-m280226', $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && $result['code'] == 200) {
            $reply = $result['data'];

            $message = $reply['bot_question'][1]['content'];

            $chatDB->summary = json_encode($reply['arr_attributes'], JSON_UNESCAPED_UNICODE);
            $chatDB->options = json_encode($reply['options'], JSON_UNESCAPED_UNICODE);
            $chatDB->content_last_send = $message;
            $chatDB->save();
        }
        return $message;
    }

    public function autoAiCheck($chat_id)
    {
        $message = null;

        $chatModel = \App::make('App\Models\Chat');
        $chatDB = $chatModel->where('id', $chat_id)->first();

        // Lấy ra lịch sử cuối
        $historyItems = $this->getHistoryItems($chat_id, $chatDB);

        // Bắn về AI
        $guzzle = new Guzzle;
        $options = [
            'json' => [
                'content'        => $chatDB->content_last_send,
                'arr_rep_old'    => $historyItems,
                'token176a'      => $this->makeToken()
            ],
            'verify' => false
        ];
        $result = $guzzle->request('POST', $this->getUrlAi() . '/api/v1/hs-push-check-m280226', $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && $result['code'] == 200) {
            $reply = $result['data'];

            if (env('APP_URL') == 'http://localhost:18018') {
                \Log::info('autoAiCheck');
                \Log::info(json_encode($reply, JSON_UNESCAPED_UNICODE));
            }

            $message = $reply['check'] == true ? $reply['message'] : null;
            // $chatDB->content_last_send = $message;
            $chatDB->save();
        }
        return $message;
    }

    // Lấy ds tin nhắn mà ko có TicketLast
    public function getHistoryItems($chat_id, $chatDB)
    {
        $arrType = [
            RepConstant::SOURCE_AI,
            RepConstant::SOURCE_USER,
            RepConstant::TYPE_ITEM,
        ];
        $repDBs = Rep::query()->where('chat_id', $chat_id)
                                ->where('id', '>=', $chatDB->rep_id_new)
                                ->whereIn('type', $arrType)
                                ->where('clear', RepConstant::CLEAR_INACTIVE)
                                ->orderBy('id', 'DESC')
                                ->whereNotNull('content')
                                ->limit(10)
                                ->offset(0)
                                ->get();
        $historyItems = [];
        if (count($repDBs) > 0) {
            foreach ($repDBs as $key => $messageDB) {
                $content = $messageDB->content;
                if ($messageDB->type == RepConstant::TYPE_ITEM) {
                    $arr_content = json_decode($content, true);
                    $content = $arr_content['name'];
                }
                $temp = [
                    "role"    => $messageDB->source == RepConstant::SOURCE_AI ? "assistant" : "user",
                    "content" => $content
                ];

                $historyItems[] = $temp;
            }
        }
        $historyItems = array_reverse($historyItems);
        return $historyItems;
    }

    public function getUrlAi()
    {
        $url = env('URL_AI');
        return $url;
    }

    public function makeToken()
    {
        return 'n4vhq1zst8mea6wbp2ry';
    }
}
