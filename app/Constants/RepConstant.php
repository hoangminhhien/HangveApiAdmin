<?php


namespace App\Constants;

class RepConstant
{
    public const SOURCE_AI   = 1;
    public const SOURCE_USER = 2;

    public const TYPE_TEXT   = 1;
    public const TYPE_IMAGE  = 2;
    public const TYPE_VIDEO  = 3;
    public const TYPE_VOTE   = 4;
    public const TYPE_ITEM   = 5; // Chi tiết sản phẩm
    public const TYPE_PDF    = 6;
    public const TYPE_WORD   = 7;
    public const TYPE_RECALL = 8; // Thu hồi tin nhắn
    public const TYPE_OPTION = 9; // Vừa text vừa option

    public const ACTION_DEFAULT  = 0;
    public const ACTION_QUESTION = 1;

    // Có dùng tin nhắn này vào lịch sử cuối không
    public const CLEAR_ACTIVE   = 1;
    public const CLEAR_INACTIVE = 0;
}
