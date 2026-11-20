<h1 align="center">Thương Đô</h1>

## Thông tin chung

Dự án đặt hàng của Thương Đô trên sàn thương mại điện tử Trung Quốc như 1688, taobao...

### Framework
 Laravel version 8

### Packages

* [Laravel Permissions](https://spatie.be/docs/laravel-permission/v5/introduction)
* [Laravel Telescope](https://laravel.com/docs/8.x/telescope)

## Các bước cài đặt

### Chạy bằng Docker (local)

Xem [hướng dẫn Docker](documents/DOCKER.md) để khởi động API, MySQL, Redis,
nhập database và bật queue/scheduler.

### Khởi tạo

1. Sử dụng composer cài đặt các packages
    ```sh
    composer install
    ```
2. Khởi tạo dự án
    ```sh
    php artisan admin:init
    ```

Test Merge Code
php artisan queue:work --queue=sent-noti-admin --tries=3
..
Tính năng in  phiếu xuất kho trong màn trả hàng cần tạo thư mục demo trong public

Fix
Test:
Test đồng bộ:

Demo 1 2

supplier: 

Local:LocalLocalLocalLocalLocalLocalLocalLocal

order:986
order_status_time:985
order_suplier:1012
supplier: 357.380
order_detail: 1.173
order_pack: 941
order_pack_status_time: 1765
order_detail_pack: 524


Sau khi reset:

order:911
order_status_time:910
order_suplier:935
supplier: 357.309
order_detail: 1.048
order_pack: 873
order_pack_status_time: 1765
order_detail_pack: 524


Check V1:

select `id`, `deposit_percent`, `code`, `thuongdo_id`, `deposit_cost`, `delivery_pay_debt_cost`,`order_cost` , `order_fee` , `inspection_cost` , `china_shipping_cost` , `exchange_rate` , `discount_cost`,
(`order_cost` + `order_fee` + `inspection_cost` + `china_shipping_cost` * `exchange_rate` + `discount_cost`) as total_order, 
(`deposit_cost` + `delivery_pay_debt_cost`) as total_pay 
from orders where status = 'status_4' AND `remaining_amount` = 0 AND (`deposit_cost` + `delivery_pay_debt_cost`) > (order_cost + order_fee + inspection_cost + china_shipping_cost * exchange_rate + discount_cost) and date_done >= '2024-10-03 00:00:00' and thuongdo_id is not null;


// Xử lý
$chenh = $total_pay - $total_al;
if($chenh > 0 & $check <= 1000) {
    1. Nếu tổng tiền thanh toán lớn hơn hoặc bằng 1000đ so với đơn hàng thì cập nhật số tiền đó vào phần tiền hàng của khách
    $order_cost = $order_cost + $chenh;    
} elseif($chenh > 1000) {
    2. Nếu tổng tiền thanh toán lớn hơn hoặc bằng 1000đ so với đơn hàng thì hoàn tiền cho khách
    $ 
}
3. Xóa TD_id,
4. Check % đặt cọc
Hoàn 100đ cho đơn hàng mã avc vì thay đổi đơn hàng



































FIX BUG LIVE:


Ngày 24-10-2024:

Kiện lỗi lệch -1000 đến 1000

17,79,83,104,129,138,144,148,159,165,176,179,203,206,209,210,244,251,262,264,270,275,282,286,300,322,323,365,368,421,425,450,472,480,494,502,511,513,516,517,569,633,638,651,654,658,677,679,691,713,715,716,719,722,730,741,742,747,768,794,809,869,877,907,959,990,1008,1042,1076,1122,1144,1148,1152,1166,1177,1179,1209,1221,1232,1240,1283,1286,1288,1290,1302,1332,1337,1365,1393,1422,1424,1436,1438,1451,1453,1471,1507,1521,1553,1581,1586,1631,1636,1637,1673,1684,1689,1699,1709,1712,1716,1759,1779,1790,1815,1821,1829,1834,1861,1877,1881,1906,1928,1965,1968,1992,1993,2004,2012,2020,2024,2028,2033,2039,2080,2089,2093,2100,2103,2109,2111,2121,2124,2128,2132,2133,2167,2169,2174,2196,2213,2223,2227,2232,2234,2254,2260,2264,2291,2311,2322,2330,2351,2357,2358,2370,2389,2410,2420,2456,2460,2482,2515,2538,2612,2620,2632,2656,2660,2663,2682,2693,2726,2734,2739,2746,2748,2753,2755,2757,2785,2787,2876,2928,2938,2947,2954,2956,2963,2964,2993,2998,3016,3023,3029,3060,3065,3087,3092,3134,3147,3154,3156,3176,3188,3196,3203,3212,3216,3220,3221,3247,3254,3306,3307,3353,3359,3361,3371,3378,3425,3428,3433,3446,3466,3470,3474,3520,3524,3543,3544,3547,3556,3576,3591,3592,3604,3618,3639,3660,3662,3671,3677,3693,3699,3703,3724,3752,3771,3796,3800,3805,3890,3927,3930,3945,3947,3953,3963,3986,4106,4166,4170,4187,4196,4236,4262,4283,4290,4299,4304,4318,4340,4348,4357,4363,4394,4396,4398,4407,4422,4430,4435,4479,4480,4482,4486,4538,4542,4553,4600,4619,4620,4639,4702,4711,4818,4882,4899,4974,4987,5072,5153,5168,5249,5283,5301,5348,5388,5448,5613,5757,5842,5843,5931,6040,6086,6165,6190,6259,6410,6416,6507,6705,6710,6797,6978,6982,7034,7043,7060,7246,7445,7528,7671,7727,7952,8014,8032,8274,8296,8553,8669,8772,8976,9011,9186,9519,9678,10022,10556,10723,10769,10775,11244,11253,11300,11531,12061,13736,14131,15494,17284,20472,21739,24012,32404,35660,35763,149710,194452,194464,443242,443259,443307,443372,443387,443388,443405,443426,443458,443492,443498,443515,443522,443530,443562,443570,443580,443602,443623,443641,443651,443655,443665,443667,443677,443679,443700,443707,443753,443756,443775,443789,443790,443804,443831,443837,443852,443855,443866,443903,443919,443920,443945,443946,443960,443990,444008,444009,444021,444025,444038,444051,444106,444108,444126,444138,444147,444181,444296,444299,444309,444320,444419,444490,444496,444513,444537


Sau khi cập nhật 202 mã có TD_iD lại:

    production  2024-10-24 06:47:28 Hoàn 19.036đ cho đơn hàng mã 2114623664 vì thay đổi đơn hàng
  info  production  2024-10-24 06:47:28 Hoàn 345.031đ cho đơn hàng mã 0474300279 vì thay đổi đơn hàng
  info  production  2024-10-24 06:47:28 Hoàn 1.154.257đ cho đơn hàng mã 0474320222 vì thay đổi đơn hàng
  info  production  2024-10-24 06:47:28 Hoàn 2.158.776đ cho đơn hàng mã 3156880017 vì thay đổi đơn hàng
  info  production  2024-10-24 06:47:28 Hoàn 32.000đ cho đơn hàng mã 2826460809 vì thay đổi đơn hàng
  info  production  2024-10-24 06:47:28 Hoàn 36.352đ cho đơn hàng mã 0713731340 vì thay đổi đơn hàng
  info  production  2024-10-24 06:47:28 Hoàn 48.374đ cho đơn hàng mã 2114623647 vì thay đổi đơn hàng
  info  production  2024-10-24 06:47:28 Hoàn 16.572đ cho đơn hàng mã 2114623651 vì thay đổi đơn hàng
  info  production  2024-10-24 06:47:28 Hoàn 24.232đ cho đơn hàng mã 2114623653 vì thay đổi đơn hàng
  info  production  2024-10-24 06:47:28 Hoàn 44.824đ cho đơn hàng mã 2114623656 vì thay đổi đơn hàng
  info  production  2024-10-24 06:47:28 Hoàn 53.467đ cho đơn hàng mã 2114623657 vì thay đổi đơn hàng
  info  production  2024-10-24 06:47:28 Hoàn 20.471đ cho đơn hàng mã 2114623659 vì thay đổi đơn hàng
  info  production  2024-10-24 06:47:28 Hoàn 508.627đ cho đơn hàng mã 1158720078 vì thay đổi đơn hàng


  28,70,116,180,216,221,223,231,234,245,248,255,295,317,338,340,426,436,440,441,452,492,498,507,522,534,557,579,597,608,626,636,642,659,690,698,709,784,1001,1010,1012,1041,1046,1659,1784,1824,1947,1964,2185,2209,2375,2640,3443,4085,4807,5397,6755,443248,443252,443282,443337,443355,443362,443403,443423,443636,443638,443781,443846,443921,443997,444157,444213,444321,444334,444353,444360,444429,444437






















































- Cập nhật check trùng mã vận đơn theo logic mới
- 



















































Kiện lỗi update trạng thái
847869,
847126,
846670,
845824,
847456,
846846,
846652,
847463,
848089,
846474





fixDuplicateCodeCustomer220425 : [[{"id":14131,"code":"018529"},{"id":11707,"code":"018529"}],[{"id":14339,"code":"018925"},{"id":13444,"code":"018925"}],[{"id":13945,"code":"019303"},{"id":13740,"code":"019303"}],[{"id":46519,"code":"071646"},{"id":36396,"code":"071646"}],[{"id":78578,"code":"179206"},{"id":78378,"code":"179206"}],[{"id":103977,"code":"204440"},{"id":103969,"code":"204440"}],[{"id":110457,"code":"210760"},{"id":110453,"code":"210760"}],[{"id":132745,"code":"232985"},{"id":132739,"code":"232985"}],[{"id":183908,"code":"284687"},{"id":183899,"code":"284687"}],[{"id":198422,"code":"299371"},{"id":198414,"code":"299371"}],[{"id":224990,"code":"5M123"},{"id":224988,"code":"5M123"},{"id":200686,"code":"5M123"}],[{"id":204595,"code":"305404"},{"id":204586,"code":"305404"}],[{"id":223544,"code":"3970J"},{"id":219160,"code":"3970J"}],[{"id":224764,"code":"J8635"},{"id":219168,"code":"J8635"}],[{"id":223699,"code":"84J13"},{"id":219525,"code":"84J13"}],[{"id":223634,"code":"6199C"},{"id":220290,"code":"6199C"}],[{"id":223749,"code":"H7416"},{"id":220485,"code":"H7416"}],[{"id":224261,"code":"789K8"},{"id":220568,"code":"789K8"}],[{"id":224755,"code":"08D40"},{"id":220784,"code":"08D40"}],[{"id":223940,"code":"05A41"},{"id":221162,"code":"05A41"}],[{"id":225383,"code":"36V14"},{"id":221216,"code":"36V14"}],[{"id":222718,"code":"1U029"},{"id":221431,"code":"1U029"}],[{"id":225223,"code":"1S952"},{"id":222035,"code":"1S952"}],[{"id":224063,"code":"803U3"},{"id":222084,"code":"803U3"}],[{"id":225814,"code":"0135F"},{"id":222950,"code":"0135F"}],[{"id":224524,"code":"S4816"},{"id":223692,"code":"S4816"}],[{"id":225784,"code":"2383Z"},{"id":224332,"code":"2383Z"}],[{"id":225948,"code":"9X129"},{"id":224517,"code":"9X129"}],[{"id":225571,"code":"5D404"},{"id":225273,"code":"5D404"}],[{"id":226313,"code":"428R2"},{"id":225543,"code":"428R2"}]]


arr_reCode : ["Đổi cho: 018529:14131","Đổi cho: 018925:14339","Đổi cho: 019303:13945","Đổi cho: 071646:46519","Đổi cho: 179206:78578","Đổi cho: 204440:103977","Đổi cho: 210760:110457","Đổi cho: 232985:132745","Đổi cho: 284687:183908","Đổi cho: 299371:198414","Đổi cho: 5M123:224990","Đổi cho: 305404:204595","Đổi cho: 3970J:223544","Đổi cho: J8635:224764","Đổi cho: 84J13:223699","Đổi cho: 6199C:220290","Đổi cho: H7416:223749","Đổi cho: 789K8:220568","Đổi cho: 08D40:224755","Đổi cho: 05A41:223940","Đổi cho: 36V14:225383","Đổi cho: 1U029:222718","Đổi cho: 1S952:222035","Đổi cho: 803U3:224063","Đổi cho: S4816:224524","Đổi cho: 2383Z:225784","Đổi cho: 9X129:225948","Đổi cho: 5D404:225273","Đổi cho: 428R2:226313"]



Đổi khách 222950: 0135F => 0135C


fixDuplicateCodeCustomer220425 : [[{"id":224988,"code":"5M123"},{"id":200686,"code":"5M123"}]]
arr_reCode : ["Đổi cho: 5M123:224988"]


















Hiển làm
Count: 1028  Time=24.39s (25073s)  Lock=0.00s (0s)  Rows_sent=1.0 (1028), Rows_examined=62444050.7 (64192484078), Rows_affected=0.0 (0), root[root]@localhost
  select count(id) as total_all,
  sum(if(status = N, N, N)) as status_pending_approval,
  sum(if(status = N, N, N)) as status_checked_policy,
  sum(if(status = N, N, N)) as status_customer_approval,
  sum(if(status = N, N, N)) as status_account_approval,
  sum(if(status = N, N, N)) as status_agent_approval,
  sum(if(status = N, N, N)) as status_reconfirm,
  sum(if(status = N, N, N)) as status_cancel,
  sum(if(status = N, N, N)) as status_cleared_custom from `quotes` where exists (select * from `users` where `quotes`.`user_id` = `users`.`id` and `company_id` = N and `users`.`deleted_at` is null) and exists (select * from `quote_cleans` where `quotes`.`id` = `quote_cleans`.`quote_id` and exists (select * from `quote_clean_packs` where `quote_cleans`.`id` = `quote_clean_packs`.`quote_clean_id` and `hangve_pack_bill_code` = 'S')) limit N

A Tân:
Count: 154  Time=11.70s (1801s)  Lock=0.00s (0s)  Rows_sent=1.0 (150), Rows_examined=1703424.3 (262327344), Rows_affected=0.0 (0), root[root]@localhost
    select * from `items` where `num_iid` = N and `source` = 'S' limit N

Count: 748  Time=1.61s (1201s)  Lock=0.00s (0s)  Rows_sent=0.2 (120), Rows_examined=1772703.4 (1325982168), Rows_affected=0.0 (0), root[root]@localhost
  select * from `items` where `detail_url` = 'S' and (`title` = 'S' or `title_vi` = 'S') order by `items`.`created_at` desc limit N

Count: 506  Time=2.20s (1111s)  Lock=0.00s (0s)  Rows_sent=5.0 (2530), Rows_examined=1870596.4 (946521783), Rows_affected=0.0 (0), root[root]@localhost
  select `id`, `key_search`, `source`, `num_iid`, `pic`, `title`, `title_vi`, `price`, `promotion_price`, `sales`, `seller_id`, `seller_nick`, `seller_nick_vi`, `detail_url` from `items` where `source` = 'S' and `seller_id` is not null order by RAND(), `items`.`created_at` desc limit N





































Count: 975  Time=8.90s (5755s)  Lock=0.00s (0s)  Rows_sent=18.0 (17584), Rows_examined=2069676.5 (2017934539), Rows_affected=0.0 (0), root[root]@localhost
  select COUNT(DISTINCT order_package.id) as quantity, order_package.status as status from order_package where order_package.deleted_at is null group by order_package.status order by order_package.id desc

===========================> Từ 9s về 1s



Count: 2006  Time=3.62s (3255s)  Lock=0.00s (0s)  Rows_sent=2.6 (5159), Rows_examined=2637778.7 (5291384025), Rows_affected=0.0 (0), root[root]@localhost
  select count() as count, `group` from `notifications` where `user_id` = 'S' and `time_seen` = N group by `group`

===========================> Tối ưu từ 4s về 0.5s



Count: 1479  Time=3.70s (2508s)  Lock=0.00s (0s)  Rows_sent=0.2 (276), Rows_examined=2637862.1 (3901398110), Rows_affected=0.0 (0), root[root]@localhost
  select count() as count, `group` from `notifications` where (`customer_id` = N or `group` = N) and `time_seen` = N and `organization_id` = N and `created_at` >= 'S' group by `group`

===========================> Tối ưu từ 4s về 0.5s


Count: 202  Time=3.35s (676s)  Lock=0.00s (0s)  Rows_sent=0.0 (0), Rows_examined=1119911.7 (226222162), Rows_affected=0.0 (0), root[root]@localhost
  select * from `order_package` where `is_lost` = N and `status` = 'S' and `order_package`.`deleted_at` is null order by `order_package`.`id` desc

===========================> Tối ưu từ 3s về .... 1s


Count: 53  Time=3.14s (166s)  Lock=0.00s (0s)  Rows_sent=192.4 (10199), Rows_examined=12723627.0 (674352231), Rows_affected=0.0 (0), root[root]@localhost
  select `carts`.*, (select count(*) from `cart_details` where `carts`.`id` = `cart_details`.`cart_id`) as `products_count` from `carts` where `carts`.`customer_id` = N order by `carts`.`created_at` desc












Chỗ truy vấn để thực hiện nạp tiền của a Tân nhánh cũ:

$fundQuery = Fund::query()->where('bank_id', $bank->id);
if ($bank_number) {
    $fundQuery = $fundQuery->where('bank_number', $bank_number);
}
$fund = $fundQuery->first();























app/Http/Controllers/OrderController.php => Con Dev
public function changeStatus(OrderChangeStatusRequest $request, Order $order): JsonResponse
{
    try {
        $status = $request->status ?? '';
        // Check nếu đơn ở trạng thái Đã đặt cọc, trạng thái mới
        $arr_status_new = [
            OrderConstant::KEY_STATUS_WAIT_TO_PAY,
            OrderConstant::KEY_STATUS_CANCEL,
        ];
        if ($order->status == Order::ORDER_STATUS_DEPOSITED && !in_array($status, $arr_status_new)) {
            return resError('Không đổi được do trạng thái ko hợp lệ', 422);
        }

        if ($order->has_edit == 2 && in_array($order->status, [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY])) {
            return resError('Đơn hàng đã có sự thay đổi, vui lòng bấm lưu', 422);
        }
        $status = $request->status ?? '';
        if (
            in_array($order->status,
                [
                    OrderConstant::KEY_STATUS_DEPOSITED,
                    OrderConstant::KEY_STATUS_ORDERED,
                    OrderConstant::KEY_STATUS_WAIT_TO_PAY
                ])
        ) {
            $totalAmount = ($order->thuongdo_id != null && $order->has_edit == 0) ?
                $order->thuongdo_tong_tien_don_hang : $order->getTotalAmountNotShip();
            $depositCostReality = roundMoney($totalAmount * $order->deposit_percent / 100); // số tiền cần đặt cọc thực thế
            if ($depositCostReality - 5 > $order->deposit_cost) {
                return resError( 'Không được thay đổi trạng thái đơn vì số tiền đặt cọc bị chênh lệch', 422);
            }
        }
        $statusNew = $status;
        $statusOld = $order->status;
        $config = env('SEND_SMS_ORDER_PACKAGE', '');
        if ($statusNew && $statusNew != $statusOld && $config == 'PRODUCT') {
            $payload['user_id'] = $order->customer_id ?? 0;
            $fcmTokenService = app()->make(FcmTokenService::class);
            $title = 'Thay đổi trạng thái đơn hàng';
            $body = 'Đơn hàng ' . $order->code . ' được chuyển sang trạng thái ' . Order::LIST_STATUS[$statusNew] ?? '';
            $dataSent = [
                'type' => 'orders',
                'body' => (string) $order->id
            ];
            $fcmTokenService->sendMessageForApp($payload, $title, $body, $dataSent);
        }
        $this->_service->changeStatus($order, $status);
        return resSuccess();
    } catch (\Exception  $exception) {
        Log::info(' changeStatus ' . $exception->getMessage() .' line ' . $exception->getLine() . ' file ' . $exception->getFile());
        return resError($exception->getMessage() .' line ' . $exception->getLine() . ' file ' . $exception->getFile());
    }

}



app/Http/Controllers/OrderController.php => Con Live
public function changeStatus(OrderChangeStatusRequest $request, Order $order): JsonResponse
{
    // Check nếu đơn ở trạng thái Đã đặt cọc, trạng thái mới
    $status = $request->status ?? '';
    $arr_status_new = [
        OrderConstant::KEY_STATUS_WAIT_TO_PAY,
        OrderConstant::KEY_STATUS_CANCEL,
    ];
    if ($order->status == Order::ORDER_STATUS_DEPOSITED && !in_array($status, $arr_status_new)) {
        return resError('Không đổi được do trạng thái ko hợp lệ', 422);
    }

    if ($order->has_edit == 2 && in_array($order->status, [OrderConstant::KEY_STATUS_DEPOSITED, OrderConstant::KEY_STATUS_WAIT_TO_PAY])) {
        return resError('Đơn hàng đã có sự thay đổi, vui lòng bấm lưu', 422);
    }
    $status = $request->status ?? '';
    if (
        in_array($order->status,
            [
                OrderConstant::KEY_STATUS_DEPOSITED,
                OrderConstant::KEY_STATUS_ORDERED,
                OrderConstant::KEY_STATUS_WAIT_TO_PAY
            ])
    ) {
        $totalAmount = ($order->thuongdo_id != null && $order->has_edit == 0) ?
            $order->thuongdo_tong_tien_don_hang : $order->getTotalAmountNotShip();
        $depositCostReality = roundMoney($totalAmount * $order->deposit_percent / 100); // số tiền cần đặt cọc thực thế
        if ($depositCostReality - 5 > $order->deposit_cost) {
            return resError( 'Không được thay đổi trạng thái đơn vì số tiền đặt cọc bị chênh lệch', 422);
        }
    }
    $statusNew = $status;
    $statusOld = $order->status;
    $config = env('SEND_SMS_ORDER_PACKAGE', '');
    if ($statusNew && $statusNew != $statusOld && $config == 'PRODUCT') {
        $payload['user_id'] = $order->customer_id ?? 0;
        $fcmTokenService = app()->make(FcmTokenService::class);
        $title = 'Thay đổi trạng thái đơn hàng';
        $body = 'Đơn hàng ' . $order->code . ' được chuyển sang trạng thái ' . Order::LIST_STATUS[$statusNew] ?? '';
        $dataSent = [
            'type' => 'orders',
            'body' => (string) $order->id
        ];
        $fcmTokenService->sendMessageForApp($payload, $title, $body, $dataSent);
    }
    return $this->_service->changeStatus($order, $status);
}
