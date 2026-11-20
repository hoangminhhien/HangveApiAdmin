<?php

use App\Http\Controllers\FundTypePayController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/',[FundTypePayController::class, 'pagination'])->middleware(['permission:fund.type_payment']);
    Route::get('/all', [FundTypePayController::class, 'getFundTypePay']);
    Route::post('/', [FundTypePayController::class, 'store']);
    Route::put('/{id}', [FundTypePayController::class, 'update']);
    Route::delete('/{id}', [FundTypePayController::class, 'destroy']);

    Route::post('/update-status/{id}', [FundTypePayController::class, 'updateStatus']);

    Route::post('/init-default/', [FundTypePayController::class, 'initDefault']);
});
