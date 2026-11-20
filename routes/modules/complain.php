<?php

use App\Http\Controllers\ComplainController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::get('/', [ComplainController::class, 'pagination'])->middleware(['permission:complain.index']);
        Route::post('/{orderId}', [ComplainController::class, 'store']);
        Route::get('/type', [ComplainController::class, 'getTypes']);
        Route::get('/solution', [ComplainController::class, 'getSolutions']);
        Route::get('/export', [ComplainController::class, 'export']);
        Route::get('/{id}', [ComplainController::class, 'detail'])->middleware(['permission:complain.index']);
        Route::patch('/{complain}', [ComplainController::class, 'modifies'])->middleware(['permission:complain.edit']);
        Route::group(['prefix' => 'comment/{id}'], function() {
            Route::get('/{type}', [ComplainController::class, 'getFeedback']);
            Route::get('/{type}/seen', [ComplainController::class, 'seen']);
            Route::post('/', [ComplainController::class, 'storeFeedback']);
            Route::delete('/', [ComplainController::class, 'destroy']);
        });

        // thêm nhân viên khiếu nại
        Route::patch('/change-staff-complain/{complain}', [ComplainController::class, 'changeStaffComplain']);

        //autoMessage
        Route::group(['prefix' => 'auto-message'], function() {
            Route::get('/get', [ComplainController::class, 'getAutoMessage'])->middleware(['permission:setting.respond-complain']);
            Route::post('/', [ComplainController::class, 'storeAutoMessage'])->middleware(['permission:setting.respond-complain']);
            Route::patch('/{id}', [ComplainController::class, 'modifiesAutoMessage'])->middleware(['permission:setting.respond-complain']);
            Route::delete('/{id}', [ComplainController::class, 'destroyAutoMessage'])->middleware(['permission:setting.respond-complain']);
        });

    }
);
