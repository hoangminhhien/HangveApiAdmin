<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

// access vào route này để xóa cache/config/route
Route::get('clear/{target}', function ($target) {
    $allowList = ['cache', 'config', 'route'];

    if (!in_array($target, $allowList)) {
        return resError('Lệnh không phù hợp');
    }

    // Artisan::call("{$target}:clear");
    // return "php artisan {$target}:clear";
});

Route::post('/sign-in', [AuthController::class, 'signIn']);
Route::post('/sign-up', [AuthController::class, 'signUp']);
Route::prefix('reset-password')->group(function() {
    Route::get('/{email}', [AuthController::class, 'resetPasswordSendMail']);
    Route::post('/', [AuthController::class, 'confirmVerifyCodeAndEmail']);
});
Route::post('change-password', [AuthController::class, 'changePassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sign-out', [AuthController::class, 'signOut']);
});
