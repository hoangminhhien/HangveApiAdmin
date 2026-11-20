<?php

namespace App\Http\Controllers;


use App\Http\Requests\SupplierListRequest;
use App\Services\SupplierService;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(SupplierService $service)
    {
        $this->_service = $service;
    }
    public function getList(SupplierListRequest $request)
    {
        return $this->_service->getListSup($request);
    }

    public function storeMessage(): ?array
    {
        return [];
    }

    public function storeRequest(): array
    {
        return [
            'name' => 'required|max:255',
        ];
    }

    public function getListByOrder(int $orderId, Request $request)
    {
        try {
            $data = $this->_service->getSupplierByCustomer($orderId);
            return resSuccessWithinData($data);
        } catch (\Exception $e) {
            return resErrorWithinData([], $e->getMessage());
        }
    }
}
