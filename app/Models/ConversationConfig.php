<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;
use App\Services\FilterService;

use Spatie\Activitylog\Traits\LogsActivity;

class ConversationConfig extends BaseModel
{
    use Filterable;
    use LogsActivity;

    protected string $_tableNameFriendly = 'ConversationConfig';
    protected $_prefixRoute = 'conversation-config';

    public $fillable = [
        'name',
        'color',
        'time',
    ];

    protected $table = 'conversation_configs';
    public $timestamps = true;
    protected $dates = [];

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;
    protected static $logName = 'conversation-configs';


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
        return $query->where('created_at', '>=', $data_start)->where('created_at', '<=', $data_end);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

}
