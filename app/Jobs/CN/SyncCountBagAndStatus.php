<?php

namespace App\Jobs\CN;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Support\Facades\Log;
use App\Constants\PackageConstant;

class SyncCountBagAndStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $origin_code;
    protected $order_package_id;
    public function __construct($origin_code, $order_package_id)
    {
        $this->origin_code      = $origin_code;
        $this->order_package_id = $order_package_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $origin_code      = $this->origin_code;
        $order_package_id = $this->order_package_id;

        $guzzle = new Guzzle;
        $url = env('URL_WAREHOUSE_CN') . '/api/admin/sync/hangve-m3322917165mshx292';
        $option = [
            'json' => [
                'event_name' => 'hangve_get_bag_real_and_status',
                'data'       => [
                    'origin_code' => $origin_code
                ],
                'token2000a' => env('TOKEN_SYNC_DD_HV')
            ],
            'verify' => false,
            'http_errors' => false,
        ];
        $result = $guzzle->request('POST', $url, $option)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result['code'] == 200) {
            $packModel = \App::make('App\Models\orderPackage');
            $arr_status = [
                PackageConstant::STATUS_RECEIVED_GOODS,
                PackageConstant::STATUS_CANCEL,
            ];
            $packDB = $packModel->where('id', $order_package_id)
                                ->where('bill_code', $origin_code)
                                ->whereNotIn('status', $arr_status)
                                ->first();

            if (!is_null($packDB)) {
                // Lưu số bao thực tế
                $packDB->cn_bag_real = $result['data']['count_bag_real'];

                // Lưu trạng thái
                $quoteController = \App::make('App\Http\Controllers\QuoteController');
                $status_new = $quoteController->convertStatusQuoteToStatusHV($result['data']['status_origin_code']);
                if (!is_null($status_new)) {
                    // Chek
                    $status_current = $packDB->status;
                    $statusArr = explode("_", $status_current);
                    $indexStatus = (int) $statusArr[1];
                    if ($indexStatus < 10) {
                        if ($packDB->status != $status_new) {
                            $packDB->status = $status_new;
                        }
                    }
                }

                $packDB->save();
                // \Log::info("SyncCountBagAndStatus success " . json_encode($result));
            }
        } else {
            \Log::info("SyncCountBagAndStatus err " . json_encode($result));
        }
    }
}
