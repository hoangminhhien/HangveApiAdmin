<?php

namespace App\Services;

use App\Constants\PackageConstant;
use App\Jobs\OrderPackage\UpdateNameProductToDongDong;
use App\Models\Order;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Support\Facades\Log;

class QuoteOneWarehouseService
{

    protected $client;

	public function __construct(Guzzle $client)
	{
		$this->client = $client;
	}

    public function forward($method, $uri, $data = [])
    {
        try {
            $domain = env('BASE_URL_QUOTE');
            $options = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Token-Auth' => env('TOKEN_ONE_WAREHOUSE')
                ],
            ];
            if($method == "GET"){
                $options = array_merge($options, ['query' => $data]);
            }
            if($method == "POST"){
                $options = array_merge($options, ['json' => $data]);
            }
			$response = $this->client->request($method, $domain.$uri, $options);
            $res = json_decode((string) $response->getBody(), true);
            return $res;
		} catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            return json_decode((string) $response->getBody(), true);
		}
    }
}