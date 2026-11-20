<?php

namespace App\Console\Commands\Customer;

use Illuminate\Console\Command;
use App\Jobs\Customer\UpdateStaffGroupIsForCustomerLongTimeJob;

class UpdateStaffGroupIsByCustomerLongTime extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:staff-group-id-by-customer-longtime';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gán lại nhân viên quản lý với KH lâu chưa lên đơn';

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
        try {
            dispatch(new UpdateStaffGroupIsForCustomerLongTimeJob())->onQueue('sync-data-report');
        } catch (\Exception $e) {
            \Log::error($e);
        }
    }
}
