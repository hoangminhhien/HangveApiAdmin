<?php

namespace App\Models;
use App\Builders\ItemBuilders;
use App\Scopes\Traits\Filterable;
use Illuminate\Database\Query\Builder;

class Item extends BaseModel
{
    use Filterable;

    /**
     * PROPERTIES
     */
    protected string $_tableNameFriendly = 'Sản phẩm tìm kiếm';
    protected $_prefixRoute = 'item';

    public $fillable = [
        'key_search',
        'source',
        'num_iid',
        'pic',
        'title',
        'title_vi',
        'price',
        'promotion_price',
        'sales',
        'seller_id',
        'seller_nick',
        'seller_nick_vi',
        'user_type',
        'delivery_fee',
        'is_china_freeshiping',
        'detail_url',
        'buyder_data',
        'translate_prop',
        'buyder_data_last_time_sync',
        'sku_properties',
        'status',
        'data',
        'last_time_sync',
    ];

    protected $table = 'items';
    public $timestamps = true;

    protected $casts = [
        'sales' => 'float',
    ];

    /**
     * Customize eloquent query builder
     *
     * @param Builder $query
     *
     * @return ItemBuilders
     */
    public function newEloquentBuilder($query): ItemBuilders
    {
        return new ItemBuilders($query);
    }

    /**
     * ACCESSORS & MUTATORS
     */
    public function getPicAttribute($value)
    {
        return $value ?? '';
    }
}
