<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\ConfigService;
use App\Models\ReasonConfirmOrder;

class ReasonConfirmOrderController extends Controller
{

    public function __construct(ConfigService $service)
    {
        $this->_service = $service;
    }

    public function list()
    {
        $data = ReasonConfirmOrder::query()->select('id', 'name')->orderBy('created_at', 'desc')->get();
        return resSuccessWithinData($data);
    }

    public function pagination(): JsonResponse
    {
        $per_page = request()->get('per_page', 25);
        $data = ReasonConfirmOrder::query()->orderBy('created_at', 'desc')->paginate($per_page);
        $_paginateResource = \App\Http\Resources\PaginateJsonResource::class;
        $_resource = \App\Http\Resources\Config\ReasonConfirmOrderResource::class;
        return resSuccessWithinData(new $_paginateResource($data, $_resource));
    }

    protected function getAttributes(): array
    {
        return [
            'name' => 'Tên lý do',
            'type' => 'Giới hạn số lần XNL',
            'count' => 'Số lần',
            'time' => 'Cảnh báo',
            'time_quote' => 'Chuyển tự động về chờ báo giá',
        ];
    }

    public function storeRequest(): array
    {
        return [
            "name"  => "required",
            "type"  => "required|in:0,1",
            "count" => "required_if:type,==,1|numeric",
            "time"    => "nullable|array|max:3",
            "time.*"  => "required|numeric|min:0",
            "time_quote"  => "nullable|numeric|min:0",
        ];
    }

    public function storeMessage(): array
    {
        return [];
    }

    public function store(): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__);
        try {
            \DB::beginTransaction();
            $params = request()->only('name', 'type', 'count', 'time', 'time_quote');
            $result = $this->_service->storeReasonConfirmOrder($params);
            \DB::commit();
            return resSuccessWithinData($result);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function updateRequest(string $id): array
    {
        return [
            "name"  => "required",
            "type"  => "required|in:0,1",
            "count" => "required_if:type,==,1|numeric",
            "time"    => "nullable|array|max:3",
            "time.*"  => "required|numeric|min:0",
            "time_quote"  => "nullable|numeric|min:0",
        ];
    }

    public function updateMessage(): array
    {
        return [];
    }

    public function update(string $id): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__, $id);
        try {
            \DB::beginTransaction();
            $params = request()->only('name', 'type', 'count', 'time', 'time_quote');
            ReasonConfirmOrder::findOrfail($id);
            $result = $this->_service->updateReasonConfirmOrder($id, $params);
            \DB::commit();
            return resSuccessWithinData($result);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function destroy(string $id): JsonResponse
    {
        ReasonConfirmOrder::query()->where('id', $id)->delete();
        return resSuccess(trans('system.deleted'));
    }

}
