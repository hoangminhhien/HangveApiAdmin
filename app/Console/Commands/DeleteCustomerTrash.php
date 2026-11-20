<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DeleteCustomerTrash extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer-trash:delete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Xoá cứng dữ liệu khách hàng khi đã xoá mềm';

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
        $customerService    = \App::make('App\Services\CustomerService');
        $orderService       = \App::make('App\Services\OrderService');
        $consignmentService = \App::make('App\Services\ConsignmentService');
        $orderPackService   = \App::make('App\Services\OrderPackageService');
        $transactionService = \App::make('App\Services\TransactionService');

        // delete customer
        $customerService->forceDeleteCustomer();
        // delete order
        $orderService->forceDeleteOrder();
        // delete consignment
        $consignmentService->forceDeleteConsignment();
        // delete pack
        $orderPackService->forceDeletePack();
        // delete transaction
        $transactionService->forceDeleteTransaction();
    }
}
