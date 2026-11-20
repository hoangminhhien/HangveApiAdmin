<?php

namespace App\Services\Reports;

use App\Abstracts\AbstractBaseService;
use App\Constants\OrderConstant;
use App\Constants\Report\ChatBiz;
use App\Constants\ViaConstant;
use App\Models\Customer;
use App\Models\Label;
use App\Models\ReportCustomerConsult;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\CalculatorWeekReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportCustomerConsultService extends AbstractBaseService
{

    protected function setModel()
    {
        $namespaceModel = 'App\Models\\' . str_replace('Service', '', class_basename($this));
        $this->_model = class_exists($namespaceModel) ? new $namespaceModel() : null;
    }

    public function index($request = []): array
    {
        $weekEnd = $request['weekEnd'] ?? 0;
        $monthEnd = $request['monthEnd'] ?? 0;
        $yearEnd = $request['yearEnd'] ?? 0;
        $weekStart = $request['weekStart'] ?? 0;
        $monthStart = $request['monthStart'] ?? 0;
        $yearStart = $request['yearStart'] ?? 0;
        $group_id = $request['groupId'] ?? '';
        $date_start_1 = $request['date_start_1'] ?? '';
        $date_start_2 = $request['date_start_2'] ?? '';
        $date_end_1 = $request['date_end_1'] ?? '';
        $date_end_2 = $request['date_end_2'] ?? '';
        $response = [
            'code' => 200,
            'data' => [],
            'message' => 'Thành công!'
        ];
        if (!$date_start_1 && $date_end_1) {
            $response['code'] = 500;
            $response['message'] = 'Thời gian tham chiếu không được để trống!';
            return $response;
        }
        if (!$date_end_1 && $date_start_1) {
            $response['code'] = 500;
            $response['message'] = 'Thời gian xem không được để trống!';
            return $response;
        }
        $is_default = true;
        if ($date_start_1 && $date_start_2 && $date_end_1 && $date_end_2) {
            $is_default = false;
            if ($date_start_1 > $date_start_2) {
                $response['code'] = 500;
                $response['message'] = 'Thời gian tham chiếu : Ngày bắt đầu phải nhỏ hơn hoặc bằng Ngày kết thúc!';
                return $response;
            }
            if ($date_end_1 > $date_end_2) {
                $response['code'] = 500;
                $response['message'] = 'Thời gian xem : Ngày bắt đầu phải nhỏ hơn hoặc bằng Ngày kết thúc!';
                return $response;
            }
            if ($date_start_1 >= $date_end_1 || $date_start_1 >= $date_end_2) {
                $response['code'] = 500;
                $response['message'] = 'Thời gian tham chiếu : Ngày bắt đầu phải nhỏ hơn Thời gian xem!';
                return $response;
            }

            if ($date_start_2 >= $date_end_1 || $date_start_2 >= $date_end_2) {
                $response['code'] = 500;
                $response['message'] = 'Thời gian tham chiếu : Ngày kết thúc phải nhỏ hơn Thời gian xem!';
                return $response;
            }

            $date_start_1 = date('Y-m-d' . ' 00:00:00', (int)$date_start_1 / 1000);
            $date_start_2 = date('Y-m-d' . ' 23:59:59', (int)$date_start_2 / 1000);
            $date_end_1 = date('Y-m-d' . ' 00:00:00', (int)$date_end_1 / 1000);
            $date_end_2 = date('Y-m-d' . ' 23:59:59', (int)$date_end_2 / 1000);
        } else {
            $calculatorDatetimeStart = CalculatorWeekReport::getDayOfWeekOfMonth($yearStart, $monthStart, $weekStart);
            $date_start_1 = $calculatorDatetimeStart['week_start_date'] . ' 00:00:00';
            $date_start_2 = $calculatorDatetimeStart['week_end_date'] . ' 23:59:59';

            $calculatorDatetimeEnd = CalculatorWeekReport::getDayOfWeekOfMonth($yearEnd, $monthEnd, $weekEnd);
            $date_end_1 = $calculatorDatetimeEnd['week_start_date'] . ' 00:00:00';
            $date_end_2 = $calculatorDatetimeEnd['week_end_date'] . ' 23:59:59';
        }
        /* Lấy data tuần tham chiếu*/
        $resultStart = $this->processData($date_start_1, $date_start_2, $group_id);
        /* Lấy data tuần xem*/
        $resultEnd = $this->processData($date_end_1, $date_end_2, $group_id);
        $isDisplayWeekEnd =  $weekEnd ? ' Tuần '.$weekEnd : '';
        $isDisplayWeekStart =  $weekStart ? ' Tham chiếu tuần '.$weekStart : '';
        if (!empty($weekStart)) {
            $res = CalculatorWeekReport::getDayOfWeekOfMonth($yearStart, $monthStart, $weekStart);
            $startDateStartOfWeek = date('d-m-Y',strtotime($res['week_start_date']));
            $endDateStartOfWeek = date('d-m-Y',strtotime($res['week_end_date']));
        } else {
            $res = CalculatorWeekReport::getStartAndEndDateOfMonth(['year' => $yearStart, 'month' => $monthStart]);
            $startDateStartOfWeek = date('d-m-Y',strtotime($res->fistDayOfMonth));
            $endDateStartOfWeek = date('d-m-Y',strtotime($res->lastDayOfMonth));
        }
        if (!empty($weekEnd)) {
            $res = CalculatorWeekReport::getDayOfWeekOfMonth($yearEnd, $monthEnd, $weekEnd);
            $startDateEndOfWeek = date('d-m-Y',strtotime($res['week_start_date']));
            $endDateEndOfWeek = date('d-m-Y',strtotime($res['week_end_date']));
        } else {
            $res = CalculatorWeekReport::getStartAndEndDateOfMonth(['year' => $yearEnd, 'month' => $monthEnd]);
            $startDateEndOfWeek = date('d-m-Y',strtotime($res->fistDayOfMonth));
            $endDateEndOfWeek = date('d-m-Y',strtotime($res->lastDayOfMonth));
        }
        /*KH tư vấn kí gửi => KH Kí gửi tạo tài khoản trong tuần báo cáo: Nhãn: Kí gửi + Kí gửi đã lên đơn*/
        $customer_consulting_consignment_start = data_get($resultStart, 'customer_consulting_consignment') ?? 0;
        $customer_consulting_consignment_end = data_get($resultEnd, 'customer_consulting_consignment') ?? 0;

        /*Tổng KH kí gửi lên đơn => KH Kí gửi tạo tài khoản trong tuần báo cáo: Nhãn: Kí gửi đã lên đơn*/
        $total_consignment_customer_order_start = data_get($resultStart, 'total_consignment_customer_order') ?? 0;
        $total_consignment_customer_order_end = data_get($resultEnd, 'total_consignment_customer_order') ?? 0;

        /*KH Tư vấn Order => KH Order tạo tài khoản trong tuần báo cáo: Gồm các Nhãn: Tham Khảo, Đang Follow, Đã lên đơn*/
        $customer_consulting_order_start = data_get($resultStart, 'customer_consulting_order') ?? 0;
        $customer_consulting_order_end = data_get($resultEnd, 'customer_consulting_order') ?? 0;

        /*Tổng KH mới lên đơn => KH Order tạo tài khoản trong tuần báo cáo: Nhãn: Đã lên đơn*/
        $total_customer_order_start = data_get($resultStart, 'total_customer_order') ?? 0;
        $total_customer_order_end = data_get($resultEnd, 'total_customer_order') ?? 0;

        /*Tổng Số Lượng Đơn KH Mới*/
        $total_qty_new_customer_order_start = data_get($resultStart, 'total_qty_new_customer_order') ?? 0;
        $total_qty_new_customer_order_end = data_get($resultEnd, 'total_qty_new_customer_order') ?? 0;

        /*Tổng Giá Trị Đơn KH Mới*/
        $total_value_new_customer_order_start = data_get($resultStart, 'total_value_new_customer_order') ?? 0;
        $total_value_new_customer_order_end = data_get($resultEnd, 'total_value_new_customer_order') ?? 0;

        /*Tổng Phí Dịch Vụ KH Mới*/
        $total_service_fee_new_customer_start = data_get($resultStart, 'total_service_fee_new_customer') ?? 0;
        $total_service_fee_new_customer_end = data_get($resultEnd, 'total_service_fee_new_customer') ?? 0;
        /* AOV = Tổng Giá Trị Đơn KH Mới/ Tổng Số Lượng Đơn KH Mới */
        $oav_data_two = $total_qty_new_customer_order_start > 0
            ? $total_value_new_customer_order_start / $total_qty_new_customer_order_start
            : 0;
        $oav_data_three =  $total_qty_new_customer_order_end > 0
            ? $total_value_new_customer_order_end / $total_qty_new_customer_order_end
            : 0;

        /* Tỉ lệ Khách Order Đặt Hàng = Tổng KH mới lên đơn/ KH Tư vấn Order */
        $ty_le_order_data_two = $customer_consulting_order_start > 0
            ? round($total_customer_order_start * 100 / $customer_consulting_order_start)
            : 0;
        $ty_le_order_data_three =  $customer_consulting_order_end > 0
            ? round($total_customer_order_end * 100 / $customer_consulting_order_end)
            : 0;
        /* Tỉ lệ Khách Kí Gửi = Tổng KH kí gửi lên đơn/ KH tư vấn kí gửi  */
        $ty_le_ky_gui_data_two = $customer_consulting_consignment_start > 0
            ? round($total_consignment_customer_order_start * 100 / $customer_consulting_consignment_start)
            : 0;
        $ty_le_ky_gui_data_three =  $customer_consulting_consignment_end > 0
            ? round($total_consignment_customer_order_end * 100 / $customer_consulting_consignment_end)
            : 0;

        /* Khách hàng mới đặt hàng */

        $new_customer_order_start = data_get($resultStart, 'new_customer_order') ?? 0;
        $new_customer_order_end = data_get($resultEnd, 'new_customer_order') ?? 0;

        $new_customer_order_gg_personal_start = data_get($resultStart, 'new_customer_order_gg_personal') ?? 0;
        $new_customer_order_gg_personal_end = data_get($resultEnd, 'new_customer_order_gg_personal') ?? 0;
        $new_customer_order_fb_personal_start = data_get($resultStart, 'new_customer_order_fb_personal') ?? 0;
        $new_customer_order_fb_personal_end = data_get($resultEnd, 'new_customer_order_fb_personal') ?? 0;

        $new_customer_order_gg_business_start = data_get($resultStart, 'new_customer_order_gg_business') ?? 0;
        $new_customer_order_gg_business_end = data_get($resultEnd, 'new_customer_order_gg_business') ?? 0;
        $new_customer_order_fb_business_start = data_get($resultStart, 'new_customer_order_fb_business') ?? 0;
        $new_customer_order_fb_business_end = data_get($resultEnd, 'new_customer_order_fb_business') ?? 0;

        /* Giá trị đơn hàng mới */

        $new_order_value_start = data_get($resultStart, 'new_order_value') ?? 0;
        $new_order_value_end = data_get($resultEnd, 'new_order_value') ?? 0;

        $new_order_value_gg_personal_start = data_get($resultStart, 'new_order_value_gg_personal') ?? 0;
        $new_order_value_gg_personal_end = data_get($resultEnd, 'new_order_value_gg_personal') ?? 0;
        $new_order_value_fb_personal_start = data_get($resultStart, 'new_order_value_fb_personal') ?? 0;
        $new_order_value_fb_personal_end = data_get($resultEnd, 'new_order_value_fb_personal') ?? 0;

        $new_order_value_gg_business_start = data_get($resultStart, 'new_order_value_gg_business') ?? 0;
        $new_order_value_gg_business_end = data_get($resultEnd, 'new_order_value_gg_business') ?? 0;
        $new_order_value_fb_business_start = data_get($resultStart, 'new_order_value_fb_business') ?? 0;
        $new_order_value_fb_business_end = data_get($resultEnd, 'new_order_value_fb_business') ?? 0;

        /*  Phí Dịch Vụ */

        $service_fee_start = data_get($resultStart, 'service_fee') ?? 0;
        $service_fee_end = data_get($resultEnd, 'service_fee') ?? 0;

        $service_fee_gg_personal_start = data_get($resultStart, 'service_fee_gg_personal') ?? 0;
        $service_fee_gg_personal_end = data_get($resultEnd, 'service_fee_gg_personal') ?? 0;
        $service_fee_fb_personal_start = data_get($resultStart, 'service_fee_fb_personal') ?? 0;
        $service_fee_fb_personal_end = data_get($resultEnd, 'service_fee_fb_personal') ?? 0;

        $service_fee_gg_business_start = data_get($resultStart, 'service_fee_gg_business') ?? 0;
        $service_fee_gg_business_end = data_get($resultEnd, 'service_fee_gg_business') ?? 0;
        $service_fee_fb_business_start = data_get($resultStart, 'service_fee_fb_business') ?? 0;
        $service_fee_fb_business_end = data_get($resultEnd, 'service_fee_fb_business') ?? 0;

        $label_start = 'Thời gian tham chiếu ( Từ ' . date('d-m-Y', strtotime($date_start_1)) . ' đến ' .  date('d-m-Y', strtotime($date_start_2)) . ' )';
        $label_end = 'Thời gian xem ( Từ ' .  date('d-m-Y', strtotime($date_end_1)) . ' đến ' . date('d-m-Y', strtotime($date_end_2)) . ' )';
        if ($is_default) {
            $label_start = $isDisplayWeekStart.' tháng '.$monthStart.'/'.$yearStart.' ( '.$startDateStartOfWeek .' đến '.$endDateStartOfWeek.' )';
            $label_end = $isDisplayWeekEnd.' tháng '.$monthEnd.'/'.$yearEnd.' ( '.$startDateEndOfWeek .' đến '.$endDateEndOfWeek.' )';
        }
        $data['format'] = [
            [
                'data_one' => '',
                'data_two' => $label_start,
                'data_three' => $label_end,
                'data_four' => '% Tăng trưởng',
                'data_five' => ''
            ],
            [
                'data_one' => 'KH tư vấn ký gửi',
                'data_two' => number_format($customer_consulting_consignment_start, 0, ',', '.'),
                'data_three' => number_format($customer_consulting_consignment_end, 0, ',', '.'),
                'data_four' => $customer_consulting_consignment_start > 0
                    ? round(
                        ($customer_consulting_consignment_end - $customer_consulting_consignment_start) * 100
                        / $customer_consulting_consignment_start
                    ) . '%' : 0 . '%',
                'data_five' => 'isBool'
            ],
            [
                'data_one' => 'Tổng KH kí gửi lên đơn',
                'data_two' => number_format($total_consignment_customer_order_start, 0, ',', '.'),
                'data_three' => number_format($total_consignment_customer_order_end, 0, ',', '.'),
                'data_four' => $total_consignment_customer_order_start > 0
                    ? round(
                        ($total_consignment_customer_order_end - $total_consignment_customer_order_start) * 100
                        / $total_consignment_customer_order_start
                    ) . '%' : 0 . '%',
                'data_five' => 'isBool'
            ],
            [
                'data_one' => 'KH Tư vấn Order',
                'data_two' => number_format($customer_consulting_order_start, 0, ',', '.'),
                'data_three' => number_format($customer_consulting_order_end, 0, ',', '.'),
                'data_four' => $customer_consulting_order_start > 0
                    ? round(
                        ($customer_consulting_order_end - $customer_consulting_order_start) * 100 / $customer_consulting_order_start
                    ) . '%' : 0 . '%',
                'data_five' => 'isBool'
            ],
            [
                'data_one' => 'Tổng KH mới lên đơn',
                'data_two' => number_format($total_customer_order_start, 0, ',', '.'),
                'data_three' => number_format($total_customer_order_end, 0, ',', '.'),
                'data_four' => $total_customer_order_start > 0
                    ? round(
                        ($total_customer_order_end - $total_customer_order_start) * 100 / $total_customer_order_start
                    ) . '%' : 0 . '%',
                'data_five' => 'isBool'
            ],
            [
                'data_one' => 'KH mới đặt hàng',
                'data_two' => number_format($new_customer_order_start, 0, ',', '.'),
                'data_three' => number_format($new_customer_order_end, 0, ',', '.'),
                'data_four' => $new_customer_order_start > 0
                    ? round(
                        ($new_customer_order_end - $new_customer_order_start) * 100 / $new_customer_order_start
                    ) . '%' : 0 . '%',
                'data_five' => 'isBool'
            ],
            [
                'data_one' => 'KH tiêu dùng mới đặt hàng từ GG Ads',
                'data_two' => number_format($new_customer_order_gg_personal_start, 0, ',', '.'),
                'data_three' => number_format($new_customer_order_gg_personal_end, 0, ',', '.'),
                'data_four' => $new_customer_order_gg_personal_start > 0
                    ? round(
                        ($new_customer_order_gg_personal_end - $new_customer_order_gg_personal_start) * 100
                        / $new_customer_order_gg_personal_start
                    ) . '%' : 0 . '%',
                'data_five' => ''
            ],
            [
                'data_one' => 'KH kinh doanh mới đặt hàng từ GG Ads',
                'data_two' => number_format($new_customer_order_gg_business_start, 0, ',', '.'),
                'data_three' => number_format($new_customer_order_gg_business_end, 0, ',', '.'),
                'data_four' => $new_customer_order_gg_business_start > 0
                    ? round(
                        ($new_customer_order_gg_business_end - $new_customer_order_gg_business_start) * 100
                        / $new_customer_order_gg_business_start
                    ) . '%' : 0 . '%',
                'data_five' => ''
            ],
            [
                'data_one' => 'KH tiêu dùng mới đặt hàng từ FB Ads',
                'data_two' => number_format($new_customer_order_fb_personal_start, 0, ',', '.'),
                'data_three' => number_format($new_customer_order_fb_personal_end, 0, ',', '.'),
                'data_four' => $new_customer_order_fb_personal_start > 0
                    ? round(
                        ($new_customer_order_fb_personal_end - $new_customer_order_fb_personal_start) * 100
                        / $new_customer_order_fb_personal_start
                    ) . '%' : 0 . '%',
                'data_five' => ''
            ],
            [
                'data_one' => 'KH kinh doanh mới đặt hàng từ FB Ads',
                'data_two' => number_format($new_customer_order_fb_business_start, 0, ',', '.'),
                'data_three' => number_format($new_customer_order_fb_business_end, 0, ',', '.'),
                'data_four' => $new_customer_order_fb_business_start > 0
                    ? round(
                        ($new_customer_order_fb_business_end - $new_customer_order_fb_business_start) * 100
                        / $new_customer_order_fb_business_start
                    ) . '%' : 0 . '%',
                'data_five' => ''
            ],
            [
                'data_one' => 'Giá trị đơn hàng mới',
                'data_two' => number_format($new_order_value_start, 0, ',', '.'),
                'data_three' => number_format($new_order_value_end, 0, ',', '.'),
                'data_four' => $new_order_value_start > 0
                    ? round(
                        ($new_order_value_end - $new_order_value_start) * 100
                        / $new_order_value_start
                    ) . '%' : 0 . '%',
                'data_five' => 'isBool'
            ],
            [
                'data_one' => 'Giá trị đơn hàng mới từ GG Ads tiêu dùng',
                'data_two' => number_format($new_order_value_gg_personal_start, 0, ',', '.'),
                'data_three' => number_format($new_order_value_gg_personal_end, 0, ',', '.'),
                'data_four' => $new_order_value_gg_personal_start > 0
                    ? round(
                        ($new_order_value_gg_personal_end - $new_order_value_gg_personal_start) * 100
                        / $new_order_value_gg_personal_start
                    ) . '%' : 0 . '%',
                'data_five' => ''
            ],
            [
                'data_one' => 'Giá trị đơn hàng mới từ GG Ads kinh doanh',
                'data_two' => number_format($new_order_value_gg_business_start, 0, ',', '.'),
                'data_three' => number_format($new_order_value_gg_business_end, 0, ',', '.'),
                'data_four' => $new_order_value_gg_business_start > 0
                    ? round(
                        ($new_order_value_gg_business_end - $new_order_value_gg_business_start) * 100
                        / $new_order_value_gg_business_start
                    ) . '%' : 0 . '%',
                'data_five' => ''
            ],
            [
                'data_one' => 'Giá trị đơn hàng mới từ FB Ads tiêu dùng',
                'data_two' => number_format($new_order_value_fb_personal_start, 0, ',', '.'),
                'data_three' => number_format($new_order_value_fb_personal_end, 0, ',', '.'),
                'data_four' => $new_order_value_fb_personal_start > 0
                    ? round(
                        ($new_order_value_fb_personal_end - $new_order_value_fb_personal_start) * 100
                        / $new_order_value_fb_personal_start
                    ) . '%' : 0 . '%',
                'data_five' => ''
            ],
            [
                'data_one' => 'Giá trị đơn hàng mới từ FB Ads kinh doanh',
                'data_two' => number_format($new_order_value_fb_business_start, 0, ',', '.'),
                'data_three' => number_format($new_order_value_fb_business_end, 0, ',', '.'),
                'data_four' => $new_order_value_fb_business_start > 0
                    ? round(
                        ($new_order_value_fb_business_end - $new_order_value_fb_business_start) * 100
                        / $new_order_value_fb_business_start
                    ) . '%' : 0 . '%',
                'data_five' => ''
            ],
            /*Service_change*/
            [
                'data_one' => 'Phí Dịch Vụ',
                'data_two' => number_format($service_fee_start, 0, ',', '.'),
                'data_three' => number_format($service_fee_end, 0, ',', '.'),
                'data_four' => $service_fee_start > 0
                    ? round(
                        ($service_fee_end - $service_fee_start) * 100
                        / $service_fee_start
                    ) . '%' : 0 . '%',
                'data_five' => 'isBool'
            ],
            [
                'data_one' => 'Phí dịch vụ mới từ GG Ads tiêu dùng',
                'data_two' => number_format($service_fee_gg_personal_start, 0, ',', '.'),
                'data_three' => number_format($service_fee_gg_personal_end, 0, ',', '.'),
                'data_four' => $service_fee_gg_personal_start > 0
                    ? round(
                        ($service_fee_gg_personal_end - $service_fee_gg_personal_start) * 100
                        / $service_fee_gg_personal_start
                    ) . '%' : 0 . '%',
                'data_five' => ''
            ],
            [
                'data_one' => 'Phí dịch vụ mới từ GG Ads kinh doanh',
                'data_two' => number_format($service_fee_gg_business_start, 0, ',', '.'),
                'data_three' => number_format($service_fee_gg_business_end, 0, ',', '.'),
                'data_four' => $service_fee_gg_business_start > 0
                    ? round(
                        ($service_fee_gg_business_end - $service_fee_gg_business_start) * 100
                        / $service_fee_gg_business_start
                    ) . '%' : 0 . '%',
                'data_five' => ''
            ],
            [
                'data_one' => 'Phí dịch vụ mới từ FB Ads tiêu dùng',
                'data_two' => number_format($service_fee_fb_personal_start, 0, ',', '.'),
                'data_three' => number_format($service_fee_fb_personal_end, 0, ',', '.'),
                'data_four' => $service_fee_fb_personal_start > 0
                    ? round(
                        ($service_fee_fb_personal_end - $service_fee_fb_personal_start) * 100
                        / $service_fee_fb_personal_start
                    ) . '%' : 0 . '%',
                'data_five' => ''
            ],
            [
                'data_one' => 'Phí dịch vụ mới từ FB Ads kinh doanh',
                'data_two' => number_format($service_fee_fb_business_start, 0, ',', '.'),
                'data_three' => number_format($service_fee_fb_business_end, 0, ',', '.'),
                'data_four' => $service_fee_fb_business_start > 0
                    ? round(
                        ($service_fee_fb_business_end - $service_fee_fb_business_start) * 100
                        / $service_fee_fb_business_start
                    ) . '%' : 0 . '%',
                'data_five' => ''
            ],
            /*Tổng Số Lượng Đơn KH Mới*/
            [
                'data_one' => 'Tổng Số Lượng Đơn KH Mới',
                'data_two' => number_format($total_qty_new_customer_order_start, 0, ',', '.'),
                'data_three' => number_format($total_qty_new_customer_order_end, 0, ',', '.'),
                'data_four' => $total_qty_new_customer_order_start > 0
                    ? round(
                        ($total_qty_new_customer_order_end - $total_qty_new_customer_order_start) * 100
                        / $total_qty_new_customer_order_start
                    ) . '%' : 0 . '%',
                'data_five' => 'isBool'
            ],
            /*Tổng Giá Trị Đơn KH Mới*/
            [
                'data_one' => 'Tổng Giá Trị Đơn KH Mới',
                'data_two' => number_format($total_value_new_customer_order_start, 0, ',', '.'),
                'data_three' => number_format($total_value_new_customer_order_end, 0, ',', '.'),
                'data_four' => $total_value_new_customer_order_start > 0
                    ? round(
                        ($total_value_new_customer_order_end - $total_value_new_customer_order_start) * 100
                        / $total_value_new_customer_order_start
                    ) . '%' : 0 . '%',
                'data_five' => 'isBool'
            ],
            /*Tổng Phí Dịch Vụ KH Mới*/
            [
                'data_one' => 'Tổng Phí Dịch Vụ KH Mới',
                'data_two' => number_format($total_service_fee_new_customer_start, 0, ',', '.'),
                'data_three' => number_format($total_service_fee_new_customer_end, 0, ',', '.'),
                'data_four' => $total_service_fee_new_customer_start > 0
                    ? round(
                        ($total_service_fee_new_customer_end - $total_service_fee_new_customer_start) * 100
                        / $total_service_fee_new_customer_start
                    ) . '%' : 0 . '%',
                'data_five' => 'isBool'
            ],
            /* AOV = Tổng Giá Trị Đơn KH Mới/ Tổng Số Lượng Đơn KH Mới*/
            [
                'data_one' => 'AOV',
                'data_two' => number_format($oav_data_two, 0, ',', '.'),
                'data_three' => number_format($oav_data_three, 0, ',', '.'),
                'data_four' =>  $oav_data_two > 0 ? round(
                        ($oav_data_two - $oav_data_three)*100/$oav_data_two
                    ).'%' : '0%',
                'data_five' => 'isBool'
            ],
            /*Tỉ lệ Khách Order Đặt Hàng */
            [
                'data_one' => 'Tỉ lệ Khách Order Đặt Hàng ',
                'data_two' => $ty_le_order_data_two . '%',
                'data_three' => $ty_le_order_data_three . '%',
                'data_four' => $ty_le_order_data_two > 0
                    ? round(
                        ($ty_le_order_data_three - $ty_le_order_data_two) * 100
                        / $ty_le_order_data_two) . '%'
                    : 0 . '%',
                'data_five' => 'isBool'
            ],
            /*Tỉ lệ Khách Kí Gửi */
            [
                'data_one' => 'Tỉ lệ Khách Kí Gửi ',
                'data_two' => $ty_le_ky_gui_data_two . '%',
                'data_three' => $ty_le_ky_gui_data_three . '%',
                'data_four' => $ty_le_ky_gui_data_two > 0
                ? round(
                    ($ty_le_ky_gui_data_three - $ty_le_ky_gui_data_two) * 100
                    / $ty_le_ky_gui_data_two) . '%'
                : 0 . '%',
                        'data_five' => 'isBool'
            ],

        ];
        $response['data'] = $data;
        return $response;
    }

    public function cronjobInsertData(array $request): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($list->year, $list->month, $list->week);
        $start_date = $calculatorDatetime['week_start_date'] . ' 00:00:00';
        $end_date = $calculatorDatetime['week_end_date'] . ' 23:59:59';
        $groupMapping = $this->processData($start_date, $end_date, $list, 'week');
        foreach ($groupMapping as $key => $item) {
            ReportCustomerConsult::query()->updateOrCreate([
                'group_id' => $key,
                'type' => 'week',
                'week' => $list->week,
                'month' => $list->month,
                'year' => $list->year,
            ], $item);
        }
        return true;
        /*$chatBiz = $this->getDataChatBiz($calculatorDatetime['week_start_date'], $calculatorDatetime['week_end_date']);*/
    }

    public function cronjobInsertDataMonth(array $request): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $calculatorDatetime = CalculatorWeekReport::getStartAndEndDateOfMonth($request);
        $start_date = $calculatorDatetime->fistDayOfMonth . ' 00:00:00';
        $end_date = $calculatorDatetime->lastDayOfMonth . ' 23:59:59';
        $groupMapping = $this->processData($start_date, $end_date, $list, 'month');
        foreach ($groupMapping as $key => $item) {
            ReportCustomerConsult::query()->updateOrCreate([
                'group_id' => $key,
                'type' => 'month',
                'month' => $list->month,
                'year' => $list->year,
            ], $item);
        }
        return true;
        /*$chatBiz = $this->getDataChatBiz($calculatorDatetime->fistDayOfMonth, $calculatorDatetime->lastDayOfMonth);*/
    }

    private function getGroupId(): array
    {
        $results = UserGroup::query()->select('group_id')->pluck('group_id')->toArray();
        return array_unique($results);
    }

    private function getUserGroup($group_id = false, $options = []): array
    {
        $query = User::query();
        if (isBusiness() && !isSalesManager() && !isDeputySalesManager()) {
            $query = $query->where('id', Auth::user()->id ?? 0);
        }
        $query = $query->whereExists(function ($query) use ($group_id, $options){
            $query->select("users_group.id")
                ->from('users_group')
                ->whereRaw('users_group.user_id = users.id');
            if ($group_id) {
                $query->where('users_group.group_id', $group_id);
            }
            if (!empty($options['month'])) {
                if (is_array($options['month'])) {
                    $query->whereIn('users_group.month', $options['month']);
                } else {
                    $query->where('users_group.month', $options['month']);
                }
            }
        })
            ->where('status', 1)
            ->select('id');
        return $query->pluck('id')
            ->toArray();
    }

    private function getLabel($arrayCode): array
    {
        return Label::query()
            ->select('id')
            ->whereIn('code', $arrayCode)
            ->pluck('id')
            ->toArray();
    }
    /* Khách hàng mới đặt hàng */
    private function processNewCustomerOrder(
        $start_date,
        $end_date,
        $user_group_ids,
        $groupMapping
    ): array
    {
        $label_ids = $this->getLabel(['DA_DAT_HANG']);
        $query = Customer::query()->select
        (
            DB::raw('DISTINCT orders.id, orders.order_cost,
                orders.inspection_cost,
                orders.international_shipping_cost,
                orders.order_fee,
                orders.china_shipping_cost,
                orders.exchange_rate,
                orders.discount_cost,
                customers.staff_group_id,
                orders.customer_id,
                orders.created_at,
                customers.via,
                customers.business_type'
        ))
            ->join('orders','orders.customer_id', 'customers.id')
            ->join('customer_label','customer_label.customer_id','=','customers.id')
            ->whereIn('customer_label.label_id', $label_ids)
            ->whereBetween('customer_label.created_at',[$start_date, $end_date])
            ->whereBetween('customers.created_at',[$start_date, $end_date])
            ->where('customers.status', 1)
            ->whereIn('customers.via',[
                ViaConstant::KEY_GOOGLE_ADS,
                ViaConstant::KEY_GOOGLE,
                ViaConstant::KEY_TIKTOK,
                ViaConstant::KEY_GOOGLE_FORM,
                ViaConstant::KEY_OTHER,
                ViaConstant::KEY_RESEARCH,
                ViaConstant::KEY_FACEBOOK_ADS,
                ViaConstant::KEY_FACEBOOK,
                ViaConstant::KEY_ZALO,
            ])
            ->whereIn('customers.business_type',[
                ChatBiz::PERSONAL,
                ChatBiz::BUSINESS
            ])
            ->whereNull('orders.deleted_at')
            ->when($user_group_ids != null, function ($q) use ($user_group_ids) {
                return $q->whereIn('customers.staff_group_id', $user_group_ids);
            })
            ->when(request()->input('source'), function($q) {
                $q->where('customers.source', request()->input('source'));
            })
            ->orderBy('orders.created_at', 'ASC')
            ->groupBy('orders.id');
        if (request()->query('processNewCustomerOrder')) {
            dd($query->toSql(), $query->getBindings());
        }
        $results = $query->get()->toArray();
        $new_customer_order = [];
        $new_customer_order_gg_personal = [];
        $new_customer_order_fb_personal = [];
        $new_customer_order_gg_business = [];
        $new_customer_order_fb_business = [];
        foreach($results as $item) {
            $amount = $item['order_cost']
                + $item['inspection_cost']
                + $item['international_shipping_cost']
                + $item['order_fee']
                + ($item['china_shipping_cost'] * $item['exchange_rate'])
                - $item['discount_cost'];
            $new_customer_order[] = $item['customer_id'];
            if(isset($groupMapping['new_customer_order'])){
                $groupMapping['new_customer_order'] = count(array_unique($new_customer_order));
            }
            if(isset($groupMapping['new_order_value'])){
                $groupMapping['new_order_value'] += $amount;
            }
            if(isset($groupMapping['service_fee'])){
                $groupMapping['service_fee'] += $item['order_fee'] ?? 0;
            }
            if ($item['business_type'] == ChatBiz::PERSONAL) {
                if (in_array($item['via'], [
                    ViaConstant::KEY_GOOGLE_ADS,
                    ViaConstant::KEY_GOOGLE,
                    ViaConstant::KEY_TIKTOK,
                    ViaConstant::KEY_GOOGLE_FORM,
                    ViaConstant::KEY_OTHER,
                    ViaConstant::KEY_RESEARCH
                    ])
                ) {
                    $new_customer_order_gg_personal[] = $item['customer_id'];
                    if(isset($groupMapping['new_customer_order_gg_personal'])){
                        $groupMapping['new_customer_order_gg_personal'] = count(array_unique($new_customer_order_gg_personal));
                    }
                    if(isset($groupMapping['new_order_value_gg_personal'])){
                        $groupMapping['new_order_value_gg_personal'] += $amount;
                    }
                    if(isset($groupMapping['service_fee_gg_personal'])){
                        $groupMapping['service_fee_gg_personal'] += $item['order_fee'] ?? 0;
                    }
                }

                if (in_array($item['via'], [
                    ViaConstant::KEY_FACEBOOK_ADS,
                    ViaConstant::KEY_FACEBOOK,
                    ViaConstant::KEY_ZALO
                ])
                ) {
                    $new_customer_order_fb_personal[] = $item['customer_id'];
                    if(isset($groupMapping['new_customer_order_fb_personal'])){
                        $groupMapping['new_customer_order_fb_personal'] = count(array_unique($new_customer_order_fb_personal));
                    }
                    if(isset($groupMapping['new_order_value_fb_personal'])){
                        $groupMapping['new_order_value_fb_personal'] += $amount;
                    }
                    if(isset($groupMapping['service_fee_fb_personal'])){
                        $groupMapping['service_fee_fb_personal'] += $item['order_fee'] ?? 0;
                    }
                }
            }
            if ($item['business_type'] == ChatBiz::BUSINESS) {
                if (in_array($item['via'], [
                    ViaConstant::KEY_GOOGLE_ADS,
                    ViaConstant::KEY_GOOGLE,
                    ViaConstant::KEY_TIKTOK,
                    ViaConstant::KEY_GOOGLE_FORM,
                    ViaConstant::KEY_OTHER,
                    ViaConstant::KEY_RESEARCH
                ])
                ) {
                    $new_customer_order_gg_business[] = $item['customer_id'];
                    if(isset($groupMapping['new_customer_order_gg_business'])){
                        $groupMapping['new_customer_order_gg_business'] = count(array_unique($new_customer_order_gg_business));
                    }
                    if(isset($groupMapping['new_order_value_gg_business'])){
                        $groupMapping['new_order_value_gg_business'] += $amount;
                    }
                    if(isset($groupMapping['service_fee_gg_business'])){
                        $groupMapping['service_fee_gg_business'] += $item['order_fee'] ?? 0;
                    }
                }

                if (in_array($item['via'], [
                    ViaConstant::KEY_FACEBOOK_ADS,
                    ViaConstant::KEY_FACEBOOK,
                    ViaConstant::KEY_ZALO
                ])
                ) {
                    $new_customer_order_fb_business[] = $item['customer_id'];
                    if(isset($groupMapping['new_customer_order_fb_business'])){
                        $groupMapping['new_customer_order_fb_business'] = count(array_unique($new_customer_order_fb_business));
                    }
                    if(isset($groupMapping['new_order_value_fb_business'])){
                        $groupMapping['new_order_value_fb_business'] += $amount;
                    }
                    if(isset($groupMapping['service_fee_fb_business'])){
                        $groupMapping['service_fee_fb_business'] += $item['order_fee'] ?? 0;
                    }
                }
            }
        }
        return $groupMapping;
    }

    private function processCustomerOrder(
        $start_date,
        $end_date,
        $user_group_ids,
        $groupMapping
    ): array
    {
        $label_ids = $this->getLabel(['THAM_KHAO', 'DANG_FOLLOW', 'DA_LEN_DON', 'DA_DAT_HANG']);

        $totalAmountOrderWithCustomerLabel = $this->getTotalAmountOrderWithCustomerLabel(
            $start_date,
            $end_date,
            $user_group_ids,
            $label_ids
        );
        if (isset($groupMapping['total_qty_new_customer_order'])) {
            $groupMapping['total_qty_new_customer_order'] = data_get($totalAmountOrderWithCustomerLabel, 'total_order') ?? 0;
        }
        if (isset($groupMapping['total_value_new_customer_order'])) {
            $groupMapping['total_value_new_customer_order'] =  data_get($totalAmountOrderWithCustomerLabel, 'amount') ?? 0;
        }
        if (isset($groupMapping['total_service_fee_new_customer'])) {
            $groupMapping['total_service_fee_new_customer'] = data_get($totalAmountOrderWithCustomerLabel, 'service_fee') ?? 0;
        }

        return $groupMapping;
    }

    private function processCustomerWithLabel(
        $start_date,
        $end_date,
        $user_group_ids,
        $groupMapping
    ): array
    {
        $groupMapping = $this->customerConsultingConsignment(
            $start_date,
            $end_date,
            $user_group_ids,
            $groupMapping
        );
        $groupMapping = $this->totalConsignmentCustomerOrder(
            $start_date,
            $end_date,
            $user_group_ids,
            $groupMapping
        );
        $groupMapping = $this->customerConsultingOrder(
            $start_date,
            $end_date,
            $user_group_ids,
            $groupMapping
        );
        $groupMapping = $this->totalCustomerOrder(
            $start_date,
            $end_date,
            $user_group_ids,
            $groupMapping
        );
        return $groupMapping;
    }
    /*KH tư vấn kí gửi => KH Kí gửi tạo tài khoản trong tuần báo cáo:
Nhãn: Kí gửi + Kí gửi đã lên đơn*/
    private function customerConsultingConsignment(
        $start_date,
        $end_date,
        $user_group_ids,
        $groupMapping
    ): array
    {
        $label_ids = $this->getLabel(['KI_GUI', 'KI_GUI_DA_LEN_DON']);

        $customerConsultingConsignmentWithCustomerLabels = $this->totalCustomerWithCustomerLabel(
            $start_date,
            $end_date,
            $user_group_ids,
            $label_ids
        );
        if (isset($groupMapping['customer_consulting_consignment'])) {
            $groupMapping['customer_consulting_consignment'] = data_get($customerConsultingConsignmentWithCustomerLabels, 'total_customer') ?? 0;
        }
        return $groupMapping;
    }
    /*Tổng KH kí gửi lên đơn => KH Kí gửi tạo tài khoản trong tuần báo cáo:
Nhãn: Kí gửi đã lên đơn*/
    private function totalConsignmentCustomerOrder(
        $start_date,
        $end_date,
        $user_group_ids,
        $groupMapping
    ): array
    {
        $label_ids = $this->getLabel(['KI_GUI_DA_LEN_DON']);

        $customerConsultingConsignmentWithCustomerLabels = $this->totalCustomerWithCustomerLabel(
            $start_date,
            $end_date,
            $user_group_ids,
            $label_ids
        );
        if (isset($groupMapping['total_consignment_customer_order'])) {
            $groupMapping['total_consignment_customer_order'] = data_get($customerConsultingConsignmentWithCustomerLabels, 'total_customer') ?? 0;
        }
        return $groupMapping;
    }
    /*KH Tư vấn Order => KH Order tạo tài khoản trong tuần báo cáo: Gồm các Nhãn: Tham Khảo, Đang Follow, Đã lên đơn*/
    private function customerConsultingOrder(
        $start_date,
        $end_date,
        $user_group_ids,
        $groupMapping
    ): array
    {
        $label_ids = $this->getLabel(['THAM_KHAO', 'DANG_FOLLOW', 'DA_LEN_DON', 'DA_DAT_HANG']);

        $customerConsultingConsignmentWithCustomerLabels = $this->totalCustomerWithCustomerLabel(
            $start_date,
            $end_date,
            $user_group_ids,
            $label_ids
        );
        if($customerConsultingConsignmentWithCustomerLabels) {
            if (isset($groupMapping['customer_consulting_order'])) {
                $groupMapping['customer_consulting_order'] = data_get($customerConsultingConsignmentWithCustomerLabels, 'total_customer') ?? 0;
            }
        }
        return $groupMapping;
    }
    /* Tổng KH mới lên đơn => KH Order tạo tài khoản trong tuần báo cáo: Nhãn: Đã lên đơn*/
    private function totalCustomerOrder(
        $start_date,
        $end_date,
        $user_group_ids,
        $groupMapping
    ): array
    {
        $label_ids = $this->getLabel(['DA_LEN_DON']);

        $customerConsultingConsignmentWithCustomerLabels = $this->totalCustomerWithCustomerLabel(
            $start_date,
            $end_date,
            $user_group_ids,
            $label_ids,
        );
        if (isset($groupMapping['total_customer_order'])) {
            $groupMapping['total_customer_order'] = data_get($customerConsultingConsignmentWithCustomerLabels, 'total_customer') ?? 0;
        }
        return $groupMapping;
    }

    private function totalCustomer($start_date, $end_date, $user_group_ids, $label_ids)
    {
        $flagDate = '2024-12-04 00:00:00';
        $month = date('m', strtotime($start_date));
        $year = date('Y', strtotime($start_date));
        if ($month < 12 && $year <= '2024') {

        } else {
            if (strtotime($start_date) < strtotime($flagDate)) {
                $start_date = $flagDate;
            }
        }

        $query = Customer::query()->select
        (
            DB::raw('COUNT(DISTINCT customers.id) as total_customer'),
            'users_group.group_id'
        )
            ->join('users_group','users_group.user_id', 'customers.staff_group_id')
            ->join('customer_label','customer_label.customer_id','=','customers.id')
            ->whereIn('customer_label.label_id', $label_ids)
            ->whereBetween('customer_label.created_at',[$start_date, $end_date])
            ->where('customers.status', 1)
            ->whereIn('customers.staff_group_id', $user_group_ids)
            ->when(request()->input('source'), function($q) {
                $q->where('customers.source', request()->input('source'));
            })
            ->groupBy('users_group.group_id');
        return $query->get();
    }

    private function totalCustomerWithCustomerLabel(
        $start_date,
        $end_date,
        $user_group_ids,
        $label_ids
    )
    {
        $flagDate = '2024-12-04 00:00:00';
        $month = date('m', strtotime($start_date));
        $year = date('Y', strtotime($start_date));
        if ($month < 12 && $year <= '2024') {
            /* nothing*/
        } else {
            if (strtotime($start_date) < strtotime($flagDate)) {
                $start_date = $flagDate;
            }
            /*if ($type == 'month') {
                if (strtotime($start_date) < strtotime($flagDate)) {
                    $start_date = $flagDate;
                }
            }*/
        }

        $query = Customer::query()->select
        (
            DB::raw('COUNT(DISTINCT customers.id) as total_customer')
        )
            ->join('customer_label','customer_label.customer_id','=','customers.id')
            ->whereBetween('customers.created_at',[$start_date, $end_date])
            ->whereIn('customer_label.label_id', $label_ids)
            ->where('customers.status', 1)
            ->when(request()->input('source'), function($q) {
                $q->where('customers.source', request()->input('source'));
            })
            ->when($user_group_ids != null, function ($q) use ($user_group_ids) {
                return $q->whereIn('customers.staff_group_id', $user_group_ids);
            });
        return $query->first();
    }

    private function getTotalAmountOrder(
        $start_date,
        $end_date,
        $user_group_ids,
        $label_ids
    )
    {
        $flagDate = '2024-12-04 00:00:00';
        $month = date('m', strtotime($start_date));
        $year = date('Y', strtotime($start_date));
        if ($month < 12 && $year <= '2024') {

        } else {
            if (strtotime($start_date) < strtotime($flagDate)) {
                $start_date = $flagDate;
            }
        }

        $query = Customer::query()->select
        (
            DB::raw('COUNT(DISTINCT orders.id) as total_order'),
            DB::raw('SUM(orders.order_cost + orders.inspection_cost
                                                + orders.international_shipping_cost
                                                + orders.order_fee
                                                + (orders.china_shipping_cost * orders.exchange_rate)
                                                - orders.discount_cost
                                                ) as amount'
            ),
            DB::raw('SUM(orders.order_fee) as service_fee'),
            'users_group.group_id'
        )
            ->join('users_group','users_group.user_id', 'customers.staff_group_id')
            ->join('orders','orders.customer_id', 'customers.id')
            ->join('customer_label','customer_label.customer_id','=','customers.id')
            ->whereIn('customer_label.label_id', $label_ids)
            ->whereBetween('customer_label.created_at',[$start_date, $end_date])
            ->where('customers.status', 1)
            ->whereIn('customers.staff_group_id', $user_group_ids)
            ->when(request()->input('source'), function($q) {
                $q->where('customers.source', request()->input('source'));
            })
            ->groupBy('users_group.group_id');
        return $query->get();
    }

    private function getTotalAmountOrderWithCustomerLabel(
        $start_date,
        $end_date,
        $user_group_ids,
        $label_ids
    )
    {
        $flagDate = '2024-12-04 00:00:00';
        $month = date('m', strtotime($start_date));
        $year = date('Y', strtotime($start_date));
        $start_date_customer = $start_date;
        if ($month < 12 && $year <= '2024') {

        } else {
            if (strtotime($start_date) < strtotime($flagDate)) {
                $start_date = $flagDate;
            }
        }


        $query = Customer::query()->select
        (
            DB::raw('COUNT(DISTINCT orders.id) as total_order'),
            DB::raw('SUM(orders.order_cost + orders.inspection_cost
                                                + orders.international_shipping_cost
                                                + orders.order_fee
                                                + (orders.china_shipping_cost * orders.exchange_rate)
                                                - orders.discount_cost
                                                ) as amount'
            ),
            DB::raw('SUM(orders.order_fee) as service_fee')
        )
            ->join('orders','orders.customer_id', 'customers.id')
            ->join('customer_label','customer_label.customer_id','=','customers.id')
            ->whereIn('customer_label.label_id', $label_ids)
            ->whereIn('orders.status', [
                OrderConstant::KEY_STATUS_WAIT_TO_PAY,
                OrderConstant::KEY_STATUS_DEPOSITED,
                OrderConstant::KEY_STATUS_DONE,
                OrderConstant::KEY_STATUS_ORDERED
            ])
            ->whereBetween('customers.created_at', [$start_date_customer, $end_date])
            ->whereBetween('orders.created_at', [$start_date_customer, $end_date])
            ->where('customers.status', 1)
            ->when(request()->input('source'), function($q) {
                $q->where('customers.source', request()->input('source'));
            })
            ->when($user_group_ids != null, function ($q) use ($user_group_ids) {
                return $q->whereIn('customers.staff_group_id', $user_group_ids);
            });
        return $query->first();
    }

    public function getDataChatBiz($weekStartDate, $weekEndDate)
    {
        try {
            $data = [
                'date_start' => $weekStartDate,
                'date_end' => $weekEndDate
            ];
            $data_string = json_encode($data);
            $curl = ChatBiz::CHAT_BIZ_URI_ALL;
            $curl = curl_init($curl);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data_string))
            );
            $result = curl_exec($curl);
            curl_close($curl);
            return json_decode($result,true);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function processData(
        $start_date,
        $end_date,
        $group_id
    ): array
    {
        $groupMapping = $this->mappingGroup();
        $group_id = request()->input('group_id');
        $month_start = date('m/Y', strtotime($start_date));
        $month_end = date('m/Y', strtotime($end_date));
        if ($group_id) {
            $user_group_ids = $this->getUserGroup($group_id, ['month' => [$month_start, $month_end]]);
        } else {
            $user_group_ids = null;
        }
        $group_id = request()->input('group_id');
        if ($group_id && !$user_group_ids) {
            return $groupMapping;
        }
        $groupMapping = $this->processNewCustomerOrder(
            $start_date,
            $end_date,
            $user_group_ids,
            $groupMapping
        );
        $groupMapping = $this->processCustomerWithLabel(
            $start_date,
            $end_date,
            $user_group_ids,
            $groupMapping
        );

        $groupMapping = $this->processCustomerOrder(
            $start_date,
            $end_date,
            $user_group_ids,
            $groupMapping
        );
        return $groupMapping;
    }

    private function mappingGroup(): array
    {
        return [
            'customer_consulting_consignment' => 0,
                'total_consignment_customer_order' => 0,
                'customer_consulting_order' => 0,
                'total_customer_order' => 0,
                'new_customer_order' => 0,
                'new_customer_order_gg_personal' => 0,
                'new_customer_order_gg_business' => 0,
                'new_customer_order_fb_personal' => 0,
                'new_customer_order_fb_business' => 0,
                'new_order_value' => 0,
                'new_order_value_gg_personal' => 0,
                'new_order_value_gg_business' => 0,
                'new_order_value_fb_personal' => 0,
                'new_order_value_fb_business' => 0,
                'service_fee' => 0,
                'service_fee_gg_personal' => 0,
                'service_fee_gg_business' => 0,
                'service_fee_fb_personal' => 0,
                'service_fee_fb_business' => 0,
                'total_qty_new_customer_order' => 0,
                'total_value_new_customer_order' => 0,
                'total_service_fee_new_customer' => 0,
        ];
    }
}
