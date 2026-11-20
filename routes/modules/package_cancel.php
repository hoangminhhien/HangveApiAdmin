<?php

use App\Http\Controllers\PackageCancelController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::post('/status-pack-cancel', [PackageCancelController::class, 'changeStatus']);
        Route::get('/', [PackageCancelController::class, 'pagination'])->middleware(['permission:pack.update-for-tbp']);
        Route::post('/{package}', [PackageCancelController::class, 'storePackageCancel']);
        Route::patch('/{id}', [PackageCancelController::class, 'modifies']);
        Route::patch('delete/{id}', [PackageCancelController::class, 'delete']);

    }
);