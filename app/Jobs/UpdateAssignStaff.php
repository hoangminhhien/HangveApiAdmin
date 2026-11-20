<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateAssignStaff implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $staffGroupIdOld;
    protected $staffGroupIdNew;
    protected $userId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($staffGroupIdOld, $staffGroupIdNew, $userId)
    {
        $this->staffGroupIdOld = $staffGroupIdOld;
        $this->staffGroupIdNew = $staffGroupIdNew;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $staffGroupIdOld = $this->staffGroupIdOld;
        $staffGroupIdNew = $this->staffGroupIdNew;
        $userId = $this->userId;
        $customerService = new CustomerService();
        Customer::query()
            ->where('staff_group_id', $staffGroupIdOld)
            ->chunk(600, function ($customers) use ($staffGroupIdNew, $userId, $customerService) {
            // update customer all
            $staffGroupId = $staffGroupIdNew;
            foreach ($customers as $customer) {
                if(count($staffGroupId) == 0) {
                    $staffGroupId = $staffGroupIdNew;
                }
                $staffGroup = $staffGroupId[0];
                array_shift($staffGroupId);
                $params['staff_group_id_old'] = $customer->staff_group_id; // Phục vụ cho việc chuyển chat
                $params['is_assign_all'] = 1; // Phục vụ cho việc chuyển chat
                $customer->update([
                    'staff_group_id' => $staffGroup,
                    'staff_group_time_assign' => date('Y-m-d H:i:s')
                ]);
                $customerService->updateStaffLogic($customer, $params, $userId);
            }
        });
        \Log::info('------------End Assign Staff All-----------');
    }
}
