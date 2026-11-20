<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;
use App\Services\FilterService;
use App\Constants\ConversationConstant;

class Chat extends BaseModel
{
    use Filterable;

    protected string $_tableNameFriendly = 'Chat';
    protected $_prefixRoute = 'chat';

    public $fillable = [
        'organization_id',
        'user_id',
        'user_code',
        'user_name',
        'user_email',
        'user_image',
        'order_id',
        'order_code',
        'content_last_send',
        'time_last_send',
        'summary',
        'attribute_name',
        'options',
        'items',
        'items_cache',
        'item_ids',
        'rep_id_new',
    ];

    protected $table = 'chat_ais';
    public $timestamps = true;

    protected $dates = [
        'time_last_send',
    ];


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

    public function scopeOrderCode($query)
    {
        $order_code = request()->query('order_code');
        return $query->where('order_code', $order_code);
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
