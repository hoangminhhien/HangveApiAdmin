<?php

use App\Http\Controllers\ChatbizController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('list-statistic',[ChatbizController::class, 'listStatistic']);
});

Route::middleware(['CheckCBHV'])->group(function () {
    // Gọi cho khách hàng
    Route::post('/log-call-out',[ChatbizController::class, 'logCallOut']);

    // Task cái bảng to
    Route::post('/log-call',[ChatbizController::class, 'logCall']);
    Route::post('/log-zalo',[ChatbizController::class, 'logZalo']);
    Route::post('/log-facebook',[ChatbizController::class, 'logFacebook']);


    // Test thử bắn về con Buyder
    Route::post('/test-buyder',[ChatbizController::class, 'testBuyder']);
    Route::post('/test-buyder-post',[ChatbizController::class, 'testBuyderPost']);

    // Tạo tài khoản liveChat
    Route::post('/make-account-live-chat',[ChatbizController::class, 'makeAccountLiveChat']);
});


// Fake gọi ra cho khách hàng
Route::post('/fake-log-call-out-xx9at2lpqz',[ChatbizController::class, 'fakeLogCallOut']);

// Check trạng thái kiện hàng
Route::post('/check-status-xx9at2lpqz',[ChatbizController::class, 'checkStatus']);
