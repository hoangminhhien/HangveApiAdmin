<?php

namespace ThuongDo\Buyder\src;

use Illuminate\Support\ServiceProvider;
use ThuongDo\Buyder\src\Actions\CancelOrder\CancelOrder1688Action;
use ThuongDo\Buyder\src\Actions\CancelOrder\CancelOrderTaobaoAction;
use ThuongDo\Buyder\src\Actions\Hook\UpdatePlatformOrdersAction;
use ThuongDo\Buyder\src\Actions\Payment\Payment1688Action;
use ThuongDo\Buyder\src\Actions\Payment\PaymentTaobaoAction;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrder1688Action;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderTaobaoAction;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;
use ThuongDo\Buyder\src\Services\Service1688;
use ThuongDo\Buyder\src\Services\ServiceHook;
use ThuongDo\Buyder\src\Services\ServiceRequest;
use ThuongDo\Buyder\src\Services\ServiceTaobao;

class BuyderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->getConfigPath(), 'buyder');
    }

    public function boot(): void
    {
        $this->registerGeneral();
        $this->registerTaobaoActions();
        $this->register1688Actions();
        $this->registerHookActions();
    }

    private function registerGeneral(): void
    {
        $this->app->instance('BuyderFactory', new BuyderFactory());
        $this->app->instance(BuyderServiceConstant::SYNC_TAOBAO, new ServiceTaobao());
        $this->app->instance(BuyderServiceConstant::SYNC_1688, new Service1688());
        $this->app->instance(ServiceRequest::class, new ServiceRequest());
        $this->app->instance(ServiceHook::class, new ServiceHook());
    }

    private function registerTaobaoActions(): void
    {
        $this->app->instance(PlaceOrderTaobaoAction::class, new PlaceOrderTaobaoAction());
        $this->app->instance(CancelOrderTaobaoAction::class, new CancelOrderTaobaoAction());
        $this->app->instance(PaymentTaobaoAction::class, new PaymentTaobaoAction());
    }

    private function register1688Actions(): void
    {
        $this->app->instance(PlaceOrder1688Action::class, new PlaceOrder1688Action());
        $this->app->instance(CancelOrder1688Action::class, new CancelOrder1688Action());
        $this->app->instance(Payment1688Action::class, new Payment1688Action());
    }

    private function registerHookActions(): void
    {
        $this->app->instance(UpdatePlatformOrdersAction::class, new UpdatePlatformOrdersAction());
    }

    /**
     * Trả về đường dẫn file config của package
     *
     * @return string
     */
    private function getConfigPath(): string
    {
        return __DIR__ . '/Configs/buyder.php';
    }
}
