<?php

namespace App\Console\Commands\Order;

use Illuminate\Console\Command;

class AutoCancelOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto:cancel-order';

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

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        \Log::info("hello");
        $job = new \App\Jobs\Order\AutoCancelOrderJob();
        dispatch($job)->onQueue('export-1800');
    }
}
