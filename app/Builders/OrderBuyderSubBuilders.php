<?php

namespace App\Builders;

use App\Constants\Buyder\BuyderStatusConstant;
use App\Constants\RoleConstant;
use App\Traits\Builders\PlatformSourceTrait;
use Illuminate\Database\Eloquent\Builder;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class OrderBuyderSubBuilders extends Builder
{
    use PlatformSourceTrait;

    /**
     * Lấy các đơn đặt hàng con của nhân viên này
     *
     * @return $this
     */
    public function owned(): self
    {
        $user = auth()->user();

        return $user->hasRoleName(RoleConstant::ADMIN) || $user->hasRoleName(RoleConstant::TRUONG_BP_GDQT)
            ? $this
            : $this->where('staff_id', auth()->id());
    }

    /**
     * Lấy các đơn đặt hàng Taobao đang ở 1 trong các trạng thái
     *   + Thanh toán, chờ giao hàng
     *   + Đã thanh toán, đang vận chuyển
     *
     * @return $this
     */
    public function whereTaobaoPaid(): self
    {
        return $this->sourceTaobao()
            ->whereIn('buyder_status', [
                BuyderStatusConstant::SUB_BUYDER_ORDER_WAIT_SELLER_SEND_GOODS,
                BuyderStatusConstant::SUB_BUYDER_ORDER_WAIT_BUYER_CONFIRM_GOODS,
            ]);
    }

    /**
     * Lấy các đơn đặt hàng 1688 con đang ở 1 trong các trạng thái
     *   + Chờ người bán gửi hàng
     *   + Chờ người mua nhận hàng
     *   + Xác nhận
     *
     * @return $this
     */
    public function where1688Paid(): self
    {
        return $this->source1688()
            ->whereIn('buyder_status', [
                BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_SELLER_SEND,
                BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_BUYER_RECEIVE,
                BuyderStatusConstant::ORDER_BUYDER_1688_CONFIRMED,
            ]);
    }
}
