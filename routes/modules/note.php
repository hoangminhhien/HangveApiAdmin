<?php

use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {

        Route::group(['prefix' => 'order/{id}'], function() {
            Route::get('/public', [NoteController::class, 'getOrderPublic']);
            Route::get('/private', [NoteController::class, 'getOrderPrivate']);
            Route::post('/', [NoteController::class, 'storeOrder']);
        });

        Route::group(['prefix' => 'order-detail/{id}'], function() {
            Route::get('/', [NoteController::class, 'getOrderDetail']);
            Route::post('/', [NoteController::class, 'storeOrderDetail']);
            Route::post('/store-arr', [NoteController::class, 'storeOrderDetailArr']);
            Route::put('/', [NoteController::class, 'editOrderDetail']);
            Route::delete('/', [NoteController::class, 'deleteOrderDetail']);
        });

        Route::get('/public/{id}', [NoteController::class, 'getOrderPublic']);
        Route::get('/get-note-warning-order', [NoteController::class, 'noteWarningOrder']);
        Route::post('/update-note-warning-order', [NoteController::class, 'updateNoteWarningOrder']);

        Route::get('orders/{order}/supplier-notes-popup', [NoteController::class, 'supplierNotesPopup']);

    }
);
