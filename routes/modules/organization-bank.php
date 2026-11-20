<?php

use App\Http\Controllers\OrganizationBankController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::get('/', [OrganizationBankController::class, 'pagination']);
        Route::post('/', [OrganizationBankController::class, 'store']);
        Route::post('/update/{id}', [OrganizationBankController::class, 'updateBank']);
        Route::put('/{id}', [OrganizationBankController::class, 'update']);
        Route::delete('/{id}', [OrganizationBankController::class, 'destroy']);
        Route::get('/website', [OrganizationBankController::class, 'website']);
    }
);
