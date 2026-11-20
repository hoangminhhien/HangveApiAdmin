<?php

namespace App\Http\Controllers;

use App\Constants\OrderConstant;
use App\Models\Ecommerce;
use App\Services\EcommerceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EcommerceController extends Controller
{
    protected EcommerceService $ecommerceService;

    function __construct(EcommerceService $ecommerceService)
    {
        $this->ecommerceService = $ecommerceService;
    }

    public function index(): JsonResponse
    {
        $params = request()->all();
        $results = $this->ecommerceService->index($params);
        return resSuccessWithinData($results);
    }

    public function create()
    {
        //
    }

    public function store(): JsonResponse
    {
        return resSuccess();
    }

    public function show($id): JsonResponse
    {
        $result = $this->ecommerceService->detail($id);
        return resErrorWithinData($result);
    }

    public function edit($id): JsonResponse
    {
        $result = $this->ecommerceService->detail($id);
        return resErrorWithinData($result);
    }

    public function update($id): JsonResponse
    {

        DB::beginTransaction();
        try {
            $params = request()->all();
            $this->ecommerceService->update($id, $params);
            $params['without_resource'] = true;
            $results = $this->ecommerceService->index($params);
            $results = $results->toArray();
            $key = Ecommerce::CACHE_KEY_ECOMMERCE;
            Cache::forget($key);
            Cache::put($key, $results);
            DB::commit();
            return resSuccess();
        } catch (\Exception $exception) {
            DB::rollBack();
            return resError($exception->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $this->ecommerceService->delete($id);
            DB::commit();
            return resSuccess();
        } catch (\Exception $exception) {
            DB::rollBack();
            return resError($exception->getMessage());
        }

    }
}
