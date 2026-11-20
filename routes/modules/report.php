<?php

use App\Http\Controllers\ReportController;
use App\Http\Controllers\RequestReportController;
use Illuminate\Support\Facades\Route;

// không yêu cẩu auth
Route::prefix('no-auth')->group(function () {
    Route::prefix('products-pipeline')->group(function () {
        // top 10 từ khóa tìm kiếm nhiều nhất
        Route::get('/keyword-quantity', [RequestReportController::class, 'getKeywordQuantity']);
    });
});

// yêu cẩu auth
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('ceo')->group(function () {
        Route::get('/total-revenue', [ReportController::class, 'totalRevenueCeo']);
        Route::get('/total-shipping-received-good', [ReportController::class, 'totalShippingReceivedGoodCeo']);
        Route::get('/total-account-balance', [ReportController::class, 'totalAccountBalanceCeo']);
        Route::get('/total-amount-payable', [ReportController::class, 'totalAmountPayableCeo']);
        Route::get('/total-shipping-warehouse-vn', [ReportController::class, 'totalShippingWareHouseVnCeo']);
        Route::get('/total-fund', [ReportController::class, 'totalFundCeo']);
        Route::get('/total-fund-vn', [ReportController::class, 'totalFundVnCeo']);
        Route::get('/total-remaining-amount', [ReportController::class, 'totalRemainingAmountCeo']);
        Route::get('/total-remaining-amount-paid', [ReportController::class, 'totalRemainingAmountPaid']);
        Route::get('/warehouse/{id}', [ReportController::class, 'getWarehouse']);
        Route::get('/category/{type}', [ReportController::class, 'getCategories']);
        Route::get('/forget-cache', [ReportController::class, 'forgetCache']);
        Route::get('/time-request', [ReportController::class, 'timeRequest']);
        Route::get('/year', [ReportController::class, 'getYear']);
    });
    Route::prefix('staff')->group(function () {
        Route::get('/update-kpi-for-user', [ReportController::class, 'updateKpi']);
        Route::get('/total-order', [ReportController::class, 'totalOrder']);
        Route::get('/total-consignment', [ReportController::class, 'totalConsignment']);
        Route::get('/total-kpi', [ReportController::class, 'totalKpi']);
        Route::get('/chart-kpi', [ReportController::class, 'chartKpi']);
        Route::get('/order-package-vn', [ReportController::class, 'orderPackageVn']);
        Route::get('/package-vn', [ReportController::class, 'packageVn']);
        Route::get('/customer-potential', [ReportController::class, 'customerPotential']);
        Route::get('/conversation-total', [ReportController::class, 'conversationTotal']);

        Route::get('/total-customer', [ReportController::class, 'totalCustomer']);
        Route::get('/total-sale', [ReportController::class, 'totalSale']);
        Route::get('/total-order-fee', [ReportController::class, 'totalOrderFee']);
        Route::get('/total-order-fee-staff', [ReportController::class, 'totalOrderFeeStaff']);
        Route::get('/follow-week', [ReportController::class, 'followWeek']);
        Route::get('/total-chatbiz', [ReportController::class, 'totalChatbiz']);
    });

    Route::get('/report-order-channel-chart', [ReportController::class, 'reportOrderChannelChart']);
    Route::get('/report-order-channel-table', [ReportController::class, 'reportOrderChannelTable']);

    Route::get('/order-complete', [ReportController::class, 'getOrderComplete'])->middleware(['permission:report.order-done']);
    Route::get('/customer-active', [ReportController::class, 'getCustomerActive'])->middleware(['permission:report.care-customer-active']);
    Route::get('/customer-order', [ReportController::class, 'getCustomerOrder'])->middleware(['permission:report.consulting-management-customer-by-orders']);
    Route::get('/customer-pack-vn', [ReportController::class, 'getCustomerPackageVN'])->middleware(['permission:report.consulting-customer-pack-to-vn']);
    Route::get('/staff-quotation', [ReportController::class, 'getStaffQuotation']);
    Route::get('/deposit-customer', [ReportController::class, 'getDepositCustomer'])->middleware(['permission:report.deposit-customer']);
    Route::get('/staff-order', [ReportController::class, 'getStaffOrder']);
    Route::get('/staff-complain', [ReportController::class, 'getStaffComplain']);
    Route::get('/staff-cancel-order', [ReportController::class, 'getStaffCancelOrder'])->middleware(['permission:report.cancel-order']);
    Route::get('/report-cancel-order-channel-chart', [ReportController::class, 'getStaffCancelOrderChart'])->middleware(['permission:report.cancel-order']);
    Route::get('/staff-care-counselor', [ReportController::class, 'getStaffCareCounselor'])->middleware(['permission:report.performance-sale-and-care-consulting']);
    Route::get('/sale-staff', [ReportController::class, 'getSaleStaff'])->middleware(['permission:report.sale-staff']);;
    Route::get('/province', [ReportController::class, 'getReportByProvince']);
    Route::post('/sync-report-province', [ReportController::class, 'syncReportProvince']);
    Route::post('/sync-report-sale-staff', [ReportController::class, 'syncReportSaleStaffByDate']);

    Route::get('/customer-potential', [ReportController::class, 'getCustomerPotential'])->middleware(['permission:report.consulting-manage-customer-potential']);
    Route::post('/edit-point-care/{id}', [ReportController::class, 'changePointCare']);
    Route::get('/purchase-channel', [ReportController::class, 'reportPurchaseChannel'])->middleware(['permission:report.purchase-channel']);
    Route::get('/customer-active/export', [ReportController::class, 'export']);
    Route::get('/transport-fee', [ReportController::class, 'transportFee']);
    Route::get('/reconciliation-warehouse', [ReportController::class, 'reconciliationWarehouse']);
    Route::get('/order-complete/export', [ReportController::class, 'orderCompleteExport']);
    Route::get('/customer-pack-vn/export', [ReportController::class, 'customerPackVNExport']);

    Route::get('/report-order-customer-vip', [ReportController::class, 'getReportOrderCustomerVip']);
    Route::get('/get-group', [ReportController::class, 'getGroup']);
    Route::get('/get-list-week', [ReportController::class, 'getWeekReport']);
    Route::post('/sync-data-report-order-customer-vip', [ReportController::class, 'syncDataReportOrderCustomerVip']);

    Route::get('/report-order-customer', [ReportController::class, 'getReportOrderCustomer']);
    Route::get('/export-excel-order-customer', [ReportController::class, 'exportExcelOrderCustomer']);

    Route::get('/report-re-order-customer', [ReportController::class, 'getReportReOrderCustomer']);

    Route::get('/report-order-consignment', [ReportController::class, 'getReportOrdersConsignment']);
    Route::get('/export-excel-order-consignment', [ReportController::class, 'exportExcelReportOrdersConsignment']);

    Route::get('/report-order-customer-sale', [ReportController::class, 'getReportCustomerOrderSale']);
    Route::get('/export-excel-order-customer-sale', [ReportController::class, 'exportExcelReportCustomerOrderSale']);

    Route::get('/report-control-marketing', [ReportController::class, 'getReportControlMarketing']);
    Route::get('/export-excel-report-control-marketing', [ReportController::class, 'exportExcelReportControlMarketing']);

    Route::get('/report-order-customer-sale-staff', [ReportController::class, 'getReportCustomerOrderSaleStaff']);
    Route::get('/export-excel-report-order-customer-sale-staff', [ReportController::class, 'exportExcelReportCustomerOrderSaleStaff']);

    Route::get('/report-closing-rate', [ReportController::class, 'getReportClosingRate']);
    Route::get('/export-excel-report-closing-rate', [ReportController::class, 'exportExcelReportClosingRate']);

    Route::get('/report-closing-rate-staff', [ReportController::class, 'getReportClosingRateStaff']);
    Route::get('/export-excel-report-closing-rate-staff', [ReportController::class, 'exportExcelReportClosingRateStaff']);

    Route::get('/report-revenue-expenditure', [ReportController::class, 'getReportRevenueExpenditure'])->middleware(['permission:fund.revenue-expenditure']);

    Route::get('/fetch-data-report', [ReportController::class, 'fetchDataReport']);
    Route::get('/fetch-data-report-customer-vip', [ReportController::class, 'fetchDataReportCustomerVip']);
    Route::get('/get-all-week-of-month', [ReportController::class, 'getAllWeekOfMonth']);

    Route::get('/report-order-channel', [ReportController::class, 'reportOrderChannel']);
    Route::get('/report-fee-service', [ReportController::class, 'reportFeeService']);
    Route::get('/report-slow-quote', [ReportController::class, 'reportSlowQuote']);
    Route::get('/report-slowest-quote', [ReportController::class, 'reportSlowestQuote']);
    Route::get('/package-consignment', [ReportController::class, 'reportConsignment']);
    Route::post('/export-package-consignment', [ReportController::class, 'exportConsignment']);
    Route::get('/fetch-data-report-customer-order', [ReportController::class, 'fetchDataReportCustomerOrder']);

    // báo cáo thống kê số lượng request trong products pipeline: tìm kiếm, thêm vào giỏ hàng, lên đơn, đặt hàng
    Route::prefix('products-pipeline')->group(function () {
        // dùng chung
        Route::get('/city-list', [RequestReportController::class, 'getCityList']);
        Route::get('/request-source-list', [RequestReportController::class, 'getRequestSourceList']);
        // màn 1: báo cáo thống kê qua form
        Route::get('/action-quantity', [RequestReportController::class, 'getActionQuantity']);
        Route::get('/keyword-quantity', [RequestReportController::class, 'getKeywordQuantity']);
        Route::get('/city-quantity', [RequestReportController::class, 'getCityQuantity']);
        // màn 2: báo cáo kết quả tìm kiếm
        Route::get('/keyword-summary', [RequestReportController::class, 'getKeywordSummary']);
        // màn 3: báo cáo kết quả qua từ khóa khách hàng
        Route::get('/customer-summary', [RequestReportController::class, 'getCustomerSummary']);
    });

    Route::prefix('performance-sale')->group(function () {
        Route::get('/list', [ReportController::class, 'performanceSaleList']);
        Route::get('/top', [ReportController::class, 'performanceSaleTop']);
        Route::get('/in-week', [ReportController::class, 'performanceSaleInWeek']);
        Route::get('/by-time', [ReportController::class, 'performanceSaleByTime']);
        Route::get('/sync-data', [ReportController::class, 'syncDataPerformanceSale']);
        Route::get('/get-last-time', [ReportController::class, 'getLastTimePerformance']);
    });

    Route::get('/update-organization-order', [ReportController::class, 'updateOrganizationOrder']);
    Route::get('/update-organization-delivery', [ReportController::class, 'updateOrganizationDelivery']);


    // Xử lý phần đối soát marking mới
    // Route::get('/cron-report-check-marketing-2024', [ReportController::class, 'cronReportCheckMarketing']);
    Route::post('/get-report-check-marketing-2024', [ReportController::class, 'getReportCheckMarketing']);
    Route::post('/update-report-check-marketing-2024/{id}', [ReportController::class, 'updateReportCheckMarketing']);


    // Xử lý phần đối soát marking mới 08/01/2025
    Route::get('/cron-report-run-marketing-2025', [ReportController::class, 'cronReportRunMarketing']);
    Route::post('/get-report-run-marketing-2025', [ReportController::class, 'getReportRunMarketing'])->middleware(['permission:report.view-run-marketing']);
    Route::post('/update-report-run-marketing-2025/{id}', [ReportController::class, 'updateReportRunMarketing']);
    Route::post('/get-list-user-by-group', [ReportController::class, 'getListUserByGroup']);

    Route::post('/get-report-run-marketing-2025-realtime', [ReportController::class, 'getReportRunMarketingRealtime']);
    Route::get('/list-date-period', [ReportController::class, 'listDatePeriod']);
    Route::get('/list-date-month', [ReportController::class, 'listDateMonth']);
});

    Route::get('/cron-report-check-marketing-2024', [ReportController::class, 'cronReportCheckMarketing']);

Route::get('/truncate-report-request-measurement', function () {
    // \App\Models\ReportProductsPipelineStatistics::query()->truncate();
});

Route::get('/init-report-performance-staff-sale', [ReportController::class, 'initReportPerformanceStaffSale']);
Route::get('/run-cron-job', function(){
    Artisan::call('insert:report-order-consignment');
    echo "<br>";
    Artisan::call('insert:report-customer-order-sale');
    echo "<br>";
    echo Carbon\Carbon::now();
});
