<?php

namespace App\Jobs\Report;

use App\Services\ReportCustomerReOrders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReportCustomerReOrdersByCursorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $date;
    public array $userIds;
    public $list;
    public function __construct($date, $userIds, $list)
    {
        $this->date = $date;
        $this->userIds = $userIds;
        $this->list = $list;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ReportCustomerReOrders $reportCustomerReOrders)
    {
        $reportCustomerReOrders->cursorQuery($this->userIds, $this->date, $this->list);
        // Log::info('ReportCustomerReOrdersByCursorJob ' . $this->date);
    }
}
