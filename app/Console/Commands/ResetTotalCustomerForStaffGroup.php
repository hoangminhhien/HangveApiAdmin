<?php

namespace App\Console\Commands;

use App\Services\GroupService;
use Illuminate\Console\Command;

class ResetTotalCustomerForStaffGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staff-group:reset-total-customer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset total customer for staff group';

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
    public function handle(GroupService $service)
    {
        echo "Start Reset". PHP_EOL;

        try {
            $service->resetTotalCustomer();

            \Log::info('Reset total customer success');
        } catch (\Exception $e) {
            \Log::info('Failed: '. $e->getMessage());
        }

        echo "Finish Reset". PHP_EOL;
    }
}
