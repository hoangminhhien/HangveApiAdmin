<?php

use App\Http\Controllers\DepartmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::get('/', [DepartmentController::class, 'pagination'])->middleware(['permission:department.index']);
        Route::get('/list', [DepartmentController::class, 'index']);
        Route::post('/', [DepartmentController::class, 'store'])->middleware(['permission:department.add']);
        Route::put('/{deparment}', [DepartmentController::class, 'update'])->middleware(['permission:department.edit']);
        Route::delete('/{deparment}', [DepartmentController::class, 'destroy'])->middleware(['permission:department.delete']);
    }
);
