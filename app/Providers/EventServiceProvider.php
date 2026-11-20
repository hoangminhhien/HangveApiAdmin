<?php

namespace App\Providers;

use App\Events\PlaceOrder\PlaceOrderCreateLocalDataEvent;
use App\Events\SyncProductDetailEvent;
use App\Listeners\PlaceOrderCreateLocalDataListener;
use App\Listeners\SyncProductDetailListener;
use App\Models\AssignSupportOrder;
use App\Models\CartDetail;
use App\Models\Complain;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\CustomerOffer;
use App\Models\Delivery;
use App\Models\Fine;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderDetailNote;
use App\Models\OrderPackage;
use App\Models\SubOrderBuyder;
use App\Models\Transaction;
use App\Models\Notification;
use App\Models\ReportCustomer;
use App\Models\Category;
use App\Observers\AssignSupportOrderObserver;
use App\Observers\CartDetailObserver;
use App\Observers\ComplainObserver;
use App\Observers\ConsignmentObserver;
use App\Observers\CustomerObserver;
use App\Observers\CustomerOfferObserver;
use App\Observers\DeliveryObserver;
use App\Observers\FineObserve;
use App\Observers\OrderBuyderSubObserver;
use App\Observers\OrderDetailNoteObserve;
use App\Observers\OrderDetailObserve;
use App\Observers\OrderObserve;
use App\Observers\OrderPackageObserver;
use App\Observers\TransactionObserver;
use App\Observers\NotificationObserver;
use App\Observers\ReportCustomerObserver;
use App\Observers\CategoryObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        PlaceOrderCreateLocalDataEvent::class => [
            PlaceOrderCreateLocalDataListener::class,
        ],
        SyncProductDetailEvent::class => [
            SyncProductDetailListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        Customer::observe(CustomerObserver::class);
        CustomerOffer::observe(CustomerOfferObserver::class);
        CartDetail::observe(CartDetailObserver::class);
        OrderDetailNote::observe(OrderDetailNoteObserve::class);
        Order::observe(OrderObserve::class);
        OrderDetail::observe(OrderDetailObserve::class);
        Fine::observe(FineObserve::class);
        OrderPackage::observe(OrderPackageObserver::class);
        Delivery::observe(DeliveryObserver::class);
        Complain::observe(ComplainObserver::class);
        Transaction::observe(TransactionObserver::class);
        Notification::observe(NotificationObserver::class);
        Consignment::observe(ConsignmentObserver::class);
        SubOrderBuyder::observe(OrderBuyderSubObserver::class);
        AssignSupportOrder::observe(AssignSupportOrderObserver::class);
        ReportCustomer::observe(ReportCustomerObserver::class);
        Category::observe(CategoryObserver::class);
    }
}
