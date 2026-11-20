<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SyncChargeAppService extends BaseService
{
    protected $url;
    protected $apiToken;

    public function __construct()
    {
        parent::__construct();

        $this->url      = env('SYNC_CHARGE_APP_URL');
        $this->apiToken = env('SYNC_CHARGE_APP_TOKEN');
    }

    public function setHeaderCurl($data_string): array
    {
        return [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string),
            'X-Token-Auth: '.$this->apiToken,
        ];
    }

    public function sync($request)
    {
        $endpoint = 'transaction';
        $url      = $this->url.$endpoint;
        $method   = 'POST';

        $res = $this->curl($request, $url, $method);
        //dd($res, $url, $request);
        if ($res && $res->code == 200) {
            return true;
        }

        Log::error('Sync charge app failed. Data: '. json_encode($request));
        return false;
    }
}
