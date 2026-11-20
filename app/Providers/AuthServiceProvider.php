<?php

namespace App\Providers;

use App\Policies\PlaceOrderPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
         'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        $this->registerPoliciesPlaceOrder();
    }

    /**
     * Đăng ký policies cho module 'Đặt hàng'
     *
     * @return void
     */
    private function registerPoliciesPlaceOrder(): void
    {
        // Xem danh sách đơn đặt hàng
        Gate::define('place_order_list', [PlaceOrderPolicy::class, 'placeOrderList']);
        // Xem danh sách đơn hỗ trợ
        Gate::define('place_order_assign_support_list', [PlaceOrderPolicy::class, 'placeOrderAssignSupportList']);
        // Tạo đơn hỗ trợ
        Gate::define('place_order_assign_support', [PlaceOrderPolicy::class, 'placeOrderAssignSupport']);
        // Hủy đơn hỗ trợ
        Gate::define('place_order_assign_support_cancel', [PlaceOrderPolicy::class, 'placeOrderAssignSupportCancel']);
        // Đặt hàng
        Gate::define('place_order_place_order', [PlaceOrderPolicy::class, 'placeOrderPlaceOrder']);
        // Hủy đặt hàng
        Gate::define('place_order_place_order_cancel', [PlaceOrderPolicy::class, 'placeOrderPlaceOrderCancel']);
        // Thanh toán
        Gate::define('place_order_payment', [PlaceOrderPolicy::class, 'placeOrderPayment']);
        // Xem báo cáo đặt hàng
        Gate::define('place_order_report_list', [PlaceOrderPolicy::class, 'placeOrderReportList']);
    }
}
