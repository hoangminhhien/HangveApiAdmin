<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Constants\PackageConstant;
use App\Services\OrderPackageCancelService;
use App\Models\OrderPackage;
use App\Models\OrderPackageCancel;
use App\Http\Requests\Package\PackageCancelStoreRequest;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Package\PackageCancelModifiesRequest;
use App\Http\Requests\Package\ChangeStatusPackCancelRequest;
use Illuminate\Support\Facades\DB;
use App\Services\OrderPackageService;

class PackageCancelController extends Controller
{
    public function __construct(
        OrderPackageCancelService $service,
        OrderPackageService $packageService
    )
    {
        $this->_service = $service;
        $this->packageService = $packageService;
    }

    // yêu cần hủy kiện

    public function storePackageCancel(PackageCancelStoreRequest $request, OrderPackage $package): JsonResponse
    {
        
        try {
            \DB::beginTransaction();
            $params = $request->all();
            $params['order_package_id'] = $package->id;
            $params['bill_code'] = $package->bill_code;
            $params['customer_id'] = $package->customer_id;
            $params['staff_id'] = getCurrentUserId();
            
            OrderPackage::query()->where(['id' => $package->id])->update(['is_waiting_cancel' => PackageConstant::STATUS_CANCELED]);
            $data = OrderPackageCancel::query()->create($params);
            \DB::commit();
 
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }
    public function modifies(PackageCancelModifiesRequest $request, string $id): JsonResponse
    {
        try {
            \DB::beginTransaction();

            $params = $request->all();
            $order_package_cancel = OrderPackageCancel::query()->where(['id' => $id])->first();
            OrderPackageCancel::query()->where(['id' => $id])->update(['status' => PackageConstant::STATUS_CANCEL_DONE]);
            OrderPackageCancel::query()->where(['id' => $id])->update(['staff_management_id' => getCurrentUserId()]);
            $package = OrderPackage::query()->where(['id' => $order_package_cancel->order_package_id])->first();
            if($package) {
                $statusOld = $package->status;
                $package->update(['status' => PackageConstant::STATUS_CANCEL]);
                $this->packageService->updateShareShip($package);
                if($statusOld == PackageConstant::STATUS_RECEIVED_GOODS) {
                    $this->packageService->refundAmount($package);
                }else {
                    $this->packageService->calculatorWhenCancelPackage($package);
                }
            }
            \DB::commit();


            // Bắn đồng bộ sang bên
            $orderPackageDB = OrderPackage::where('id', $order_package_cancel->order_package_id)
                                        ->where('status', PackageConstant::STATUS_CANCEL)
                                        ->first();

            if (!is_null($orderPackageDB)) {
                $arr_packs = [
                    $orderPackageDB
                ];
                $job = new \App\Jobs\OneWarehouse\SyncCancelPack($arr_packs);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin');
            }

            return resSuccess();

        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }
    public function delete(PackageCancelModifiesRequest $request, string $id, OrderPackage $package): JsonResponse
    {
        try {
        \DB::beginTransaction();

        $params = $request->all();
        $order_package_cancel = OrderPackageCancel::query()->where(['id' => $id])->first();
        OrderPackageCancel::query()->where(['id' => $id])->update(['status' => PackageConstant::STATUS_CANCEL_DELETE]);
        OrderPackageCancel::query()->where(['id' => $id])->update(['staff_management_id' => getCurrentUserId()]);
        OrderPackage::query()
            ->where(['id' => $order_package_cancel->order_package_id])
            ->update(['is_waiting_cancel' => PackageConstant::STATUS_WAITING_CANCEL]);
        \DB::commit();

        return resSuccess();

        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }
    // thay đổi status nhiều kiện
    public function changeStatus(ChangeStatusPackCancelRequest $request): JsonResponse
    {
        OrderPackageCancel::query()->findMany($request->packages_cancel)->each(
            function ($packages_cancel) use ($request) {
                if($packages_cancel->status = request()->input('status') == "status_done") {
                    $packages_cancel->status = request()->input('status');
                    $package = OrderPackage::query()->where(['id' => $packages_cancel->order_package_id])->first();
                    if($package) {
                        $statusOld = $package->status;
                        $package->update(['status' => PackageConstant::STATUS_CANCEL]);
                        $this->packageService->updateShareShip($package);
                        if($statusOld == PackageConstant::STATUS_RECEIVED_GOODS) {
                            $this->packageService->refundAmount($package);
                        }else {
                            $this->packageService->calculatorWhenCancelPackage($package);
                        }
                    }
                    OrderPackageCancel::query()->where(['id' => $packages_cancel->id])->update(['staff_management_id' => getCurrentUserId()]);
                    $packages_cancel->save();
                } else {
                    $packages_cancel->status = request()->input('status');
                    OrderPackageCancel::query()->where(['id' => $packages_cancel->id])->update(['staff_management_id' => getCurrentUserId()]);
                    OrderPackage::query()
                        ->where(['id' => $packages_cancel->order_package_id])
                        ->update(['is_waiting_cancel' => PackageConstant::STATUS_WAITING_CANCEL]);
                    $packages_cancel->save();
                }
            }
        );

        // Bắn đồng bộ sang bên
        if (request()->input('status') == "status_done") {
            $orderPackageCancelDB = OrderPackageCancel::query()->whereIn('id', $request->packages_cancel)->get();
            $pack_ids = \Arr::pluck($orderPackageCancelDB, 'order_package_id');
            $orderPackageDB = OrderPackage::whereIn('id', $pack_ids)
                                        ->where('status', PackageConstant::STATUS_CANCEL)
                                        ->get();

            if (count($orderPackageDB) > 0) {
                $job = new \App\Jobs\OneWarehouse\SyncCancelPack($orderPackageDB);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin');
            }
        }

        return resSuccess();
    }    
}
