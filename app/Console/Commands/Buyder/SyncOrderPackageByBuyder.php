<?php

namespace App\Console\Commands\Buyder;

use Illuminate\Console\Command;
use App\Jobs\Buyder\SyncOrderPackageByBuyder as JobSyncOrderPackageByBuyder;

class SyncOrderPackageByBuyder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:orderpackage-by-buyder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tự động tạo kiện dựa vào đơn hàng trên buyder';

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
        dispatch(new JobSyncOrderPackageByBuyder);
    }
}
