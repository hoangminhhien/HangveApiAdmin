<?php

use App\Http\Controllers\CarrierController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\CNController;
use App\Http\Controllers\HrmController;
use App\Http\Controllers\OneCRMController;
use Illuminate\Support\Facades\Route;

Route::post('/order', [CarrierController::class, 'webhookOrder']);

// Route::post('/dongdong-x329kxm229xj2aqo1947165', [WebhookController::class, 'dongdongSubscribe']);

Route::get('/info-213292', [WebhookController::class, 'infoServer']);

Route::middleware(['CheckDDHV'])->group(function () {
    Route::post('/dongdong-x329kxm229xj2aqo1947165', [WebhookController::class, 'dongdongSubscribe']);
    // phần kiện chờ xác nhận bên dong dong
    Route::post('/set-order-code-dd', [WebhookController::class, 'setOrderCodeDD']);
    Route::post('/store-waiting-confirm-hv', [WebhookController::class, 'storeWaitingConfirmHV']);
    Route::post('/search-customer-condition-hv', [WebhookController::class, 'searchCustomerConditionHV']);
    // phần thống kê phiếu in bên dongdong
    Route::get('/print-receive-goods', [WebhookController::class, 'printReceiveGoods']);
    Route::get('/print-file/{id}', [WebhookController::class, 'printFile']);
    Route::post('/get-pack-in-print-file-hv', [WebhookController::class, 'getPackInFilePrintHV']);
    Route::post('/check-pack-in-print-file-hv', [WebhookController::class, 'checkPackInFilePrintHV']);

    // Phần này cho bên Quote
    Route::post('/search-order-2s01293', [QuoteController::class, 'quoteSeachOrder']);
    Route::post('/search-pack-3s01295', [QuoteController::class, 'quoteSeachPack']);
    Route::post('/list-pack-3s01293', [QuoteController::class, 'quoteListPack']);
    Route::post('/sync-status-pack-3s01596', [QuoteController::class, 'quoteSyncStatusPack']); // Đồng bộ trạng thái từ Quote
    Route::post('/sync-pack-hv-5s01596', [QuoteController::class, 'quoteSyncPack']); // Đồng bộ trạng thái từ Quote
    // Route::post('/sync-data-cn', [CNController::class, 'cnSyncData']); // Đồng bộ từ kho chính ngạch
    Route::post('/sync-official-service-fee', [QuoteController::class, 'syncOfficialServiceFee']);
    Route::post('/rs-official-service-fee', [QuoteController::class, 'rsOfficialServiceFee']);
    Route::post('/sync-official-service-fee-order', [QuoteController::class, 'syncOfficialServiceFeeOrder']);
    Route::post('/sync-unit-price-international', [QuoteController::class, 'syncUnitPriceInternational']);
    // Route::post('/sync-pack-hv-5s01596', [QuoteController::class, 'quoteSyncPackHV']); // mr Vương dùng để chặn
});

Route::post('/sync-data-cn', [CNController::class, 'cnSyncData']); // Đồng bộ từ kho chính ngạch


// Webhook cho bên HRM
Route::post('/hrm-a9x3q7z1m4b6c2d', [HrmController::class, 'hrmSubscribe']);
Route::post('/hrm-statistic-fee-m4b6c2d/{fee}', [HrmController::class, 'hrmStatisticFee']);

// Webhook cho bên CN
Route::post('/cn-jnead39hm1ewyc6', [OneCRMController::class, 'cnSubscribe']);


Route::post('/thuongdo-2024', [WebhookController::class, 'thuongdoSubscribe']);
Route::post('/thuongdo-withdrawal', [WebhookController::class, 'thuongdoWithdrawal']);
Route::post('/thuongdo-deposit', [WebhookController::class, 'thuongdoDeposit']);

// Route::post('/sync-order-detail-note', [WebhookController::class, 'syncOrderDetailNoteToOrderDetail']);

Route::post('/test-sms', [WebhookController::class, 'testSms']);
Route::post('/test-sms-phone', [WebhookController::class, 'testSmsPhone']);
Route::post('/test-fcm-token', [WebhookController::class, 'testPushNotificationFcmForApp']);

Route::post('/sent-otp', [WebhookController::class, 'sentOtpApiClient']);
Route::post('/sent-otp-reset-password', [WebhookController::class, 'resetPasswordSendOtpPhone']);
Route::post('/forget-cache', [WebhookController::class, 'forgetCache']);
Route::post('/set-refresh-token-zalo', [WebhookController::class, 'setRefreshTokenZalo']);
Route::get('/get-token-zalo', [WebhookController::class, 'getTokenZalo']);
Route::post('/set-token-zalo', [WebhookController::class, 'setTokenZalo']);
Route::post('/ecommerce', [WebhookController::class, 'ecommerce']);
Route::post('/refund-from-xadal', [WebhookController::class, 'refundFromXadal']);

Route::post('/test-job-ecommerce', [WebhookController::class, 'testJobEcommerce']);
