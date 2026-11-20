<?php

namespace App\Console\Commands;

use App\Constants\OrderConstant;
use App\Jobs\SentNotificationOrderSlowQuoteJob;
use Illuminate\Console\Command;

class SentNotificationOrderSlowQuoteCommand1688 extends Command
{
    protected $signature = 'command:sent-notification-order-slow-quote-1688';

    protected $description = 'Command description';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $jobs = new SentNotificationOrderSlowQuoteJob(['ecommerce' => OrderConstant::ECOMMERCE_1688]);
        dispatch($jobs)->onQueue('sent-noti-admin');
    }
}
