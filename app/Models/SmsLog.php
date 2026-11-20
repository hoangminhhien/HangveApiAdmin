<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_number',
        'content',
        'log_type',
        'user_code',
        'response_status',
        'sms_source'
    ];

    const SMS_SOURCE_ZALO = 'sms_zalo';
    const SMS_SOURCE_PHONE = 'sms_phone';

    const LOG_TYPE_SMS_CHANGE_STATUS_PACKAGE = 1; /* Chuyển trạng thái kiện hàng sang đã về việt nam*/
    const LOG_TYPE_SMS_RECHARGE = 2; /* Nạp tiền */
    const LOG_TYPE_SMS_CHANGE_STATUS_ORDER = 3; /* Chuyển trạng thái đơn hàng sang chờ báo giá*/
    const RESPONSE_500 = "-500" ; /* Số điện thoại không đúng định dạng */
    const RESPONSE_5 = "-5" ; /* Một trong các tham số truyền vào là null */
    const RESPONSE_2 = "-2" ; /* Trùng nội dung trong 10 phút */
    const RESPONSE_1 = "-1" ; /* Lỗi hệ thống */
    const RESPONSE_SUCCESS = "1" ; /* Thành công */
    const ZALO_TEMPLATE_ID_KIEN_HANG_DEN_KHO_VN = 410786;
    const ZALO_TEMPLATE_ID_BAO_GIA_DON_HANG = 410789;

    const ZALO_TEMPLATE_ID_NAP_TIEN_THANH_CONG = 432903;

    const ZALO_TEMPLATE_ID_THONG_BAO_GIAO_HANG = 431906;

    const LOG_TYPE_SMS_WITH_DRAW_MONEY = 4; /* Rút tiền */

    const LOG_TYPE_SMS_CHANGE_PASSWORD = 5; /* Thay đổi mật khẩu */
    const LOG_TYPE_SMS_LOGIN = 6; /* Login */
    const LOG_TYPE_SMS_THONG_BAO_GIAO_HANG = 7;
    const ZALO_TEMPLATE_ID_OTP = 410777;
}
