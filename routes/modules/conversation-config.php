<?php

use App\Http\Controllers\ConversationConfigController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [ConversationConfigController::class, 'pagination']);
    Route::post('/',[ConversationConfigController::class, 'store']);
    Route::put('/{id}',[ConversationConfigController::class, 'update']);
});
