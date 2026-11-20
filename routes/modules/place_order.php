<?php

use App\Http\Controllers\PlaceOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // danh sách trạng thái đặt hàng (order_buyders.supplier_status, kèm theo số lượng order_buyders tương ứng
    Route::get('/supplier-status-list', [PlaceOrderController::class, 'getSupplierStatusList']);
    // danh sách trạng thái đặt hàng con (order_buyder_subs.buyder_status
    // kèm theo số lượng order_buyder_subs tương ứng
    Route::get('/supplier-sub-status-list', [PlaceOrderController::class, 'getSupplierSubStatusList']);
    // danh sách trạng thái đơn đặt hàng trang 'Danh sách thanh toán', kèm số lượng đơn từng tt
    Route::get('/payment-supplier-status-list', [PlaceOrderController::class, 'getPaymentSupplierStatusList']);

    // báo cáo đặt hàng
    Route::get('/place-order-report', [PlaceOrderController::class, 'getOrderReport']);

    // lý do hủy đặt hàng
    Route::get('/place-order-cancel-reasons', [PlaceOrderController::class, 'getCancelReasons']);

    // danh sách order_buyders tương ứng với 1 order
    Route::get('/order-order-buyders/{order_id}', [PlaceOrderController::class, 'getAllOrderOrderBuyders']);
    // danh sách các order_buyder_subs thuộc một order
    Route::get('/order-sub-orders/{order_id}', [PlaceOrderController::class, 'getAllOrderSubs']);
    // danh sách các order_buyder_subs thuộc một order_detail
    Route::get('/order-detail-sub-orders/{order_detail_id}', [PlaceOrderController::class, 'getAllOrderDetailSubs']);

    // danh sách đơn nhờ hỗ trợ
    Route::get('/assign-support/list', [PlaceOrderController::class, 'getAssignSupportList']);
    // gán người hỗ trợ
    Route::post('/assign-support', [PlaceOrderController::class, 'assignSupport']);
    // hủy đơn hỗ trợ
    Route::post('/assign-support-cancel', [PlaceOrderController::class, 'assignSupportCancel']);
    // kiểm tra xem đã nhờ nv này hỗ trợ chưa
    Route::post('/assign-support/check-assigned', [PlaceOrderController::class, 'checkIsAssignedSupport']);

    // danh sách đơn đặt hàng (không kèm order_buyders)
    Route::get('/', [PlaceOrderController::class, 'listPlaceOrder']);

    Route::post('/sync-skuId', [PlaceOrderController::class, 'syncSkuId']);
    Route::post('/cancel_order_detail', [PlaceOrderController::class, 'cancelOrderDetail']);

    Route::post('/preview-order', [PlaceOrderController::class, 'previewOrder']);
    // đặt hàng (order_buyders, order_buyder_subs)
    Route::post('/place-order', [PlaceOrderController::class, 'placeOrder'])
        ->middleware('ensureUserHavePlatformId');

    Route::post('/update-order-buyer-tao-bao', [PlaceOrderController::class, 'updateOrderBuyerTaoBao']);

    Route::post('/order-detail', [PlaceOrderController::class, 'orderDetail']);
    Route::post('/xadal', [PlaceOrderController::class, 'xadal']);
    // hủy đặt hàng (order_buyders, order_buyder_subs)
    Route::post('/cancel-order', [PlaceOrderController::class, 'cancelOrder'])
        ->middleware('ensureUserHavePlatformId');

    // thanh toán
    Route::get('/payment', [PlaceOrderController::class, 'getPaymentList']);
    Route::post('/payment', [PlaceOrderController::class, 'handlePayment'])
        ->middleware('ensureUserHavePlatformId');

    // kết nối IM
    Route::post('/connect-im', [PlaceOrderController::class, 'connectIm'])
        ->middleware('ensureUserHavePlatformId');
    Route::post('/connect-chat', [PlaceOrderController::class, 'connectChat']);

    // chi tiết đơn đặt hàng
    Route::get('/{order_detail_id}', [PlaceOrderController::class, 'getPlaceOrderDetail']);
    // Cập nhật MĐH bằng tay khi job lỗi
    Route::get('/update/order-code-po', [PlaceOrderController::class, 'updateOrderCodePo']);
});
Route::post('/sync-status-order-buyder', [PlaceOrderController::class, 'syncStatusOrderBuyder']);
Route::post('/cancel-order-from-other-website', [PlaceOrderController::class, 'cancelOrderFromOtherWebsite']);
Route::post('/update-order-ordered-from-other-website', [PlaceOrderController::class, 'updateOrderOrderedFromOtherWebsite']);
Route::post('/update-order-status-from-other-website', [PlaceOrderController::class, 'updateOrderStatusFromOtherWebsite']);
