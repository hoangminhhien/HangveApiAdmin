<?php

use App\Http\Controllers\ConfigInternationalController;
use Illuminate\Support\Facades\Route;

Route::get('/get-price-list-current-not-login', [ConfigInternationalController::class, 'getPriceListCurrent']);
Route::get('/get-list-warehouse', [ConfigInternationalController::class, 'getListWarehouse']);

Route::middleware(['auth:sanctum'])->group(function () {
    // Bảng giá hiện tại
    Route::get('/get-price-list-current', [ConfigInternationalController::class, 'getPriceListCurrent']);
    // CURD
    Route::get('/',[ConfigInternationalController::class, 'pagination']);
    Route::get('/{id}', [ConfigInternationalController::class, 'detail']);
    Route::post('/', [ConfigInternationalController::class, 'store']);
    Route::put('/{id}', [ConfigInternationalController::class, 'update']);
    Route::delete('/{id}', [ConfigInternationalController::class, 'destroy']);
});
Route::post('/list-price-no-auth', [ConfigInternationalController::class, 'listPriceNoAuth']);
