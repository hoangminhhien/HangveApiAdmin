<?php

namespace App\Traits\Relationships;

use App\Builders\OrderBuyderBuilders;
use App\Models\Order;
use App\Models\OrderPackage;
use App\Models\SubOrderBuyder;
use App\Models\User;
use App\Models\OrderBuyderCategory;
use App\Pivots\OrderBuyderOrder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;

trait OrderBuyderRelationships
{
    /**
     * Customize eloquent query builder
     *
     * @param Builder $query
     * @return OrderBuyderBuilders
     */
    public function newEloquentBuilder($query): OrderBuyderBuilders
    {
        return new OrderBuyderBuilders($query);
    }

    /**
     * Lấy ra các record tương ứng trong bảng 'orders'
     *
     * @return BelongsTo
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(
            Order::class, OrderBuyderOrder::class,
            'order_buyder_id', 'order_id'
        );
    }

    /**
     * Lấy ra các record tương ứng trong bảng 'sub_order_buyders'
     *
     * @return BelongsTo
     */
    public function subOrderBuyders(): HasMany
    {
        return $this->hasMany(SubOrderBuyder::class);
    }

    /**
     * Lấy nhân viên phụ trách đơn hàng (nv đặt hàng)
     *
     * @return BelongsTo
     */
    public function staffOrder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }

    public function orderBuyderCategories(): HasMany
    {
        return $this->HasMany(OrderBuyderCategory::class);
    }

    public function orderPackage(): HasMany
    {
        return $this->hasMany(OrderPackage::class, 'buyder_no', 'buyder_no');
    }

    /**
     * Tổng số sản phẩm đã đặt hàng của order_buyder này
     *
     * @return int
     */
    public function totalPlacedQuantity(): int
    {
        return $this->subOrderBuyders->sum('quantity');
    }
}
