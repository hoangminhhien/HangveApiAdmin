<?php

namespace App\Policies;

use App\Models\User;

class PlaceOrderPolicy extends BasePolicy
{
    /**
     * Danh sách đặt hàng
     *
     * @param User $user
     * @return bool
     */
    public function placeOrderList(User $user): bool
    {
        return $user->hasPermissionName('place_order.index', $user);
    }

    /**
     * Danh sách đơn hỗ trợ
     *
     * @param User $user
     * @return bool
     */
    public function placeOrderAssignSupportList(User $user): bool
    {
        return $user->hasPermissionName('place_order.assign_support_list', $user);
    }

    /**
     * Lên đơn hỗ trợ
     *
     * @param User $user
     * @return bool
     */
    public function placeOrderAssignSupport(User $user): bool
    {
        return $user->hasPermissionName('place_order.assign_support', $user);
    }

    /**
     * Hủy đơn hỗ trợ
     *
     * @param User $user
     * @return bool
     */
    public function placeOrderAssignSupportCancel(User $user): bool
    {
        return $user->hasPermissionName('place_order.assign_support_cancel', $user);
    }

    /**
     * Đặt hàng
     *
     * @param User $user
     * @return bool
     */
    public function placeOrderPlaceOrder(User $user): bool
    {
        return $user->hasPermissionName('place_order.place_order', $user);
    }

    /**
     * Hủy đặt hàng
     *
     * @param User $user
     * @return bool
     */
    public function placeOrderPlaceOrderCancel(User $user): bool
    {
        return $user->hasPermissionName('place_order.place_order_cancel', $user);
    }

    /**
     * Thanh toán
     *
     * @param User $user
     * @return bool
     */
    public function placeOrderPayment(User $user): bool
    {
        return $user->hasPermissionName('place_order.payment', $user);
    }

    /**
     * Báo cáo đặt hàng
     *
     * @param User $user
     * @return bool
     */
    public function placeOrderReportList(User $user): bool
    {
        return $user->hasPermissionName('place_order.report_list', $user);
    }
}
