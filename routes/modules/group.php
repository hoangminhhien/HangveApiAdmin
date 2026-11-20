<?php

use App\Http\Controllers\GroupController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [GroupController::class, 'pagination'])->middleware(['permission:staff.list-group']);
    Route::get('/user-list', [GroupController::class, 'getListUser']);
    Route::get('/customer-list', [GroupController::class, 'getListCustomer']);
    Route::get('/list', [GroupController::class, 'getList'])->name('list');
    Route::get('/kpi-monthly', [GroupController::class, 'groupKpiMonthly']);
    Route::get('/{id}', [GroupController::class, 'detail'])->name('detail');
    Route::post('/', [GroupController::class, 'create']);
    Route::put('/customer-for-user', [GroupController::class, 'updateCustomerForUser']);
    Route::put('/{userGroup}', [GroupController::class, 'edit']);
    Route::post('/change-setting-customer', [GroupController::class, 'changeStatusGetCustomer']);
    Route::patch('/{id}', [GroupController::class, 'modifies']);
    Route::delete('/{id}', [GroupController::class, 'destroy']);
});
