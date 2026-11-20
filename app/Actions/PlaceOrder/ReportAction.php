<?php

namespace App\Actions\PlaceOrder;

use App\Constants\ResourceConstant;
use App\Models\OrderDetail;
use App\Models\SubOrderBuyder;
use Illuminate\Pagination\LengthAwarePaginator;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;

class ReportAction extends BaseAction
{
    /**
     * Lấy dữ liệu Báo cáo đặt hàng
     *
     * Danh sách các trường dùng cho filter
     *  + Mã đơn KH: hangve_order_code
     *  + Mã đơn hàng: sub_order_id
     *  + Mã đơn NCC: provider_order_id
     *  + Danh mục (name hoặc id): category
     *
     * @param array|null $params
     * @return LengthAwarePaginator
     */
    public function handle(?array $params): LengthAwarePaginator
    {
        $request = request();

        $subBuyderOrders = SubOrderBuyder::query()
            ->with(['orderBuyder', 'order', 'orderDetailSub'])
            ->when(data_get($request, 'hangve_order_code'), function ($query, $hangVeOrderCode) {
                return $query->whereHas('orderBuyder', function ($subQuery) use ($hangVeOrderCode) {
                    $subQuery->whereHas('orders', function ($innerSubQuery) use ($hangVeOrderCode) {
                        $innerSubQuery->where('code', 'like', "%{$hangVeOrderCode}%");
                    });
                });
            })->when(data_get($request, 'sub_order_id'), function ($query, $subOrderId) {
                return $query->where('order_no', 'like', "%{$subOrderId}%");
            })->when(data_get($request, 'provider_order_id'), function ($query, $providerOrderId) {
                return $query;
            })->when(data_get($request, 'category'), function  ($query, $category) {
                return $query->whereHas('orderDetail.category', function ($innerSubQuery) use ($category) {
                    $innerSubQuery->where('name', 'like', "%{$category}%")->orWhere('id', $category);
                });
            })->owned()->latest()->paginate(ResourceConstant::PER_PAGE_MIN);

        return $this->formatOrderReport($subBuyderOrders);
    }

    /**
     * Format dữ liệu cho Báo cáo đặt hàng
     *
     * @param LengthAwarePaginator $subBuyderOrders
     * @return LengthAwarePaginator
     */
    private function formatOrderReport(LengthAwarePaginator $subBuyderOrders): LengthAwarePaginator
    {
        // thông tin chung
        //  - mã đơn khách hàng: hangve_order_code (order.code)
        // thông tin của từng sub-order
        //  - danh mục: category
        //  - mã đơn hàng: sub_order_id (sub_order_buyders.order_no)
        //  - mã đơn ncc: provider_order_id (-)
        //  - tên sp: product_name
        //  - ảnh sp: product_image
        //  - thuộc tính sp: product_classification
        //  - số lượng tồn kho: product_inventory (từ product và classification, lục trong items.buyder_data lấy inventory)
        //  - số lượng khách hàng đặt: product_number_customer
        //  - số lượng đã đặt: product_number_placed
        //  - số lượng chưa đặt: product_number_unplaced

        $subBuyderOrders->getCollection()->transform(function ($subBuyderOrder) {
            // lấy phân loại (classification) sản phẩm
            /*$source = $subBuyderOrder->sourceable;
            if ($source instanceof OrderDetail) {
                $orderDetail = $source;
                $orderDetail->load(['item', 'orderBuyderSubs', 'category']);
            } else {
                $source->loadMissing(['orderDetail']);
                $orderDetail = $source->orderDetailSub;
            }*/
            $orderDetail = $subBuyderOrder->orderDetailSub;
            $orderDetail->load(['item', 'orderBuyderSubs', 'category']);
            $productClassification = data_get($orderDetail, 'classification');
            $itemBuyderData = json_decode(data_get($orderDetail->item, 'buyder_data'));

            // từ phân loại sản phẩm tìm ra sku tương ứng
            $productSku = $this->getProductSku(
                data_get($itemBuyderData, 'skuList'),
                $productClassification
            );
            $product_image = str_replace('_.webp', '', data_get($orderDetail, 'image'));
            return [
                'hangve_order_code' => data_get($subBuyderOrder->order, 'code'),
                'category' => data_get($orderDetail->category, 'name'),
                'sub_order_id' => data_get($subBuyderOrder, 'order_no'),
                'provider_order_id' => '',
                'product_name' => data_get($orderDetail, 'name'),
                'product_image' => $product_image,
                'product_url' => data_get($orderDetail, 'link'),
                'product_classification' => $productClassification,
                'product_inventory' => data_get($productSku, 'quantity', 0),
                'product_number_customer' => data_get($subBuyderOrder, 'quantity'),
                'product_number_placed' => $orderDetail->getTotalNumberPlacedOrder(),
                'type' => $subBuyderOrder->sourceable_type
            ];
        });

        return $subBuyderOrders;
    }
}
