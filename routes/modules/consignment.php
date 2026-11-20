<?php

use App\Http\Controllers\ConsignmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::get('/', [ConsignmentController::class, 'pagination']);
        Route::get('/status', [ConsignmentController::class, 'reportStatus']);
        Route::get('/status-count', [ConsignmentController::class, 'statusCount']);
    }
);
