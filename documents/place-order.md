# Quy trình đặt hàng dành cho dev

### Các file cần dùng
- Router: ```routes/modules/place_order.php```
- Controller: ```app/Http/Controllers/PlaceOrderController.php```
- Service: ```app/Services/PlaceOrderService.php```
- Action: ```app/Actions/PlaceOrder```

### Xem danh sách đặt hàng
Nhân viên vào tab 'Đặt hàng', ở đây sẽ hiển thị danh sách đặt hàng.

Mỗi block sẽ tương ứng với một order, trong mỗi block gồm 2 cột:
- Bên phải: Danh sách mọi order_detail (cũng chính là sản phẩm) thuộc order đó
- Bên trái: Danh sách mọi đơn đã đặt (order_buyder_subs) của mọi order_detail, gồm các đơn mình đặt & các đơn nhờ đặt hộ

Code flow
- URL: ```GET /place_order```
- Controller: 
- Method: ```PlaceOrderService@pagination```
- Action: ```PlaceOrder/PlaceOrderListAction.php```

### Nhờ người hỗ trợ
Các bước thực hiện:
- Tick chọn các order_detail trong cột bên phải
- Chọn 'Gán người hỗ trợ' trong select 'Tác vụ xử lý đơn hàng'
- Lúc này sẽ hiển thị form để xem thông tin -> chọn số lượng và các nhân viên mình nhờ
- Nghĩa là
  + 1 assign_support_order sẽ gần giống như 1 order_detail
  + 1 ```assign_support_order``` tương ứng 1 ```order_buyder```
  + 1 ```assign_support_order``` tương ứng 1 ```order_buyder_sub```

Code flow
- URL: ```GET /place_order/{order_detail_id}```, ```POST /place_order/assign-support```
- Controller: ```PlaceOrderController@getPlaceOrderDetail```, ```PlaceOrderController@assignSupport```
- Method: ```PlaceOrderService@pagination```
- Action: ```PlaceOrder/PlaceOrderListAction.php```

### Đặt hàng
- FE gửi danh sách gồm ```order_detail_id``` và ```quantity``` lên
- BE gộp các ```order_detail_id``` cùng ```order``` (groupBy order_id) rồi xử lý
- Gọi mỗi group là ```buyderInputGroup```, loop qua và gửi từng ```buyderInputGroup``` qua Buyder để đặt hàng
- Mỗi ```buyderInputGroup``` đặt hàng thành công sẽ tạo ra
  + 1 record trong ```order_buyder```
  + 1 hoặc nhiều record trong ```order_buyder_subs```
  + 1 record trong ```order_package```
- Nghĩa là
  + 1 ```order``` gồm mọi sản phẩm của 1 shop (ncc), mỗi sản phẩm đó sẽ được tách thành 1 ```order_detail```
  + 1 ```order``` tương ứng 1 hoặc nhiều ```order_buyder``` nếu tách các sản phẩm cùng 1 shop ra đặt hàng nhiều lần
  + 1 ```order``` tương ứng 1 hoặc nhiều ```order_buyder_sub```
  + 1 ```order_detail``` tương ứng 1 ```order_buyder```
  + 1 ```order_detail``` tương ứng 1 hoặc nhiều ```order_buyder_sub```
- Dữ liệu mẫu gửi qua Buyder khi đặt hàng
```
$buyderInputData = [
    "delay_time" => 100,
    "orderSource" => "hangve.com",
        "receiver" => [
        "zip" => "311121",
        "name" => "测试",
        "country" => "中国",
        "address" => "仓前街道欧美金融城2幢1103",
        "phone" => "18926668611",
        "mobile_phone" => "18720122943",
        "city" => "杭州市",
        "district" => "浙江省",
        "state" => "余杭区",
    ],
    "userId" => "2100005883094",
    "orderLineList" => [
        [
            "localOrderId" => "1ee9ed9d-b9f1-66a6-b26b-0242ac130003",
            "orderDetailId" => "1ee9ed9d-bb51-621c-b398-0242ac130003",
            "assignSupportId" => null,
            "itemId" => "2048008996585928",
            "skuId" => "184077815240",
            "orderLineNo" => 1,
            "quantity" => 1,
            "price" => 5.0,
            "currency" => "CNY",
        ],
    ],
    "purchaseAmount" => "500",
    "sellerOrderNumber" => "CC_XuEzmcZWLckU",
];
```
- Dữ liệu mẫu response từ Buyder khi đặt hàng
```
$createBuyder = [
    "code" => 200,
    "message" => "Thành công!",
    "data" => [
        "id" => "1054700825936396656",
        "orderNo" => "1054700825932202804",
        "subOrderVOS" => [
            [
                "subOrderId" => "1054700829874847752",
                "orderNo" => "1054700825932202804",
                "quantity" => 1,
                "title" => null,
                "purchaseCurrency" => null,
                "purchaseAmount" => null,
                "itemId" => "2048008996585928",
                "domesticPostFee" => null,
                "closeReason" => null,
                "skuId" => "184077815240",
                "status" => null,
                "taobaoPurchaseId" => "200034221176",
                "taobaoSubPurchaseId" => "200034221177",
                "errorCode" => null,
                "errorMessage" => null,
            ],
        ],
        "createTime" => "2023-12-20 10:04:32",
    ],
];
```

### Hủy đơn đặt hàng
- FE gửi danh sách ```order_buyder_subs.id``` lên
- Để hủy đơn, BE gửi các dữ liệu sau qua Buyder
  + ```order_buyders.buyder_id```
  + Mảng chứa các ```order_buyder_subs.buyder_sub_id```
  + Lý do hủy
  + userId
- Nếu hủy thành công thì


- Sử dụng makefile:
```bash
make generate
```
