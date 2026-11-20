<?php

namespace App\Observers;

use App\Models\Consignment;
use App\Services\ConsignmentService;
use App\Services\ActivityService;

class ConsignmentObserver
{
    private ConsignmentService $_service;

    public function __construct(
        ConsignmentService $service,
        ActivityService $activeService
    ) {
        $this->_service = $service;
        $this->_activityService = $activeService;
    }
    /**
     * Handle the Consignment "created" event.
     *
     * @param  \App\Models\Consignment  $consignment
     * @return void
     */
    public function created(Consignment $consignment)
    {
        //
    }

    /**
     * Handle the Consignment "updated" event.
     *
     * @param  \App\Models\Consignment  $consignment
     * @return void
     */
    public function updated(Consignment $consignment)
    {
        if (($oldStatus = $consignment->getOriginal('status')) !== $consignment->status) {
            $this->_service->reportStatuses($consignment->id, $consignment->status, $oldStatus);
            $this->_activityService->setConsignment(
                $consignment,
                trans("activity.consignment_$consignment->status"),
                $consignment->id
            );
        }
    }

    /**
     * Handle the Consignment "deleted" event.
     *
     * @param  \App\Models\Consignment  $consignment
     * @return void
     */
    public function deleted(Consignment $consignment)
    {
        //
    }

    /**
     * Handle the Consignment "restored" event.
     *
     * @param  \App\Models\Consignment  $consignment
     * @return void
     */
    public function restored(Consignment $consignment)
    {
        $consignment->details()->withTrashed()->restore();
        $consignment->statusTime()->withTrashed()->restore();
    }

    /**
     * Handle the Consignment "force deleted" event.
     *
     * @param  \App\Models\Consignment  $consignment
     * @return void
     */
    public function forceDeleted(Consignment $consignment)
    {
        $consignment->details()->withTrashed()->forceDelete();
        $consignment->statusTime()->withTrashed()->forceDelete();
    }
}
