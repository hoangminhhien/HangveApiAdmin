<?php

namespace App\Console\Commands;

use App\Services\ConsignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncDateDoneConsignmentOld extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:update-date-done-consignment-old';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cập nhật ngày hoàn thành đơn hàng cho dữ liệu cũ bảng consignments';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle(ConsignmentService $service)
    {
        $colorError = "\033[1m\033[31m";
        $colorSuccess = "\033[32m";
        echo 'Start: Update date_done to consignments'.PHP_EOL;
        try {
            $dataConsignmentDone = $service->getConsignmentDoneByDate();
            foreach ($dataConsignmentDone as $item) {
                $listOrderPackage = $item->orderPackage->toArray() ?? null;
                if ($item->date_done || empty($listOrderPackage)) {
                    continue;
                }
                $item->date_done = $listOrderPackage ? date("Y-m-d H:i:s",
                    strtotime($listOrderPackage[count($listOrderPackage) - 1]["updated_at"])) : $item->updated_at;
                $item->save();
                echo $colorSuccess.$item->id.": Consignment update finish \033[0m".PHP_EOL;
            }
            echo "Finish: Update date_done to consignments".PHP_EOL;
            Log::info('sync:update-date-done-consignment-old Success');
        } catch (\Exception $e) {
            echo $colorError."Error: Update date_done to consignments".PHP_EOL;
            Log::error($e->getMessage());
        }
    }
}
