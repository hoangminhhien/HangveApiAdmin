<?php

namespace App\Services\Buyder;

use GuzzleHttp\Client as Guzzle;
use Illuminate\Support\Facades\Log;

class Client
{
	protected $client;

	public function __construct(Guzzle $client)
	{
		$this->client = $client;
	}

	public function forward($method, $uri, $data = [], $options = [])
	{
		try {
            $domain = config('partner.buyder.domain');
            $options = array_merge($options, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Token-Auth' => config('partner.buyder.tokenAuth'),
                ],
            ]);
            if($method == "GET"){
                $options['query'] = $data;
            }
            if($method == "POST"){
                $options['json'] = $data;
            }
			$response = $this->client->request($method, $domain.$uri, $options);
            $res = json_decode((string) $response->getBody(), true);
            return $res;
		} catch (\GuzzleHttp\Exception\ClientException $e) {
            Log::error($e);
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents(true);
            return json_decode((string) $responseBodyAsString, true);
		}
	}

    public function createOrder($data)
    {
        $dataCreate = [];
        $purchaseAmount = 0;
        $orderLineList = [];
        foreach($data as $key => $item){
            if($item['order_detail'] && $item['order_detail']->mp_id && $item['order_detail']->mp_sku_id){
                $order_detail = $item['order_detail'];
                $orderLineList[$key]['itemId'] = $order_detail->mp_id;
                $orderLineList[$key]['skuId'] = $order_detail->mp_sku_id;
                $orderLineList[$key]['orderLineNo'] = $key + 1;
                $orderLineList[$key]['quantity'] = $item['quantity'];
                $orderLineList[$key]['price'] = $order_detail->unit_price_cny;
                $orderLineList[$key]['currency'] = 'CNY';
                $purchaseAmount += $item['quantity'] * $item['order_detail']->unit_price_cny;
            }
        }
        $dataCreate['orderLineList'] = $orderLineList;
        $dataCreate['purchaseAmount'] = $purchaseAmount;
        $dataCreate['orderSource'] = "lazada";
        $dataCreate['sellerOrderNumber'] = 1;
        $dataCreate['receiver'] = [
            'zip'=>'510430',
            'country'=>'中国',
            'address'=>'石井街道 凰岗村 凤鸣路117号凰岗二社大型停车场F1档口',
            'phone'=>'18926668611',
            'city'=>'广州市',
            'district'=>'白云区',
            'name'=>'咚咚代收',
            'state'=>'广东省'
        ];
        $data = [
            "orderLineList" => [
                [
                    'itemId'=>'2048270155843005',
                    'orderLineNo'=>'1',
                    'quantity'=>'200',
                    'price'=>'1',
                    'currency'=>'CNY',
                    'skuId'=>'5910356015549'
                ],
                [
                    'itemId'=>'2048043406080862',
                    'orderLineNo'=>'2',
                    'quantity'=>'150',
                    'price'=>'1',
                    'currency'=>'CNY',
                    'skuId'=>'857611068254'
                ]
                
            ],
            "orderSource" => "lazada",
            "purchaseAmount" => "400",
            "receiver" => [
                'zip'=>'510430',
                'country'=>'中国',
                'address'=>'石井街道 凰岗村 凤鸣路117号凰岗二社大型停车场F1档口',
                'phone'=>'18926668611',
                'city'=>'广州市',
                'district'=>'白云区',
                'name'=>'咚咚代收',
                'state'=>'广东省'
            ],
            "sellerOrderNumber" => 1
        ];
        return $this->forward('POST', '/open/taobao/create-order', $data);
    }
}