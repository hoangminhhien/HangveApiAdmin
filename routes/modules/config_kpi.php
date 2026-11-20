<?php

use App\Http\Controllers\ConfigKpiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/list', [ConfigKpiController::class, 'list'])->middleware(['permission:setting.kpi-index']);
    Route::get('/{id}', [ConfigKpiController::class, 'detail'])->middleware(['permission:setting.kpi-index']);
    Route::post('/', [ConfigKpiController::class, 'store'])->middleware(['permission:setting.kpi-add-edit-delete']);
    Route::put('/{id}', [ConfigKpiController::class, 'update'])->middleware(['permission:setting.kpi-add-edit-delete']);
    Route::delete('/{id}', [ConfigKpiController::class, 'destroy'])->middleware(['permission:setting.kpi-add-edit-delete']);
    Route::get('/latest/by-type', [ConfigKpiController::class, 'latestByType'])->middleware(['permission:setting.kpi-index']);
});