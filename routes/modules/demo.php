<?php

use App\Http\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

Route::get('/init-label-default',[DemoController::class, 'initLabelDefault'])->name('demo.init-label-default');
// Route::get('/delete-warehouse-init-293122',[DemoController::class, 'deleteWarehouseInit'])->name('demo.delete-warehouse-init-29312');
// Route::get('/delete-2-warehouse-76522',[DemoController::class, 'delete2Warehouse'])->name('demo.delete-2-warehouse-76522');
// Route::get('/init-category-items-928131',[DemoController::class, 'initCategoryItem'])->name('demo.init-category-items-928131');
// Route::get('/init-user-admin-DD-920031',[DemoController::class, 'initUserAdminDD'])->name('demo.init-user-admin-DD-920031');
// Route::get('/sync-code-user',[DemoController::class, 'synCodeUser']);

// Route::get('/update-total-customers-9193212',[DemoController::class, 'updateTotalCustomer']);
// Route::post('/webhook-test-nt19',[DemoController::class, 'webhookTest']);

// Route::get('/test-php',[DemoController::class, 'testPHP']);


Route::middleware('auth:sanctum')->group(
    function () {
        // Route::post('/test-per-2024',[DemoController::class, 'testPer2024']);
        // Route::post('/hot-fix-nhung',[DemoController::class, 'hotFixNhung']);
        // Route::post('/sync-note-pack',[DemoController::class, 'syncNotePack']);
        // Route::post('/delete-pack-one-error',[DemoController::class, 'deletePackOneError']);
        // Route::post('/delete-pack-one-error-261224',[DemoController::class, 'deletePackOneError261224']);
        // Route::post('/test-ship-order-sup',[DemoController::class, 'testShipOrderSup']);
        // Route::post('/fix-for-mr-kho',[DemoController::class, 'fixForMrKho']);
        // Route::post('/fix-for-mr-kho-v2',[DemoController::class, 'fixForMrKhoV2']);
        // Cập nhật tiền đặt cọc (msThao)
        // Route::post('/update-deposit-cost',[DemoController::class, 'updateDepositCost']);
        // Route::post('/add-bank-tpb',[DemoController::class, 'addBankTPB']);
        // Route::post('/update-for-mr-Truong',[DemoController::class, 'udpateMrTruong']);
        // Route::post('/reset-bill-code',[DemoController::class, 'resetBillCode']);
        // Route::post('/update-dat-hang',[DemoController::class, 'updateDatHang']);

        // Cập nhật phần phí vận chuyển quốc tế
        // Route::post('/update-all-international-shipping-cost',[DemoController::class, 'updateAllInternalShippingCost']);

        // Kiểm tra thông tin 5 giá trị đơn hàng có khớp
        // Route::post('/update-all-5-cost',[DemoController::class, 'updateAll5Cost']);

        // Cập nhật đơn hoàn thành mà thu tiền thừa
        // Route::post('/get-list-order-error',[DemoController::class, 'getListOrderError']);
        // Route::post('/update-list-order-error',[DemoController::class, 'updateListOrderError']);

        // Cập nhật đơn hoàn thành mà thu tiền thừa (V2)
        // Route::post('/get-list-order-error-v2',[DemoController::class, 'getListOrderErrorV2']);
        // Route::post('/update-list-order-error-v2',[DemoController::class, 'updateListOrderErrorV2']);

        // Lấy lại Thương Đô ID
        // Route::post('/revert-thuongdo-id',[DemoController::class, 'revertThuongDoID']);


        // Check khách hàng chưa login lần nào
        // Route::post('/check-all-not-login-first-customer',[DemoController::class, 'checkAllNotLoginFirst']);

        // Xử lý cập nhật thu lệch (V3)
        // Route::post('/get-list-order-error-v3',[DemoController::class, 'getListOrderErrorV3']);

        // Xử lý kiện có mã vận đơn, nhưng chưa có mã kho
        // Route::post('/get-list-pack-not-code-xd',[DemoController::class, 'getListPackNotCodeXD']);
        // Route::post('/update-list-pack-not-code-xd',[DemoController::class, 'updateListPackNotCodeXD']);

        // Cập nhật tiền hàng thực tế
        // Route::post('/update-cost-and-ship-real',[DemoController::class, 'updateCostAndShipReal']);

        // Fix 471 kiện lỗi do Vương gây ra 27-12-2024
        // Route::post('/fix-471-pack-for-mr-Vuong-271224',[DemoController::class, 'fix471PackForVuong271224']);
        // Route::post('/fix-471-pack-for-mr-Vuong-271224-v2',[DemoController::class, 'fix471PackForVuong271224V2']);

        // Xóa hết token đứa nào không hoạt động
        // Route::post('/clear-token-user-not-active',[DemoController::class, 'clearTokenUserNotActive']);

        // Đổi 2 nhân viên Diệu Linh cho Nguyễn Hà
        // Route::post('/change-customer-from-2-user',[DemoController::class, 'changeCustomerFrom2User']);

        // Fix kế toán lệch 2triệu do 2 giao dịch nạp của khách thực hiện đồng thời
        // Route::post('/fix-fund-diff-2-tr-130125',[DemoController::class, 'fixFundDiff2tr130125']);

        // Update hết khách hàng lelve 0 lên 1 luôn
        // Route::post('/update-customer-level-0-to-1',[DemoController::class, 'updateCustomerLevel0To1']);

        // Fix kế toán lệch 13k do 2 giao dịch nạp của nạp tiền tự động vs nạp tay cùng 1 bank
        // Route::post('/fix-fund-diff-13k-080425',[DemoController::class, 'fixFundDiff13k080425']);

        // Đổi lại các tài khoản bị trùng mã khách hàng
        // Route::post('/fix-duplicate-code-customer-220425',[DemoController::class, 'fixDuplicateCodeCustomer220425']);

        // Fix Hack 40K khách hàng ngày 30/4/2025
        // Route::post('/fix-hack-customer-300425',[DemoController::class, 'fixHackCustomer300425']);

        // Test
        // Route::post('/test-report-customer-150525',[DemoController::class, 'testReportCustomer150525']);
    }
);

Route::post('/update-international-shipping-cost',[DemoController::class, 'updateInternationalShippingCost']);
Route::post('/update-status-received-goods-package',[DemoController::class, 'updateStatusReciceGoodPack']);
Route::post('/update-info-order',[DemoController::class, 'updateInfoOrder']);
Route::post('/check-order-cost-real-and-shipping-cost-real',[DemoController::class, 'checkOrderCostRealAndShippingReal']);

// Đổi pass khách hàng cho Nhung
Route::post('/change-pass-customer-2025',[DemoController::class, 'changePassCustomer2025']);

// Đổi các dịch vụ của đơn cho Nhung
Route::post('/change-order-service-2025-mtbrbk98',[DemoController::class, 'changeServiceOrderAndPack']);
Route::post('/change-pack-consigment-2025-mtbrbk99',[DemoController::class, 'changePackConsigment']);

// Đồng bộ lại kiện sang báo giá
Route::post('/repush-pack-to-quote-mtbrbk92',[DemoController::class, 'rePushPackToQuote']);

// Đồng bộ lại lịch sử cuộc gọi ra từ Chatbiz
Route::post('/re-sync-call-out-chatbiz-mtbrbk93',[DemoController::class, 'reSyncCallOutChatbiz']);

// Route::post('/reset-db-dev',[DemoController::class, 'resetDbDev']);

// Route::post('/reset-warehouse-04042024',[DemoController::class, 'resetWarehouse2024']);

// Route::post('/update-org-order-package-2024',[DemoController::class, 'updateOrgOrderPackage2024']);
// Route::post('/update-org-order-package-2024-v2',[DemoController::class, 'updateOrgOrderPackage2024V2']);

// Route::post('/update-org-complain-2024',[DemoController::class, 'updateOrgComplain2024']);

// Route::post('/reset-pass-admin',[DemoController::class, 'resetPassAdmin']);

// Resest lại số noti
Route::post('/reset-noti-2024',[DemoController::class, 'resetNoti2024']);

// Đổi email nhân sự 151225
Route::post('/reset-email-agent-151225',[DemoController::class, 'resetEmailAgent151225']);

// Lỗi trừ giao hàng hơn 500 kiện 1 lúc delivery_id=144480, customer_phone:0335676088
Route::post('/check-delivery-err-080126',[DemoController::class, 'checkDeliveryErr080126']);

// Cập nhật lại số dư giao dịch bị sai
// Route::post('/update-balance-transaction-err-230126',[DemoController::class, 'updateBlanceTranErr230126']);

// Đồng bộ lại các mã kiện bị tạo sau nhưng kho CN lại bắn thông tin kg, m3 về trước
Route::post('/update-pack-cn-lag-280126',[DemoController::class, 'updatePackCnLag280126']);
