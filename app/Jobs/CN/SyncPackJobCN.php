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

class SyncPackJobCN implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $payload;
    public function __construct($payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Log::info('Vào phần đồng bộ sang CN');
        $guzzle = new Guzzle;
        $url = env('URL_WAREHOUSE_CN') . '/api/admin/sync/hangve-m3322917165mshx292';
        $option = [
            'verify' => false,
            'http_errors' => false,
            'json' => $this->payload
        ];
        $guzzle->request('POST', $url, $option)->getBody()->getContents();
    }
}
