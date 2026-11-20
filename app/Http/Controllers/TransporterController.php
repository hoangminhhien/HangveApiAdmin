<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransporterChildrenStore;
use App\Http\Requests\TransporterChildrenUpdate;
use App\Models\TransporterDetail;
use App\Services\TransporterService;
use Illuminate\Http\JsonResponse;

class TransporterController extends Controller
{
    public function __construct(TransporterService $service)
    {
        $this->_service = $service;
    }

    /**
     * @param  string  $id
     * @return JsonResponse
     */
    public function getChildren(string $id): JsonResponse
    {
        $customerId = request()->get('customer_id');
        return resSuccessWithinData(
            TransporterDetail::query()
                ->where('transporter_id', $id)
                ->when($customerId, function ($query, $customerId) {
                    $query->where('customer_id', $customerId);
                })
                ->select('id', 'name', 'phone_number', 'address')->get()
        );
    }

    /**
     * @param  TransporterChildrenStore  $request
     * @param  string  $id
     * @return JsonResponse
     */
    public function storeChildren(TransporterChildrenStore $request, string $id): JsonResponse
    {
        $params = $request->all();
        $params['transporter_id'] = $id;
        $params['phone_number'] = $params['phone_number'] ? $params['phone_number'] : "";
        $detail = TransporterDetail::query()->create($params);
        return resSuccessWithinData($detail);
    }

    /**
     * @param  TransporterChildrenUpdate  $request
     * @param  string  $detailId
     * @return JsonResponse
     */
    public function updateChildren(TransporterChildrenUpdate $request, string $detailId): JsonResponse
    {
        optional(TransporterDetail::query()->findOrFail($detailId))->update($request->all());
        return resSuccess();
    }

    /**
     * @param  string  $detailId
     * @return JsonResponse
     */
    public function destroyChildren(string $detailId): JsonResponse
    {
        optional(TransporterDetail::query()->findOrFail($detailId))->delete();
        return resSuccess();
    }
}
