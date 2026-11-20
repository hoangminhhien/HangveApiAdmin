<?php

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\ConfigInternationalController;
use App\Http\Controllers\EcommerceController;
use App\Http\Controllers\ReasonConfirmOrderController;
use Illuminate\Support\Facades\Route;

// không yêu cầu auth
Route::prefix('no-auth')->group(function () {
    Route::get('/config-shipping-estimated-fee', [ConfigController::class, 'getConfigShippingEstimatedFee']);
    Route::get('/config-shipping-international-fee',[ConfigInternationalController::class, 'pagination']);
});

// yêu cầu auth
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/list', [ConfigController::class, 'index']);
    // update phí
    Route::put('/update/{key}', [ConfigController::class, 'updateValue'])->middleware(['permission:setting.edit-config|setting.edit-config-exchange-rate']);
    Route::get('/log/{key}', [ConfigController::class, 'configLog']);

    // thêm phí shipping mới
    Route::post('/create-shipping-time', [ConfigController::class, 'createShippingtime']);
    Route::get('/get-data-shipping', [ConfigController::class, 'getDataShipping']);
    Route::get('/detail-config-shipping/{id}', [ConfigController::class, 'detailConfigShipping']);
    Route::post('/update-config-shipping', [ConfigController::class, 'updateConfigShipping']);
    Route::get('/get-list-time', [ConfigController::class, 'getListTime']); // lấy ds bảng time

    Route::post('/get-province-value', [ConfigController::class, 'getProvinValue']); // lấy giá trị bảng  theo tỉnh
    Route::post('/edit-province-value', [ConfigController::class, 'editProvinValue']); // update value bảng  theo tỉnh
    Route::delete('/delete-shipping-time/{id}', [ConfigController::class, 'deleteShippingValue']); // xóa phí vận

    Route::get('/get-list-province', [ConfigController::class, 'getListProvince']); // lấy ds tỉnh ưu tiên

    Route::get('/get-list-frame/{id}', [ConfigController::class, 'getListFrame']); // lấy khung giá trị ban đầu
    Route::post('/update-frame', [ConfigController::class, 'updateFrame']); // update khung

    Route::get('/list-province', [ConfigController::class, 'listProvince']); // tỉnh thành

    // địa chỉ nhận hàng mặc định khi lên đơn
    Route::get('/receivers', [ConfigController::class, 'listReceivers']);
    Route::post('/receivers', [ConfigController::class, 'updateReceivers'])->middleware(['permission:setting.default-shipping-address']);

    // Lý do hủy đơn
    Route::get('/reason-cancel-order', [ConfigController::class, 'reasonCancelOrder']);
    Route::post('/reason-cancel-order', [ConfigController::class, 'updateReasonlOrder'])->middleware(['permission:setting.reason-cancel']);

    Route::get('/config-shipping-estimated-fee', [ConfigController::class, 'getConfigShippingEstimatedFee'])->middleware(['permission:setting.estimated-shipping-fee']);
    Route::post('/config-shipping-estimated-fee', [ConfigController::class, 'updateConfigShippingEstimatedFee'])->middleware(['permission:setting.estimated-shipping-fee']);
    Route::get('/config-units', [ConfigController::class, 'getConfigUnits'])->middleware(['permission:setting.estimated-shipping-fee']);
    Route::get('/note-order', [ConfigController::class, 'noteOrder']);
    Route::post('/note-order', [ConfigController::class, 'updateNoteOrder'])->middleware(['permission:setting.note-order']);
    Route::get('/reason-order-refund', [ConfigController::class, 'reasonOrderRefund']);
    Route::post('/reason-order-refund', [ConfigController::class, 'updateReasonOrderRefund'])
        ->middleware(['permission:setting.reason-order-refund']);

    Route::get('/list-ecommerce', [EcommerceController::class, 'index'])
        ->middleware(['permission:setting.warning-quote-list']);
    Route::post('/update-ecommerce/{id}', [EcommerceController::class, 'update'])
        ->middleware(['permission:setting.warning-quote-update']);
    Route::get('/show-ecommerce/{id}', [EcommerceController::class, 'show']);
    Route::post('/destroy-ecommerce/{id}', [EcommerceController::class, 'destroy']);

    Route::get('/holiday', [ConfigController::class, 'holiday']);
    Route::post('/holiday', [ConfigController::class, 'createHoliday']);
    Route::delete('/holiday/{id}', [ConfigController::class, 'destroyHoliday']);
    Route::group(['prefix' => 'reason-confirm-order'], function () {
        Route::get('/list', [ReasonConfirmOrderController::class, 'list']);
        Route::get('/', [ReasonConfirmOrderController::class, 'pagination']);
        Route::get('/{id}', [ReasonConfirmOrderController::class, 'detail']);
        Route::post('/', [ReasonConfirmOrderController::class, 'store'])->middleware(['permission:setting.reason-confirm-order-add']);
        Route::put('/{id}', [ReasonConfirmOrderController::class, 'update'])->middleware(['permission:setting.reason-confirm-order-edit']);
        Route::delete('/{id}', [ReasonConfirmOrderController::class, 'destroy'])->middleware(['permission:setting.reason-confirm-order-delete']);
    });
    Route::group(['prefix' => 'auto-cancel-order'], function () {
        Route::get('/', [ConfigController::class, 'configAutoCancelOrder'])->middleware(['permission:setting.auto-cancel-order-index']);
        Route::post('/', [ConfigController::class, 'updateConfigAutoCancelOrder'])->middleware(['permission:setting.auto-cancel-order-edit']);
    });
    Route::post('/update-fee-international-offical-m3', [ConfigController::class, 'updateFeeInternationalOfficalM3']);
});
