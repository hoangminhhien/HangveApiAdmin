<!DOCTYPE html>
<html lang="en">
<head>
</head>
<body>
<table id="customers">
    <tr class="font-weight-bold">
        <td rowspan="2" colspan="2"  style="border: 1px solid #333333; font-weight: bold; width: 200px; text-align: center; vertical-align: center"><span>{{ $data['start_date'] }} - {{ $data['end_date'] }}</span></td>
        <td colspan="2" style="background: yellow; border: 1px solid #333333; font-weight: bold; width: 200px; text-align: center">Nguồn Google</td>
        <td colspan="2" style="background: aqua; border: 1px solid #333333; font-weight: bold; width: 200px; text-align: center">Nguồn Zalo+Facebook</td>
    </tr>
    <tr class="font-weight-bold">
        <td style="border: 1px solid #333333; font-weight: bold; width: 200px; text-align: center">SL</td>
        <td style="border: 1px solid #333333; font-weight: bold; width: 200px; text-align: center">Tỷ lệ</td>
        <td style="border: 1px solid #333333; font-weight: bold; width: 200px; text-align: center">SL</td>
        <td style="border: 1px solid #333333; font-weight: bold; width: 200px; text-align: center">Tỷ lệ</td>
    </tr>
    <tr style="background: orange">
        <td colspan="2" style="border: 1px solid #333333; font-weight: bold; width: 200px">Tổng KH nhận được</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_all_customer_google'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_all_customer_google'] }}%</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_all_customer_facebook'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_all_customer_facebook'] }}%</td>
    </tr>
    <tr>
        <td colspan="2" style="border: 1px solid #333333; font-weight: bold; width: 200px">Không tương tác được</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_not_interact_google'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_not_interact_google'] }}%</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_not_interact_facebook'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_not_interact_facebook'] }}%</td>
    </tr>
    <tr>
        <td colspan="2" style="border: 1px solid #333333; font-weight: bold; width: 200px">Không có nhu cầu</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_not_need_google'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_not_need_google'] }}%</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_not_need_facebook'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_not_need_facebook'] }}%</td>
    </tr>
    <tr>
        <td colspan="2" style="border: 1px solid #333333; font-weight: bold; width: 200px">Có nhu cầu nhưng ko nhập được</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_not_imported_google'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_not_imported_google'] }}%</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_not_imported_facebook'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_not_imported_facebook'] }}%</td>
    </tr>
    <!--  -->
    <tr>
        <td rowspan="3" style="border: 1px solid #333333; font-weight: bold; width: 200px; vertical-align: center; text-align: center">Có nhu cầu</td>
        <td style="border: 1px solid #333333; font-weight: bold; width: 200px">Tham khảo chưa có NC ngay</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_not_immediately_google'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_not_immediately_google'] }}%</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_not_immediately_facebook'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_not_immediately_facebook'] }}%</td>
    </tr>
    <tr>
        <td style="border: 1px solid #333333; font-weight: bold; width: 200px">Tham khảo</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_reference_google'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_reference_google'] }}%</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_reference_facebook'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_reference_facebook'] }}%</td>
    </tr>
    <tr>
        <td style="border: 1px solid #333333; font-weight: bold; width: 200px">Đang follow</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_follow_google'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_follow_google'] }}%</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_follow_facebook'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_follow_facebook'] }}%</td>
    </tr>
    <!--   -->
    <tr>
        <td rowspan="2" style="border: 1px solid #333333; font-weight: bold; width: 200px; text-align: center; vertical-align: center">Đã đặt hàng</td>
        <td style="border: 1px solid #333333; font-weight: bold; width: 200px">Khách tiêu dùng</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_personal_google'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_personal_google'] }}%</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_personal_facebook'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_personal_facebook'] }}%</td>
    </tr>
    <tr>
        <td style="border: 1px solid #333333; font-weight: bold; width: 200px">Khách kinh doanh</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_business_google'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_business_google'] }}%</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['total_customer_business_facebook'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data['percent_total_customer_business_facebook'] }}%</td>
    </tr>
</table>
</body>
</html>
