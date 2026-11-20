<?php

namespace App\Console\Commands\LiveChat;

use Illuminate\Console\Command;

class ClearConversation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear-conversation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Xóa hội thoại nào mà content_last_send null';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
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
}
