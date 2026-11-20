<?php

use App\Http\Controllers\RepController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/list-message', [RepController::class, 'listMessage']);
    Route::post('/store-message', [RepController::class, 'storeMessageV2']);
    Route::post('/check-after-time-message', [RepController::class, 'checkAfterTimeMessage']);
    Route::post('/edit-message', [RepController::class, 'editMessage']);
});
