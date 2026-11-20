<?php

namespace App\Http\Controllers;

use App\Constants\CarrierConstant;
use App\Http\Requests\Carrier\CarrierCancelOrderRequest;
use App\Http\Requests\Carrier\CarrierCreateOrderRequest;
use App\Http\Requests\Carrier\CarrierPriceRequest;
use App\Models\Customer;
use App\Models\CustomerDelivery;
use App\Models\Delivery;
use App\Models\DeliveryOrderStatus;
use App\Services\Carriers\CarrierService;
use App\Services\DeliveryService;
use App\Services\OrderPackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CarrierController extends Controller
{
    public function __construct(CarrierService $service)
    {
        $this->_service = $service;
    }

    public function getPrice(CarrierPriceRequest $request)//: JsonResponse
    {
        $customer = Customer::query()->find($request->input('customer_id'));
        if (!$customer->warehouse_id) {
            return resError(trans('carrier.dont_set_warehouse'));
        }
        $price = $this->_service->getPrice(request()->all() + ['warehouse_id' => $customer->warehouse_id]);

        // Lưu lại phí tạm tính khi tạo xong giao hàng thì trừ (không phải gọi lên bên vận chuyển lần thứ 2)
        CustomerDelivery::query()->findOrFail(request()->input('customer_delivery_id'))->update(
            ['delivery_cost' => $price > 0 ? $price : 0]
        );
        if ($price == -1) {
            return resError(trans('carrier.throw'));
        }

        return resSuccessWithinData($price);
    }

    public function createOrder(CarrierCreateOrderRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $result = $this->_service->createOrder($request->toArray());

            if ($result == -1) {
                return resError(trans('carrier.throw'));
            }

            if ($result == -2) {
                return resErrorWithinData("Dữ liệu đầu vào sai");
            }
            $carrierService = \App::make('App\Services\Carriers\CarrierService');
            $deliveryId = $request->input('deliveryId');
            $delivery = Delivery::find($deliveryId);
            if($delivery){
                $carrierService->sendNotificationCreateOrCancelCarrier($delivery);
            }
            DB::commit();
            return resSuccessWithinData($result);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e);
            return resError();
        }
        
    }

    public function cancelOrder(CarrierCancelOrderRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $result = $this->_service->cancelOrder($request->toArray());
            if ($result == -1) {
                return resError(trans('carrier.throw'));
            }

            (new OrderPackageService())->packageRemoveDelivery($request->input('delivery_id'));
            $carrierService = \App::make('App\Services\Carriers\CarrierService');
            $deliveryId = $request->input('delivery_id');
            $delivery = Delivery::find($deliveryId);
            if($delivery){
                $carrierService->sendNotificationCreateOrCancelCarrier($delivery, 'cancel');
            }
            DB::commit();
            return resSuccessWithinData($result);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e);
            return resError();
        }
    }

    public function webhookOrder(): JsonResponse
    {
        $delivery = Delivery::query()->withoutGlobalScopes()->where(
            'carrier_code',
            request()->input('label_id') ?? request()->input('OrderCode')
        )->first();
        if (!$delivery) {
            return resSuccess();
        }
        $deliveryId = optional($delivery)->id;
        $carrierStatus = DeliveryOrderStatus::query()->withoutGlobalScopes()->firstOrCreate(
            ['delivery_id' => $deliveryId]
        );
        $status = request()->input('status_id') ?? request()->input('Status');
        if ($carrierStatus->status !== $status) {
            DeliveryOrderStatus::query()->create(
                [
                    'delivery_id' => $deliveryId,
                    'response' => json_encode(request()->all()),
                    'status' => $status,
                    'carrier' => $carrierStatus->carrier,
                    'desc' => CarrierConstant::GHTK_ORDER_STATUS[$status] ?? CarrierConstant::GHN_STATUS[$status],
                    'shop_id' => $carrierStatus->shop_id,
                ]
            );
        }
        if($status === -1 || $status === 'cancel') {
            (new DeliveryService())->removeCarrierCode($deliveryId);
        }

        return resSuccess();
    }
}
