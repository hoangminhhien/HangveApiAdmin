<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <style>
        * {
            font-family: Arial;
            font-size: 10pt;
        }

        a.NoteRef {
            text-decoration: none;
        }

        hr {
            height: 1px;
            padding: 0;
            margin: 1em 0;
            border: 0;
            border-top: 1px solid #CCC;
        }

        table {
            border: 1px solid black;
            border-spacing: 0px;
            width: 100%;
        }

        td {
            border: 1px solid black;
        }
    </style>
</head>
<body>
<div style="text-align: center;text-transform: uppercase;font-size: 14px;font-weight: bold;height: 25px;vertical-align: middle">
    PHIẾU XUẤT KHO KIÊM BIÊN BẢN BÀN GIAO
</div>
<div style="text-align: center;text-transform: uppercase;font-size: 14px;font-weight: bold;height: 25px;vertical-align: middle">
    Mã phiếu: {{ $print_receipt_code ?? '' }}
    <div id="warehouse-pack-data" data-id="{{ $print_receipt_code }}"></div>
</div>
<div style="text-align: center;font-size: 12px;vertical-align: middle">
    Ngày {{ date('d') }} tháng {{ date('m') }} năm {{ date('Y') }}
</div>
<div style="font-weight: bold; text-align: left;font-size: 12px">
    Người nhận hàng: {{data_get($customer, 'name')}}
</div>
<div style="font-weight: bold; text-align: left;font-size: 12px;">
    Số điện thoại: {{data_get($customer, 'phone_number')}}
</div>
<div style="font-weight: bold; text-align: left;font-size: 12px; margin-bottom: 10px">
    Xuất tại kho: {{data_get($warehouse, 'address')}}
</div>
<table style="font-family: Roboto, sans-serif">
    <tr>
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            STT
        </th>
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Mã đơn hàng
        </th>
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Mã vận đơn mới
        </th>
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Mã vận đơn gốc
        </th>
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Số kiện
        </th>
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Trọng lượng/ Thể tích
        </th>
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Đơn Giá
        </th>
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Phí VCQT
        </th>
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Phí đóng gỗ
        </th>
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Phí chống sốc
        </th>
        {{-- <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Phí lưu kho
        </th> --}}
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Chiết khấu
        </th>
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Tổng tiền
        </th>
    </tr>
    @php
        $totalPackAmount = 0;
    @endphp
    @foreach($packages as $key => $package)
        @php
            $unit = $package['type_quantitative'] == 'weight' ? $package['weight'] : $package['volume'];
            $totalPackAmount += $package['amount'];
        @endphp
        <tr>
            <td style="text-align: center;border:1px solid #333;height:25px"> {{++$key}} </td>
            <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;"> {{ $package['order_code'] }} </td>
            <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;"> {{ $package['code_warehouse'] }} </td>
            <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;"> {{ $package['bill_code'] }} </td>
            <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;">{{  $package['package_number']  }}</td>
            @if($package['type_quantitative'] == 'weight')
                <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;">{{ $package['weight'] }} Kg</td>
            @else
                @if($package['thuongdo_id'])
                    <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;">{{ $package['thuongdo_volumn'] }} M3</td>
                @else
                    <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;">{{ $package['volume'] }} M3</td>
                @endif
            @endif

            @if($package['unit_price'])
                <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;"> {{ \App\Helpers\ConvertHelper::numericToVND(\App\Helpers\AccountingHelper::getCosts($package['unit_price']))}} </td>
            @elseif($package['weight'] == 0 && $package['volume'] == 0)
                <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;"> {{ \App\Helpers\ConvertHelper::numericToVND(\App\Helpers\AccountingHelper::getCosts($package['international_shipping_cost']))}} </td>
            @else
                <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;"> {{ \App\Helpers\ConvertHelper::numericToVND(\App\Helpers\AccountingHelper::getCosts($unit ? $package['international_shipping_cost']/$unit : $package['international_shipping_cost']))}} </td>
            @endif
            <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;"> {{ \App\Helpers\ConvertHelper::numericToVND($package['international_shipping_cost']) }}</td>
            <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;"> {{ \App\Helpers\ConvertHelper::numericToVND($package['woodworking_cost']) }}</td>
            <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;"> {{ \App\Helpers\ConvertHelper::numericToVND($package['shock_proof_cost']) }}</td>
            {{-- <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;"> {{ \App\Helpers\ConvertHelper::numericToVND($package['storage_cost']) }}</td> --}}
            <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;"> {{ \App\Helpers\ConvertHelper::numericToVND($package['discount_cost']) }}</td>
            <td style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;"> {{ \App\Helpers\ConvertHelper::numericToVND($package['amount']) }}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="11" style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;font-weight: bold">
            Tổng tiền vận chuyển
        </td>
        <td colspan="1" style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;"> {{ \App\Helpers\ConvertHelper::numericToVND($totalPackAmount) }} </td>
    </tr>
    <tr>
        <td colspan="12" style="font-weight: bold">Thông tin đơn hàng</td>
    </tr>
    <tr>
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            STT
        </th>
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Mã đơn hàng
        </th>
        <th colspan="7" style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Tổng tiền
        </th>
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Đã thanh toán
        </th>
        <th style=" text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            Còn thiếu
        </th>
    </tr>
    @php
        $totalOrder = 0;
    @endphp
    @foreach($orders as $key => $order)
        @php
            $totalAmount = (data_get($order, 'thuongdo_id') && data_get($order, 'has_edit') == 0) ? data_get($order, 'thuongdo_tong_tien_don_hang') : data_get($order, 'total_amount');
            $totalAmount = round($totalAmount);
            $depositCost = data_get($order, 'deposit_cost', 0);
            if(data_get($order, 'delivery_pay_id') > 0) {
                $depositCost += data_get($order, 'delivery_pay_debt_cost', 0);
            }
            $totalOrder += $totalAmount - $depositCost;
        @endphp
        <tr>
            <td style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
                {{ ++$key }}
            </td>
            <td style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
                {{ $order['code'] }}
            </td>
            <td colspan="7"
                style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
            </td>
            <td style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
                {{ \App\Helpers\ConvertHelper::numericToVND($totalAmount) }}
            </td>
            <td style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
                {{ \App\Helpers\ConvertHelper::numericToVND($depositCost) }}
            </td>
            <td style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">
                {{ \App\Helpers\ConvertHelper::numericToVND($totalAmount - $depositCost) }}
            </td>
        </tr>
    @endforeach
    <tr>
        <td colspan="11" style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;font-weight: bold;">
            Tổng tiền đơn hàng còn thiếu
        </td>
        <td colspan="1" style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;"> {{ \App\Helpers\ConvertHelper::numericToVND($totalOrder) }} </td>
    </tr>
    <tr style="background-color: #78bbfa">
        <td colspan="11" style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;font-weight: bold;">
            Tổng tiền cần thanh toán (Đơn hàng+ Phí VCQT)
        </td>
        <td colspan="1" style="border:1px solid #333;height:25px;vertical-align: middle;text-align:center;"> {{ \App\Helpers\ConvertHelper::numericToVND($totalOrder + $totalPackAmount) }} </td>
    </tr>

</table>
<table style="border: none; padding-top: 15px; border: none !important">
    <tr style="border: none !important">
        <td colspan="11" style="border:none ;height:25px;vertical-align: middle;font-size: 10px; text-align: center; border: none; font-style: italic">
            (Bằng chữ: {{ numberInVietnameseCurrency($totalOrder + $totalPackAmount) }})
        </td>
    </tr>
    <tr>
        <td colspan="5" style="border: none; height: 50px;">
            <div>Hiện trạng hàng hóa: Nguyên kiện</div>
        </td>
        <td colspan="5" style="border: none; height: 50px;">
            <div>Hình thức vận chuyển:
                @if(isset($type) && $type == 'pack_delivery')
                    Giao hàng
                @else
                    Tại kho
                @endif
            </div>
        </td>
    </tr>
    <tr style="margin: 10px 10px">
        <td colspan="5" style="text-align: center; border: none">
            <div style="font-weight: bold;">Người lập phiếu</div>
            <div style="margin-top: 20px">{{$userName}}</div>
        </td>
        <td colspan="5" style="text-align: center; border: none">
            <div style="font-weight: bold;">Người nhận hàng</div>
            <div style="margin-top: 20px">{{data_get($customer, 'name')}}</div>
        </td>
    </tr>
</table>
<div style="font-weight: bold; text-align: left;font-size: 12px; margin-top: 20px; font-style: italic;">
    Chú ý: Quý khách vui lòng kiểm tra lại kiện hàng ngày sau khi nhận hàng và quay lại video & chụp ảnh mã vận đơn trong quá trình mở kiểm tra hàng hoá ( Trường hợp thiếu, hỏng Thương Đô có đầy đủ căn cứ để khiếu nại đến nhà cung cấp cho quý khách hàng ), chúng tôi chỉ tiếp nhận khiếu nại trong vòng 24h qua website thuongdo.com, Zalo, Facebook hoặc Hotline: 19006825 / 0898169666. Xin chân thành cảm ơn!
</div>
</body>

</html>
