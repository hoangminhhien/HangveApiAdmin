<?php

namespace App\Console;

use App\Models\Holiday;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Xóa token hết hạn
        // $schedule->command('passport:purge')->hourly();
        // $schedule->command('telescope:prune')->daily();
        // $schedule->command('customer-trash:delete')->hourly();
        $schedule->command('insert:report-order-customer-vip')->hourly()->runInBackground();
        $schedule->command('insert:report-order-customer-vip-all')->hourly()->runInBackground();
        $schedule->command('insert:report-order-customer')->hourly()->runInBackground();
        $schedule->command('insert:report-order-consignment')->hourly()->runInBackground();
        /* TỔNG DATA PHÒNG VÀ TỶ LỆ CHỐT ĐƠN */
        $schedule->command('insert:report-closing-rate')->hourly()->runInBackground();
        /*$schedule->command('insert:report-customer-order-sale')->hourly()->runInBackground();*/
        /* TỔNG DATA PHÒNG VÀ TỶ LỆ CHỐT ĐƠN NHÂN VIÊN */
        $schedule->command('insert:report-closing-rate-staff')->hourly()->runInBackground();
        $schedule->command('insert:report-province')->hourly()->runInBackground();
        /* Doanh số theo nhân viên tư vấn */
        $schedule->command('insert:report-customer-order-sale-staff')->hourly()->runInBackground();
        // Đồng bộ dữ liệu báo cáo order và consignment
        $schedule->command('sync:report-order-consignment-date')->hourly()->runInBackground();
        $schedule->command('sent-sms-order-package')->hourly()->runInBackground();
        $schedule->command('insert:report-revenue-expenditure')->hourly()->runInBackground();
        /*$schedule->command('insert:report-customer-re-order')->hourly()->runInBackground();*/
        /*$schedule->command('insert:financial-report')->hourly()->runInBackground();
        $schedule->command('insert:report-order-client')->hourly()->runInBackground();
        $schedule->command('insert:report-order-package-client')->hourly()->runInBackground();*/
        /*$schedule->command('insert:report-control-marketing')->hourly();
        $schedule->command('insert:report-check-marketing')->hourly();*/
        $schedule->command('group:kpi-monthly')->monthlyOn(1, '01:00');
        $schedule->command('staff-group:reset-total-customer')->daily();
        $schedule->command('update:staff-group-id-by-customer-longtime')->dailyAt('02:00');
        $schedule->command('command:close-customer')->dailyAt('23:00')->runInBackground();
        $schedule->command('command:delete-order-cancel')->dailyAt('23:30')->runInBackground();
        $schedule->command('command:create-or-update-bill-code-order-package')->dailyAt('22:00')->runInBackground();


        /* Kiện hàng ký gửi quá 3 tháng về kho VN mà không lấy hàng => chuyển hết về trạng thái  HÀNG CHỜ THANH LÝ */
        $schedule->command('command:update-order-package-clearance')->hourly()->runInBackground();

        $schedule->command('command:sent-notification-order-slow-quote')->everyMinute()->runInBackground();
        $schedule->command('command:sent-notification-order-slow-quote-taobao')->everyMinute()->runInBackground();
        $schedule->command('command:sent-notification-order-slow-quote-1688')->everyMinute()->runInBackground();
        $schedule->command('command:sent-notification-order-slow-quote-tmall')->everyMinute()->runInBackground();


        // cập nhật dữ liệu đặt hàng
        // $schedule->command('sync:place-order-taobao-general-data')->hourly(); // Tạm ngắt

        // Báo cáo tỉnh thành phố
        // $schedule->command('sync:report-order-province')->hourly(); // Tạm ngắt

        // Cập nhật mã vân đơn cho order_buyder_subs
        // $schedule->command('sync:paid-order-buyder-subs-logistic-code')->dailyAt('00:00');

        // Tự động tạo kiện khi có mã vận đơn
        // $schedule->command('sync:orderpackage-by-buyder')->dailyAt('00:00');
        // Cập nhật giá trị lưu cache danh sách đơn hàng của Admin
        // $schedule->command('update:admin-cache-order-list')->everyFiveMinutes();
        $schedule->command('insert:report-order-performance')->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
