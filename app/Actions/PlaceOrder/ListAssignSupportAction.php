<?php

namespace App\Actions\PlaceOrder;

use App\Constants\Buyder\BuyderStatusConstant;
use App\Constants\ResourceConstant;
use App\Models\AssignSupportOrder;
use App\Traits\PlaceOrder\GetPlatformStatusConstantNameTrait;
use Illuminate\Pagination\LengthAwarePaginator;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class ListAssignSupportAction extends BaseAction
{
    use GetPlatformStatusConstantNameTrait;

    /**
     * Lấy danh sách đơn hàng hỗ trợ
     *
     * Danh sách các filter keys
     *   + Mã đơn hàng: order_code (orders.code)
     *   + Mã đặt hàng: order_buyder_no (order_buyders.no)
     *   + Từ ngày: from (assign_support_orders.created_at)
     *   + Đến ngày: to (assign_support_orders.created_at)
     *   + Trạng thái NCC: order_buyder_status (order_buyder_subs.buyder_status)
     *
     * @param array|null $params
     * @return LengthAwarePaginator
     */
    public function handle(?array $params): LengthAwarePaginator
    {
        $request = request();
        $source = $request->get('source') ?? BuyderServiceConstant::SOURCE_TAOBAO;
        $subOrderBuyderStatus = $request->get('order_buyder_status');

        // Taobao và 1688 có các trạng thái đặt hàng khác nhau, lưu trong các constant khác nhau
        // method này được định nghĩa trong trait GetPlatformStatusConstantNameTrait
        $platformStatusConstantName = $this->getSupplierSubStatusListConstantNames($source)[0];

        $assignSupportOrders = AssignSupportOrder::query()
            ->whereHas('orderDetail', fn($query) => $query->where('source', $source))
            ->with(['orderDetail', 'orderBuyder', 'orderBuyder.subOrderBuyders'])
            ->when($request->get('order_code'), function ($query, $orderCode) {
                return $query->whereHas('orderDetail.order', fn($q) => $q->where('code', $orderCode));
            })
            ->when($request->get('order_buyder_no'), function ($query, $orderBuyderNo) {
                return $query->whereHas('orderBuyder', fn($q) => $q->where('buyder_no', $orderBuyderNo));
            })
            ->when($request->get('from'), fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($request->get('to'), fn ($query, $from) => $query->where('created_at', '<=', $from))
            ->when(
                $subOrderBuyderStatus && $subOrderBuyderStatus != 'all',
                fn ($query) => $query->whereHas(
                    'orderBuyder.subOrderBuyders',
                    fn ($orderBuyderSub) => $orderBuyderSub
                        ->withoutGlobalScope('without_canceled')
                        ->where('buyder_status', $subOrderBuyderStatus)
                )
            )->assigned()->latest()->paginate(ResourceConstant::PER_PAGE_MIN);
        return $this->formatData($assignSupportOrders);
    }

    /**
     * Format dữ liệu danh sách đơn hàng hỗ trợ
     *
     * @param LengthAwarePaginator $assignSupportOrders
     * @return LengthAwarePaginator
     */
    private function formatData(LengthAwarePaginator $assignSupportOrders): LengthAwarePaginator
    {
        // assign_support_order_id (assign_support_orders.id)
        // mã đặt hàng: order_code (order_buyder.buyder_no)
        // tình trạng: order_status (order_buyder.supplier_status)
        // ảnh sp: product_image
        // tên sp: product_name
        // link taobao của sản phẩm: product_url
        // thuộc tính sp: product_classification
        // số sản phẩm nhờ đặt hộ: product_assigned_number

        $assignSupportOrders->getCollection()->transform(function ($assignSupportOrder) {
            $orderDetail = $assignSupportOrder->orderDetail;
            $orderBuyderSubs = $assignSupportOrder->orderBuyder ? $assignSupportOrder->orderBuyder->subOrderBuyders->first() : [];
            $orderStatusCode = data_get($orderBuyderSubs, 'buyder_status');
            $orderBuyder = data_get($orderBuyderSubs, 'orderBuyder');
            $order = data_get($orderDetail, 'order');

            // ở đây check để thực hiện 2 tác vụ: 'Đặt hàng' hoặc 'Hủy hỗ trợ'
            // có thể check nếu đạt 1 trong 3 điều kiện
            //   + chưa đặt hàng ($orderBuyder null)
            //   + đã đặt hàng nhưng đơn hàng ở trạng thái 'Chưa thanh toán'
            //   + đã đặt hàng nhưng đơn hàng con ở trạng thái 'mới tạo đơn'
            $orderBuyderStatus = data_get($orderBuyder, 'supplier_status');
            $isCheckable = is_null($orderBuyder)
                || $orderBuyderStatus == BuyderStatusConstant::ORDER_WAIT_BUYER_P;

            return [
                'is_checkable' => $isCheckable,
                'order_buyder_platform_source' => data_get($orderDetail, 'source'),
                'buyder_id' => data_get($orderBuyder, 'buyder_id'),
                'buyder_sub_id' => data_get($orderBuyderSubs, 'buyder_sub_id'),
                'order_detail_id' => data_get($orderDetail, 'id'),
                'order_detail_source' => data_get($orderDetail, 'source'),
                'assign_support_order_id' => data_get($assignSupportOrder, 'id'),
                'order_code' => data_get($orderBuyder, 'buyder_no'),
                'order_status' => BuyderStatusConstant::LIST_ORDER_STATUS_NAME[$orderStatusCode] ?? '',
                /*'order_status' => BuyderStatusConstant::getConstantValue(
                    'SUB_BUYDER_ORDER_STATUSES',
                    $orderStatusCode
                ),*/
                'order_status_color' => BuyderStatusConstant::LIST_ORDER_STATUS_COLOR[$orderStatusCode] ?? '',
                'order_delay_time' => data_get($orderBuyder, 'delay_time'),
                'product_image' => data_get($orderDetail, 'image'),
                'product_name' => data_get($orderDetail, 'name'),
                'product_url' => data_get($orderDetail, 'link'),
                'product_classification' => data_get($orderDetail, 'classification'),
                'product_assigned_number' => data_get($assignSupportOrder, 'quantity'),
                'order_hv_id' => data_get($order, 'id'),
                'order_hv_code' => data_get($order, 'code')
            ];
        });

        return $assignSupportOrders;
    }
}
