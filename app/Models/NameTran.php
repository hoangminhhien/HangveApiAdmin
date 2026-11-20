<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;
use App\Services\FilterService;

class NameTran extends BaseModel
{
    use Filterable;

    protected string $_tableNameFriendly = 'NameTran';
    protected $_prefixRoute = 'name_tran';

    public $fillable = [
        'order_id',
        'order_detail_id',
        'name_vi',
    ];

    protected $table = 'name_trans';
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
