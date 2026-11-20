<?php

namespace App\Jobs\Quote;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncStatusPack implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $orderPackage;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($orderPackage)
    {
        $this->orderPackage = $orderPackage;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $orderPackage = $this->orderPackage;
        // \Log::info('Quote-SyncStatusPack Job: ' . $orderPackage->id);

        $notiService = \App::make('App\Services\NotificationService');
        $notiService->pushStatusToQuote($orderPackage);
    }
}
