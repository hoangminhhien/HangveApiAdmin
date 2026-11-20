<?php

namespace App\Jobs\OneWarehouse;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client as Guzzle;

class SyncPackJob implements ShouldQueue
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
        $guzzle = new Guzzle;
        $url = env('URL_DONGDONG') . 'webhook/hangve-x329kxm2k2ixk39x92x1911947165';
        $option = [
            'verify' => false,
            'http_errors' => false,
            'json' => $this->payload
        ];
        $guzzle->request('POST', $url, $option)->getBody()->getContents();
    }
}
