<?php

namespace App\Jobs\Quote;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncUpdateBillCodePackOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data_push;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data_push)
    {
        $this->data_push  = $data_push;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        // $hangve_pack_id  = $this->hangve_pack_id;
        // $bill_code_old   = $this->bill_code_old;
        // $bill_code_new   = $this->bill_code_new;
        // $hangve_order_id = $this->hangve_order_id;

        $data_push = $this->data_push;

        \Log::info('Quote-SyncUpdateBillCodePackOrder Job: ' . json_encode($data_push));

        $notiService = \App::make('App\Services\NotificationService');
        $notiService->syncUpdateBillCodePackOrder($data_push);
    }
}
