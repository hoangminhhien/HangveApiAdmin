<?php

namespace App\Jobs\Report;

use App\Models\ReportOrderProvince;
use App\Services\ReportProvinceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncReportOrderProvinceByIdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $_itemProvince;
    protected $_key;
    protected $_dateByType;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($itemProvince, $key, $dateByType)
    {
        $this->_itemProvince = $itemProvince;
        $this->_key = $key;
        $this->_dateByType = $dateByType;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ReportProvinceService $reportProvinceService)
    {
        $itemProvince = $this->_itemProvince ?? [];
        $provinceId = $this->_key;
        $dateType = $this->_dateByType;

        $colorSuccess = "\033[32m";
        echo $colorSuccess."Start report_order_province_by_id: ".$provinceId." \033[0m".PHP_EOL;
        DB::beginTransaction();
        try {
            $dataCreate = [];
            if (!count($itemProvince)) {
                DB::commit();
                return;
            }
            foreach ($itemProvince as $item) {
                $dataCreate = $reportProvinceService->mapDateOrderByProvince($dataCreate, $item, $provinceId,
                    $dateType);
            }
            if (!count($dataCreate)) {
                echo $colorSuccess."Done (data empty) report_order_province_by_id: ".$provinceId." \033[0m".PHP_EOL;
                DB::commit();
                return;
            }
            foreach ($dataCreate as $key => $itemList) {
                foreach ($itemList as $keyChild => $itemChild) {
                    $dataCheckConditions = [
                        "province_id" => $key,
                        "type" => $keyChild
                    ];

                    $itemChild["total_customer"] = count($itemChild["customer_ids"]);
                    $itemChild["total_customer_created"] = count($itemChild["customer_ids_created"]);
                    $itemChild["total_customer_deposit"] = count($itemChild["customer_ids_deposit"]);
                    $itemChild["date_report"] = date("Y-m-d");
                    $itemChild["conversion_rate_customer"] = 0;
                    $itemChild["conversion_rate_order"] = 0;
                    if ($itemChild["total_customer_deposit"]) {
                        if ($itemChild["total_customer_created"]) {
                            $itemChild["conversion_rate_customer"] = ($itemChild["total_customer_deposit"] / $itemChild["total_customer_created"]) * 100;
                        } else {
                            $itemChild["conversion_rate_customer"] = $itemChild["total_customer_deposit"] * 100;
                        }
                        $itemChild["conversion_rate_customer"] = $itemChild["conversion_rate_customer"] > 0 ? round($itemChild["conversion_rate_customer"],
                            2) : 0;
                    }

                    if ($itemChild["total_order_deposit"]) {
                        if ($itemChild["total_order_created"]) {
                            $itemChild["conversion_rate_order"] = ($itemChild["total_order_deposit"] / $itemChild["total_order_created"]) * 100;
                        } else {
                            $itemChild["conversion_rate_order"] = $itemChild["total_order_deposit"] * 100;
                        }
                        $itemChild["conversion_rate_order"] = $itemChild["conversion_rate_order"] > 0 ? round($itemChild["conversion_rate_order"],
                            2) : 0;
                    }

                    unset($itemChild["customer_ids"]);
                    unset($itemChild["customer_ids_deposit"]);
                    unset($itemChild["customer_ids_created"]);
                    $dataCreateOrUpdate = array_merge($itemChild, $dataCheckConditions);

                    ReportOrderProvince::query()->updateOrCreate($dataCheckConditions, $dataCreateOrUpdate);
                }
            }
            Log::info('Progressed: SyncReportOrderProvinceByIdJob '.$provinceId);
            echo $colorSuccess."Done report_order_province_by_id: ".$provinceId." \033[0m".PHP_EOL;
            DB::commit();
        } catch (\Exception $exception) {
            Log::error(
                'SYNC_DATA_ORDER_PROVINCE_BY_ID# Lỗi khi tổng hợp báo cáo tỉnh thành phố',
                [
                    'line' => __LINE__,
                    'method' => __METHOD__,
                    'error_message' => $exception->getMessage(),
                ]
            );
            DB::rollBack();
            return;
        }
    }
}
