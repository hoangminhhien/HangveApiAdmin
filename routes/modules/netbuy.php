<?php

use App\Http\Controllers\NetBuyController;
use Illuminate\Support\Facades\Route;

Route::middleware([])->group(function () {
    Route::post('/sync-aK9xP2mQ7vL1nRs', [NetBuyController::class, 'netBuySubscribe']);
});
