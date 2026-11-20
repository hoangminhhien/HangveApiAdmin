<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client as Guzzle;

class WebhookDongDongService
{
    protected string $url;
    /**
     * @var mixed
     */
    protected $code;

    function __construct()
    {
        $this->url = env('URL_DONGDONG') . 'webhook/hangve-x329kxm2k2ixk39x92x1911947165';
    }

    public function updateOrderDetail($orderDetail)
    {
        $classification = "";
        if(data_get($orderDetail, 'classification_zh_fast')) {
            $classification = data_get($orderDetail, 'classification_zh_fast');
        }elseif(data_get($orderDetail, 'classification_zh')) {
            $classification = data_get($orderDetail, 'classification_zh');
        }else {
            $classification = data_get($orderDetail, 'classification');
        }
        $data = [
            'order_detail_id' => $orderDetail->id,
            'classification' => $classification,
            'note_china' => $orderDetail->note_china
        ];
        $this->hookDongdong($data, 'update_order_detail');
    }

    public function updateNameOrderDetails($data)
    {
        // Log::info('Vào phần đồng bộ tên TQ của sản phẩm');
        // Log::info(json_encode($data));
        $this->hookDongdong($data, 'update_order_details');
    }

    private function hookDongdong(array $data, $event)
    {
        $guzzle = new Guzzle;
        $options = [
            'json' => [
                'event_name' => $event,
                'data'       => $data
            ],
            'verify' => false,
            'http_errors' => false,
        ];

        $result = $guzzle->request('POST', $this->url, $options)->getBody()->getContents();
        $result = json_decode($result, true);
    }
}
