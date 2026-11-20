<?php


namespace App\Constants;


class OrderBuyderConstant
{
    public const KEY_STATUS_DEPOSITED = 'status_0';
    public const KEY_STATUS_WAITING_PAY = 'status_1';
    public const KEY_STATUS_ORDERED = 'status_2';
    public const KEY_STATUS_DONE = 'status_3';
    public const STATUSES = [
        self::KEY_STATUS_DEPOSITED => 'Đã đặt cọc',
        self::KEY_STATUS_WAITING_PAY => 'Chờ thanh toán',
        self::KEY_STATUS_ORDERED => 'Đặt hàng',
        self::KEY_STATUS_DONE => 'Đã hoàn thành',
    ];
    public const SUPPLIER_STATUS = [
        0 => "Chưa thanh toán",
        1 => "Đã thanh toán/Chờ xác nhận tài chính",
        2 => "Xác nhận tài chính thành công",
        3 => "Xác nhận tài chính không thành công",
        4 => "Đã hủy",
        10 => "Chờ giao hàng",
        11 => "Đã giao hàng",
    ];
    public const CANCEL_TYPE = [
        1 => "Tôi không muốn mua",
        2 => "Thông tin điền lỗi, mua lại",
        3 => "Người bán ra khỏi chứng khoán",
        4 => "Gặp gỡ giao dịch",
        5 => "Vấn đề thanh toán (chẳng hạn như thiếu số dư, không biết làm thế nào để thanh toán, vv)",
        6 => "Mua sai",
        7 => "Những lý do khác",
    ];
}
