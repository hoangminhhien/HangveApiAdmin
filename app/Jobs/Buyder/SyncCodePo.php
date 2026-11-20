<?php

namespace App\Jobs\Buyder;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\OrderBuyder;
use App\Services\OrderBuyderService;

class SyncCodePo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $orderBuyder;
    public function __construct($orderBuyder)
    {
        $this->orderBuyder = $orderBuyder;
    }

    /**
     * @throws BindingResolutionException
     */
    public function handle(OrderBuyderService $orderBuyderService)
    {
        $orderBuyderService->updateOrderTaoBao($this->orderBuyder);
    }
}
