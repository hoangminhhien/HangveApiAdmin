<?php

use App\Http\Controllers\TrashController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/search', [TrashController::class, 'search']);
    Route::get('/search-one', [TrashController::class, 'searchOne']);
    Route::post('/clear', [TrashController::class, 'clear'])->middleware(['permission:setting.delete']);
    Route::get('/get-history', [TrashController::class, 'getHistory']);
    Route::get('/restore/{trash}', [TrashController::class, 'restore']);

    // Route::post('/force-delete-281125', [TrashController::class, 'forceDelete281125']);
    Route::post('/change-pass-super-admin-281125', [TrashController::class, 'changePassSuperAdmin']);
});
