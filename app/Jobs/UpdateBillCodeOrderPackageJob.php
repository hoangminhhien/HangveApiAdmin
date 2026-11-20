<?php

namespace App\Jobs;

use App\Constants\PackageConstant;
use App\Factories\BasePlatformFactory;
use App\Models\Order;
use App\Models\OrderPackage;
use App\Services\OrderPackageService;
use App\Services\PlatformServices\BasePlatformService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateBillCodeOrderPackageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private $item;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($item)
    {
        $this->item = $item;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        $item = $this->item;
        $orderPackageService = new OrderPackageService();
        $orderPackageService->handleUpdateBillCode($item);
    }
}
