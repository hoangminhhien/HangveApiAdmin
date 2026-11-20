<?php

namespace App\Services;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;

class BuyderService
{
    /**
     * Id Taobao của nhân viên
     * Lấy theo id nhân viên đó được gán, hoặc mặc định theo config
     *
     * @var string
     */
    private string $userId;

    public function __construct()
    {
    }

    public function forward($method, $uri, $data = [], $options = [], ?string $userId = null)
    {
        $this->userId = $userId ?? data_get(getCurrentUser(), 'id_taobao');
        $guzzle = new Guzzle();
        try {
            $domain = config('partner.buyder.domain');
            $options = array_merge($options, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Token-Auth' => config('partner.buyder.tokenAuth'),
                ],
            ]);

            $data = array_merge($data, [
                'userId' => $this->userId,
                'api_provider' => 'x_port'
            ]);

            if ($method == "GET") {
                $options['query'] = $data;
            }
            if ($method == "POST") {
                $options['json'] = $data;
            }
            $response = $guzzle->request($method, $domain . $uri, $options);
            return json_decode((string)$response->getBody(), true);
        } catch (\Exception $e) {
            Log::error($e);
            return $e->getMessage();
        }
    }

    public function createOrder($data)
    {
        Log::info('F88 : ' . request()->ip() . ' - ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        return $this->forward('POST', '/open/taobao/create-order', $data);
    }

    public function cancelOrder($data)
    {
        return $this->forward('POST', '/open/taobao/cancel-order', $data);
    }

    public function connectIM($data)
    {
        return $this->forward('POST', '/open/taobao/connect-im', $data);
    }

    /**
     * Lấy danh sách đơn đã đặt của một nhân viên
     *
     * @param $data
     * @return mixed
     */
    public function listOrder(?string $userId, array $data = [])
    {
        return $this->forward('GET', '/open/taobao/list-order', $data, [], $userId);
    }

    /**
     * Bắn API qua Buyder thực hiện thanh
     *
     * @param array $data
     * @return mixed
     */
    public function handlePayment(array $data)
    {
        return $this->forward('POST', '/open/taobao/pay-order', $data);
    }
}
