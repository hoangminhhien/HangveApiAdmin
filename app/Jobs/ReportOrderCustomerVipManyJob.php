<?php

namespace App\Jobs;

use App\Services\ReportOrderCustomerVip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReportOrderCustomerVipManyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $weekEndDate;
    public $weekStartDate;
    public $user_group_ids;
    public $label_ordered;
    public $level;
    public $label_order;
    public $label_order_new;
    public $not_group_id;
    public function __construct
    (
        $weekStartDate,
        $weekEndDate,
        $user_group_ids,
        $label_order,
        $label_order_new,
        $label_ordered,
        $level,
        $not_group_id
    )
    {
        $this->weekStartDate = $weekStartDate;
        $this->weekEndDate = $weekEndDate;
        $this->user_group_ids = $user_group_ids;
        $this->label_order = $label_order;
        $this->label_order_new = $label_order_new;
        $this->label_ordered = $label_ordered;
        $this->level = $level;
        $this->not_group_id = $not_group_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws BindingResolutionException
     */
    public function handle(ReportOrderCustomerVip $reportOrderCustomerVip)
    {
        Log::info(' ReportOrderCustomerVipManyJob Start ' . $this->weekStartDate);
        $reportOrderCustomerVip->processData
        (
            $this->weekStartDate,
            $this->weekEndDate,
            $this->user_group_ids,
            $this->label_order,
            $this->label_order_new,
            $this->label_ordered,
            $this->level,
            $this->not_group_id
        );
        Log::info(' ReportOrderCustomerVipManyJob End ' . $this->weekEndDate);
    }
}
