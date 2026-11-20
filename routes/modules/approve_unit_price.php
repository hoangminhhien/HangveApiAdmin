<?php

use App\Http\Controllers\ApproveUnitPriceControler;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::get('/',[ApproveUnitPriceControler::class, 'pagination'])->middleware(['permission:pack.approve-unit-price-hangve']);
        Route::get('/count-status-pending',[ApproveUnitPriceControler::class, 'countStatusPending']);
        Route::get('/count-status',[ApproveUnitPriceControler::class, 'countStatus'])->middleware(['permission:pack.approve-unit-price-hangve']);
        Route::post('/approve',[ApproveUnitPriceControler::class, 'approve'])->middleware(['permission:pack.approve-unit-price-hangve']);
        Route::post('/cancel',[ApproveUnitPriceControler::class, 'cancel'])->middleware(['permission:pack.approve-unit-price-hangve']);
        Route::get('/quote/list-confirm-quote', [ApproveUnitPriceControler::class, 'listConfirmQuote'])->middleware(['permission:pack.approve-unit-price-quote']);
        Route::post('/quote/approve-quote', [ApproveUnitPriceControler::class, 'approveQuote'])->middleware(['permission:pack.approve-unit-price-quote']);
        Route::post('/quote/approve-multi-quote', [ApproveUnitPriceControler::class, 'approveMultiQuote'])->middleware(['permission:pack.approve-unit-price-quote']);
        Route::post('/quote/cancel-quote', [ApproveUnitPriceControler::class, 'cancelQuote'])->middleware(['permission:pack.approve-unit-price-quote']);
        Route::get('/quote/count-status',[ApproveUnitPriceControler::class, 'quoteCountStatus'])->middleware(['permission:pack.approve-unit-price-quote']);
    }
);