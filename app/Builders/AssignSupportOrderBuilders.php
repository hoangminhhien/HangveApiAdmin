<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class AssignSupportOrderBuilders extends Builder
{
    /**
     * Lấy các đơn hàng nhân viên này nhờ người khác đặt hộ
     *
     * @return $this
     */
    public function owned(): self
    {
        return $this->where('assigner_id', auth()->id());
    }

    /**
     * Lấy các đơn hàng nhân viên được người khác nhờ đặt hộ
     *
     * @return $this
     */
    public function assigned(): self
    {
        return $this->where('assignee_id', auth()->id());
    }

    /**
     * Lấy các đơn được nhờ và đặt hàng
     *
     * @return $this
     */
    public function placed(): self
    {
        return $this->whereNotNull('order_no');
    }
}
