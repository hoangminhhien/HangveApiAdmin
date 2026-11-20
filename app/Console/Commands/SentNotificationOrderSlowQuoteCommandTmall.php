<?php

namespace App\Console\Commands;

use App\Constants\OrderConstant;
use App\Jobs\SentNotificationOrderSlowQuoteJob;
use Illuminate\Console\Command;

class SentNotificationOrderSlowQuoteCommandTmall extends Command
{
    protected $signature = 'command:sent-notification-order-slow-quote-tmall';

    protected $description = 'Command description';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $jobs = new SentNotificationOrderSlowQuoteJob(['ecommerce' => OrderConstant::ECOMMERCE_T_MALL]);
        dispatch($jobs)->onQueue('sent-noti-admin');
    }
}
