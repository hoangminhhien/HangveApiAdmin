<?php

namespace App\Jobs\Quote;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client as Guzzle;

class SyncCustonerOfferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $data;
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->data;
        $guzzle = new Guzzle;
        $url = env('URL_QUOTE') . 'webhook/hangve-m2329k1947165msh';
        $options = [
            'json' => [
                'event_name' => 'sync_customer_offer',
                'data' => $data,
                'token2000a'         => env('TOKEN_SYNC_DD_HV')
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);
    }
}
