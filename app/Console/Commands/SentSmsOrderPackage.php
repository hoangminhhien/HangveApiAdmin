<?php

namespace App\Console\Commands;

use App\Jobs\SentSmsOrderPackageJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SentSmsOrderPackage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sent-sms-order-package';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $config = env('SEND_SMS_ORDER_PACKAGE', '');
        Log::info('SEND_SMS_ORDER_PACKAGE ' . $config);
        if ($config == 'PRODUCT') {
            $job = new SentSmsOrderPackageJob();
            dispatch($job)->onQueue('sent-sms-customer');
        }
    }
}
