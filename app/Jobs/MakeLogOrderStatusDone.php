<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Notifications\PushNotiAdmin;

class MakeLogOrderStatusDone implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userOnline;
    protected $contentLog;
    protected $order;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($userOnline, $contentLog, $order)
    {
        $this->userOnline = $userOnline;
        $this->contentLog = $contentLog;
        $this->order = $order;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Sau khi đơn hàng hoàn thành thì ghi log (mục đích để cho cái log này lưu sau cái log Hoàn thành)
        $userOnline = $this->userOnline;
        $contentLog = $this->contentLog;
        $order = $this->order;


        $logService = \App::make('App\Services\ActivityService');
        $logService->setOrderLog($userOnline, $contentLog, $order->id);
    }
}
