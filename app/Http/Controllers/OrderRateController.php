<?php

namespace App\Http\Controllers;

use App\Services\OrderRateService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderRateController extends Controller
{
    public function __construct(OrderRateService $service)
    {
        $this->_service = $service;
    }

    /**
     * @throws Exception
     */
    public function list(Request $request): JsonResponse
    {
        return $this->_service->list($request->all());
    }

    /**
     * @throws Exception
     */
    public function delete(string $id): JsonResponse
    {
        return $this->_service->delete($id);
    }
}
