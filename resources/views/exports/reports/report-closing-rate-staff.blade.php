<!DOCTYPE html>
<html lang="en">
    <head>
    </head>
    <body>
        <table id="customers">
            <tr>
                <td rowspan="3" colspan="3" style="border: 1px solid #CCCCCC; font-weight: bold; vertical-align: center"><span>{{ $date['start_date'] }} - {{ $date['end_date'] }}</span></td>
                @foreach($data as $item)
                    <td colspan="6" style="border: 1px solid #CCCCCC; font-weight: bold; text-align: center">{{ $item['name'] }}</td>
                @endforeach
            </tr>
            <tr>
                @foreach($data as $item)
                    <td style="background: yellow; border: 1px solid #CCCCCC; font-weight: bold; text-align: center" colspan="3">Nguồn Google</td>
                    <td style="background: aqua; border: 1px solid #CCCCCC; font-weight: bold; text-align: center" colspan="3">Nguồn Zalo+Facebook</td>
                @endforeach
            </tr>
            <tr class="font-weight-bold">
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; font-weight: bold; text-align: center; width: 150px">SL</td>
                    <td style="border: 1px solid #CCCCCC; font-weight: bold; text-align: center; width: 150px">Tỷ lệ/tổng</td>
                    <td style="border: 1px solid #CCCCCC; font-weight: bold; text-align: center; width: 150px">Tỷ lệ/CNC</td>
                @endforeach
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; font-weight: bold; text-align: center; width: 150px">SL</td>
                    <td style="border: 1px solid #CCCCCC; font-weight: bold; text-align: center; width: 150px">Tỷ lệ/tổng</td>
                    <td style="border: 1px solid #CCCCCC; font-weight: bold; text-align: center; width: 150px">Tỷ lệ/CNC</td>
                @endforeach
            </tr>
            <!-- Tổng khách hàng nhận được -->
            <tr>
                <td colspan="3" style="border: 1px solid #CCCCCC; width: 150px; text-align: left">Tổng khách hàng nhận được</td>
                @foreach($data as $item)
                    <td style="background: #BBE1CD; border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_google_all'] }}</td>
                    <td style="background: #BBE1CD; border: 1px solid #CCCCCC; width: 150px; text-align: center"></td>
                    <td rowspan="5" style="border: 1px solid #CCCCCC; width: 150px; text-align: left"></td>
                @endforeach
                @foreach($data as $item)
                    <td style="background: #BBE1CD; border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_facebook_all'] }}</td>
                    <td style="background: #BBE1CD; border: 1px solid #CCCCCC; width: 150px; text-align: center"> </td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center" rowspan="5"></td>
                @endforeach
            </tr>
            <!-- Không có nhu cầu -->
            <tr>
                <td colspan="2" rowspan="4" style="border: 1px solid #CCCCCC; width: 150px; text-align: left; vertical-align: center">Không có nhu cầu</td>
                <td style="border: 1px solid #CCCCCC; width: 250px; text-align: left">Tổng</td>
                @foreach($data as $item)
                    <td style="background: #fe6701; border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_not_need_google_all'] }}</td>
                    <td style="background: #fe6701; border: 1px solid #CCCCCC; width: 150px; text-align: center"></td>
                @endforeach
                @foreach($data as $item)
                    <td style="background: #fe6701; border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_not_need_facebook_all'] }}</td>
                    <td style="background: #fe6701; border: 1px solid #CCCCCC; width: 150px; text-align: center"></td>
                @endforeach
            </tr>
            <!-- Không tương tác được -->
            <tr>
                <td style="border: 1px solid #CCCCCC; width: 250px; text-align: left">Không tương tác được</td>
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_not_interact_google'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_not_interact_google'] }}%</td>
                @endforeach
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_not_interact_facebook'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_not_interact_facebook'] }}%</td>
                @endforeach
            </tr>
            <!-- Không có nhu cầu -->
            <tr>
                <td style="border: 1px solid #CCCCCC; width: 250px; text-align: left">Không có nhu cầu</td>
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_not_need_google'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_not_need_google'] }}%</td>
                @endforeach
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_not_need_facebook'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_not_need_facebook'] }}%</td>
                @endforeach
            </tr>
            <!-- Có nhu cầu nhưng không nhập được -->
            <tr>
                <td style="border: 1px solid #CCCCCC; width: 250px; text-align: left">Có nhu cầu nhưng không nhập được</td>
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_not_imported_google'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_not_imported_google'] }}%</td>
                @endforeach
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_not_imported_facebook'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_not_imported_facebook'] }}%</td>
                @endforeach
            </tr>

            <!-- Có có nhu cầu -->
            <tr>
                <td colspan="2" rowspan="6" style="border: 1px solid #CCCCCC; width: 150px; text-align: left; vertical-align: center">Có nhu cầu</td>
                <td style="border: 1px solid #CCCCCC; width: 250px; text-align: left">Tổng</td>
                @foreach($data as $item)
                    <td style="background: #fe6701; border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_need_google_all'] }}</td>
                    <td style="background: #fe6701; border: 1px solid #CCCCCC; width: 150px; text-align: center"></td>
                    <td style="background: #fe6701; border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_cnc_need_google_all'] }}%</td>
                @endforeach
                @foreach($data as $item)
                    <td style="background: #fe6701; border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_need_facebook_all'] }}</td>
                    <td style="background: #fe6701; border: 1px solid #CCCCCC; width: 150px; text-align: center"></td>
                    <td style="background: #fe6701; border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_cnc_need_facebook_all'] }}%</td>
                @endforeach
            </tr>
            <!-- Tham khảo chưa có NC ngay -->
            <tr>
                <td style="border: 1px solid #CCCCCC; width: 250px; text-align: left">Tham khảo chưa có NC ngay</td>
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_not_immediately_google'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_not_immediately_google'] }}%</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_not_immediately_cnc_google'] }}%</td>
                @endforeach
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_not_immediately_facebook'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_not_immediately_facebook'] }}%</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_not_immediately_cnc_facebook'] }}%</td>
                @endforeach
            </tr>
            <!-- Tham khảo -->
            <tr>
                <td style="border: 1px solid #CCCCCC; width: 250px; text-align: left">Tham khảo</td>
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_reference_google'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_reference_google'] }}%</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_reference_cnc_google'] }}%</td>
                @endforeach
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_reference_facebook'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_reference_facebook'] }}%</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_reference_cnc_facebook'] }}%</td>
                @endforeach
            </tr>
            <!-- Đang follow -->
            <tr>
                <td style="border: 1px solid #CCCCCC; width: 250px; text-align: left">Đang follow</td>
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_follow_google'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_follow_google'] }}%</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_follow_cnc_google'] }}%</td>
                @endforeach
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_follow_facebook'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_follow_facebook'] }}%</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_follow_cnc_facebook'] }}%</td>
                @endforeach
            </tr>
            <!-- Đã đặt hàng -->
            <tr>
                <td style="border: 1px solid #CCCCCC; width: 250px; text-align: left">Khách tiêu dùng đã đặt hàng</td>
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_personal_google'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_personal_google'] }}%</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_personal_cnc_google'] }}%</td>
                @endforeach
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_personal_facebook'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_personal_facebook'] }}%</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_personal_cnc_facebook'] }}%</td>
                @endforeach
            </tr>
            <!-- Khách kinh doanh -->
            <tr>
                <td style="border: 1px solid #CCCCCC; width: 250px; text-align: left">Khách kinh doanh đã đặt hàng</td>
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_business_google'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_business_google'] }}%</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_business_cnc_google'] }}%</td>
                @endforeach
                @foreach($data as $item)
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['total_customer_business_facebook'] }}</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_business_facebook'] }}%</td>
                    <td style="border: 1px solid #CCCCCC; width: 150px; text-align: center">{{ $item['percent_business_cnc_facebook'] }}%</td>
                @endforeach
            </tr>
        </table>
    </body>
</html>
