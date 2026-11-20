<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;
use App\Services\FilterService;
use App\Constants\ConversationConstant;

class AdviseCN extends BaseModel
{
    use Filterable;

    protected string $_tableNameFriendly = 'AdviseCN';
    protected $_prefixRoute = 'advise_cn';
    public $fillable = [
        'type',
        'customer_name',
        'customer_phone',
        'warehouse_id',
        'warehouse_code',
        'item_name',
        'item_color',
        'item_size',
        'item_material',
        'item_info',
        'item_branch',
        'item_quantity',
        'item_kg',
        'item_m3',
        'item_unit_declare',
        'hs_code',
        't_tax_xnk',
        't_tax_vat',
        't_tax_ttdb',
        't_tax_protect',
        't_tax_nature',
        'taxTTDB',
        'taxNature',
        'taxProtect',
        'taxVAT',
        'taxXNK',
        'feeVCQT',
        'feeService',
        'feeOpen',
        'total_amount',
        'user_id',
        'status',
        'source'
    ];

    protected $table = 'advise_cns';
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
        return $query->where('status', $status);
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

    public function scopeCustomerName($query)
    {
        $customer_name = request()->query('customer_name');
        return $query->where('customer_name', 'like', "%{$customer_name}%");
    }

    public function scopeCustomerPhone($query)
    {
        $customer_phone = request()->query('customer_phone');
        return $query->where('customer_phone', 'like', "%{$customer_phone}%");
    }

    public function scopeItemName($query)
    {
        $item_name = request()->query('item_name');
        return $query->where('item_name', 'like', "%{$item_name}%");
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

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }


}
