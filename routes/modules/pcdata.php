<?php

use App\Http\Controllers\PCDataController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/update-setsun1837120251', [PCDataController::class, 'updateData']);
});

