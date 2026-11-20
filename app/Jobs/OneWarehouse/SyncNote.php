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

class SyncNote implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $packageDB;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($packageDB)
    {
        $this->packageDB = $packageDB;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $packageDB = $this->packageDB;

        $this->pushToOneWarehouse($packageDB);
    }

    public function pushToOneWarehouse($packageDB)
    {
        if ($packageDB->code_warehouse == null) {
            return;
        }

        if ($packageDB->note == null) {
            return;
        }

        \Log::info('SyncNote-V1: ' . $packageDB->id);

        // Đồng bộ ghi chú sang kho
        $data_sync = [
            'id'   => (int)$packageDB->id,
            'note' => $packageDB->note
        ];

        // Lấy dữ liệu
        $guzzle = new Guzzle;
        $url = env('URL_DONGDONG') . 'webhook/hangve-x329kxm2k2ixk39x92x1911947165';
        $options = [
            'json' => [
                'event_name' => 'update_note',
                'data'       => $data_sync
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);
    }
}
