<?php

namespace App\Http\Controllers;

use App\Http\Requests\Accounting\AccountingRequest;
use App\Http\Requests\Accounting\InspectionRequest;
use App\Http\Requests\Accounting\InternationalCostRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Services\AccountingService;
use App\Services\CustomerService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function __construct()
    {
        $this->_service = new AccountingService();
    }

    /**
     * @param  AccountingRequest  $request
     * @return JsonResponse
     */
    public function getOrderFee(AccountingRequest $request): JsonResponse
    {
        $level = (new CustomerService())->getCustomerLevelById($request->customer_id);
        $order = Order::query()->find($request->order_id);
        $is_fix_order_percent = $order->is_fix_order_percent;
        $orderService = new OrderService();
        if ($is_fix_order_percent == 1) {
            $orderFee = $request->amount * $order->order_percent;
            $discountCost = $this->_service->getCustomerLevelCost($orderFee, $level);
        } else if($orderService->checkSplitOrder($order)) {
            // nếu là đơn tách
            $orderPercent = $order->order_percent;
            $orderFee = $this->_service->getOrderFee(
                $request->amount,
                $request->customer_id,
                $orderPercent, 'isMultipleSupplier');
            $discountCost = $this->_service->getCustomerLevelCost($orderFee, $level);
        } else {
            $orderFee = $this->_service->getOrderFee($request->amount, $request->customer_id);
            $discountCost = $this->_service->getCustomerLevelCost($orderFee, $level);
        }
        // return resSuccessWithinData(
        //     [
        //         'order_fee' => $this->_service->getOrderFee($request->amount),
        //         'discount_cost' => $this->_service->getCustomerLevelCost($request->amount, $level)
        //     ]
        // ); // Logic cũ

        return resSuccessWithinData(
            [
                'order_fee' => $orderFee,
                'discount_cost' => $discountCost
            ]
        );
    }

    /**
     * @param  InspectionRequest  $request
     * @return JsonResponse
     */
    public function getInspectionCost(InspectionRequest $request): JsonResponse
    {
        if(empty($request->order_id)) {
            return resSuccessWithinData($this->_service->getInspectionCost($request->quantity));
        }
        $order = Order::query()->find($request->order_id);
        $orderService = new OrderService();

        if($orderService->checkSplitOrder($order)) {
            $totalQuantityOld = $order->details->sum('quantity');
            $unitInspectionCost = $order->inspection_cost / $totalQuantityOld;
            $inspectionCost = $unitInspectionCost * $request->quantity;
            return resSuccessWithinData($inspectionCost);
        }

        return resSuccessWithinData($this->_service->getInspectionCost($request->quantity));
    }

    public function getInspectionCostAdvanced(InspectionRequest $request): JsonResponse
    {
        $quantity = $request->quantity ?? 0;
        $quantity_advanced = $request->quantity_advanced ?? 0;
        if ($quantity == 0 && $quantity_advanced == 0) {
            return resError('Số lượng sản phẩm phải lớn hơn 0');
        }
        $inspection_cost = 0;
        if ($quantity > 0) {
            $inspection_cost = $this->_service->getInspectionCost($quantity);
        }
        $advance_inspection_cost = 0;
        if ($quantity_advanced > 0) {
            $advance_inspection_cost = ($this->_service->getAdvancedInspectionCost($quantity_advanced));
        }
        $response = [
            'inspection_cost' => $inspection_cost,
            'advanced_inspection_cost' => $advance_inspection_cost,
        ];
        return resSuccessWithinData($response);
    }

    public function getInternationalCost(InternationalCostRequest $request): JsonResponse
    {
       $params     = $request->all();
       $weight     = $params['weight'] ?? 0;
       $volume     = isset($params['height']) ? ((float)$params['height'] * (float)$params['width'] * (float)$params['length']) : 0;
       $customerId = !empty($params['customer_id']) ? $params['customer_id'] : '';
       return resSuccessWithinData($this->_service->getInternationShippingCost($params['province_id'], $weight, $volume, $customerId));

    }

    /**
     * @param  InspectionRequest  $request
     * @return JsonResponse
     */
    public function getInspectionCostV2(Request $request): JsonResponse
    {
        return resSuccessWithinData($this->_service->getInspectionCost($request->quantity));
    }

}
