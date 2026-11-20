<?php

namespace App\Jobs\Customer;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\ActivityService;
use App\Services\CustomerService;
use App\Models\Customer;
use App\Models\Consignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Constants\CustomerConstant;

class AutoCloseAccountLongTimeOrderJob implements ShouldQueue
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
        \Log::info('---------- Start run AutoCloseAccountLongTimeOrderJob --------------');
        $user = getCurrentUser();
        $query = Customer::query();
        (new CustomerService)->getCustomerCloseAccount($query)->where('status', CustomerConstant::STATUS_ACTIVE)
            ->chunk(1000, function($customers) use ($user)
            {
                \Log::info($customers->count());
                foreach($customers as $key => $customer) {
                    $customer->update([
                        'status' => CustomerConstant::STATUS_INACTIVE
                    ]);
                    $dataActivities = [];
                    array_push($dataActivities, [
                        'subject_type'    => get_class($customer),
                        'subject_id'      => $customer->id,
                        'causer_type'     => get_class($user),
                        'causer_id'       => $user->id,
                        'log_name'        => 'customer_log',
                        'content'         => __('activity.auto_close_account'),
                        'object_id'       => $customer->id,
                        'properties'      => null,
                        'organization_id' => $user->organization_id ?? getOrganization(),
                        'created_at'      => date('Y-m-d H:i:s'),
                        'updated_at'      => date('Y-m-d H:i:s')
                    ]);
                    (new ActivityService)->setCustomerLogs($dataActivities);
                }
            });

        \Log::info('---------- End run AutoCloseAccountLongTimeOrderJob --------------');
    }
}
