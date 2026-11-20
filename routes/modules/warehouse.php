<?php

use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/with-province', [WarehouseController::class, 'getListByCountryVN']);
    Route::get('/{country}', [WarehouseController::class, 'getListByCountry']);
    Route::get('/group-by-province/{country}', [WarehouseController::class, 'getListGroupByProvince']);
});
