<?php

namespace App\Observers;

use App\Constants\ReportConstant;
use App\Helpers\RandomHelper;
use App\Models\Customer;
use App\Models\ReportLevel;
use App\Services\ActivityService;
use App\Services\ConsignmentService;
use App\Services\CustomerService;
use App\Services\GroupService;
use App\Services\ReportService;
use App\Constants\CustomerConstant;

class CustomerObserver
{
    /**
     * Handle the Customer "created" event.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    public function created(Customer $customer)
    {
        // Phục vụ lúc đồng bộ dữ liệu
        $syncMaxPing = env('SYNC_MAX_PING', false);
        if ($syncMaxPing) {
            \Log::info('CustomerObserver created loại');
            return;
        }
        // \Log::info('CustomerObserver created');

        Customer::withoutEvents(function () use ($customer) {
            (new CustomerService())->updateCode($customer);
        });
        ReportLevel::query()->where(['organization_id' => request()->input('organization_id'), 'level' => 0])->increment('quantity');
        (new ReportService())->incrementQuantity(ReportConstant::QUANTITY_CUSTOMER);
        // (new GroupService())->setStaffForCustomer($customer);
    }

    /**
     * Handle the Customer "updated" event.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    public function updated(Customer $customer)
    {
        // Phục vụ lúc đồng bộ dữ liệu
        $syncMaxPing = env('SYNC_MAX_PING', false);
        if ($syncMaxPing) {
            \Log::info('CustomerObserver updated loại');
            return;
        }
//         \Log::info('CustomerObserver updated');

        (new GroupService())->changeStaffCustomerRelation($customer);
        (new ActivityService())->activitiesCustomer($customer);
        (new ConsignmentService())->syncWarehouseByCustomer($customer);
        if ($customer->getOriginal('last_order_at') != $customer->last_order_at && $customer->status == CustomerConstant::STATUS_INACTIVE) {
            $customer->update(['status' => CustomerConstant::STATUS_ACTIVE]);
        }
    }

    /**
     * Handle the Customer "deleted" event.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    public function deleted(Customer $customer)
    {
        Customer::withoutEvents(function () use ($customer) {
            $customer->deleted_by = getCurrentUserId();
            $customer->save();
        });
    }

    /**
     * Handle the Customer "restored" event.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    public function restored(Customer $customer)
    {
        $customer->orders()->withTrashed()->restore();
        $customer->consignments()->withTrashed()->restore();
        $customer->packages()->withTrashed()->restore();
        $customer->reportPack()->withTrashed()->restore();
        $customer->transactions()->withTrashed()->restore();
    }

    /**
     * Handle the Customer "force deleted" event.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    public function forceDeleted(Customer $customer)
    {
        $customer->orders()->withTrashed()->forceDelete();
        $customer->consignments()->withTrashed()->forceDelete();
        $customer->packages()->withTrashed()->forceDelete();
        $customer->reportPack()->withTrashed()->forceDelete();
        $customer->transactions()->withTrashed()->forceDelete();
    }

    /**
     * Handle the Customer "deleting" event
     * @param Customer $customer
     */
    public function deleting(Customer $customer)
    {
        //
    }
}
