<?php

namespace App\Services;

use GuzzleHttp\Client as Guzzle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private $guzzle;

    const URI_SEND = 'push/send';

    public function __construct()
    {
       $this->guzzle = new Guzzle();
    }

    /**
     * @param $method
     * @param $uri
     * @param array $data
     * @param array $options
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function send($method, $uri, $data = [], $options = [])
    {
        try {
            $domain  = config('partner.fcmApi.endpoint');
            $options = array_merge($options, [
                'headers' => [
                    'Content-Type'    => 'application/json',
                    'Accept'          => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate'
                ],
            ]);

            if ($method == "GET") {
                $options['query'] = $data;
            }
            if ($method == "POST") {
                $options['json'] = $data;
            }

            $response = $this->guzzle->request($method, $domain . $uri, $options);

            return json_decode((string)$response->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            Log::error($e);

            $response             = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();

            return json_decode((string)$responseBodyAsString, true);
        }
    }

    /**
     * @param array $userIds
     * @param array $data
     * @return mixed
     */
    public function sendNotifyUser(array $userIds, array $data)
    {
        $userTokens = $this->getTokenByUser($userIds);
        $data       = [
            'to'                => $userTokens,
            'title'             => $data['title'],
            'body'              => $data['body'],
            'data'              => $data['data'],
            '_contentAvailable' => true
        ];
        // Phần này là bắn noti cho app react native - giờ k dùng nữa thì bỏ, Bao giờ làm với app sau
        return true;
        return $this->send('POST', self::URI_SEND, $data);
    }

    public function getTokenByUser(array $userIds)
    {
        $query = DB::table('devices')
            ->whereIn('user_id', $userIds)
            ->where('status', 1); // 1: Active, 0: Inactive

        return $query->pluck('token')->toArray();
    }
}
