<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ConfigKpiService;
use Illuminate\Http\JsonResponse;


class ConfigKpiController extends Controller
{
    public function __construct(ConfigKpiService $service)
    {
        $this->_service = $service;
    }

    public function storeRequest(): array
    {
        return [
            'type'          => 'required|in:1,2,3',
            'date_start'    => 'required|date_format:Y-m',
            'config'  => 'required|array',
            'config.*.name' => 'required',
            'config.*.min' => 'required|numeric',
            'config.*.max' => 'required|numeric|gt:config.*.min',
            'config.*.value' => 'required|numeric',
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
            $params = request()->all();
            $result = $this->_service->store($params);
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
            'type'          => 'required|in:1,2,3',
            'date_start'    => 'required|date_format:Y-m',
            'config'  => 'required|array',
            'config.*.name' => 'required',
            'config.*.min' => 'required|numeric',
            'config.*.max' => 'required|numeric|gt:config.*.min',
            'config.*.value' => 'required|numeric',
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
            $params = request()->all();
            $record = $this->_service->update($id, $params);
            \DB::commit();
            return resSuccessWithinData($record);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function list(Request $request)
    {
        return $this->_service->list();
    }

    public function detail(string $id): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__, $id);
        return $this->_service->detail($id);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__, $id);

        try {
            \DB::beginTransaction();
            $result = $this->_service->destroy($id);
            \DB::commit();
            return $result;
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function latestByType()
    {
        return $this->_service->latestByType();
    }
}
