<?php

use App\Http\Controllers\DeliveryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::get('/', [DeliveryController::class, 'pagination'])->middleware(['permission:delivery.index']);
        Route::get('/export', [DeliveryController::class, 'export']);
        Route::get('/{id}', [DeliveryController::class, 'detail']);
        Route::post('/', [DeliveryController::class, 'store'])->middleware(['permission:pack.add-delivery']);
        Route::put('/{id}', [DeliveryController::class, 'update'])->middleware(['permission:delivery.edit-delivery|delivery.add-pack-to-delivery']);
        Route::patch('/{delivery}', [DeliveryController::class, 'modifies'])->middleware(['permission:delivery.edit-delivery']);
        Route::patch('/modifies-status-done/{delivery}', [DeliveryController::class, 'modifiesStatusDone'])->middleware(['permission:delivery.edit-delivery']);
        Route::delete('/{id}', [DeliveryController::class, 'destroy'])->middleware(['permission:delivery.delete']);

        // Cập nhật thông tin khi in phiếu
        Route::post('/update-print', [DeliveryController::class, 'updatePrint'])->middleware(['permission:delivery.edit-delivery']);

        Route::group(
            ['prefix' => 'print/{delivery}'],
            function () {
                Route::get('', [DeliveryController::class, 'printDelivery'])->middleware(['permission:delivery.print-delivery']);
                Route::get('/ex-warehouse', [DeliveryController::class, 'printExWarehouse'])->middleware(['permission:delivery.print-bill']);
                Route::get('/xlsx', [DeliveryController::class, 'getXlsx']);
                Route::patch('/note', [DeliveryController::class, 'storeNote']);
            }
        );

        Route::get('/download-file/template-cod', [DeliveryController::class, 'downloadFileTemplateCod']);
        Route::post('/submit-file/template-cod', [DeliveryController::class, 'submitFileTemplateCod']);
        Route::patch('/update-note/{delivery}', [DeliveryController::class, 'updateNote'])->middleware(['permission:delivery.edit-delivery']);
    }
);
