<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;

class UserChatbizPing extends BaseModel
{
    use Filterable;

    public $fillable = [
        'user_id',
        'user_code',
        'user_name',
        'date',
        'phone_call_out',
        'phone_call_out_success',
        'phone_call_in',
        'phone_call_in_success',
        'phone_call_out_list',
        'phone_call_in_list',
        'zalo_handle',
        'zalo_done',
        'zalo_late',
        'zalo_handle_list',
        'zalo_done_list',
        'zalo_late_list',
        'facebook_handle',
        'facebook_done',
        'facebook_late',
        'facebook_handle_list',
        'facebook_done_list',
        'facebook_late_list',
        'livechat_handle',
        'livechat_done',
        'livechat_late',
        'livechat_handle_list',
        'livechat_done_list',
        'livechat_late_list',
        'total_customer',
        'phone_call_out_full_list',
    ];

    protected $table = 'user_chatbiz_pings';
    public $timestamps = true;
}
