<?php


namespace App\Constants;


class CarrierConstant
{
    public const GHTK_TOKEN = '536942EAaE3F68b68ca96C115C365996210927D3';
    public const ENDPOINT_GHTK_PRICE = '/services/shipment/fee';
    public const ENDPOINT_GHTK_CREATE_ORDER = '/services/shipment/order/?ver=1.5';
    public const ENDPOINT_GHTK_GET_PICK_UP_ADDRESS = '/services/shipment/list_pick_add';
    public const ENDPOINT_GHTk_CANCEL_ORDER = '/services/shipment/cancel';
    public const GHTK_ORDER_STATUS = [
        -1 => "Hủy đơn hàng",
        1 => "Chưa tiếp nhận",
        2 => "Đã tiếp nhận",
        3 => "Đã lấy hàng/Đã nhập kho",
        4 => "Đã điều phối giao hàng/Đang giao hàng",
        5 => "Đã giao hàng/Chưa đối soát",
        6 => "Đã đối soát",
        7 => "Không lấy được hàng",
        8 => "Hoãn lấy hàng",
        9 => "Không giao được hàng",
        10 => "Delay giao hàng",
        11 => "Đã đối soát công nợ trả hàng",
        12 => "Đã điều phối lấy hàng/Đang lấy hàng",
        13 => "Đơn hàng bồi hoàn",
        20 => "Đang trả hàng (COD cầm hàng đi trả)",
        21 => "Đã trả hàng (COD đã trả xong hàng)",
        123 => "Shipper báo đã lấy hàng",
        127 => "Shipper (nhân viên lấy/giao hàng) báo không lấy được hàng",
        128 => "Shipper báo delay lấy hàng",
        45 => "Shipper báo đã giao hàng",
        49 => "Shipper báo không giao được giao hàng",
        410 => "Shipper báo delay giao hàng",
    ];

    public const GHN_STATUS = [
        "ready_to_pick" => "Mới tạo đơn hàng",
        "picking" => "Nhân viên đang lấy hàng",
        "cancel" => "Hủy đơn hàng",
        "money_collect_picking" => "Đang thu tiền người gửi",
        "picked" => "Nhân viên đã lấy hàng",
        "storing" => "Hàng đang nằm ở kho",
        "transporting" => "Đang luân chuyển hàng",
        "sorting" => "Đang phân loại hàng hóa",
        "delivering" => "Nhân viên đang giao cho người nhận",
        "money_collect_delivering" => "Nhân viên đang thu tiền người nhận",
        "delivered" => "Nhân viên đã giao hàng thành công",
        "delivery_fail" => "Nhân viên giao hàng thất bại",
        "waiting_to_return" => "Đang đợi trả hàng về cho người gửi",
        "return" => "Trả hàng",
        "return_transporting" => "Đang luân chuyển hàng trả",
        "return_sorting" => "Đang phân loại hàng trả",
        "returning" => "Nhân viên đang đi trả hàng",
        "return_fail" => "Nhân viên trả hàng thất bại",
        "returned" => "Nhân viên trả hàng thành công",
        "exception" => "Đơn hàng ngoại lệ không nằm trong quy trình",
        "damage" => "Hàng bị hư hỏng",
        "lost" => "Hàng bị mất"
    ];

    public const ENDPOINT_GHN_GET_STORE = '/shiip/public-api/v2/shop/all';
    public const ENDPOINT_GHN_PRICE = '/shiip/public-api/v2/shipping-order/fee';
    public const ENDPOINT_GHN_CREATE_ORDER = '/shiip/public-api/v2/shipping-order/create';
    public const ENDPOINT_GHN_AVAILABLE_SERVICES = '/shiip/public-api/v2/shipping-order/available-services';
    public const ENDPOINT_GHN_CANCEL_ORDER = '/shiip/public-api/v2/switch-status/cancel';
    public const GHN_ORDER_STATUS_CREATED = 'ready_to_pick';

    public const ENDPOINT_VIETTEL_LOGIN = '/user/Login';
    public const ENDPOINT_VIETTEL_PRICE = '/order/getPrice';
    public const ENDPOINT_VIETTEL_ORDER = '/order/createOrder';

    public const GHN = 'ghn';
    public const GHTK = 'ghtk';
    public const VIETTEL = 'viettel';

    public const CARRIERS = [self::GHTK, self::GHN, self::VIETTEL];
    public const CARRIERS_HAS_DELIVERY_TYPE = [self::GHTK, self::VIETTEL];

    public const GHN_NOTE_SHIPPING = ['CHOTHUHANG', 'CHOXEMHANGKHONGTHU', 'KHONGCHOXEMHANG'];
}
