<?php

namespace ThuongDo\Buyder\src\Actions\ConnectChat;

use Illuminate\Http\JsonResponse;
use ThuongDo\Buyder\src\Actions\CancelOrder\CancelOrderTrait;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;

class ConnectChat1688Action extends BaseAction
{
    use CancelOrderTrait;

    /**
     * @return JsonResponse
     */
    public function handle(?array $params): JsonResponse
    {
        $url = config('partner.buyder.domain') . config('partner.buyder_1688.connect_chat');
        $options = array_merge($this->buyder1688Options, [
            'json' => [
                'openUid' => data_get($params, 'seller_id'),
                'userId' => data_get($params, 'platform_id'),
                'offerId' => data_get($params, 'offer_id')
            ]
        ]);

        $connectResult = $this->serviceRequest->handlePost($url, $options);

        if (data_get($connectResult, 'code') != 200) {
            return resError('Kết nối chat không thành công', 400);
        }

        return resSuccessWithinData(data_get($connectResult, 'data.wangwangUrl'));
    }
}
