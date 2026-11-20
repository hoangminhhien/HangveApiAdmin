<?php

namespace App\Console\Commands;

use App\Services\OrderPackageService;
use Illuminate\Console\Command;

class UpdateOrCreateBillCodeOrderPackage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:create-or-update-bill-code-order-package';

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


    public function handle(OrderPackageService $orderPackageService)
    {
        $orderPackageService->updateBillCode([]);
    }
}
