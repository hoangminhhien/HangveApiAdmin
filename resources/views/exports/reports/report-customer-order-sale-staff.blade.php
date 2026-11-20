<!DOCTYPE html>
<html lang="en">
<head>

</head>
<body>

<table id="customers">
    <!-- nhan vien   -->
    <tr class="font-weight-bold">
        <th colspan="3" style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold"></th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">{{ $user['user_name'] }}</td>
        @endforeach
    </tr>
    <!--  khách mới đặt hàng -->
    <tr>
        <th rowspan="4" style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold; width: 300px; vertical-align: center">KH mới Đặt hàng</th>
        <th rowspan="2" style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold; vertical-align: center">GOOGLE</th>
        <th style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">Tiêu dùng</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">{{ $user['total_first_customer_google_personal'] }}</td>
        @endforeach
    </tr>
    <tr style="background: wheat">
        <th style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">Kinh doanh</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC; background: wheat">{{ $user['total_first_customer_google_business'] }}</td>
        @endforeach
    </tr>
    <tr>
        <th rowspan="2" style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold; vertical-align: center">FACEBOOK</th>
        <th style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">Tiêu dùng</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC;">{{ $user['total_first_customer_facebook_ads_personal'] }}</td>
        @endforeach
    </tr>
    <tr style="background: wheat">
        <th style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">Kinh doanh</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC; background: wheat">{{ $user['total_first_customer_facebook_ads_business'] }}</td>
        @endforeach
    </tr>
    <!--  Giá trị đơn hàng mới -->
    <tr>
        <th rowspan="4" style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold; width: 300px; vertical-align: center">GT đơn hàng mới</th>
        <th rowspan="2" style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold; vertical-align: center">GOOGLE</th>
        <th style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">Tiêu dùng</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC;">{{ $user['total_first_amount_google_personal'] }}</td>
        @endforeach
    </tr>
    <tr style="background: wheat">
        <th style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">Kinh doanh</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC; background: wheat">{{ $user['total_first_amount_google_business'] }}</td>
        @endforeach
    </tr>
    <tr>
        <th rowspan="2" style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold; vertical-align: center">FACEBOOK</th>
        <th style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">Tiêu dùng</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC;">{{ $user['total_first_amount_facebook_ads_personal'] }}</td>
        @endforeach
    </tr>
    <tr style="background: wheat">
        <th style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">Kinh doanh</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC; background: wheat">{{ $user['total_first_amount_facebook_ads_business'] }}</td>
        @endforeach
    </tr>
    <tr>
        <th rowspan="4" style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold; width: 300px; vertical-align: center">Phí dịch vụ</th>
        <th rowspan="2" style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold; vertical-align: center">GOOGLE</th>
        <th style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">Tiêu dùng</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC;">{{ $user['total_first_service_change_google_personal'] }}</td>
        @endforeach
    </tr>
    <tr style="background: wheat">
        <th style="width:200px; text-align: center; border: 1px solid #CCCCCC; font-weight: bold">Kinh doanh</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC; background: wheat">{{ $user['total_first_service_change_google_business'] }}</td>
        @endforeach
    </tr>
    <tr>
        <th rowspan="2" style="width:200px; text-align: center; border: 1px solid #CCCCCC; font-weight: bold; vertical-align: center">FACEBOOK</th>
        <th style="width:200px; text-align: center; border: 1px solid #CCCCCC; font-weight: bold;">Tiêu dùng</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC; width: 200px">{{ $user['total_first_service_change_facebook_ads_personal'] }}</td>
        @endforeach
    </tr>
    <tr style="background: wheat">
        <th style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold; vertical-align: center">Kinh doanh</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC; background: wheat">{{ $user['total_first_service_change_facebook_ads_business'] }}</td>
        @endforeach
    </tr>
    <tr>
        <th rowspan="2" colspan="2" style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold; vertical-align: center">KH đặt từ đơn thứ 2 trở lên</th>
        <th style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">GOOGLE</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC;">{{ $user['total_second_customer_google']}}</td>
        @endforeach
    </tr>
    <tr>
        <th style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">FACEBOOK</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC;">{{ $user['total_second_customer_facebook']}}</td>
        @endforeach
    </tr>
    <tr>
        <th colspan="3" style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">Giá trị đơn từ đơn thứ 2 trở lên</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC;">{{ $user['total_second_customer_amount']}}</td>
        @endforeach
    </tr>
    <tr>
        <th colspan="3" style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">Phí dịch vụ từ đơn thứ 2 trở lên</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC;">{{ $user['total_second_customer_service_change']}}</td>
        @endforeach
    </tr>
    <tr style="background: #42b983">
        <th colspan="3" style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">Tổng giá trị đơn</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC;background: #42b983">{{ $user['total_amount_all']}}</td>
        @endforeach
    </tr>
    <tr style="background: orange">
        <th colspan="3" style="text-align: center; border: 1px solid #CCCCCC; font-weight: bold">Tổng phí dịch vụ</th>
        @foreach($data as $user)
            <td style="text-align: center; border: 1px solid #CCCCCC;background: orange">{{ $user['total_service_change_all']}}</td>
        @endforeach
    </tr>
</table>

</body>
</html>
