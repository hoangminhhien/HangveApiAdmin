<?php

namespace App\Jobs\OneWarehouse;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncServicePackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $packageIds;
    protected $services;
    public function __construct($packageIds, $services)
    {
        $this->packageIds = $packageIds;
        $this->services = $services;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $packageIds = $this->packageIds;
        $services = $this->services;
        $data = [
            'package_ids' => $packageIds,
        ];
        $data =  array_merge($data, $services);
        $oneWarehouseService = \App::make('App\Services\OneWarehouseService');
        return $oneWarehouseService->forward('POST', 'open/sync/pack-service', $data);
    }
}
