<?php

use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::get('/', [RoleController::class, 'pagination'])->middleware(['permission:role.index']);
        Route::get('/list', [RoleController::class, 'index'])->middleware(['permission:role.index']);
        Route::get('/{role}', [RoleController::class, 'detail'])->middleware(['permission:role.index']);
        Route::post('/', [RoleController::class, 'store'])->middleware(['permission:role.add']);
        Route::put('/{role}', [RoleController::class, 'update'])->middleware(['permission:role.edit']);
        Route::delete('/{role}', [RoleController::class, 'destroy'])->middleware(['permission:role.delete']);

        Route::post('/make-role-default-9218321',[RoleController::class, 'makeRoleDefault'])->name('role.make-role-default');
        // Route::post('/fix-duplicate-role-2091321',[RoleController::class, 'fixDuplicateRole'])->name('role.fix-duplicate-role');
    }
);
