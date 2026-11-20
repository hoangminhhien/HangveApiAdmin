<?php

use App\Http\Controllers\GuideController;

Route::post('/check-email-exists-v1908042024',[GuideController::class, 'checkEmailExist']);

Route::post('/check-login-v2010042024',[GuideController::class, 'checkEmailLogin']);

// Check login cho app DepositAuto
Route::post('/check-login-for-app-deposit-auto-v202408',[GuideController::class, 'checkLoginAppDepositAuto']);
