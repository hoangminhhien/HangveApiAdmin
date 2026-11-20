<?php


namespace App\Constants;

class MessageConstant
{
    public const SOURCE_CUSTOMER = 1;
    public const SOURCE_USER     = 2;

    public const TYPE_TEXT  = 1;
    public const TYPE_IMAGE = 2;
    public const TYPE_VIDEO = 3;
    public const TYPE_VOTE  = 4;
    public const TYPE_ITEM  = 5; // Chi tiết sản phẩm
    public const TYPE_PDF   = 6;
    public const TYPE_WORD  = 7;
    public const TYPE_RECALL  = 8; // Thu hồi tin nhắn
    public const TYPE_CREATE_DELIVERY  = 9; // Tin nhắn tạo giao hàng
}
