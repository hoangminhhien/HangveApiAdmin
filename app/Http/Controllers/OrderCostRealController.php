<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderCostRealRequest;
use App\Http\Resources\OrderCostRealLogResource;
use App\Http\Resources\PaginateJsonResource;
use App\Services\OrderCostRealService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderCostRealController extends Controller
{
    public function __construct(OrderCostRealService $service)
    {
        $this->_service = $service;
    }

    /**
     * @return JsonResponse
     * @throws \Throwable
     */
    public function create(OrderCostRealRequest $request)
    {
        try {
            $this->_service->store($request->all());

            return resSuccess('Tạo yêu cầu thành công!');
        } catch (\Exception $e) {
            //dd($e->getMessage());
            return resError('Tạo yêu cầu lỗi!');
        }

    }

    /**
     * @param int $id
     * @return JsonResponse
     * @throws \Throwable
     */
    public function edit(int $id, OrderCostRealRequest $request)
    {
        try {
            $this->_service->update($id, $request->all());

            return resSuccess('Sửa yêu cầu thành công!');
        } catch (\Exception $e) {
            //dd($e->getMessage());
            return resError('Sửa yêu cầu lỗi!');
        }

    }

    /**
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->_service->destroy($id);

            return resSuccess('Xoá yêu cầu thành công');
        } catch (Exception $e) {
            //throw $e;
            return resError('Xoá yêu cầu lỗi!');
        }
    }

    public function approve(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array'
        ]);

        DB::beginTransaction();
        try {
            $ids = $request->input('ids');
            $this->_service->approve($ids);
            DB::commit();

            return resSuccess('Chấp nhận yêu cầu thành công');
        } catch (Exception $e) {
            DB::rollBack();
            //throw $e;
            return resError('Chấp nhận yêu cầu lỗi!');
        }
    }

    public function getOldCost(Request $request)
    {
        $request->validate([
            'order_code' => 'required'
        ]);

        try {
            $cost = $this->_service->getOldCost($request->order_code);
            $data['cost'] = $cost;
            return resSuccessWithinData($data,  'Thành công');
        } catch (Exception $e) {
            //throw $e;
            return resErrorWithinData([], 'Fail!');
        }
    }

    /**
     * get log
     */
    public function log()
    {
        try {
            $data = $this->_service->log();

            return resSuccessWithinData(new PaginateJsonResource($data, OrderCostRealLogResource::class));
        } catch (\Exception $e) {
            //dd($e->getMessage());
            return resErrorWithinData([], 'Lấy lịch sử quỹ thất bại');
        }
    }
}

