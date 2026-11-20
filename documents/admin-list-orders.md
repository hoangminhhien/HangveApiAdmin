# WIP/ADMIN - TỐI ƯU MÀN ĐƠN HÀNG

### Cấu trúc code
- Code cũ chỉ dừng lại ở service khiến service OrderService bị phình rất to, không hợp lý.
- Hiện đang cấu trúc lại theo luồng controller -> service -> action, trong action có thể thêm event-listener nếu cần.
  Ưu tiên tạo trait các hàm dùng chung, và những gì sử dụng chung ở mọi action thì cho vào abstract class BaseAction. 

### Các việc cần làm
- [x] Lưu cache danh sách đơn hàng của admin và từng nhân viên, thực hiện trong ListOrderAction.getUpdatedData.
- [x] Chạy console command mỗi 5 phút để tự động update cache danh sách đơn hàng của Admin, thực hiện trong job
  UpdateAdminCacheOrderList.
- [ ] Chạy console command mỗi 5 phút để tự động update cache danh sách đơn hàng của nhân viên, có thể nâng cấp luôn
  job UpdateAdminCacheOrderList.
- [ ] Hiện các API bị chậm vì thực hiện quá nhiều queries, xuất phát từ việc with quá nhiều relationshíp, nên cần 
  tối ưu queries trong ListOrderAction. Có thể đổi qua viết bằng Query Builder hoặc Raw Query nhưng khá cực, nên có 
  thể thực hiện bằng cách tách nhỏ 2 API common/order và order ra thành nhiều API nhỏ. Hiện đang tách dở "Tổng tiền 
  chờ đặt hàng" trong api "order/" sang route "/list/amount", action ListAmountAction. Tách xong APi cần sửa 
  Frontend cho khớp, Frontend chạy async, không await nó.
- Ghi chú: [x] là hoàn thành, [ ] là chưa làm hoặc chưa hoàn thành.

### Danh sách bugs phát sinh khi refactor (liên hệ chị Nhung for more details)
- Không tìm kiếm được theo mã đơn hàng.

Rất cảm ơn ai tiếp quản và hoàn thành công việc này ạ.

-- ChungNB --
