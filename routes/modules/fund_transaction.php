<?php

use App\Http\Controllers\FundTransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/',[FundTransactionController::class, 'paginationVN'])->middleware(['permission:fund.history-vn']);
    Route::get('/export',[FundTransactionController::class, 'export']);
    Route::get('/tq',[FundTransactionController::class, 'pagination'])->middleware(['permission:fund.history-tq']);
    Route::get('refresh/refresh-cache-with-condition',[FundTransactionController::class, 'refreshCacheWithCondition']);
    Route::get('/get-total-fund',[FundTransactionController::class, 'getTotalFund'])->middleware(['permission:fund.history-vn']);


    // Thêm mới giao dịch
    Route::get('/get-category-vi', [FundTransactionController::class, 'getCategoryVi']);
    Route::get('/get-category-cn', [FundTransactionController::class, 'getCategoryCn']);
    Route::post('/get-customer', [FundTransactionController::class, 'getCustomer']);
    Route::post('/', [FundTransactionController::class, 'store']);
    Route::post('/store-arr', [FundTransactionController::class, 'storeArr']); // Bản mới, cho phép thêm đồng thời nhiều khách

    // Route::get('/{id}', [FundTransactionController::class, 'detail']);

    // Xóa giao dịch
    Route::post('hide/{id}', [FundTransactionController::class, 'hide']);

    // Cập nhật logic cũ
    Route::put('/{id}', [FundTransactionController::class, 'update']);

    // Cập nhật dành riêng cho giao dịch chờ xác nhận (Type 11)
    Route::post('/update-wait-confirm', [FundTransactionController::class, 'updateWaitConfirm']);

    // Cập nhật cho logic Nạp tiền (03/09/2024)
    Route::post('/update-deposit-2024', [FundTransactionController::class, 'updateDeposit']);

    // Xóa giao dịch (03/09/2024)
    Route::post('/delete-2024', [FundTransactionController::class, 'delete2024']);

    // Danh sách giao dịch đã bị xóa (03/09/2024)
    Route::get('/index-delete',[FundTransactionController::class, 'paginationDelete'])->middleware(['permission:fund.history-vn']);
    Route::get('/index-delete/tq',[FundTransactionController::class, 'paginationDeleteTQ'])->middleware(['permission:fund.history-tq']);

    // Thêm mới chuyển quỹ
    Route::post('/save-transfer-fund', [FundTransactionController::class, 'saveTransferFund']);

    // Đổi tiền TQ cho khách hàng
    Route::post('/save-money-change', [FundTransactionController::class, 'saveMoneyChange']);
    Route::post('/list-money-change', [FundTransactionController::class, 'listMoneyChange'])->middleware(['permission:fund.rmb']); // Danh sách đổi tiền và lợi nhuận
    Route::post('/sum-money-change', [FundTransactionController::class, 'sumMoneyChange'])->middleware(['permission:fund.rmb']); // Tổng lợi nhuận RMB

    // Nạp tiền ngân hàng Trung Quốc
    Route::post('/save-money-recharge', [FundTransactionController::class, 'saveMoneyReCharge'])->middleware(['permission:fund.recharge-tq']);
    Route::get('/save-money-recharge-get-category', [FundTransactionController::class, 'saveMoneyReChargeGetCategory']);

    // Rút tiền
    Route::post('/save-money-withdrawal', [FundTransactionController::class, 'saveMoneyWithdrawal']);

    // Danh sách Chi tiết đặt hàng thực tế nhà cung cấp
    Route::get('/list-supplier-order-details', [FundTransactionController::class, 'listSupplierOrderDetails'])->middleware(['permission:fund.detail-real-ncc']);
    // Xác nhận đặt hàng
    Route::post('/order-confirmation', [FundTransactionController::class, 'orderConfirmation']);
    // Báo lỗi
    Route::post('/order-error', [FundTransactionController::class, 'orderError']);

    // Tạo nạp tiền Fake từ ngân hàng
    // Route::post('/fake-save', [FundTransactionController::class, 'fakeSave']);

    // Chi tiết
    Route::get('/{id}', [FundTransactionController::class, 'detail']);


});
