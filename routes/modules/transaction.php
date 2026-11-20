<?php

use App\Http\Controllers\FineController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::group(['prefix' => 'withdrawal'], function () {
            Route::get('/', [TransactionController::class, 'getWithdrawal'])->middleware(['permission:fund.index-withdraw|fund.index-withdraw-managed']);
            Route::patch('/status/{withdrawal}', [TransactionController::class, 'withdrawalChangeStatus'])->middleware(['permission:fund.edit-withdraw']);
            Route::get('/{withdrawal}', [TransactionController::class, 'getWithdrawalDetail'])->middleware(['permission:fund.index-withdraw']);
            Route::get('/count/withdrawal-status-pending', [TransactionController::class, 'countWithdrawalStatusPending']);

        });
    }
);
