<?php

namespace App\Http\Controllers;


use App\Interfaces\Validation\StoreValidationInterface;
use App\Interfaces\Validation\UpdateValidationInterface;
use App\Models\CustomerDelivery;
use App\Services\CustomerDeliveryService;
use Illuminate\Http\JsonResponse;
use App\Models\Order;
use App\Constants\OrderConstant;
use App\Constants\CustomerDeliveryConstant;

/**
 * Class CustomerDeliveryController
 * @package App\Http\Controllers
 */
class CustomerDeliveryController extends Controller implements UpdateValidationInterface, StoreValidationInterface
{
    /**
     * DashboardController constructor.
     * @param  CustomerDeliveryService  $service
     */
    public function __construct(CustomerDeliveryService $service)
    {
        $this->_service = $service;
    }

    /**
     * @return array
     */
    public function updateMessage(): array
    {
        return [];
    }

    /**
     * @param string $id
     * @return string[]
     */
    public function updateRequest(string $id): array
    {
        return [
            'customer_id' => 'required',
            'receiver' => 'required|max:255',
            'address' => 'required|max:255',
            'phone_number' => 'required|max:10',
        ];
    }

    public function changeStatus(CustomerDelivery $customerDelivery): JsonResponse
    {
        CustomerDelivery::query()
            ->where('customer_id', request()->input('customer_id'))
            ->update(['is_default' => false]);
        $customerDelivery->update(['is_default' => true]);
        return resSuccessWithinData($customerDelivery);
    }

    public function storeMessage(): ?array
    {
        return [];
    }

    public function storeRequest(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'receiver' => 'required|max:255',
            'address' => 'required|max:255',
            'phone_number' => 'required|max:10',
        ];
    }

    public function updateStatus(string $detailId): JsonResponse
    {
        $delivery = CustomerDelivery::query()->findOrFail($detailId);
        $checkDelete = Order::query()
            ->where('customer_delivery_id', $detailId)
            ->whereNotIn('status', [OrderConstant::KEY_STATUS_CANCEL, OrderConstant::KEY_STATUS_DONE])
            ->exists();
        if ($checkDelete) {
            $result = [
                'error' => 'Không thể xóa do có đơn hàng đang được xử lý !'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        $delivery->update(['status' => CustomerDeliveryConstant::STATUS_IN_ACTIVE]);
        return resSuccess();
    }
}
