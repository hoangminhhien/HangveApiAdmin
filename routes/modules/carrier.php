<?php

use App\Http\Controllers\CarrierController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::patch('/get-price', [CarrierController::class, 'getPrice']);
        Route::post('/create-order', [CarrierController::class, 'createOrder'])->middleware(['permission:delivery.create-order-in-carrier']);
        Route::post('/cancel-order', [CarrierController::class, 'cancelOrder']);
    }
);
