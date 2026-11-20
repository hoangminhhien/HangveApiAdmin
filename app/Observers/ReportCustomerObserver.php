<?php

namespace App\Observers;

use App\Models\ReportCustomer;

class ReportCustomerObserver
{
    /**
     * Handle the ReportCustomer "created" event.
     *
     * @param  \App\Models\ReportCustomer  $reportCustomer
     * @return void
     */
    public function created(ReportCustomer $reportCustomer)
    {
        //
    }

    /**
     * Handle the ReportCustomer "updated" event.
     *
     * @param  \App\Models\ReportCustomer  $reportCustomer
     * @return void
     */
    public function updated(ReportCustomer $reportCustomer)
    {
        \DB::afterCommit(function () use ($reportCustomer) {
           $this->check($reportCustomer);
        });
    }

    public function check($reportCustomer)
    {
        // \Log::info('go ReportCustomer');
        $blanceNew =  $reportCustomer->balance_amount;
        $blance_negative_last = $reportCustomer->blance_negative_last;

        if ($blanceNew < 0) {
            // \Log::info('go ReportCustomer1');
            $reportCustomer->blance_negative_last = date('Y-m-d H:i:s');
            $reportCustomer = ReportCustomer::withoutEvents(function () use ($reportCustomer) {
                $reportCustomer->save();
                return $reportCustomer;
            });
        }

        if ($blanceNew >= 0 && $blance_negative_last != null) {
            // \Log::info('go ReportCustomer2');
            $reportCustomer->blance_negative_last = null;
            $reportCustomer = ReportCustomer::withoutEvents(function () use ($reportCustomer) {
                $reportCustomer->save();
                return $reportCustomer;
            });
        }
    }

    /**
     * Handle the ReportCustomer "deleted" event.
     *
     * @param  \App\Models\ReportCustomer  $reportCustomer
     * @return void
     */
    public function deleted(ReportCustomer $reportCustomer)
    {
        //
    }

    /**
     * Handle the ReportCustomer "restored" event.
     *
     * @param  \App\Models\ReportCustomer  $reportCustomer
     * @return void
     */
    public function restored(ReportCustomer $reportCustomer)
    {
        //
    }

    /**
     * Handle the ReportCustomer "force deleted" event.
     *
     * @param  \App\Models\ReportCustomer  $reportCustomer
     * @return void
     */
    public function forceDeleted(ReportCustomer $reportCustomer)
    {
        //
    }
}
