<?php

namespace App\Console\Commands\Customer;

use Illuminate\Console\Command;

class AutoCloseAccountLongTimeOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer:auto-close-account-long-time-order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tự động đóng tài khoản';

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
        $job = new \App\Jobs\Customer\AutoCloseAccountLongTimeOrderJob();
        dispatch($job)->onQueue('export-1800');
    }
}
