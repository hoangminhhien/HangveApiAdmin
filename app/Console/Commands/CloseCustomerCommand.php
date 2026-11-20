<?php

namespace App\Console\Commands;

use App\Services\CustomerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CloseCustomerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:close-customer';

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

    public function handle(CustomerService $customerService)
    {
        Log::info('CloseCustomerCommand start ' . date('d-m-Y H:i:s'));
        $customerService->closeCustomer();
        Log::info('CloseCustomerCommand finish ' . date('d-m-Y H:i:s'));
    }
}
