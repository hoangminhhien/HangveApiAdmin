<?php

namespace App\Traits\Relationships;

use App\Models\SubOrderBuyder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait OrderPackageRelationships
{
    /**
     * Lấy ra bản ghi tương ứng trong bảng 'order_buyder_subs'
     *
     * @return BelongsTo
     */
    public function orderBuyderSub(): BelongsTo
    {
        return $this->belongsTo(SubOrderBuyder::class, 'order_no', 'buyder_no');
    }
}
