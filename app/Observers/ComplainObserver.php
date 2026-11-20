<?php

namespace App\Observers;

use App\Constants\ComplainConstant;
use App\Models\Complain;
use App\Models\ComplainStatusTime;
use App\Models\ReportComplain;
use App\Services\ActivityService;
use App\Services\OrderService;
use App\Services\ReportService;
use App\Services\Service;

class ComplainObserver
{
    private Service $_activityService;

    public function __construct()
    {
        $this->_activityService = new ActivityService();
    }

    public function updated(Complain $complain)
    {
        if(($old = $complain->getOriginal('status')) != $complain->status)
        {
            (new ReportService())->inDecrementByOrganization(ReportComplain::class, $complain->status, $old, 1, ['customer_id' => $complain->customer_id]);
            // update complain time
            ComplainStatusTime::query()->create(['complain_id' => $complain->id, 'key' => "$complain->status"]);
        }
    }

    public function created(Complain $complain)
    {
        ReportComplain::query()->firstOrCreate(['organization_id' => getOrganization(), 'customer_id' => $complain->customer_id])->increment(ComplainConstant::KEY_STATUS_PENDING);
        $this->_activityService->setOrderLog($complain, trans("activity.order_complain"), $complain->order_id);
        (new OrderService())->incrementByColumn($complain->order_id, 'complains_number');
        ComplainStatusTime::query()->create(['complain_id' => $complain->id, 'key' => "$complain->status"]);
    }
}
