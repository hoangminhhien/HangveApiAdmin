<?php

namespace App\Traits\PlaceOrder;

use App\Models\AssignSupportOrder;
use App\Models\OrderBuyder;
use App\Models\OrderDetail;
use App\Models\SubOrderBuyder;
use App\Models\User;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

trait GetBuyderRequestResource
{
    /**
     * Lấy URL và options tương ứng để bắn request cập nhật dữ liệu chi tiết đơn hàng Taobao hoặc 1688
     *
     * @return array
     */
    private function getOrderDetailRequestResource(string $source, string $userId, string $buyderId): array
    {
        switch ($source) {
            case BuyderServiceConstant::SOURCE_TAOBAO:
                return [
                    'url' => config('partner.buyder.domain') . config('partner.buyder_taobao.order-detail'),
                    'options' => [
                        'headers' => [
                            'X-Token-Auth' => config('partner.buyder.tokenAuth'),
                        ],
                        'query' => [
                            'userId' => $userId,
                            'orderId' => $buyderId,
                        ],
                    ]
                ];
            case BuyderServiceConstant::SOURCE_1688:
                return [
                    'url' => config('partner.buyder.domain') . config('partner.buyder_1688.order-detail'),
                    'options' => [
                        'headers' => [
                            'X-Token-Auth' => config('partner.buyder.tokenAuth1688'),
                        ],
                        'json' => [
                            'webSite' => "1688",
                            'userId' => $userId,
                            'orderId' => $buyderId
                        ],
                    ]
                ];
            default:
                return [
                    'url' => '',
                    'options' => []
                ];
        }
    }
}
