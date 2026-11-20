<?php

namespace App\Jobs\OneWarehouse;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as Guzzle;
use App\Constants\PackageConstant;

class SyncAllPackToOne implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \Log::info('SyncAllPackToOne');

        $auto_push = true;

        if ($auto_push) {
            // Đơn order chờ xác nhận
            $packModel = \App::make('App\Models\OrderPackage');
            $arr_status = [
                PackageConstant::STATUS_PENDING,
                PackageConstant::STATUS_ON_THE_WAY_CHINA,
                // PackageConstant::STATUS_IN_GUANGZHOU,
            ];
            $packDBs = $packModel->whereIn('status', $arr_status)
                                ->where('order_type', 'App\Models\Order')
                                ->whereNotNull('bill_code')
                                ->whereNotNull('thuongdo_id')
                                ->get();

            if (count($packDBs) > 0) {
                foreach ($packDBs as $key => $packDB) {
                    $job = new \App\Jobs\OneWarehouse\SyncPackStatusPending($packDB);
                    dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
                }
            }


            // Đơn order đã đến kho China
            $packModel = \App::make('App\Models\OrderPackage');
            $arr_status = [
                PackageConstant::STATUS_IN_GUANGZHOU,
            ];
            $packDBs = $packModel->whereIn('status', $arr_status)
                                ->where('order_type', 'App\Models\Order')
                                ->whereNotNull('bill_code')
                                ->whereNotNull('thuongdo_id')
                                ->get();

            if (count($packDBs) > 0) {
                foreach ($packDBs as $key => $packDB) {
                    $job = new \App\Jobs\OneWarehouse\SyncPackStatusInChina($packDB);
                    dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
                }
            }

            // Kiện kí gửi
            $packModel = \App::make('App\Models\OrderPackage');
            $arr_status = [
                PackageConstant::STATUS_PENDING,
                PackageConstant::STATUS_ON_THE_WAY_CHINA,
                // PackageConstant::STATUS_IN_GUANGZHOU,
            ];
            $packDBs = $packModel->whereIn('status', $arr_status)
                                ->where('order_type', 'App\Models\Consignment')
                                ->whereNotNull('bill_code')
                                ->whereNotNull('thuongdo_id')
                                ->orderBy('updated_at', 'desc')
                                ->limit(500)->offset(0)
                                ->get();

            if (count($packDBs) > 0) {
                foreach ($packDBs as $key => $packDB) {
                    $job = new \App\Jobs\OneWarehouse\SyncPackConsigmentStatusPending($packDB);
                    dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
                }
            }

            // Kiện kí gửi đã đến kho China
            $packModel = \App::make('App\Models\OrderPackage');
            $arr_status = [
                // PackageConstant::STATUS_PENDING,
                // PackageConstant::STATUS_ON_THE_WAY_CHINA,
                PackageConstant::STATUS_IN_GUANGZHOU,
            ];
            $packDBs = $packModel->whereIn('status', $arr_status)
                                ->where('order_type', 'App\Models\Consignment')
                                ->whereNotNull('bill_code')
                                ->whereNotNull('thuongdo_id')
                                ->orderBy('updated_at', 'desc')
                                ->limit(500)->offset(0)
                                ->get();

            if (count($packDBs) > 0) {
                foreach ($packDBs as $key => $packDB) {
                    $job = new \App\Jobs\OneWarehouse\SyncPackConsigmentInChina($packDB);
                    dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
                }
            }


            // Kiện chờ xác định
            $packModel = \App::make('App\Models\OrderPackage');
            $arr_status = [
                PackageConstant::STATUS_PENDING,
                PackageConstant::STATUS_ON_THE_WAY_CHINA,
                // PackageConstant::STATUS_IN_GUANGZHOU,
            ];
            $packDBs = $packModel->whereIn('status', $arr_status)
                                ->whereNull('order_type')
                                ->whereNotNull('bill_code')
                                ->whereNotNull('thuongdo_id')
                                ->get();

            if (count($packDBs) > 0) {
                foreach ($packDBs as $key => $packDB) {
                    $job = new \App\Jobs\OneWarehouse\SyncPackNotStatusPending($packDB);
                    dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
                }
            }


            // Kiện chờ xác định đã đến kho China
            $packModel = \App::make('App\Models\OrderPackage');
            $arr_status = [
                // PackageConstant::STATUS_PENDING,
                // PackageConstant::STATUS_ON_THE_WAY_CHINA,
                PackageConstant::STATUS_IN_GUANGZHOU,
            ];
            $packDBs = $packModel->whereIn('status', $arr_status)
                                ->whereNull('order_type')
                                ->whereNotNull('bill_code')
                                ->whereNotNull('thuongdo_id')
                                ->get();

            if (count($packDBs) > 0) {
                foreach ($packDBs as $key => $packDB) {
                    $job = new \App\Jobs\OneWarehouse\SyncPackNotInChina($packDB);
                    dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');
                }
            }
        }
    }
}
