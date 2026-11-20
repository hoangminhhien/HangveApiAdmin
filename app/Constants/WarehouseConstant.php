<?php


namespace App\Constants;


class WarehouseConstant
{
    public const COUNTRIES = ['vi', 'cn'];
    public const DEFAULT = 'HN-TD'; // Kho đích mặc định

    public const CODE_HP = 'HP';
    public const STATUS_ACTIVE = 1;
    // Danh sách này khớp với bên DD để check
    public const WAREHOUSE_QUANGCHAU = 'quangchau-001'; // Kho Quảng Châu
    public const WAREHOUSE_DONGHUNG  = 'donghung-002'; // Kho Đông Hưng
    public const WAREHOUSE_VIETNAM  = 'vietnam-003'; // Kho Việt Nam

    public const WAREHOUSE_BANGTUONG      = 'bangtuong-004'; // Kho Bằng Tường

    public const STATUSES_COLOR = [
        'HN-TD' => ColorConstant::WH_GREEN,
        'HN-HD' => ColorConstant::WH_PURPLE,
        'HN-TQB' => ColorConstant::WH_BLUE,
        'HCM' => ColorConstant::WH_ORANGE,
    ];
}
