<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('non-auth')->group(
    function () {
        Route::post('/map-signature/{contract_id}', [OrderController::class, 'mapSignature']);
        Route::get('/download-file-contract/{contrac_id}', [OrderController::class, 'downloadContract']);
    }
);
