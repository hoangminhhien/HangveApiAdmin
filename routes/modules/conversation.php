<?php

use App\Http\Controllers\ConversationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [ConversationController::class, 'pagination']);
    Route::post('/', [ConversationController::class, 'storeV2']);
    Route::post('/check-convertion-null', [ConversationController::class, 'checkConversationNull']);
    Route::post('/store-search-null', [ConversationController::class, 'storeSearchNull']);
    Route::post('/change-agent', [ConversationController::class, 'changeAgent'])->middleware(['permission:live_chat.change-agent-conversation']);

    Route::post('/find-customer-by-phone', [ConversationController::class, 'findCustomerByPhone']);
});
