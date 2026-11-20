<?php

namespace App\Console\Commands;

use App\Constants\OrderConstant;
use App\Jobs\SentNotificationOrderSlowQuoteJob;
use Illuminate\Console\Command;

class SentNotificationOrderSlowQuoteCommandTaoBao extends Command
{
    protected $signature = 'command:sent-notification-order-slow-quote-taobao';

    protected $description = 'Command description';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $jobs = new SentNotificationOrderSlowQuoteJob(['ecommerce' => OrderConstant::ECOMMERCE_TAO_BAO]);
        dispatch($jobs)->onQueue('sent-noti-admin');
    }
}
