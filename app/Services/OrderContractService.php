<?php


namespace App\Services;


use App\Http\Resources\User\UserResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use App\Models\Notification;

class OrderContractService extends BaseService
{
    public function setLogOrder($order, $fileDefault)
    {
        $this->sentNotificationCustomer($order);
        \App::make('App\Services\ActivityService')->setOrderLog(
            $order,
            trans(
                "activity.order_contract",
                ['name' => getCurrentUser()->name, 'fileName'=> $fileDefault]
            ),
            $order->id,
        );
    }

    private function sentNotificationCustomer($order)
    {
        $data_order = [
            'order_code'  => $order->code,
            'order_id'    => $order->id,
        ];
        $data = [
            'group'           => 2,
            'name'            => 2009, // Yêu cầu kí hợp đồng
            'data'            => json_encode($data_order),
            'time_create'     => time(),
            'status'          => 1,
            'organization_id' => $order->organization_id,
            'customer_id'     => $order->customer_id
        ];
        Notification::create($data);
    }

    public function setLogOrderFromCustomer($order, $contract)
    {
        $this->sentNotificationAdmin($order, $contract->path);
        $customer = $order->customer;
        \App::make('App\Services\ActivityService')->setOrderLog(
            $order,
            trans(
                "activity.order_contract_customer",
                ['customer_name' => $customer ? $customer->name : "", 'order_contract_name' => $contract->original_name]
            ),
            $order->id,
        );
    }

    private function sentNotificationAdmin($order, $path)
    {
        $user = $order->staffGroup;
        $data_order = [
            'order_code'  => $order->code,
            'order_id'    => $order->id,
            'path'        => $path,
            'customer_name' => $user ? $user->name : ""
        ];
        $data = [
            'group'           => 2,
            'name'            => 2010, // Kí hợp đồng đơn hàng thành công
            'data'            => json_encode($data_order),
            'time_create'     => time(),
            'status'          => 1,
            'organization_id' => $order->organization_id,
            'user_id'     => $order->staff_group_id
        ];
        Notification::create($data);
    }
}
