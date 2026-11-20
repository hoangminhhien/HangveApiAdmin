<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;
use App\Services\FilterService;

class Message extends BaseModel
{
    use Filterable;

    protected string $_tableNameFriendly = 'Message';
    protected $_prefixRoute = 'message';

    public $fillable = [
        'organization_id',
        'conversation_id',
        'source',
        'type',
        'content',
        'parent_id',
        'user_id',
    ];

    protected $table = 'messages';
    public $timestamps = true;
    protected $dates = [];


    public function scopeCode($query)
    {
        $code = request()->query('code');
        return $query->where('code', 'like', "%{$code}%");
    }

    public function scopeDateStart($query)
    {
        $date_start = request()->query('date_start');
        if (is_null($date_start)) {
            return $query->where('created_at', '>=', $date_start);
        }
        // return $query->where('created_at', '>=', $date_start);
    }

    public function scopeDateEnd($query)
    {
        $date_end = request()->query('date_end');
        if (is_null($date_end)) {
            return $query->where('created_at', '<=', $date_end);
        }
        // return $query->where('created_at', '<=', $date_end);
    }

    public function scopeMethod($query)
    {
        return $query->where('method', request()->query('method'));
    }

    public function scopeGroup($query)
    {
        return $query->where('group', request()->query('group'));
    }

    public function scopeStatus($query)
    {
        return $query->where('status', request()->query('status'));
    }

    public function scopeIdSort($query)
    {
        return $query->orderBy('id', request()->query('id_sort'));
    }

    public function getGroupText()
    {
        $list = CenterApiConstant::ARR_GROUP;

        return array_key_exists($this->group, $list) ? $list[$this->group] : 'Không xác định';
    }

    public function messageParent()
    {
        return $this->belongsTo(Message::class, 'parent_id', 'id');
    }

}
