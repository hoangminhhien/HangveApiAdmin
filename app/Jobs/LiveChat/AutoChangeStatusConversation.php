<?php

namespace App\Jobs\LiveChat;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as Guzzle;
use App\Constants\PackageConstant;

class AutoChangeStatusConversation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $conversation_id;
    protected $conversationConfigDB;
    protected $data_last_send_old;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($conversation_id, $conversationConfigDB, $data_last_send_old)
    {
        $this->conversation_id      = $conversation_id;
        $this->conversationConfigDB = $conversationConfigDB;
        $this->data_last_send_old   = $data_last_send_old;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Lấy ra hội thoại
        $conversationModel = \App::make('App\Models\Conversation');
        $conversaionDB = $conversationModel->where('id', $this->conversation_id)->first();
        $conversationConfigDB = $this->conversationConfigDB;
        $data_last_send_old = $this->data_last_send_old;

        // Log
        // \Log::info('AutoChangeStatusConversation: ' . $this->conversation_id . ' :time: ' . $conversationConfigDB->time);

        // Xử lý
        $this->checkAfterTimeMessageAction($conversaionDB, $conversationConfigDB, $data_last_send_old);
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
            // \Log::info('AutoChangeStatusConversation: Dính ' . $this->conversation_id . ' :time: ' . $conversationConfigDB->time);
            // Set trạng thái tự động
            $conversaionDB->conversation_config_id = $conversationConfigDB->id;
            $conversaionDB->save();

            // Đẩy queue tiếp theo nếu còn config
            // $conversationConfigNextDB = $this->getConversationConfig($int_time);
            // if (!is_null($conversationConfigNextDB)) {
            //     $time_run = $conversationConfigNextDB->time = $conversationConfigDB->time;
            //     $job = new \App\Jobs\LiveChat\AutoChangeStatusConversation($this->conversation_id, $conversationConfigNextDB);
            //     dispatch($job)->delay(now()->addSeconds($time_run))->onQueue('cron-data-2023');
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
}
