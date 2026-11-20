<!DOCTYPE html>
<html lang="en">
<head>

</head>
<body>

<table id="customers">
    <tr>
        <td style="border: 1px solid #333333; width: 300px; text-align: center; font-weight: bold" colspan="2"></td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center; font-weight: bold">{{ $dataReportOrderSale[0]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center; font-weight: bold">{{ $dataReportOrderSale[0]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center; font-weight: bold">{{ $dataReportOrderSale[0]['data_four'] }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" colspan="2" >{{ $dataReportOrderSale[1]['data_one'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[1]['data_two'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[1]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[1]['data_four'] }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" colspan="2">{{ $dataReportOrderSale[2]['data_one'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[2]['data_two'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[2]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[2]['data_four'] }}</td>
    </tr>
    <tr>
        <td colspan="2" style="font-weight: bold; border: 1px solid #333333; width: 300px; text-align: center">{{ $dataReportOrderSale[3]['data_one'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[3]['data_two'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[3]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[3]['data_four'] }}</td>
    </tr>

    <tr>
        <td colspan="2" style="font-weight: bold; border: 1px solid #333333; width: 300px; text-align: center">{{ $dataReportOrderSale[4]['data_one'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[4]['data_two'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[4]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[4]['data_four'] }}</td>
    </tr>
    <tr>
        <td rowspan="2" style="border: 1px solid #333333; width: 300px; text-align: center; vertical-align: center">KH từ GG Ads</td>
        <td style="border: 1px solid #333333; width: 150px; text-align: center">Tiêu dùng</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[5]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[5]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[5]['data_four'] }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #333333; width: 150px; text-align: center">Kinh doanh</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[6]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[6]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[6]['data_four'] }}</td>
    </tr>
    <tr>
        <td rowspan="2" style="border: 1px solid #333333; width: 300px; text-align: center; vertical-align: center">KH từ FB Ads</td>
        <td style="border: 1px solid #333333; width: 150px; text-align: center">Tiêu dùng</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[7]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[7]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[7]['data_four'] }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #333333; width: 150px; text-align: center">Kinh doanh</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[8]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[8]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[8]['data_four'] }}</td>
    </tr>

    <tr>
        <td colspan="2" style="font-weight: bold; border: 1px solid #333333; width: 300px; text-align: center" >Giá trị đơn hàng mới</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[9]['data_two'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[9]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[9]['data_four'] }}</td>
    </tr>
    <tr>
        <td rowspan="2" style="border: 1px solid #333333; width: 300px; text-align: center; vertical-align: center">KH từ GG Ads</td>
        <td style="border: 1px solid #333333; width: 150px; text-align: center">Tiêu dùng</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[10]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[10]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[10]['data_four'] }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #333333; width: 150px; text-align: center">Kinh doanh</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[11]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[11]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[11]['data_four'] }}</td>
    </tr>
    <tr>
        <td rowspan="2" style="border: 1px solid #333333; width: 300px; text-align: center; vertical-align: center">KH từ FB Ads</td>
        <td style="border: 1px solid #333333; width: 150px; text-align: center">Tiêu dùng</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[12]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[12]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[12]['data_four'] }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #333333; width: 150px; text-align: center">Kinh doanh</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[13]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[13]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[13]['data_four'] }}</td>
    </tr>

    <tr>
        <td colspan="2" style="font-weight: bold; border: 1px solid #333333; width: 300px; text-align: center">Phí dịch vụ mới</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[14]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[14]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[14]['data_four'] }}</td>
    </tr>
    <tr>
        <td rowspan="2" style="border: 1px solid #333333; width: 300px; text-align: center; vertical-align: center">KH từ GG Ads</td>
        <td style="border: 1px solid #333333; width: 150px; text-align: center">Tiêu dùng</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[15]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[15]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[15]['data_four'] }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #333333; width: 150px; text-align: center">Kinh doanh</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[16]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[16]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[16]['data_four'] }}</td>
    </tr>
    <tr>
        <td rowspan="2" style="border: 1px solid #333333; width: 300px; text-align: center; vertical-align: center">KH từ FB Ads</td>
        <td style="border: 1px solid #333333; width: 150px; text-align: center">Tiêu dùng</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[17]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[17]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[17]['data_four'] }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #333333; width: 150px; text-align: center">Kinh doanh</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[18]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[18]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center">{{ $dataReportOrderSale[18]['data_four'] }}</td>
    </tr>
    <tr>
        <td rowspan="2" style="font-weight: bold; border: 1px solid #333333; width: 300px; text-align: center; vertical-align: center" >KH đặt từ đơn thứ 2 trở lên</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 150px; text-align: center" >GG</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[19]['data_two'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[19]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[19]['data_four'] }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold; border: 1px solid #333333; width: 150px; text-align: center" >FB</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[20]['data_two'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[20]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[20]['data_four'] }}</td>
    </tr>
    <tr>
        <td colspan="2" style="font-weight: bold; border: 1px solid #333333; width: 300px; text-align: center" >Giá trị đơn từ đơn thứ 2 trở lên</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[21]['data_two'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[21]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[21]['data_four'] }}</td>
    </tr>
    <tr>
        <td colspan="2" style="font-weight: bold; border: 1px solid #333333; width: 300px; text-align: center" >Phí dịch vụ đơn từ đơn thứ 2 trở lên</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[22]['data_two'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[22]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[22]['data_four'] }}</td>
    </tr>
    <tr>
        <td colspan="2" style="font-weight: bold; border: 1px solid #333333; width: 300px; text-align: center" >Tỉ lệ chốt (%)</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[23]['data_two'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[23]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[23]['data_four'] }}</td>
    </tr>
    <tr>
        <td colspan="2" style="font-weight: bold; border: 1px solid #333333; width: 300px; text-align: center" >Tỷ lệ chốt GG (%)</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[24]['data_two'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[24]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[24]['data_four'] }}</td>
    </tr>
    <tr>
        <td colspan="2" style="font-weight: bold; border: 1px solid #333333; width: 300px; text-align: center" >Tỷ lệ chốt FB (%)</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[25]['data_two'] }}</td>
        <td style="font-weight: bold; border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[25]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 500px; text-align: center" >{{ $dataReportOrderSale[25]['data_four'] }}</td>
    </tr>
</table>

</body>
</html>


