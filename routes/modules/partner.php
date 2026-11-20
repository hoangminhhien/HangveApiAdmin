<?php

use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PartnerV2Controller;

Route::middleware(['auth:sanctum'])->group(function () {
    // Với DD
    Route::post('/sync-category',[PartnerController::class, 'syncCategory'])->name('partner.sync-category');
    // Route::post('/sync-warehouse',[PartnerController::class, 'syncWarehouse'])->name('partner.sync-warehouse');
    Route::post('/sync-transporter',[PartnerController::class, 'syncTransporter'])->name('partner.sync-transporter');

    // Route::post('/search-package',[PartnerController::class, 'searchPackage'])->name('partner.search-package');
    // Route::post('/search-customer',[PartnerController::class, 'searchCustomer'])->name('partner.search-customer');
    // Route::post('/test-send-hook',[PartnerController::class, 'testSendHook'])->name('partner.test-send-hook');

    // Đồng bộ với TD cũ
    // Route::post('/sync-city-live',[PartnerController::class, 'syncCityLive'])->name('partner.sync-city-live');
    // Route::post('/sync-warehouse-live',[PartnerController::class, 'syncWarehouseLive'])->name('partner.sync-warehouse-live');
    // Route::post('/sync-department-live',[PartnerController::class, 'syncDepartmentLive'])->name('partner.sync-department-live');
    // Route::post('/sync-employee-live',[PartnerController::class, 'syncEmployeeLive'])->name('partner.sync-employee-live');
    // Route::post('/sync-customer-live',[PartnerController::class, 'syncCustomerLive'])->name('partner.sync-customer-live');

    // Route::post('/sync-customer-deliveries',[PartnerController::class, 'syncCustomerDeliveryLive'])->name('partner.sync-customer-deliveries-live');
    Route::post('/sync-supplier-live',[PartnerController::class, 'syncSupplierLive'])->name('partner.sync-supplier-live');
    Route::post('/sync-order-live',[PartnerController::class, 'syncOrderLive'])->name('partner.sync-order-live');
    // Route::post('/sync-order-live-max',[PartnerController::class, 'syncOrderLiveMax'])->name('partner.sync-order-live-max');
    // Route::post('/remove-order-live',[PartnerController::class, 'removeOrderLive'])->name('partner.remove-order-live');
    // Route::post('/remove-order-live-v2-20032024',[PartnerController::class, 'removeOrderLiveV2'])->name('partner.remove-order-live-v2');
    // Route::post('/sync-carriers-live',[PartnerController::class, 'syncCarrierLive'])->name('partner.sync-carriers-live');
    // Route::post('/sync-category-live',[PartnerController::class, 'syncCategoryLive'])->name('partner.sync-category-live');
    // Route::post('/sync-order-supplier',[PartnerController::class, 'syncOrderSupplierLive'])->name('partner.sync-order-supplier');
    // Route::post('/sync-order-package-live',[PartnerController::class, 'syncOrderPackageLive'])->name('partner.sync-order-package-live');
    // Route::post('/sync-complain-type-live',[PartnerController::class, 'syncComplainTypeLive'])->name('partner.sync-complain-type-live');

    // Route::post('/sync-order-refund-live',[PartnerController::class, 'syncOrderRefundLive'])->name('partner.sync-order-refund-live');
    // Route::post('/sync-image-live',[PartnerController::class, 'syncImageToLive'])->name('partner.sync-image-live');
    // Route::post('/sync-image-refund-live',[PartnerController::class, 'syncImageRefundLive'])->name('partner.sync-image-refund-live');
    // Route::post('/sync-image-order-cost-real-live',[PartnerController::class, 'syncImageOrderCostRealLive'])->name('partner.sync-image-order-cost-real-live');

    // Route::post('/sync-delivery-live',[PartnerController::class, 'syncDeliveryLive'])->name('partner.sync-delivery-live');
    // Route::post('/sync-carriers-vn-live',[PartnerController::class, 'syncCarrierVNLive'])->name('partner.sync-carriers-vn-live');

    // Kế toán
    // Route::post('/sync-fund-balance-live',[PartnerController::class, 'syncFundBalanceLive'])->name('partner.sync-fund-balance-live');
    // Route::post('/sync-payment-type-live',[PartnerController::class, 'syncPaymentTypeLive'])->name('partner.sync-payment-type-live');

    // Đồng bộ, xử lý Max
    // Route::post('/sync-order-live-v2',[PartnerV2Controller::class, 'syncOrderLiveV2'])->name('partner.sync-order-live-v2');

    // Xử lý đồng bộ các kiện chờ xử lý sang kho
    // Route::post('/sync-pack-status-pending',[PartnerController::class, 'syncPackStatusPending'])->name('partner.sync-pack-status-pending');

    // Xử lý đồng bộ các kiện đang kho TQ sang kho
    // Route::post('/sync-pack-status-in-china',[PartnerController::class, 'syncPackStatusInChina'])->name('partner.sync-pack-status-in-china');

    // Xử lý đồng bộ kí gửi sang kho
    // Route::post('/sync-pack-consigment',[PartnerController::class, 'syncPackConsigment'])->name('partner.sync-pack-consigment');
    // Route::post('/sync-pack-consigment-in-china',[PartnerController::class, 'syncPackConsigmentInChina'])->name('partner.sync-pack-consigment-in-china');

    // Xử lý đồng bộ kiện không xác định
    // Route::post('/sync-pack-not',[PartnerController::class, 'syncPackNot'])->name('partner.sync-pack-not');
    // Route::post('/sync-pack-not-in-china',[PartnerController::class, 'syncPackNotInChina'])->name('partner.sync-pack-not-in-china');

    // Xử lý đồng bộ hàng loạt kiện order, kí gửi và ko xác định qua bên kho
    // Route::post('/sync-all-pack-to-one',[PartnerController::class, 'syncAllPackToOne'])->name('partner.sync-all-pack-to-one');

    // Xử lý vừa đồng bộ xong là đẩy sang kho luôn
    // Route::post('/sync-and-push-to-one',[PartnerController::class, 'syncAndPushToOne'])->name('partner.sync-and-push-to-one');


    // Cập nhật kho mặc định bị thiếu
    // Route::post('/sync-update-warehouse-customer',[PartnerController::class, 'syncUpdateWarehouseCustomer'])->name('partner.sync-update-warehouse-customer');
    // Route::post('/sync-update-warehouse-customer-from-file',[PartnerController::class, 'syncUpdateWarehouseCustomerFromFile'])->name('partner.sync-update-warehouse-customer-from-file');


    // Cập nhật lại ngày hoàn thành của kiện
    // Route::post('/sync-update-date-done-package',[PartnerController::class, 'syncUpdateDateDonePackage'])->name('partner.sync-update-date-done-package');

});

// Route::post('/sync-package-td-last',[PartnerController::class, 'syncPackageTDLast'])->name('partner.sync-package-td-last');

// Route::get('/make-account-sync-082829002',[PartnerController::class, 'makeAccountSync'])->name('partner.make-account-sync-08282');

// Giờ toàn bộ các phần liên quan đến tương tác với kho viết ở phần Webhook, không viết thêm ở mục dưới này nữa
// Giờ toàn bộ các phần liên quan đến tương tác với kho viết ở phần Webhook, không viết thêm ở mục dưới này nữa
// Giờ toàn bộ các phần liên quan đến tương tác với kho viết ở phần Webhook, không viết thêm ở mục dưới này nữa
// Giờ toàn bộ các phần liên quan đến tương tác với kho viết ở phần Webhook, không viết thêm ở mục dưới này nữa
Route::middleware(['CheckDDHV'])->group(function () {
    Route::post('/sync-warehouse',[PartnerController::class, 'syncWarehouse'])->name('partner.sync-warehouse');
    Route::post('/search-package',[PartnerController::class, 'searchPackage'])->name('partner.search-package');
    Route::post('/save-customer-pack-unconfirmed',[PartnerController::class, 'saveCustomerPackUnconfirmed'])->name('partner.save-customer-pack-unconfirmed'); // Lưu khách hàng vào kiện chờ xác nhận
    Route::post('/search-customer',[PartnerController::class, 'searchCustomer'])->name('partner.search-customer');
    // Xuất 2 cái file
    Route::post('/export-manifest',[PartnerController::class, 'exportManifest'])->name('partner.export-manifest');
    // cập nhật mã nhân viên kho và gửi thông báo
    Route::post('/check-staff-order',[PartnerController::class, 'checkStaffOrder']);
    Route::post('/export-manifest-screen-bag',[PartnerController::class, 'exportManifestScreenBag']);
});
