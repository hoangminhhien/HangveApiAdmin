<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::get('/me', [UserController::class, 'me']);
        Route::get('/user-waiting-deposit', [UserController::class, 'waitingDeposit']);
        Route::get('/user-waiting-deposit-total', [UserController::class, 'waitingDepositTotal']);
        Route::get('/user-ordered', [UserController::class, 'ordered']);
        Route::get('/user-ordered-total', [UserController::class, 'orderedTotal']);
        Route::get('/', [UserController::class, 'pagination'])->middleware(['permission:staff.index']);
        Route::get('/list', [UserController::class, 'index']);
        Route::get('/active/{user}', [UserController::class, 'activeUser']);
        Route::post('/', [UserController::class, 'store'])->middleware(['permission:staff.add']);
        Route::post('/avatar', [UserController::class, 'uploadAvt']);
        Route::put('/{user}', [UserController::class, 'update'])->middleware(['permission:staff.edit']);
        Route::patch('/change-password', [UserController::class, 'changePassword']);
        Route::post('/change-account-xadal', [UserController::class, 'changeAccountXadal']);
        Route::delete('/{user}', [UserController::class, 'destroy'])->middleware(['permission:staff.delete']);

        Route::post('/save-token-noti', [UserController::class, 'saveTokenNoti']);
        Route::post('/sent-token-test', [UserController::class, 'sentNotiTest']);
        Route::post('/sent-token-test-client', [UserController::class, 'sentNotiTestClient']);

        Route::patch('/update-reset-staff-status/{user}', [UserController::class, 'updateResetStaffStatus'])
            ->middleware(['permission:staff.on-off-receive-order']);
        Route::get('/list/user-ordered', [UserController::class, 'listUserOrdered']);
        Route::get('/staff-by-department', [UserController::class, 'getStaffListByType']);

        // Livechat
        Route::post('/remove-conversation-id-curent', [UserController::class, 'removeConversationIdCurrent']);
    }
);

Route::post('/save-token-noti-client-927162', [UserController::class, 'saveTokenNotiClient']);
Route::post('/re-sync-count-bag-and-status-816291', [UserController::class, 'reSyncCountBagAndStatus']);
