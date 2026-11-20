<?php

use App\Http\Controllers\FineController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {

        Route::group(['prefix' => 'comment/{id}'], function() {
            Route::get('/', [FineController::class, 'getComments']);
            Route::post('/', [FineController::class, 'addComment']);
        });

        Route::get('/', [FineController::class, 'pagination'])->middleware(['permission:fine.index']);
        Route::get('/exportFine',[FineController::class, 'exportFine']);

        Route::get('/{id}', [FineController::class, 'detail'])->middleware(['permission:fine.index']);
        Route::post('/', [FineController::class, 'store'])->middleware(['permission:order.add-fine|fine.add']);
        Route::put('/{id}', [FineController::class, 'update'])->middleware(['permission:fine.edit']);
        Route::get('/cancel/{fine}', [FineController::class, 'cancel']);
        Route::patch('/status/{fine}', [FineController::class, 'changeStatus'])->middleware(['permission:fine.edit']);
        Route::delete('/{id}', [FineController::class, 'destroy']);
    }
);
