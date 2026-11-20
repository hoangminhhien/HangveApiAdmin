<?php

use App\Http\Controllers\PackageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(
    function () {
        Route::get('/report-list', [PackageController::class, 'getReportPackageList']);
        Route::get('/list-status', [PackageController::class, 'listStatus']);
        Route::get('/', [PackageController::class, 'pagination']);
        Route::get('/export', [PackageController::class, 'export']);
        Route::get('/receive-goods', [PackageController::class, 'receiveGoods'])->middleware(['permission:pack.receive-goods']);
        Route::post('/print-word', [PackageController::class, 'printWord']);
        Route::post('/report-print', [PackageController::class, 'reportPrint']);
        Route::patch('/update-receive-goods', [PackageController::class, 'updateReceiveGoods']);
        Route::get('/{customerId}/vn', [PackageController::class, 'getListVNByCustomerId'])->middleware(['permission:pack.index']);
        Route::get('/category', [PackageController::class, 'getCategory']);
        Route::get('/{customerId}/vn-v2', [PackageController::class, 'getListVNByCustomerIdV2'])->middleware(['permission:pack.index']);
        Route::get('/count-package', [PackageController::class, 'countPackage']);
        Route::get('/{id}', [PackageController::class, 'detail'])->middleware(['permission:pack.index']);
        Route::get('/get-china-cost/{ids}', [PackageController::class, 'getChinaCost']);
        Route::get('/products/{package}', [PackageController::class, 'getProducts']);
        Route::get('/order/{orderId}', [PackageController::class, 'getListByOrderId']);
        Route::get('/check/code-exist', [PackageController::class, 'checkCodeExist']);
        Route::post('/order/{order}', [PackageController::class, 'storeByOrderId'])->middleware(['permission:order.add-pack']);
        Route::post('/customer', [PackageController::class, 'store'])->middleware(['permission:pack.add']);
        Route::put('/{package}', [PackageController::class, 'updateByOrderId']);
        Route::patch('/status', [PackageController::class, 'changeStatus'])->middleware(['permission:pack.update-for-tbp']);
        Route::patch('/is_make_up', [PackageController::class, 'changeIsMakeUp']);
        Route::patch('/{package}', [PackageController::class, 'modifies'])->middleware(['permission:pack.edit']);
        Route::delete('/{id}', [PackageController::class, 'destroy']);

        Route::post('/get-customer-package', [PackageController::class, 'getCustomerPackage']);
        Route::get('/bill-code-exist/{billCode}', [PackageController::class, 'checkBillCodeExist']);

        // Đổi mã vận đơn
        Route::post('/change-bill-code', [PackageController::class, 'changeBillCode']);

        Route::patch('set-order-code/{package}', [PackageController::class, 'setOrderCode'])->middleware(['permission:pack.index']);
        Route::patch('warehouse/set-order-code', [PackageController::class, 'setCodeOrderDD']);
        Route::post('print-multiple', [PackageController::class, 'printMultiple']);
        Route::post('/export-multiple', [PackageController::class, 'exportMultiple']);
        Route::post('/export-receive-goods', [PackageController::class, 'exportReceiveGoods']);

        // Phần in phiếu xuất cùng lúc nhiều kiện (nâng cấp của phần print-multiple và export-multiple)
        Route::post('print-multiple-all', [PackageController::class, 'printMultipleAll']);
        Route::post('export-multiple-all', [PackageController::class, 'exportMultipleAll']);
        Route::post('store-waiting-confirm', [PackageController::class, 'storeWaitingConfirm']);
        Route::get('/search/by-code', [PackageController::class, 'searchByCode']);
        Route::post('/change-price-pack', [PackageController::class, 'changePricePack']);
        Route::patch('/change-staff/{id}', [PackageController::class, 'changeStaff']);

        // Cập nhật tỉ giá với kiện chưa có tỉ giá
        Route::post('/update-exchange-rate', [PackageController::class, 'updateExchangeRate']);
        Route::post('/update-bill-code', [PackageController::class, 'updateBillCode']);
        Route::post('/update-type', [PackageController::class, 'changeType']);
        // Đồng bộ date_done khi chưa lưu ở trạng thái hoàn thành
        // Route::get('/sync/date-done', [PackageController::class, 'syncDateDone']);
        // Route::get('/sync/date-done-v2', [PackageController::class, 'syncDateDoneV2']);

        Route::post('/order-details-by-package-ids', [PackageController::class, 'getOrderDetailsByPackageIds']);

        // Kiểm tra các kiện gửi lên để trả hàng có kiện nào bị chung mã bill của nhiều đơn không
        Route::post('/check-shared-bill-code-multi', [PackageController::class, 'checkSharedBillCodeMulti']);
        Route::get('/report/control', [PackageController::class, 'reportControl']);
        Route::get('/report/control/table', [PackageController::class, 'reportControlTable']);
        Route::get('/common/report/controle', [PackageController::class, 'commonReportControl']);

        // Xử lý các kiện đánh dấu thất lạc và đã trả hàng cho khách thì đồng bộ ngược cho bên kho
        Route::post('/update-pack-lost-and-return-customer', [PackageController::class, 'updatePackLostAndReturnCustomer']);
        Route::post('/confirm-info-dd', [PackageController::class, 'confirmInfoDD']);
        Route::post('/update-official-partner', [PackageController::class, 'updateOfficialPartner']);

        // Đổi trạng thái đồng bộ ghi chú sang kho
        Route::post('/update-sync-note', [PackageController::class, 'updateSyncNote']);
        // Nếu HV giữ hàng lại thì bên kho chính ngạch k được xuất
        Route::post('/update-sync-block-package', [PackageController::class, 'updateSyncBlockPackage']);
        Route::get('/wh/inspection', [PackageController::class, 'whInspection']);
        Route::get('/wh/count-inspection', [PackageController::class, 'whCountInspection']);
        Route::get('/wh/waiting-confirm', [PackageController::class, 'whWaitingConfirm'])->middleware(['permission:pack.wait-confirm']);
        Route::get('/wh/inspection/list-status', [PackageController::class, 'whInspectionListStatus']);
        Route::get('/sync/fee-from-quote', [PackageController::class, 'syncFeeFromQuote']);
        Route::post('/update-service', [PackageController::class, 'updateService']);
        Route::post('/request-change-price', [PackageController::class, 'requestChangePrice']);
    }
);
Route::post('/notification/wh', [PackageController::class, 'notiPackRefund']);
