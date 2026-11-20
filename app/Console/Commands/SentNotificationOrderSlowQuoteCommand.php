<?php

namespace App\Console\Commands;

use App\Jobs\SentNotificationOrderSlowQuoteJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SentNotificationOrderSlowQuoteCommand extends Command
{
    protected $signature = 'command:sent-notification-order-slow-quote';

    protected $description = 'Command description';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        \Log::info(">>> Command thực sự đang chạy lúc: " . now());

        $jobs = new SentNotificationOrderSlowQuoteJob([]);
        dispatch($jobs)->onQueue('sent-noti-admin');
    }
}
