<?php

namespace App\Actions\OrderDetail;

use App\Models\Item;
use App\Models\OrderDetail;
use App\Models\OrderDetailHistory;
use App\Traits\OrderDetail\UpdateOrderDetailTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;
use App\Services\WebhookDongDongService;

class UpdateOrderDetailAction extends BaseAction
{
    use UpdateOrderDetailTrait;

    /**
     * Tiếp nhận dữ liệu, phân phối tới các hàm xử lý tương ứng
     *
     * @param array|null $params
     * @return JsonResponse
     */
    public function handle(?array $params): JsonResponse
    {
        $method = data_get($params, 'method');
        unset($params['function']);
        return $this->{$method}($params);
    }

    /**
     * Cập nhật nguồn đặt hàng của OrderDetail
     *
     * @param array|null $data
     * @return JsonResponse
     */
    public function updateSource(?array $data): JsonResponse
    {
        $orderDetail = OrderDetail::query()->withoutGlobalScopes()->findOrFail(data_get($data, 'order_detail_id'));

        $orderDetail->source = data_get($data, 'source');
        $orderDetail->save();

        return resSuccessWithinData($orderDetail, 'Cập nhật nguồn đặt hàng thành công');
    }

    public function updateLink(?array $params): JsonResponse
    {
        try {
            $rs = DB::transaction(function () use ($params) {
                $link = data_get($params, 'link');
                $orderDetailId = data_get($params, 'order_detail_id');
                $orderDetail = OrderDetail::query()->findOrFail($orderDetailId);
                if($link && $orderDetail->link != $link) {
                    // Lưu lịch sử
                    OrderDetailHistory::query()->create([
                        'order_detail_id' => $orderDetail->id,
                        'object' => OrderDetailHistory::LINK,
                        'value' => $link,
                        'value_old' => $orderDetail->link,
                        'user_id' => getCurrentUser()->id,
                    ]);
                }
                $orderDetail->link = $link;
                $source = $orderDetail->source;
                $platformItemId = $this->getPlatformItemId($link, $source);
                $item = Item::query()
                    ->where('source', $source)
                    ->firstWhere('num_iid', $platformItemId);
                return $item && $item->buyder_data
                    ? $this->updateOrderDetailWithExistedItem($item, $orderDetail)
                    : $this->syncBuyderItem($link, $orderDetail);
            });
            return resSuccessWithinData($rs);
        } catch (\Exception $e) {
            Log::error($e);
            return resError($e->getMessage() . ' LINE ' . $e->getLine() . ' FILE ' . $e->getFile());
        }
    }

    /**
     * Cập nhật thuộc tính sản phẩm của OrderDetail
     *
     * @param array|null $data
     * @return JsonResponse
     */
    public function updateClassification(?array $data): JsonResponse
    {
        $data = array_filter($data);
        $is_not_update_classic_name = $data['is_not_update_classic_name'] ?? false;
        $classificationNew = '';
        if(isset($data['classification'])) {
            $classificationNew = data_get($data, 'classification');
        }
        $orderDetail = OrderDetail::query()
            ->withoutGlobalScopes()
            ->findOrFail(data_get($data, 'order_detail_id'));
        if($classificationNew) {
           // Lưu lịch sử
            OrderDetailHistory::query()->create([
                'order_detail_id' => $orderDetail->id,
                'object' => OrderDetailHistory::CLASSSIFICATION,
                'value' => $classificationNew,
                'value_old' => $orderDetail->classification,
                'user_id' => getCurrentUser()->id,
            ]);
        }
        if(isset($data['is_not_update_classic_name'])) {
            unset($data['is_not_update_classic_name']);
        }
        $data['mp_sku_id_fast'] = $data['mp_sku_id'] ?? null;
        $data['classification_zh_fast'] = $data['classification'] ?? null;
        if(isset($data['classification'])) {
            unset($data['classification']);
        }

        $orderDetail->update($data);
        (new WebhookDongDongService())->updateOrderDetail($orderDetail);
        return resSuccess('Cập nhật thuộc tính sản phẩm thành công');
    }
}
