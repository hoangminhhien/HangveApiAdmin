<?php

namespace App\Builders;

use App\Traits\Builders\PlatformSourceTrait;
use Illuminate\Database\Eloquent\Builder;

class OrderDetailBuilders extends Builder
{
    use PlatformSourceTrait;

    /**
     * Kèm thêm các sub_order_buyder tương ứng
     *
     * @return $this
     */
    public function subOrderBuyders(): self
    {
        return $this->with(['subOrderBuyders']);
    }

    /**
     * Chỉ lấy các order_detail đã có sub_order_buyder
     *
     * @return $this
     */
    public function hasSubOrderBuyder(): self
    {
        return $this->whereHas('subOrderBuyder')->with(['subOrderBuyders']);
    }
}
