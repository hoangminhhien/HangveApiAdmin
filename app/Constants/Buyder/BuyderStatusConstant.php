<?php

namespace App\Constants\Buyder;

use App\Constants\Base\BaseConstant;

class BuyderStatusConstant extends BaseConstant
{
    /**
     * Danh sách trạng thái buyder_order của Taobao trả về từ Buyder (pandapon) khi xem danh sách đơn hàng
     */
    public const BUYDER_ORDER_WAITING_PAY = '0';
    public const BUYDER_ORDER_PAID = '1';
    public const BUYDER_ORDER_CONFIRM_FINANCE_SUCCESS = '2';
    public const BUYDER_ORDER_CONFIRM_FINANCE_FAIL = '3';
    public const BUYDER_ORDER_CANCELED = '4';
    public const BUYDER_ORDER_WAIT_SELLER_SEND_GOODS = '10';
    public const BUYDER_ORDER_DELIVERIES = '11';

    /**
     * Mọi trạng thái của đơn hàng (order_buyders) sau khi đặt hàng từ Taobao
     */
    public const BUYDER_ORDER_STATUSES = [
        self::BUYDER_ORDER_WAITING_PAY => 'Chờ thanh toán',
        self::BUYDER_ORDER_PAID => 'Đã thanh toán/Chờ xác nhận TC',
        self::BUYDER_ORDER_CONFIRM_FINANCE_SUCCESS => 'Xác nhận TC thành công',
        self::BUYDER_ORDER_CONFIRM_FINANCE_FAIL => 'Xác nhận TC không thành công',
        self::BUYDER_ORDER_CANCELED => 'Đã hủy',
        self::BUYDER_ORDER_WAIT_SELLER_SEND_GOODS => 'Chờ giao hàng',
        self::BUYDER_ORDER_DELIVERIES => 'Đã giao hàng',
    ];

    /**
     * Màu sắc của các trạng thái của đơn hàng (order_buyders) sau khi đặt hàng
     */
    public const BUYDER_ORDER_STATUS_COLORS = [
        self::BUYDER_ORDER_WAITING_PAY => 'rgba(237, 28, 36, 1)',
        self::BUYDER_ORDER_PAID => 'rgba(254, 104, 71, 0.98)',
        self::BUYDER_ORDER_CONFIRM_FINANCE_SUCCESS => 'rgba(69, 180, 206, 1)',
        self::BUYDER_ORDER_CONFIRM_FINANCE_FAIL => 'rgba(214, 11, 74, 1)',
        self::BUYDER_ORDER_CANCELED => 'rgba(23, 34, 89, 1)',
        self::BUYDER_ORDER_WAIT_SELLER_SEND_GOODS => 'rgba(240, 153, 32, 1)',
        self::BUYDER_ORDER_DELIVERIES => 'rgba(38, 176, 28, 1)',
    ];

    /**
     * Danh sách trạng thái sub_buyder_order của Taobao trả về từ Buyder(pandapon) khi 'Đặt hàng'
     */
    public const SUB_BUYDER_ORDER_BUILDING = 'BUILDING';
    public const SUB_BUYDER_ORDER_WAIT_BUYER_P = 'WAIT_BUYER_P';
    public const SUB_BUYDER_ORDER_WAIT_SELLER_SEND_GOODS = 'WAIT_SELLER_SEND_GOODS';
    public const SUB_BUYDER_ORDER_WAIT_BUYER_CONFIRM_GOODS = 'WAIT_BUYER_CONFIRM_GOODS';
    public const SUB_BUYDER_ORDER_TRADE_CLOSED = 'TRADE_CLOSED';

    /**
     * Mọi trạng thái của đơn hàng con (order_buyders) sau khi đặt hàng từ Taobao
     */
    public const SUB_BUYDER_ORDER_STATUSES = [
        self::SUB_BUYDER_ORDER_BUILDING => 'Đơn hàng đã được tạo',
        self::SUB_BUYDER_ORDER_WAIT_BUYER_P => 'Chờ thanh toán',
        self::SUB_BUYDER_ORDER_WAIT_SELLER_SEND_GOODS => 'Thanh toán, chờ giao hàng',
        self::SUB_BUYDER_ORDER_WAIT_BUYER_CONFIRM_GOODS => 'Đã thanh toán, đang vận chuyển',
        self::SUB_BUYDER_ORDER_TRADE_CLOSED => 'Giao dịch đã kết thúc',
    ];

    /**
     * Màu sắc của các trạng thái của đơn hàng con (order_buyders) sau khi đặt hàng từ Taobao
     */
    public const SUB_BUYDER_ORDER_STATUS_COLORS = [
        self::SUB_BUYDER_ORDER_BUILDING => 'rgba(237, 28, 36, 1)',
        self::SUB_BUYDER_ORDER_WAIT_BUYER_P => 'rgba(237, 28, 36, 1)',
        self::SUB_BUYDER_ORDER_WAIT_SELLER_SEND_GOODS => 'rgba(254, 104, 71, 0.98)',
        self::SUB_BUYDER_ORDER_WAIT_BUYER_CONFIRM_GOODS => 'rgba(240, 153, 32, 1)',
        self::SUB_BUYDER_ORDER_TRADE_CLOSED => 'rgba(23, 34, 89, 1)',
    ];

    /**
     * Danh sách trạng thái đặt hàng Taobao màn 'Danh sách thanh toán'
     */
    public const ORDER_BUYDER_TAOBAO_PAYMENT_STATUSES = [
        self::BUYDER_ORDER_WAITING_PAY => 'Chờ thanh toán',
        self::BUYDER_ORDER_PAID => 'Đã thanh toán/Chờ xác nhận TC',
        self::BUYDER_ORDER_CONFIRM_FINANCE_SUCCESS => 'Xác nhận TC thành công',
        self::BUYDER_ORDER_WAIT_SELLER_SEND_GOODS => 'Chờ giao hàng',
    ];
    /**
     * Màu sắc tương ứng với các trạng thái đặt hàng taobao màn 'Danh sách thanh toán'
     */
    public const ORDER_BUYDER_TAOBAO_PAYMENT_STATUS_COLORS = [
        self::BUYDER_ORDER_WAITING_PAY => 'rgba(237, 28, 36, 1)',
        self::BUYDER_ORDER_PAID => 'rgba(254, 104, 71, 0.98)',
        self::BUYDER_ORDER_CONFIRM_FINANCE_SUCCESS => 'rgba(69, 180, 206, 1)',
        self::BUYDER_ORDER_WAIT_SELLER_SEND_GOODS => 'Chờ giao hàng',
    ];




    /**
     * Danh sách trạng thái buyder_order của 1688 trả về từ Buyder (pandapon) khi tạo đơn
     */
    public const ORDER_BUYDER_1688_WAIT_BUYER_PAY = 'waitbuyerpay';
    public const ORDER_BUYDER_1688_WAIT_SELLER_SEND = 'waitsellersend';
    public const ORDER_BUYDER_1688_WAIT_BUYER_RECEIVE = 'waitbuyerreceive';
    public const ORDER_BUYDER_1688_CONFIRMED = 'confirm_goods';
    public const ORDER_BUYDER_1688_SUCCESS = 'success';
    public const ORDER_BUYDER_1688_CANCELED = 'cancel';
    public const ORDER_BUYDER_1688_TERMINATED = 'terminated';
    public const ORDER_BUDYER_1688_OTHERS = 'others';

    /**
     * Mọi trạng trạng thái buyder_order của 1688 trả về từ Buyder (pandapon) khi tạo đơn
     */
    public const ORDER_BUYDER_1688_STATUSES = [
        self::ORDER_BUYDER_1688_WAIT_BUYER_PAY => 'Chờ người mua trả tiền',
        self::ORDER_BUYDER_1688_WAIT_SELLER_SEND => 'Chờ người bán gửi hàng',
        self::ORDER_BUYDER_1688_WAIT_BUYER_RECEIVE => 'Chờ người mua nhận hàng',
        self::ORDER_BUYDER_1688_CONFIRMED => 'Xác nhận',
        self::ORDER_BUYDER_1688_SUCCESS => 'Giao dịch thành công',
        self::ORDER_BUYDER_1688_CANCELED => 'Giao dịch bị hủy bỏ',
        self::ORDER_BUYDER_1688_TERMINATED => 'Giao dịch chấm dứt',
        self::ORDER_BUDYER_1688_OTHERS => 'Trạng thái khác',
    ];

    /**
     * Màu sắc của các trạng thái của đơn hàng con (order_buyders) sau khi đặt hàng từ Taobao
     */
    public const ORDER_BUYDER_1688_STATUS_COLORS = [
        self::ORDER_BUYDER_1688_WAIT_BUYER_PAY => 'rgba(237, 28, 36, 1)',
        self::ORDER_BUYDER_1688_WAIT_SELLER_SEND => 'rgba(38, 176, 28, 1)',
        self::ORDER_BUYDER_1688_WAIT_BUYER_RECEIVE => 'rgba(254, 104, 71, 0.98)',
        self::ORDER_BUYDER_1688_CONFIRMED => 'rgba(69, 180, 206, 1)',
        self::ORDER_BUYDER_1688_SUCCESS => 'rgba(38, 176, 28, 1)',
        self::ORDER_BUYDER_1688_CANCELED => 'rgba(23, 34, 89, 1)',
        self::ORDER_BUYDER_1688_TERMINATED => 'rgba(23, 34, 89, 1)',
        self::ORDER_BUDYER_1688_OTHERS => 'rgba(237, 28, 36, 1)',
    ];

    /**
     * Danh sách trạng thái đặt hàng 1688 màn 'Danh sách thanh toán'
     */
    public const ORDER_BUYDER_1688_PAYMENT_STATUSES = [
        self::ORDER_BUYDER_1688_WAIT_BUYER_PAY => 'Chờ người mua trả tiền',
        self::ORDER_BUYDER_1688_WAIT_SELLER_SEND => 'Chờ người bán gửi hàng',
        self::ORDER_BUYDER_1688_WAIT_BUYER_RECEIVE => 'Chờ người mua nhận hàng',
        self::ORDER_BUYDER_1688_CONFIRMED => 'Xác nhận',
    ];

    /**
     * Danh sách màu ứng với các trạng thái đặt hàng 1688 màn 'Danh sách thanh toán'
     */
    public const ORDER_BUYDER_1688_PAYMENT_STATUS_COLORS = [
        self::ORDER_BUYDER_1688_WAIT_BUYER_PAY => 'rgba(237, 28, 36, 1)',
        self::ORDER_BUYDER_1688_WAIT_SELLER_SEND => 'rgba(38, 176, 28, 1)',
        self::ORDER_BUYDER_1688_WAIT_BUYER_RECEIVE => 'rgba(254, 104, 71, 0.98)',
        self::ORDER_BUYDER_1688_CONFIRMED => 'rgba(69, 180, 206, 1)',
    ];

    /* danh sách trạng thái taobao, 1688*/
    const ORDER_ALL = 'all';
    const ORDER_WAIT_BUYER_P = 'WAIT_BUYER_P'; /* Chờ thanh toán*/
    const ORDER_WAIT_SELLER_SEND_GOODS = 'WAIT_SELLER_SEND_GOODS'; /*  chờ phát hàng (đã thanh toán) */
    const ORDER_WAIT_BUYER_CONFIRM_GOODS  = 'WAIT_BUYER_CONFIRM_GOODS'; /*  chờ nhận hàng (Đã giao hàng) */
    const ORDER_SUCCESS  = 'ORDER_SUCCESS'; /* hoàn thành */
    const ORDER_TRADE_CLOSED = 'ORDER_TRADE_CLOSED'; /* HỦY */
    const ORDER_COMPLAINING = 'ORDER_COMPLAINING'; /* KHIẾU NẠI */

    const LIST_ORDER_STATUS_NAME = [
        self::ORDER_ALL => 'Tất cả',
        self::ORDER_WAIT_BUYER_P => 'Chờ thanh toán',
        self::ORDER_WAIT_SELLER_SEND_GOODS => 'Chờ phát hàng ( Đã thanh toán )',
        self::ORDER_WAIT_BUYER_CONFIRM_GOODS => 'Chờ nhận hàng ( Đã giao hàng )',
        self::ORDER_SUCCESS => 'Hoàn thành',
        self::ORDER_TRADE_CLOSED => 'Hủy',
        self::ORDER_COMPLAINING => 'Đang khiếu nại',
    ];

    const LIST_ORDER_STATUS_COLOR = [
        self::ORDER_ALL => 'rgba(237, 28, 36, 1)',
        self::ORDER_WAIT_BUYER_P => 'rgba(237, 28, 36, 1)',
        self::ORDER_WAIT_SELLER_SEND_GOODS => 'rgba(254, 104, 71, 0.98)',
        self::ORDER_WAIT_BUYER_CONFIRM_GOODS => 'rgba(240, 153, 32, 1)',
        self::ORDER_SUCCESS => 'rgba(38, 176, 28, 1)',
        self::ORDER_TRADE_CLOSED => 'rgba(23, 34, 89, 1)',
        self::ORDER_COMPLAINING => 'rgba(237, 28, 36, 1)',
    ];
}
