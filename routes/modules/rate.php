<?php

use App\Http\Controllers\OrderRateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::get('/', [OrderRateController::class, 'list'])->middleware(['permission:rate.index']);
        Route::delete('/{id}', [OrderRateController::class, 'delete'])->middleware(['permission:rate.delete']);
    }
);
