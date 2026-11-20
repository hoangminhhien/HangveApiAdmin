<?php

namespace App\Builders;

use App\Constants\OrderConstant;
use App\Constants\RoleConstant;
use Illuminate\Database\Eloquent\Builder;

class OrderBuilders extends Builder
{
    /**
     * Lấy các đơn hàng đã được đặt cọc
     *
     * @return $this
     */
    public function deposited(): self
    {
        return $this->where('status', OrderConstant::KEY_STATUS_DEPOSITED);
    }

    /**
     * Nếu là admin hoặc trưởng bộ phận giao dịch quốc tế thì lấy hết
     * Còn lại lấy các đơn được giao cho auth user
     *
     * @return $this
     */
    public function owned(): self
    {
        $user = auth()->user();

        return $user->hasRoleName(RoleConstant::ADMIN) || $user->hasRoleName(RoleConstant::TRUONG_BP_GDQT)
            ? $this
            : $this->where('staff_order_id', auth()->id());
    }

    /**
     * Lấy các bản ghi có thể xuất hiện trong màn 'Đặt hàng'
     * Phải thuộc 1 trong 3 trạng thái
     *   + OrderConstant::KEY_STATUS_DEPOSITED (Đã đặt cọc)
     *   + OrderConstant::KEY_STATUS_WAIT_TO_PAY (Chờ thanh toán)
     *   + OrderConstant::KEY_STATUS_ORDERED (Đặt hàng)
     *
     * @return $this
     */
    public function visibleInPlaceOrder(): self
    {
        return $this->whereIn('status', [
            OrderConstant::KEY_STATUS_DEPOSITED,
            OrderConstant::KEY_STATUS_WAIT_TO_PAY,
            OrderConstant::KEY_STATUS_ORDERED,
        ]);
    }
}
