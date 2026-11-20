<?php

use App\Http\Controllers\AccountingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::patch('/order-fee', [AccountingController::class, 'getOrderFee']);
        Route::patch('/inspection', [AccountingController::class, 'getInspectionCost']);
        Route::patch('/inspection-advanced', [AccountingController::class, 'getInspectionCostAdvanced']);
        Route::patch('/international', [AccountingController::class, 'getInternationalCost']);

        Route::patch('/inspection-v2', [AccountingController::class, 'getInspectionCostV2']); // Hàm dùng riêng cho bên Hoàn tiền
    }
);
