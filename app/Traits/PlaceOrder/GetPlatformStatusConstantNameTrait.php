<?php

namespace App\Traits\PlaceOrder;

use App\Constants\Buyder\BuyderStatusConstant;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

trait GetPlatformStatusConstantNameTrait
{
    /**
     * Lấy constant name về trạng thái đơn hàng to và màu sắc tương ứng
     * với nguồn đặt hàng trong BuyderServiceConstant
     *
     * @param string $source
     * @return string[]
     */
    private function getSupplierStatusListConstantNames(string $source): array
    {
        switch ($source) {
            case BuyderServiceConstant::SOURCE_TAOBAO:
                return ['BUYDER_ORDER_STATUSES', 'BUYDER_ORDER_STATUS_COLORS'];
            case BuyderServiceConstant::SOURCE_1688:
                return ['ORDER_BUYDER_1688_STATUSES', 'ORDER_BUYDER_1688_STATUS_COLORS'];
            default:
                return [];
        }
    }

    /**
     * Lấy constant name về trạng thái đơn hàng con và màu sắc tương ứng
     * với nguồn đặt hàng trong BuyderServiceConstant
     *
     * @param string $source
     * @return string[]
     */
    private function getSupplierSubStatusListConstantNames(string $source): array
    {
        switch ($source) {
            case BuyderServiceConstant::SOURCE_TAOBAO:
                return ['SUB_BUYDER_ORDER_STATUSES', 'SUB_BUYDER_ORDER_STATUS_COLORS'];
            case BuyderServiceConstant::SOURCE_1688:
                return ['ORDER_BUYDER_1688_STATUSES', 'ORDER_BUYDER_1688_STATUS_COLORS'];
            default:
                return [];
        }
    }

    /**
     * Lấy trạng thái đặt hàng của đơn con khi mới khởi tạo, tương ứng với các nguồn đặt hàng
     *
     * @param string $source
     * @return string
     */
    private function getOrderBuyderSubsInitStatus(string $source): string
    {
        switch ($source) {
            case BuyderServiceConstant::SOURCE_TAOBAO:
                return BuyderStatusConstant::SUB_BUYDER_ORDER_BUILDING;
            case BuyderServiceConstant::SOURCE_1688:
                return BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_BUYER_PAY;
            default:
                return '';
        }
    }

    /**
     * Lấy trạng thái đặt hàng của đơn con khi mới khởi tạo, tương ứng với các nguồn đặt hàng
     *
     * @param string $source
     * @return string
     */
    private function getOrderBuyderSubsCancelStatus(string $source): string
    {
        switch ($source) {
            case BuyderServiceConstant::SOURCE_TAOBAO:
                return BuyderStatusConstant::BUYDER_ORDER_CANCELED;
            case BuyderServiceConstant::SOURCE_1688:
                return BuyderStatusConstant::ORDER_BUYDER_1688_CANCELED;
            default:
                return '';
        }
    }

    /**
     * Lấy trạng thái đặt hàng của đơn con khi mới khởi tạo, tương ứng với các nguồn đặt hàng
     *
     * @param string $source
     * @return string
     */
    private function getOrderBuyderSubsClosedStatus(string $source): string
    {
        switch ($source) {
            case BuyderServiceConstant::SOURCE_1688:
            case BuyderServiceConstant::SOURCE_TAOBAO:
                return BuyderStatusConstant::ORDER_TRADE_CLOSED;
            default:
                return '';
        }
    }

    /**
     * Lấy constant name về các trạgn thái đặt hàng trang 'Danh sách thanh toán'
     *
     * @param string $source
     * @return array
     */
    private function getPaymentSupplierStatusListConstantNames(string $source): array
    {
        switch ($source) {
            case BuyderServiceConstant::SOURCE_TAOBAO:
                return ['ORDER_BUYDER_TAOBAO_PAYMENT_STATUSES', 'ORDER_BUYDER_TAOBAO_PAYMENT_STATUS_COLORS'];
            case BuyderServiceConstant::SOURCE_1688:
                return ['ORDER_BUYDER_1688_PAYMENT_STATUSES', 'ORDER_BUYDER_1688_PAYMENT_STATUS_COLORS'];
            default:
                return [];
        }
    }
}
