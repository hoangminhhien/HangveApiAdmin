<?php

namespace App\Actions\PlaceOrder;

use App\Models\OrderBuyder;
use App\Models\OrderDetail;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;

class DetailAction extends BaseAction
{
    /**
     * Lấy thông tin chi tiết đơn hàng
     *
     * @param array|null $params
     * @return array
     */
    public function handle(?array $params): array
    {
        $orderDetail = OrderDetail::query()
            ->subOrderBuyders()
            ->findOrFail($params['id']);

        return $this->formatPlaceOrderDetail($orderDetail);
    }

    /**
     * Format thông tin chi tiết đơn hàng
     *
     * @param OrderBuyder $buyderOrder
     * @return array
     */
    private function formatPlaceOrderDetail(OrderDetail $orderDetail): array
    {
        // + mã đơn hàng: order_code (orders.code)
        // + thông tin sp: product_info
        //    - tên sp: product_name
        //    - ảnh sp: product_image
        //    - phân loại sp: product_classification
        // + thông tin liên quan tới số lượng: number_info
        //    - số sp khách đặt: product_number_customer
        //    - số sp đã nhờ: product_number_assigned
        //    - số sp đã đặt (cả của mình và được nhờ): product_number_placed
        //    - số sp có thể nhờ (tổng khách đặt - mình đã đặt - đã nhờ): product_number_assign_available

        $generalInfo = [
            'order_code' => $orderDetail->order()->first()->code,
        ];

        $productInfo = [
            'product_name' => data_get($orderDetail, 'name'),
            'product_image' => data_get($orderDetail, 'image'),
            'product_classification' => data_get($orderDetail, 'classification'),
        ];

        return array_merge($generalInfo, [
            'product_info' => $productInfo,
            'number_info' => $orderDetail->getNumberInfo()
        ]);
    }
}
