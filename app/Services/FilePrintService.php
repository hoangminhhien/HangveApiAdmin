<?php

namespace App\Services;

use Illuminate\Support\Facades\View;
use App\Http\Resources\FilePrint\FilePrintPaginateResource;
use App\Http\Resources\FilePrint\FilePrintResource;
use App\Models\OrderPackage;

class FilePrintService extends BaseService
{
    protected string $_paginateResource = FilePrintPaginateResource::class;
    protected string $_resource = FilePrintResource::class;

    public function store($params = null, $type = null)
    {
        $this->throwModel();
        $orderPackIds = data_get($params, 'orderPackIds', []);
        $packQuantity = OrderPackage::whereIn('id', $orderPackIds)->count();
        $userId = data_get(\Auth::user(), 'id');
        $customerId = data_get(OrderPackage::whereIn('id', $orderPackIds)->first(), 'customer_id');
        $dataResult  = [
            'packQuantity' => $packQuantity,
            'orderPackIds' => $orderPackIds
        ];
        return $this->_model->create([
            'type' => $type,
            'user_id' => $userId,
            'customer_id' => $customerId,
            'data_json' => json_encode($dataResult),
            'user_dongdong_id' => data_get($params, 'user_dongdong_id', null),
            'user_dongdong_name' => data_get($params, 'user_dongdong_name', null),
        ]);
    }

    public function getData($params)
    {
        $type = $params['type'] ?? '';
        $customer_phone = data_get($params, 'customer_phone');
        $user_name = data_get($params, 'user_name');
        $query = $this->_model->select('file_prints.*');
        $order_code = data_get($params, 'order_code');
        $bill_code = data_get($params, 'bill_code');
        $data = $query
            ->when(!empty($type), function ($q) use ($type) {
                $q->where('file_prints.type', $type);
            })
            ->when(!empty($customer_phone), function ($q) use ($customer_phone) {
                $q->join('customers', 'customers.id', 'file_prints.customer_id')
                    ->where('customers.phone_number', $customer_phone);
            })
            ->when(!empty($user_name), function ($q) use ($user_name) {
                $q->join('users', 'users.id', 'file_prints.user_id')
                    ->where('users.name', 'like', '%' . $user_name . '%');
            })
            ->when(!empty($order_code) || !empty($bill_code), function ($q) use ($order_code, $bill_code) {

                $q->whereExists(function ($sub) use ($order_code, $bill_code) {

                    $sub->selectRaw(1)
                        ->from('order_package')
                        ->whereRaw("
                JSON_CONTAINS(
                    JSON_EXTRACT(file_prints.data_json,'$.orderPackIds'),
                    JSON_QUOTE(CAST(order_package.id AS CHAR))
                )
            ");

                    if (!empty($order_code)) {
                        $sub->where('order_package.order_code', $order_code);
                    }

                    if (!empty($bill_code)) {
                        $sub->where('order_package.bill_code', $bill_code);
                    }
                });

            })
            ->with(['user', 'customer'])->orderBy('created_at', 'desc')
            ->paginate();
        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }
}
