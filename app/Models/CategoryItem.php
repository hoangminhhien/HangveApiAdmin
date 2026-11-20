<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;

class CategoryItem extends BaseModel
{
    use Filterable;

    protected string $_tableNameFriendly = 'Danh mục sản phẩm tìm kiếm';
    protected $_prefixRoute = 'category_item';

    public $fillable = [
        'name',
        'slug',
        'image',
        'parant_id',
        'last_time_sync',
        'priority',
        'hot',
        'status',
        'name_cn',
        'keyword_type',
        'user_id'
    ];

    protected $table = 'category_items';
    public $timestamps = true;

    public function scopeName($query)
    {
        $name = request()->query('name');
        return $query->where('name', 'like', "%{$name}%");
    }


    public function scopeIdSort($query)
    {
        return $query->orderBy('id', request()->query('id_sort'));
    }

    public function scopeStatus($query)
    {
        return $query->where('status', request()->query('status'));
    }


}
