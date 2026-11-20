<?php

namespace App\Builders;

use App\Constants\Buyder\BuyderStatusConstant;
use App\Constants\RoleConstant;
use App\Traits\Builders\PlatformSourceTrait;
use Illuminate\Database\Eloquent\Builder;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class OrderBuyderBuilders extends Builder
{
    use PlatformSourceTrait;

    /**
     * Danh sách các đơn đặt hàng do mình tạo
     *
     * @return $this
     */
    public function owned(): self
    {
        $user = auth()->user();

        return $user->hasRoleName(RoleConstant::ADMIN) || $user->hasRoleName(RoleConstant::TRUONG_BP_GDQT)
            ? $this
            : $this->where('creator_id', auth()->id());
    }

    /**
     * Lấy các Buyder Order đã được phân chia cho >= 1 nhân viên (có bản ghi trong SubOrderBuyder)
     *
     * @return $this
     */
    public function hasOrderBuyderSubs(): self
    {
        return $this->whereHas('subOrderBuyders');
    }

    /**
     * Lấy các đơn đặt hàng chưa bị hủy
     *
     * @return $this
     */
    public function notCanceled(): self
    {
        return $this->where('supplier_status', '!=', BuyderStatusConstant::ORDER_TRADE_CLOSED);
    }

    /**
     * Lấy các đơn đặt hàng chưa được giao thành công
     *
     * @return $this
     */
    public function notDelivered(): self
    {
        return $this->where('supplier_status', '!=', BuyderStatusConstant::BUYDER_ORDER_DELIVERIES);
    }

    /**
     * Lấy các đơn đặt hàng Taobao đang ở 1 trong các trạng thái
     *   + Đã thanh toán/Chờ xác nhận TC
     *   + Xác nhận TC thành công
     *
     * @return $this
     */
    public function whereTaobaoPaid(): self
    {
        return $this->sourceTaobao()
            ->whereIn('supplier_status', [
                BuyderStatusConstant::BUYDER_ORDER_PAID,
                BuyderStatusConstant::BUYDER_ORDER_CONFIRM_FINANCE_SUCCESS,
            ]);
    }

    /**
     * Lấy các đơn đặt hàng Taobao từ sau khi đã thanh toán
     *
     * @return $this
     */
    public function whereTaobaoPaidOrAfter(): self
    {
        return $this->sourceTaobao()
            ->whereIn('supplier_status', [
                BuyderStatusConstant::BUYDER_ORDER_PAID,
                BuyderStatusConstant::BUYDER_ORDER_CONFIRM_FINANCE_SUCCESS,
                BuyderStatusConstant::BUYDER_ORDER_CONFIRM_FINANCE_FAIL,
                BuyderStatusConstant::ORDER_TRADE_CLOSED,
                BuyderStatusConstant::BUYDER_ORDER_WAIT_SELLER_SEND_GOODS,
                BuyderStatusConstant::BUYDER_ORDER_DELIVERIES,
            ]);
    }

    /**
     * Lấy các đơn đặt hàng 1688 đang ở 1 trong các trạng thái
     *   + Chờ người bán gửi hàng
     *   + Chờ người mua nhận hàng
     *   + Xác nhận
     *
     * @return $this
     */
    public function where1688Paid(): self
    {
        return $this->source1688()
            ->whereIn('supplier_status', [
                BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_SELLER_SEND,
                BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_BUYER_RECEIVE,
                BuyderStatusConstant::ORDER_BUYDER_1688_CONFIRMED,
            ]);
    }

    /**
     * Lấy các đơn đặt hàng không còn ở trạng thái 'chờ người mua thanh toán' của 1688 (waitbuyderpay)
     * Nghĩa là đã thanh toán hoặc các trạng thái sau đó
     *
     * @return $this
     */
    public function where1688PaidOrAfter(): self
    {
        return $this->where('supplier_status', '!=', BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_BUYER_PAY);
    }

    /**
     * Lấy các đơn đặt hàng chưa bị hủy
     *
     * @return $this
     */
    public function where1688NotCancel(): self
    {
        return $this->where('supplier_status', '!=', BuyderStatusConstant::ORDER_BUYDER_1688_CANCELED);
    }

    /**
     * Lấy danh sách các order_buyders được xuất hiện trong màn 'Danh sách thanh toán'
     *   + Taobao: BuyderStatusConstant::ORDER_BUYDER_TAOBAO_PAYMENT_STATUSES
     *   + 1688: BuyderStatusConstant::ORDER_BUYDER_1688_PAYMENT_STATUSES
     *
     * @return $this
     */
    public function whereShownInPaymentListPage(): self
    {
        $source = request()->get('source') ?? BuyderServiceConstant::SOURCE_TAOBAO;

        switch ($source) {
            case BuyderServiceConstant::SOURCE_1688:
            case BuyderServiceConstant::SOURCE_TAOBAO:
                $statuses = array_keys(BuyderStatusConstant::LIST_ORDER_STATUS_NAME);
                break;
            default:
                $statuses = [];
                break;
        }

        return $this->whereSource($source)->whereIn('supplier_status', $statuses);
    }
}
