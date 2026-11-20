<?php

namespace App\Jobs\SyncTD;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as Guzzle;

use App\Models\Customer;

class SyncResetPassCustomerNotLogin implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $customerDB;
    protected $passDefault;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($customerDB, $passDefault)
    {
        $this->customerDB = $customerDB;
        $this->passDefault = $passDefault;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $customerDB = $this->customerDB;
        $passDefault = $this->passDefault;

        // \Log::info('SyncResetPassCustomerNotLogin: ' . $customerDB->id);

        // Xử lý
        $customerDB->password = $passDefault;
        $customerDB = Customer::withoutEvents(function () use ($customerDB) {
            $customerDB->save();
            return $customerDB;
        });
    }
}
