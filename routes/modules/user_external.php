<?php

use App\Http\Controllers\UserExternalController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/login', [UserExternalController::class, 'login']);
});
