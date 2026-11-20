<?php

use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::post('/check-notify', [CustomerController::class, 'checkPush']);

Route::middleware('auth:sanctum')->group(
    function () {
        // Route::get('/init-count-order', [CustomerController::class, 'initCountOrder']);
        Route::patch('/warehouse', [CustomerController::class, 'setWarehouse']);
        Route::get('/customer-debt', [CustomerController::class, 'debt'])->middleware(['permission:fund.fund-debt']);
        Route::get('/exportDebt',[CustomerController::class, 'exportDebt']);

        Route::get('/get-supplier', [CustomerController::class, 'getSupplier'])->name('getSupplier');
        Route::get('/', [CustomerController::class, 'pagination'])->middleware(['permission:customer.index']);
        Route::get('/export', [CustomerController::class, 'export']);
        Route::get('/reports', [CustomerController::class, 'reports']);
        Route::get('with-trashed', [CustomerController::class, 'withTrashed'])->middleware(['permission:customer.with-trashed']);
        Route::get('/note', [CustomerController::class, 'getNote']);
        Route::post('/delete-customer-note/{id}', [CustomerController::class, 'deleteCustomerNote']);
        Route::get('/{customer}', [CustomerController::class, 'detail']);
        Route::post('/', [CustomerController::class, 'store'])->middleware(['permission:customer.add']);
        Route::put('/{customer}', [CustomerController::class, 'update'])->middleware(['permission:customer.edit']);
        Route::patch('/change-staff-to-customers', [CustomerController::class, 'changeStaff']);
        Route::patch('/{customer}', [CustomerController::class, 'updateSomething'])->middleware(['permission:customer.edit']);
        Route::patch('/status/{customer}', [CustomerController::class, 'changeStatus'])->middleware(['permission:customer.edit']);
        Route::patch('/offer/{customer_offer?}', [CustomerController::class, 'updateOffer'])->middleware(['permission:customer.edit-offer']);;
        Route::post('/offer', [CustomerController::class, 'createOffer']);
        Route::post('/update-is-endow-ship', [CustomerController::class, 'updateIsEndowShip']);
        Route::delete('/{customer}', [CustomerController::class, 'destroy'])->middleware(['permission:customer.delete']);
        Route::post('/update/label', [CustomerController::class, 'updateLabel'])->middleware(['permission:customer.edit-label']);
        Route::post('/customer_label', [CustomerController::class, 'updateCustomerLabel']);
        Route::post('/assign-staff', [CustomerController::class, 'assignStaff']);
        Route::post('/assign-all-staff', [CustomerController::class, 'assignAllStaff']);
        Route::get('/assign-staff/count-customer', [CustomerController::class, 'countCustomerByStaff']);

        Route::get('/get-history-change-staff/{id}', [CustomerController::class, 'getHistoryChangeStaff']);

        Route::post('/get-customer', [CustomerController::class, 'getCustomer']);
        Route::post('/search-customer-with-condition', [CustomerController::class, 'getCustomerWithCondition']);

        Route::post('/update-customer-care-category', [CustomerController::class, 'updateCustomerCareCategory'])->middleware(['permission:customer.edit']);

        // Đếm số lượng khách chờ gán tư vấn, chờ gán chăm sóc
        Route::post('/count-customer-wait-assign/care-and-counselor', [CustomerController::class, 'countCustomerWaithAssign']);

        Route::get('/trash/force-delete', [CustomerController::class, 'forceDelete']);
        Route::get('/export-transaction/{customerId}', [CustomerController::class, 'exportTransaction']);
        Route::get('/change-staff-requests/lists', [CustomerController::class, 'changeStaffRequests'])->middleware(['permission:customer.change-staff']);
        Route::delete('/change-staff-requests/{id}', [CustomerController::class, 'deleteChangeStaffRequests']);
        Route::patch('/change-staff-requests/{id}', [CustomerController::class, 'updateChangeStaffRequests']);
        Route::post('/note', [CustomerController::class, 'note']);
        Route::get('/log-offer/{id}', [CustomerController::class, 'logOffer']);

        Route::post('/charge-money', [CustomerController::class, 'chargeMoney']);
        Route::get('/run-command/customer-longtime', [CustomerController::class, 'runCommandCustomerLongTime']);
        Route::get('/sync/last-order-at-by-order', [CustomerController::class, 'syncLastOrderAtByOrder']);

        Route::post('/sms-zaLo-kien-hang-ve-viet-nam', [CustomerController::class, 'smsZaLoKienHangVeVietNam']);

        Route::post('/list-customer-confirm-close', [CustomerController::class, 'listCustomerConfirmClose'])->middleware(['permission:customer.list-close']);
        Route::post('/update-status-customer-confirm-close/{id}', [CustomerController::class, 'updateStatusCustomerConfirmClose'])->middleware(['permission:customer.update-status-close']);
        Route::post('/update-status-customer-confirm-close-many', [CustomerController::class, 'updateStatusCustomerConfirmCloseMany'])->middleware(['permission:customer.update-status-close']);
        Route::post('/change/password-by-phone-number', [CustomerController::class, 'changePasswordByPhoneNumber']);

        // Route::get('/init/total-orders', [CustomerController::class, 'initTotalOrders']);

        // Thống kê dữ liệu lại cho khách hàng đồng bộ
        // Route::get('/init/total-orders-v2', [CustomerController::class, 'initTotalOrdersV2']);
        // Route::get('/init/total-packages-v2', [CustomerController::class, 'initTotalPackagesV2']);
        // Route::get('/init/total-packages-of-order-v2', [CustomerController::class, 'initTotalPackagesOfOrderV2']);

        // Hủy đơn ở trạng thái Chờ đặt cọc quá 3 tháng
        // Route::post('/cancel-order-waiting-deposit-3-month', [CustomerController::class, 'cancelOrderWaitingDeposit3Month']);
        Route::post('/cancel-order-waiting-deposit-3-month-v2', [CustomerController::class, 'cancelOrderWaitingDeposit3MonthV2']);
        Route::get('/waiting/for-care', [CustomerController::class, 'waitingForCare'])->middleware(['permission:customer.view-waiting-for-care']);
        Route::get('/count/waiting-for-care', [CustomerController::class, 'countWaitingForCare']);
        Route::get('/run-job/waiting-for-care', [CustomerController::class, 'runJobtWaitingForCare']);
        Route::get('/run-job/auto-close-account', [CustomerController::class, 'runJobAutoCloseAccount']);
        Route::get('/sync/last-order-at-pack', [CustomerController::class, 'runJobSyncLastOrderAtPack']);
        Route::get('/run-job/update-staff-group-longtime', [CustomerController::class, 'runJobUpdateStaffGroupLongTime']);
    }
);
Route::post('/get-by-phone-number', [CustomerController::class, 'getByPhoneNumber']);
