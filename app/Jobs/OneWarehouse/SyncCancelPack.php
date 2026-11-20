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

// Đồng bộ trạng thái hủy của kiện

class SyncCancelPack implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $packageDBs;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($packageDBs)
    {
        $this->packageDBs = $packageDBs;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $packageDBs = $this->packageDBs;

        $this->syncCancelToDongDong($packageDBs);
    }

    public function syncCancelToDongDong($packageDBs)
    {
        // Đồng bộ trạng thái hủy sang bên kho
        $arr_json = [];
        foreach ($packageDBs as $key => $packageDB) {
            $temp = [
                'pack_id'             => $packageDB->id,
                'pack_code_warehouse' => $packageDB->code_warehouse,
                'bill_code_old'       => $packageDB->bill_code
            ];
            if ($packageDB->code_warehouse != null && $packageDB->bill_code != null) {
                array_push($arr_json, $temp);
            }
        };

        if (count($arr_json) > 0) {
            $guzzle = new Guzzle;
            $url = env('BASE_URL_DONGDONG') . 'open/sync/pack-set-cancel-admin';
            $options = [
                'json' => [
                    'list_arr_pack' => $arr_json
                ],
                'headers' => [
                    'X-Token-Auth' => env('TOKEN_ONE_WAREHOUSE')
                ],
                'verify' => false,
                'http_errors' => false,
            ];

            $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
            $result = json_decode($result, true);
        }
    }
}
