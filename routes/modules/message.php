<?php

use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/list-message', [MessageController::class, 'listMessage']);
    Route::post('/store-message', [MessageController::class, 'storeMessage']);
    Route::post('/check-after-time-message', [MessageController::class, 'checkAfterTimeMessage']);
    Route::post('/edit-message', [MessageController::class, 'editMessage']);
});
