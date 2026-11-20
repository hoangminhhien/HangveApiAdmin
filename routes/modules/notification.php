<?php

use App\Http\Controllers\NotificationController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/',[NotificationController::class, 'pagination']);
    Route::post('/', [NotificationController::class, 'store']);
    Route::post('/click-noti/{id}', [NotificationController::class, 'clickNoti']);
    Route::post('/click-all-noti', [NotificationController::class, 'clickAllNoti']);
    Route::get('/download-file/{id}', [NotificationController::class, 'downloadFile']);
    Route::post('/click-group', [NotificationController::class, 'clickAllNotiByGroup']);
    // Route::post('/test-push-status-to-quote', [NotificationController::class, 'testPushStatusToQuote']);
});
