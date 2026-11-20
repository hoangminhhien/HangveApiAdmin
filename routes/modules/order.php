<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Fix lỗi đơn sai % đặt hàng
    Route::get('/order-check-price', [OrderController::class, 'checkPrice']);
    Route::post('/order-fee-update', [OrderController::class, 'updateOrderFee']);
    Route::get('/total-amount-payable', [OrderController::class, 'totalAmountPayable']);
//    Route::post('/update-deposit-cost', [OrderController::class, 'updateDepositCost']);
    Route::post('/update-exchange-rate', [OrderController::class, 'updateExchangeRate']);
    Route::post('/circle-staff-order', [OrderController::class, 'circleStaffOrder']);
    Route::post('/split-order/{supplierId}/{orderId}', [OrderController::class, 'splitOrder']);
    Route::get('/get-list-deposited', [OrderController::class, 'getListDeposited']);
    Route::get('/export', [OrderController::class, 'export'])->middleware(['permission:order.index']);
    Route::post('/demo-per-0', [OrderController::class, 'demoPer']);
    Route::get('/service-charge', [OrderController::class, 'serviceCharge']);
    Route::patch('/change-ecommerce/{id}', [OrderController::class, 'changeEcommerce']);
    Route::patch('/update-bonus/{id}', [OrderController::class, 'updateBonus']);
    Route::get('/', [OrderController::class, 'pagination'])->middleware(['permission:order.index']);
    Route::get('/{id}', [OrderController::class, 'detail'])->name('order_detail');
    Route::post('/{id}', [OrderController::class, 'update'])->middleware(['permission:order.edit']);
    Route::patch('/change-staff/{order}', [OrderController::class, 'changeStaff'])->middleware(['permission:order.edit']);
    Route::post('/change-staff-multi/v1', [OrderController::class, 'changeStaffMulti'])->middleware(['permission:order.edit']);
    Route::patch('/cancel', [OrderController::class, 'cancel']);
    Route::patch('/is_waiting_for_sale', [OrderController::class, 'isWaitingForSale']);
    Route::patch('/is_quoting', [OrderController::class, 'isQuoting']);
    Route::patch('/is_waiting_confirm', [OrderController::class, 'isWaitingConfirm']);
    Route::patch('/is_china_shipping', [OrderController::class, 'isChinaShipping']);
    Route::patch('/price', [OrderController::class, 'updateOrderCostAndChinaShippingCost']);
    Route::patch('/status/{order}', [OrderController::class, 'changeStatus'])->middleware(['permission:order.edit']);
    Route::patch('/change-supplier', [OrderController::class, 'changeSupplier'])->middleware(['permission:order.edit']);
    Route::delete('/{order}', [OrderController::class, 'destroy'])->middleware(['permission:order.delete']);
    Route::post('create-cancel/{order}', [OrderController::class, 'createCancel'])
        ->middleware(['permission:order.create_cancel']);
    Route::post('create-refund/{order}', [OrderController::class, 'createRefund'])
        ->middleware(['permission:order.create_refund']);
    Route::get('/get/list-refund', [OrderController::class, 'listRefund'])
        ->middleware(['permission:order.view_refund']);
    Route::post('/refund/multiple', [OrderController::class, 'refundMultiple']);
    Route::post('/refund/cancel', [OrderController::class, 'refundCancel']);
    Route::get('/get-debt/{packageIds}', [OrderController::class, 'getDebt']);
    Route::get('/products/{id}', [OrderController::class, 'getProducts']);
    Route::group(['prefix' => 'note/{orderId}'], function () {
        Route::get('/', [OrderController::class, 'getNotes'])->middleware(['permission:order.index']);
        Route::post('/', [OrderController::class, 'addNote'])->middleware(['permission:order.edit']);
    });

     Route::get('/get-products-of-order/{id}', [OrderController::class, 'getProductOfOrder']); // Lấy danh sách sản phẩm
     Route::post('/has-edit-true/{id}', [OrderController::class, 'hasEditTrue'])->name('order.has-edit-true');

    // Đếm số lượng đơn chờ gán tư vấn, chờ gán chăm sóc
    Route::post('/count-order-wait-assign/care-and-counselor', [OrderController::class, 'countOrderWaithAssign']);
    Route::get('/list/get-by-customer', [OrderController::class, 'getOrdersByCustomer'])->name('get-order-customer');
    Route::patch('/change-deposit/{order}', [OrderController::class, 'changeDeposit']);
    Route::get('/user/{order}', [OrderController::class, 'getUserInOrder']);
    // ứng tiền
    Route::group(['prefix' => 'advance-money'], function () {
        Route::post('/{order}', [OrderController::class, 'storeAdvanceMoney'])->middleware(['permission:order.index']);
        Route::put('/approve', [OrderController::class, 'approveAdvanceMoney']);
        Route::put('/cancel', [OrderController::class, 'cancelAdvanceMoney']);
        Route::get('/list', [OrderController::class, 'listAdvanceMoney'])->middleware(['permission:advanceMoney.index']);
    });
    Route::group(['prefix' => 'order-detail'], function () {
        Route::get('/related-order', [OrderController::class, 'relatedOrder']);
        Route::get('/{id}', [OrderController::class, 'getOrderDetail']);
    });
    Route::post('/contact-docx/preview/{orderId}', [OrderController::class, 'previewContactDocx'])->middleware(['permission:order.request_order_contract']);

    // tách nhỏ các api màn 'đơn hàng'
    // lấy tổng tiền chờ đặt hàng bằng tệ và vnd
    Route::get('/list/amount', [OrderController::class, 'getListAmount'])
        ->middleware(['permission:order.index']);
    // Danh sách đơn hàng hủy
    Route::get('/list/cancel', [OrderController::class, 'listCancel']);
    Route::get('/order-history/{order_id}', [OrderController::class, 'getOrderHistory']);
    Route::post('/update-quick-order/{order_id}', [OrderController::class, 'updateQuickOrder']);
    Route::post('/update-quick-inspection/{order_id}', [OrderController::class, 'updateQuickInspection']);
    Route::post('/update-quick-advanced-inspection/{order_id}', [OrderController::class, 'updateQuickAdvancedInspection']);
    Route::post('/update-quick-wood-working/{order_id}', [OrderController::class, 'updateQuickWoodworking']);
    Route::post('/update-quick-shock-proof/{order_id}', [OrderController::class, 'updateShockProof']);
    Route::post('/update-quick-warehouse/{order_id}', [OrderController::class, 'updateWarehouse']);
    Route::post('/update-quick-official/{order_id}', [OrderController::class, 'updateOfficial']);
    Route::post('/update-official-is-partner/{order_id}', [OrderController::class, 'updateOfficialIsPartner']);
    Route::post('/update-quick-customer-delivery/{order_id}', [OrderController::class, 'updateCustomerDelivery']);


    // Fix lỗi đơn đồng bộ khi tách ra
    // Route::post('/fix-order-td/07-10-24', [OrderController::class, 'fixOrderTD']);
    // Route::post('/fix-order-td/07-10-24-v2', [OrderController::class, 'fixOrderTDV2']);
    // Route::post('/fix-order-td/07-10-24-v3', [OrderController::class, 'fixOrderTDV3']);
    // Route::get('/sync/date-done', [OrderController::class, 'syncDateDone']);
    Route::get('/actual-payment-supplier/export', [OrderController::class, 'actualPaymentExport']);
    Route::get('/sync/date-by-orderstatustime', [OrderController::class, 'syncDateByOrderStatusTime']);
    Route::get('/sync/order-cost', [OrderController::class, 'syncOrderCost']);
    Route::get('/check-valid-packge/{order_id}', [OrderController::class, 'checkValidPackage']);

    Route::post('/create-order-buyder/{order_id}', [OrderController::class, 'createOrderBuyder']);
    Route::post('/change-status/confirm-order', [OrderController::class, 'changeStatusConfirm']);
    Route::get('/auto/cancel-order', [OrderController::class, 'autoCancelOrder']);
    Route::get('/links/by-supplier/{id}', [OrderController::class, 'linkBySupplier']);
    Route::post('/request/deposit-percent', [OrderController::class, 'requestDepositPercent'])->middleware(['permission:order.deposit_for_supplier']);
    Route::post('/request/deposit-percent-customer', [OrderController::class, 'requestDepositPercentCustomer'])->middleware(['permission:order.request_deposit_customer']);
    Route::get('/total-order/by-exchange-rate-new/{order_id}', [OrderController::class, 'totalOrderByExchangeRateNew']);
    Route::patch('/update-exchange-rate-by-id', [OrderController::class, 'updateExchangeRateId'])->middleware(['permission:order.change_exchange_rate']);
});
