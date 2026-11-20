<!DOCTYPE html>
<html lang="en">
<head>
</head>
<body>
<table id="customers">
    <tr class="font-weight-bold">
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center" colspan="2">{{ $data[0]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center" colspan="6">{{ $data[0]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center" colspan="6">{{ $data[0]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center">{{ $data[0]['data_four'] }}</td>
    </tr>
    <!--        Tong KH  -->
    <tr style="background-color: yellow">
        <td style="border: 1px solid #333333; width: 200px; text-align: center; background: yellow" colspan="2">{{ $data[1]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center; background: yellow" colspan="6">{{ $data[1]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center; background: yellow" colspan="6">{{ $data[1]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center; background: yellow">{{ $data[1]['data_four'] }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" colspan="2">{{ $data[2]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" colspan="6">{{ $data[2]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" colspan="6">{{ $data[2]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[2]['data_four'] }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" colspan="2">{{ $data[3]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" colspan="6">{{ $data[3]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" colspan="6">{{ $data[3]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[3]['data_four'] }}</td>
    </tr>
    <tr>
        <td style="text-align: center; border: 1px solid #333333" colspan="2"></td>
        <td style="text-align: center; border: 1px solid #333333">Tổng fb+gg</td>
        <td style="text-align: center; border: 1px solid #333333">Tỷ lệ (fb+gg)</td>
        <td style="background-color: #00A962; text-align: center; border: 1px solid #333333">KH FB + Zalo OA</td>
        <td style="text-align: center; border: 1px solid #333333">Tỷ lệ</td>
        <td style="background-color: #DF4747; text-align: center; border: 1px solid #333333">KH google</td>
        <td style="text-align: center; border: 1px solid #333333">Tỷ lệ</td>
        <td style="text-align: center; border: 1px solid #333333">Tổng fb+gg</td>
        <td style="text-align: center; border: 1px solid #333333">Tỷ lệ (fb+gg)</td>
        <td style="background-color: #00A962; text-align: center; border: 1px solid #333333">KH FB + Zalo OA</td>
        <td style="text-align: center; border: 1px solid #333333">Tỷ lệ</td>
        <td style="background-color: #DF4747; text-align: center; border: 1px solid #333333">KH google</td>
        <td style="text-align: center; border: 1px solid #333333">Tỷ lệ</td>
        <td style="text-align: center; border: 1px solid #333333"></td>
    </tr>
    <!-- "Tổng KH tương tác được" -->
    <tr class="font-weight-bold" style="background-color: orange">
        <td style="border: 1px solid #333333; width: 200px; background: orange; text-align: center" colspan="2">{{ $data[4]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: orange; text-align: center">{{ $data[4]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: orange; text-align: center">{{ $data[4]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: orange; text-align: center">{{ $data[4]['data_four'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: orange; text-align: center">{{ $data[4]['data_five'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: orange; text-align: center">{{ $data[4]['data_six'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: orange; text-align: center">{{ $data[4]['data_seven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: orange; text-align: center">{{ $data[4]['data_eight'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: orange; text-align: center">{{ $data[4]['data_nine'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: orange; text-align: center">{{ $data[4]['data_ten'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: orange; text-align: center">{{ $data[4]['data_eleven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: orange; text-align: center">{{ $data[4]['data_twelve'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: orange; text-align: center">{{ $data[4]['data_thirteen'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: orange; text-align: center">{{ $data[4]['data_fourteen'] }}</td>
    </tr>
    <!-- "KH không có nhu cầu" -->
    <tr>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" colspan="2">{{ $data[5]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" rowspan="7"></td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" rowspan="7"></td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[5]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[5]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[5]['data_four'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[5]['data_five'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" rowspan="7"></td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" rowspan="7"></td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[5]['data_six'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[5]['data_seven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[5]['data_eight'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[5]['data_nine'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" rowspan="7"></td>
    </tr>
    <!-- "KH Tham khảo chưa có nhu cầu ngay" -->
    <tr>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" colspan="2">{{ $data[6]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[6]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[6]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[6]['data_four'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[6]['data_five'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[6]['data_six'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[6]['data_seven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[6]['data_eight'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[6]['data_nine'] }}</td>
    </tr>
    <!-- "KH có nhu cầu nhưng mặt hàng không nhập đươc" -->
    <tr>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" colspan="2">{{ $data[7]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[7]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[7]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[7]['data_four'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[7]['data_five'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[7]['data_six'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[7]['data_seven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[7]['data_eight'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[7]['data_nine'] }}</td>
    </tr>
    <!-- "KH tư vấn ký gửi" -->
    <tr>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" colspan="2">{{ $data[8]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[8]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[8]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[8]['data_four'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[8]['data_five'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[8]['data_six'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[8]['data_seven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[8]['data_eight'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[8]['data_nine'] }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #333333; width: 200px; text-align: center; vertical-align: center" rowspan="2">KH đã lên đơn</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[9]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[9]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[9]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[9]['data_four'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[9]['data_five'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[9]['data_six'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[9]['data_seven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[9]['data_eight'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[9]['data_nine'] }}</td>
    </tr>
    <tr>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[10]['data_one']}}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[10]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[10]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[10]['data_four'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[10]['data_five'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[10]['data_six'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[10]['data_seven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[10]['data_eight'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[10]['data_nine'] }}</td>
    </tr>
    <!--"KH đang follow lên đơn"-->
    <tr>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" colspan="2">{{ $data[11]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[11]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[11]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[11]['data_four'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[11]['data_five'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[11]['data_six'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[11]['data_seven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[11]['data_eight'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[11]['data_nine'] }}</td>
    </tr>
    <!-- Tổng KH không tương tác được  -->
    <tr class="font-weight-bold" style="background-color: #3F92D1">
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center" colspan="2">{{ $data[12]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center">{{ $data[12]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center">{{ $data[12]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center">{{ $data[12]['data_four'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center">{{ $data[12]['data_five'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center">{{ $data[12]['data_six'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center">{{ $data[12]['data_seven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center">{{ $data[12]['data_eight'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center">{{ $data[12]['data_nine'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center">{{ $data[12]['data_ten'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center">{{ $data[12]['data_eleven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center">{{ $data[12]['data_twelve'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center">{{ $data[12]['data_thirteen'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; font-weight: bold; text-align: center">{{ $data[12]['data_fourteen'] }}</td>
    </tr>
    <!--    KH nhắn tin không phản hồi  -->
    <tr>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" colspan="2">{{ $data[13]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" rowspan="2"></td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" rowspan="2"></td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[13]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[13]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[13]['data_four'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[13]['data_five'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" rowspan="2"></td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" rowspan="2"></td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[13]['data_six'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[13]['data_seven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[13]['data_eight'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[13]['data_nine'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" rowspan="2"></td>
    </tr>
    <!--    KH gọi không nghe máy  -->
    <tr>
        <td style="border: 1px solid #333333; width: 200px; text-align: center" colspan="2">{{ $data[14]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[14]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[14]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[14]['data_four'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[14]['data_five'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[14]['data_six'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[14]['data_seven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[14]['data_eight'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; text-align: center">{{ $data[14]['data_nine'] }}</td>
    </tr>
    <!--      KH kinh doanh ...   -->
    <tr style="background-color: #26b01c">
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c; text-align: center" colspan="2">{{ $data[15]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c; text-align: center">{{ $data[15]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c; text-align: center">{{ $data[15]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c; text-align: center">{{ $data[15]['data_four'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c; text-align: center">{{ $data[15]['data_five'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c; text-align: center">{{ $data[15]['data_six'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c; text-align: center">{{ $data[15]['data_seven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c; text-align: center">{{ $data[15]['data_eight'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c; text-align: center">{{ $data[15]['data_nine'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c; text-align: center">{{ $data[15]['data_ten'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c; text-align: center">{{ $data[15]['data_eleven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c; text-align: center">{{ $data[15]['data_twelve'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c; text-align: center">{{ $data[15]['data_thirteen'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c; text-align: center">{{ $data[15]['data_fourteen'] }}</td>
    </tr>
    <!--      KH Tieu dung   -->
    <tr style="background-color: #26b01c">
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c;text-align: center" colspan="2">{{ $data[16]['data_one'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c;text-align: center">{{ $data[16]['data_two'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c;text-align: center">{{ $data[16]['data_three'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c;text-align: center">{{ $data[16]['data_four'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c;text-align: center">{{ $data[16]['data_five'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c;text-align: center">{{ $data[16]['data_six'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c;text-align: center">{{ $data[16]['data_seven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c;text-align: center">{{ $data[16]['data_eight'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c;text-align: center">{{ $data[16]['data_nine'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c;text-align: center">{{ $data[16]['data_ten'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c;text-align: center">{{ $data[16]['data_eleven'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c;text-align: center">{{ $data[16]['data_twelve'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c;text-align: center">{{ $data[16]['data_thirteen'] }}</td>
        <td style="border: 1px solid #333333; width: 200px; background: #26b01c;text-align: center">{{ $data[16]['data_fourteen'] }}</td>
    </tr>
</table>
</body>
</html>
