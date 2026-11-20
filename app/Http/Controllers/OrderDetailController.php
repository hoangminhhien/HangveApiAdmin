<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderDetail\UpdateClassificationRequest;
use App\Http\Requests\OrderDetail\UpdateLinkRequest;
use App\Http\Requests\OrderDetail\UpdateSourceRequest;
use App\Services\OrderDetailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\OrderDetail;
use App\Constants\OrderConstant;
use App\Services\WebhookDongDongService;
use App\Models\OrderDetailHistory;

class OrderDetailController extends Controller
{
    public function __construct(OrderDetailService $service)
    {
        $this->_service = $service;
    }

    /**
     * @throws \Exception
     */
    public function getSkuList(string $orderDetailId): JsonResponse
    {
        $res = $this->_service->getSkuList($orderDetailId);
        if ($res['code'] == 500) {
            \Log::info($res);
            return resError();
        }
        return resSuccessWithinData($res['data']);
    }

    /**
     * Cập nhật nguồn đặt hàng của OrderDetail
     *
     * @param UpdateSourceRequest $request
     * @param string $orderDetailId
     * @return JsonResponse
     */
    public function updateSource(UpdateSourceRequest $request, string $orderDetailId): JsonResponse
    {
        return $this->_service->updateSource(array_merge(
            $request->validated(),
            ['order_detail_id' => $orderDetailId]
        ));
    }

    /**
     * Cập nhật link sản phẩm của OrderDetail
     *
     * @param UpdateLinkRequest $request
     * @param string $orderDetailId
     * @return JsonResponse
     */
    public function updateLink(UpdateLinkRequest $request, string $orderDetailId): JsonResponse
    {
        DB::beginTransaction();
        try {
            $res = $this->_service->updateLinkOrderDetail($request->validated());
            if ($res['code'] == 500) {
                return resError($res['message']);
            }
            DB::commit();
            return resSuccess();
        } catch (\Exception $exception) {
            DB::rollBack();
            return resError($exception->getMessage());
        }

    }

    public function updateClassification(UpdateClassificationRequest $request, string $orderDetailId): JsonResponse
    {
        $params = $request->validated();
        if (empty($params['classification']) && empty($params['mp_sku_id'])) {
            return resError('Sản phẩm đã hết hàng!');
        }
        $orderDetail = OrderDetail::query()->where('id', $orderDetailId)->first();
        if($orderDetail &&
            $orderDetailDupplicate = OrderDetail::query()
                ->where('order_id', $orderDetail->order_id)
                ->where('link', $orderDetail->link)
                ->where('id', '!=', $orderDetailId)
                ->where('mp_sku_id', $params['mp_sku_id'])
                ->first()
        ) {
            return resError("Thuộc tính sản phẩm $orderDetailDupplicate->classification đã tồn tại");
        }
        return $this->_service->updateClassification($request->validated());
    }

    public function getHistories($orderDetailId): JsonResponse
    {
        try {
            $data = $this->_service->getHistories($orderDetailId);
            return resSuccessWithinData($data, 'OK');
        } catch (\Exception $e) {
            Log::info($e);
            return resErrorWithinData([], 'Có lỗi xảy ra');
        }
    }

    public function updateNoteChina(Request $request)
    {
        $validationRules = [
            "order_detail_id" => "required|exists:order_details,id",
            "note_china" => "required",
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);

        try {
            \DB::beginTransaction();
            $params = $request->all();
            $orderDetailId = data_get($params, 'order_detail_id');
            $noteChina = data_get($params, 'note_china');
            $orderDetail = OrderDetail::with(['order'])->find($orderDetailId);
            if($orderDetail->order && $orderDetail->order->status != OrderConstant::KEY_STATUS_WAITING_QUOTE) {
                $result = [
                    'error' => 'Đơn không ở trạng thái chờ báo giá'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            if($orderDetail->order && $orderDetail->order->type_order != 1) {
                $result = [
                    'error' => 'Đơn không phải là đơn thủ công'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            // Luôn lưu ghi chú tiếng trung vào thuộc tính của đặt hàng kể cả thuộc tính đó đúng hay sai
            $orderDetail->update([
                'note_china' => $noteChina,
                'classification_zh' => $noteChina,
                'classification_zh_fast' => $noteChina
            ]);
            // Lưu lịch sử
            OrderDetailHistory::query()->create([
                'order_detail_id' => $orderDetail->id,
                'object' => OrderDetailHistory::CLASSSIFICATION,
                'value' => $noteChina,
                'value_old' => $orderDetail->classification,
                'user_id' => getCurrentUser()->id,
            ]);
            (new WebhookDongDongService())->updateOrderDetail($orderDetail);
            $job = new \App\Jobs\Buyder\SyncMpSkuIdByNoteChinaJob($orderDetailId, getCurrentUser()->id);
            dispatch($job)->onQueue('export-1800');
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            \Log::error($e);
            return resError();
        }
    }
}
