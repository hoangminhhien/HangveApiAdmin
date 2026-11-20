<?php

namespace ThuongDo\Buyder\src\Interfaces;

use Illuminate\Http\JsonResponse;

interface ServiceActionInterface
{
    // TODO: Bỏ command các methods trong interface này sau khi hoàn thiện code trong các implementations
    /**
     * Đặt hàng
     *
     * @param array $params
     * @return JsonResponse
     */
    //public function placeOrder(array $params): JsonResponse;

    /**
     * Hủy đặt hàng
     *
     * @param array $params
     * @return JsonResponse
     */
    //public function cancelOrder(array $params): JsonResponse;

    /**
     * Thanh toán
     *
     * @param array $params
     * @return JsonResponse
     */
    //public function handlePayment(array $params): JsonResponse;
}
