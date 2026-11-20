<?php

use App\Http\Controllers\OrderCostRealController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/',[OrderCostRealController::class, 'pagination'])->name('cost_real.pagination');
    Route::get('/get-old-cost',[OrderCostRealController::class, 'getOldCost'])->name('cost_real.getOldCost');
    Route::get('/log',[OrderCostRealController::class, 'log'])->name('cost_real.getLog');
    Route::post('/',[OrderCostRealController::class, 'create'])->name('cost_real.create');
    Route::post('/approve',[OrderCostRealController::class, 'approve'])->name('cost_real.approve');
    Route::put('/{id}',[OrderCostRealController::class, 'edit'])->name('cost_real.edit');
    Route::delete('/{id}',[OrderCostRealController::class, 'destroy'])->name('cost_real.delete');
});
