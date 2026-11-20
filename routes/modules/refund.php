<?php

use App\Http\Controllers\RefundController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::get('/', [RefundController::class, 'pagination'])->middleware(['permission:refund.index']);
        Route::post('/', [RefundController::class, 'store'])->middleware(['permission:refund.add']);
        Route::post('/store-v2', [RefundController::class, 'storeV2'])->middleware(['permission:refund.add']); // Logic mới
        Route::get('/status', [RefundController::class, 'getStatus']);
        Route::get('/{refund}', [RefundController::class, 'detail']);
        Route::put('/update-status/{id}', [RefundController::class, 'updateStatusProcess']);
        Route::put('/{id}', [RefundController::class, 'update'])->middleware(['permission:refund.edit']);
        Route::delete('/{id}', [RefundController::class, 'delete'])->middleware(['permission:refund.edit']);
        Route::post('/refund-multi', [RefundController::class, 'refund'])->middleware(['permission:refund.confirm']);
        Route::post('/feedback-multi', [RefundController::class, 'feedbackMulti'])->middleware(['permission:refund.feedback']);
        Route::group(['prefix' => 'feedback/{id}'], function() {
            Route::get('/', [RefundController::class, 'getFeedback']);
            Route::post('/', [RefundController::class, 'storeFeedback']);
            Route::delete('/', [RefundController::class, 'destroy']);
        });
        Route::group(['prefix' => 'payment/{id}'], function() {
            Route::post('/', [RefundController::class, 'storePayment'])->middleware(['permission:refund.payment']);
        });


        Route::post('/update-v2', [RefundController::class, 'updateV2'])->middleware(['permission:refund.edit']); // Logic mới
        Route::post('/history-v2', [RefundController::class, 'historyV2'])->middleware(['permission:refund.edit']); // Lịch sử hoàn tiền
    }
);
