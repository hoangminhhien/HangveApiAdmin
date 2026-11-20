<?php

namespace App\Jobs\Deposit;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ChargeAdmin implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Job này đảm bảo chắc chắn lệnh bên admin thực hiện xong thì các lệnh nạp từ app mới được thực thi
        \Log::info("Deposit.ChargeAdmin");
        sleep(3);
        \Log::info("Deposit.ChargeAdmin done");
    }
}
