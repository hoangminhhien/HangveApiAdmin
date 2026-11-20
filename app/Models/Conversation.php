<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;
use App\Services\FilterService;
use App\Constants\ConversationConstant;

class Conversation extends BaseModel
{
    use Filterable;

    protected string $_tableNameFriendly = 'Conversation';
    protected $_prefixRoute = 'conversation';

    public $fillable = [
        'organization_id',
        'type',
        'type_id',
        'type_code',
        'type_status',
        'order_id',
        'customer_id',
        'customer_code',
        'customer_name',
        'customer_phone',
        'customer_image',
        'supplier_id',
        'supplier_name',
        'supplier_image',
        'user_id',
        'user_code',
        'user_name',
        'user_image',
        'unread_client',
        'unread_admin',
        'time_last_send',
        'time_last_send_client',
        'time_last_send_admin',
        'content_last_send',
        'conversation_config_id',
    ];

    protected $table = 'conversations';
    public $timestamps = true;
    protected $dates = [];


    public function scopeType($query)
    {
        $type = request()->query('type');
        return $query->where('type', $type);
    }

    public function scopeUnread($query)
    {
        $unread = request()->query('unread');
        if ($unread > 0) {
            return $query->where('unread_admin', '>', 0);
        }
    }

    public function scopeDate($query)
    {
        $date = request()->query('date');
        $data_start = $date . ' 00:00:00';
        $data_end   = $date . ' 23:59:59';
        return $query->where('time_last_send', '>=', $data_start)->where('time_last_send', '<=', $data_end);
    }

    public function scopeStatus($query)
    {
        $status = request()->query('status');
        return $query->where('conversation_config_id', $status);
    }

    public function scopeUserId($query)
    {
        $user_id = request()->query('user_id');
        return $query->where('user_id', $user_id);
    }

    public function scopeQ($query)
    {
        $q = request()->query('q');
        $query = $query->where(function($query) use($q) {
            $query->orWhere('type_code', 'like', "%{$q}%")
              ->orWhere('customer_name', 'like', "%{$q}%")
              ->orWhere('customer_phone', 'like', "%{$q}%")
              ->orWhere('customer_code', 'like', "%{$q}%");
        });
        return $query;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function conversationconfig()
    {
        return $this->belongsTo(ConversationConfig::class, 'conversation_config_id', 'id');
    }

    public function getTypeText()
    {
        $list = ConversationConstant::ARR_TYPE;
        return array_key_exists($this->type, $list) ? $list[$this->type] : 'Không xác định';
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }


}
