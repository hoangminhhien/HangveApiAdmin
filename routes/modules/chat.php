<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [ChatController::class, 'pagination']);
    Route::post('/', [ChatController::class, 'storeV2']);
    Route::post('/get-item-in-order', [ChatController::class, 'getItemInOrder']);
    Route::post('/get-list-user-chat', [ChatController::class, 'getListUserChat']);
    Route::post('/check-convertion-null', [ChatController::class, 'checkConversationNull']);
});

Route::get('/proxy-image', [ChatController::class, 'proxyImage']);
