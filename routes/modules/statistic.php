<?php

use App\Http\Controllers\StatisticController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/fast', [StatisticController::class, 'fast']);
    Route::get('/slow', [StatisticController::class, 'slow']);
    Route::get('/list-warehouse', [StatisticController::class, 'getListWareHouse']);
    Route::get('/print-receive-goods', [StatisticController::class, 'printReceiveGoods'])->middleware(['permission:pack.statistical-print']);
    Route::post('/list-user-receive-goods', [StatisticController::class, 'listUserReceiveGoods']);
    Route::get('/print-file/{id}', [StatisticController::class, 'printFile']);

    // Phần quét phiếu in
    Route::post('/get-pack-in-print-file', [StatisticController::class, 'getPackInFilePrint']);
    Route::post('/check-pack-in-print-file', [StatisticController::class, 'checkPackInFilePrint']);
    Route::get('/report-quote', [StatisticController::class, 'reportQuote'])->middleware(['permission:dashboard.report-gdqt']);
    Route::get('/report-order', [StatisticController::class, 'reportOrder'])->middleware(['permission:dashboard.report-gdqt']);
    Route::get('/report-complain', [StatisticController::class, 'reportComplain'])->middleware(['permission:dashboard.report-gdqt']);
    Route::get('/report-total-sale', [StatisticController::class, 'reportTotalSale'])->middleware(['permission:dashboard.report-gdqt']);
    Route::get('/report-users', [StatisticController::class, 'reportUser'])->middleware(['permission:dashboard.report-gdqt']);
});
