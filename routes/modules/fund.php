<?php

use App\Http\Controllers\FundController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/',[FundController::class, 'pagination'])->name('fund.pagination');
    Route::get('/list',[FundController::class, 'list'])->name('fund.list')->middleware(['permission:fund.index']);
    Route::get('/log',[FundController::class, 'log'])->name('fund.log')->middleware(['permission:fund.index']);
    // Route::post('/init-default',[FundController::class, 'initDefault'])->name('fund.init-default');
    Route::post('/',[FundController::class, 'store'])->name('fund.store');
    Route::put('/{id}',[FundController::class, 'edit'])->name('fund.edit');
    Route::delete('/{id}',[FundController::class, 'delete'])->name('fund.delete');

    // Đổi loại quỹ
    Route::post('/change-type-common',[FundController::class, 'changeTypeCommon'])->name('fund.change-type-common');
    Route::post('/change-warehouse-id',[FundController::class, 'changeWarehouseId'])->name('fund.change-warehouse-id');

    // Lấy danh sách bank được phép để gán quỹ
    Route::post('/list-bank-for-create-funds', [FundController::class, 'listBankForCreateFund']);
});
