<?php


namespace ThuongDo\Buyder\src\Constants;

class BuyderServiceConstant extends BaseConstant
{
    /**
     * Các nguồn tìm kiếm
     */
    public const SOURCE_TAOBAO = 'taobao';

    public const SOURCE_1688 = '1688';

    public const AVAILABLE_SOURCES = [
        self::SOURCE_TAOBAO,
        self::SOURCE_1688
    ];

    /**
     * Các service hiện có
     */
    public const SYNC_TAOBAO = 'sync_taobao';

    public const SYNC_1688 = 'sync_1688';

    public const AVAILABLE_SERVICES = [
      self::SYNC_TAOBAO,
      self::SYNC_1688
    ];

    public const SERVICE_IMAGES = [
      self::SYNC_TAOBAO => '@/static/images/header-top/taobao.png',
      self::SYNC_1688 => '@/static/images/header-top/1688.png',
    ];

    /**
     * Tham số 'country' khi tìm kiếm sản phẩm 1688
     */
    public const COUNTRY_EN = 'en';

    /**
     * Danh sách các trang có thể gửi request đặt hàng
     */
    public const PLACE_ORDER_LIST_PAGE = 'place_order_list';
    public const PLACE_ORDER_ASSIGN_SUPPORT_PAGE = 'place_order_assign_support';
    public const PLACE_ORDER_PAGES = [
      self::PLACE_ORDER_LIST_PAGE,
      self::PLACE_ORDER_ASSIGN_SUPPORT_PAGE
    ];
}
