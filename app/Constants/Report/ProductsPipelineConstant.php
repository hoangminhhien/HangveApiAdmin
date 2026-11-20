<?php

namespace App\Constants\Report;

use App\Constants\Base\BaseConstant;

class ProductsPipelineConstant extends BaseConstant
{
    /**
     * NGUỒN THỰC HIỆN HÀNH ĐỘNG
     */
    //public const SOURCE_MOBILE = 'mobile';
    public const SOURCE_WEB_PC = 'web_pc';
    public const SOURCE_WEB_MOBILE = 'web_mobile';
    public const SOURCE_PROMOTION_SEARCH_FORM = 'promotion_search_form';

    public const SOURCE_WEBS = [
        self::SOURCE_WEB_PC,
        self::SOURCE_WEB_MOBILE,
    ];

    public const SOURCE_FORMS = [
        self::SOURCE_PROMOTION_SEARCH_FORM,
    ];

    public const AVAILABLE_SOURCES = [
        //self::SOURCE_MOBILE,
        self::SOURCE_WEB_PC => 'Web PC',
        self::SOURCE_WEB_MOBILE => 'Web Mobile',
        self::SOURCE_PROMOTION_SEARCH_FORM => 'Form Tìm Kiếm',
    ];

    /**
     * HÀNH ĐỘNG
     */
    public const ACTION_SEARCH = 'search';
    public const ACTION_ADD_TO_CART = 'add_to_cart';
    public const ACTION_ORDER_UP = 'order_up';
    public const ACTION_PLACE_ORDER = 'place_order';
    public const AVAILABLE_ACTIONS = [
        self::ACTION_SEARCH,
        self::ACTION_ADD_TO_CART,
        self::ACTION_ORDER_UP,
        self::ACTION_PLACE_ORDER,
    ];
    public const ACTION_TITLES = [
        self::ACTION_SEARCH => 'Tìm kiếm',
        self::ACTION_ADD_TO_CART => 'Thêm vào giỏ hàng',
        self::ACTION_ORDER_UP => 'Lên đơn',
        self::ACTION_PLACE_ORDER => 'Đặt hàng',
    ];

    /**
     * Sàn TMĐT
     */
    public const PLATFORM_TAOBAO = 'taobao';
    public const PLATFORM_1688 = '1688';
    public const PLATFORM_TMALL = 'tmall';
    public const PLATFORM_BAIDU = 'baidu';
    public const AVAILABLE_PLATFORMS = [
        self::PLATFORM_TAOBAO,
        self::PLATFORM_1688,
        self::PLATFORM_TMALL,
        self::PLATFORM_BAIDU,
    ];

    /**
     * CÁC KHOẢNG THỜI GIAN SỬ DỤNG ĐỂ FILTER BÁO CÁO
     */
    public const PERIOD_DAY = 'day';
    public const PERIOD_WEEK = 'week';
    public const PERIOD_MONTH = 'month';
    public const PERIOD_YEAR = 'year';
    public const AVAILABLE_PERIODS = [
        self::PERIOD_DAY,
        self::PERIOD_WEEK,
        self::PERIOD_MONTH,
        self::PERIOD_YEAR,
    ];

    /**
     * GIÁ TRỊ MẶC ĐỊNH CỦA SOURCE VÀ CITY
     */
    public const SOURCE_DEFAULT = 'no_source';
    public const CITY_DEFAULT = 'no_location';

    /**
     * SỐ TỪ KHÓA TÌM KIẾM MUỐN LẤY MẶC ĐỊNH
     */
    public const DEFAULT_NUMBER_COMMON_KEYWORD = 10;
}
