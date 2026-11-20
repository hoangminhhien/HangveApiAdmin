<?php

use App\Http\Controllers\OrderDetailController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        // lấy sku_properties của sản phẩm tương ứng order_detail
        Route::get('/sku-properties/{order_detail_id}', [OrderDetailController::class, 'getSkuList']);
        Route::get('/history/{order_detail_id}', [OrderDetailController::class, 'getHistories']);

        // cập nhật source/link/classification đơn hàng
        // order_details.source, order_details.link, order_details.classification
        Route::put('/update-source/{order_detail_id}', [OrderDetailController::class, 'updateSource']);
        Route::put('/update-link/{order_detail_id}', [OrderDetailController::class, 'updateLink']);
        Route::put('/update-classification/{order_detail_id}', [OrderDetailController::class, 'updateClassification']);
        Route::put('/update-note-china', [OrderDetailController::class, 'updateNoteChina'])->middleware(['permission:order.edit_note_china']);
    }
);
