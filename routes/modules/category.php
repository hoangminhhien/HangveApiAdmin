<?php

use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::get('/list', [CategoryController::class, 'index']);

        // Import file tiền mẫu:
        Route::get('/download-file/template-price', [CategoryController::class, 'downloadFileTemplatePrice']);
        Route::post('/submit-file/template-price', [CategoryController::class, 'submitFileTemplatePrice']);
        Route::get('/translate', [CategoryController::class, 'translate']);
    }
);
Route::get('/list-no-auth', [CategoryController::class, 'index']);
