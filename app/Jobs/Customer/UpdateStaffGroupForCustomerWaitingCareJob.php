<?php

namespace App\Jobs\Customer;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\ActivityService;
use App\Models\Label;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\CustomerService;

class UpdateStaffGroupForCustomerWaitingCareJob implements ShouldQueue
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
        \Log::info('---------- Start run UpdateStaffGroupForCustomerWaitingCareJob --------------');
        $user = getCurrentUser();
        $customerService = \App::make('App\Services\CustomerService');
        $times =  [
            '2022-12-01 00:00:00',
            '2024-12-31 23:59:59'
        ];
        $labelIds = Label::whereNotIn('code', [
            Label::KHONG_TUONG_TAC_DUOC,
            Label::KHONG_CO_NHU_CAU,
            Label::MAT_HANG_KHONG_NHAP_DUOC
        ])->pluck('id')->toArray();
        $query = Customer::query();
        (new CustomerService)->getCustomerWaitingForCare($query)->whereNotNull('staff_group_id')
            ->whereNotNull('staff_group_id')
            ->chunk(1000, function($customers) use ($user)
                {
                    \Log::info($customers->count());
                    foreach($customers as $key => $customer) {
                        $customer->update([
                            'staff_group_id' => null
                        ]);
                        $dataActivities = [];
                        array_push($dataActivities, [
                            'subject_type'    => get_class($customer),
                            'subject_id'      => $customer->id,
                            'causer_type'     => get_class($user),
                            'causer_id'       => $user->id,
                            'log_name'        => 'customer_log',
                            'content'         => __('activity.unassigned_staff_group', ['name' => $user->name]),
                            'object_id'       => $customer->id,
                            'properties'      => null,
                            'organization_id' => $user->organization_id ?? getOrganization(),
                            'created_at'      => date('Y-m-d H:i:s'),
                            'updated_at'      => date('Y-m-d H:i:s')
                        ]);
                        (new ActivityService)->setCustomerLogs($dataActivities);
                    }
                });
        \Log::info('---------- End run UpdateStaffGroupForCustomerWaitingCareJob --------------');
    }
}
