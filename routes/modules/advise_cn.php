<?php

use App\Http\Controllers\AdviseCNController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [AdviseCNController::class, 'pagination']);
    Route::post('/', [AdviseCNController::class, 'storeV2']);
    Route::post('/change-agent', [AdviseCNController::class, 'changeAgent']);
    Route::post('/change-agent-arr', [AdviseCNController::class, 'changeAgentArr']);
    Route::post('/change-status', [AdviseCNController::class, 'changeStatus']);
    Route::post('/advise-cn-status', [AdviseCNController::class, 'getAdviseCNStatus']);
    Route::post('/update-hs-code-and-fee', [AdviseCNController::class, 'updateHsCodeAndFee']);
});

Route::post('/store-not-auth', [AdviseCNController::class, 'storeV2']);
