<?php

namespace App\Jobs\OrderPackage;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use App\Models\OrderPackage;
use App\Constants\PackageConstant;

class SyncDateDone implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        \Log::info("-------Start queue sync date_done--------");
        // $start = Carbon::now()->startOfYear();
        // $end = Carbon::now();
        $packages = OrderPackage::where('status', PackageConstant::STATUS_RECEIVED_GOODS)->whereNull('thuongdo_id')
            ->whereNull('date_done')
            ->chunk(1000, function($packages)
            {
                var_dump($packages->count());
                foreach($packages as $package){
                    $package->date_done = $package->updated_at;
                    $package->save();
                }
            });
        \Log::info("-------End queue sync date_date--------");
    }
}
